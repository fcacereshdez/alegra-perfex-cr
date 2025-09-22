<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Alegra_facturacion_cr extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('alegra_cr_model');
        $this->lang->load('alegra_facturacion_cr', 'english');
        $this->lang->load('alegra_facturacion_cr', 'spanish');
    }

    /**
 * Método para configuración administrativa directa
 */
public function admin_settings()
{
    if (!has_permission('alegra_cr', '', 'view')) {
        access_denied('alegra_cr');
    }
    
    // Procesar formulario si se envía
    if ($this->input->post()) {
        $this->process_admin_settings();
        return;
    }
    
    // Cargar datos necesarios
    $data = $this->get_settings_data();
    $data['title'] = 'Configuración de Alegra Costa Rica';
    
    $this->load->view('alegra_facturacion_cr/admin_settings_full', $data);
}

/**
 * Obtener contenido de settings via AJAX
 */
public function get_settings_content()
{
    if (!$this->input->is_ajax_request()) {
        show_404();
    }
    
    if (!has_permission('alegra_cr', '', 'view')) {
        http_response_code(403);
        echo 'Sin permisos';
        return;
    }
    
    // Obtener datos
    $data = $this->get_settings_data();
    
    // Renderizar solo el contenido de las pestañas
    echo $this->load->view('alegra_facturacion_cr/admin_settings_tab', $data, true);
}

/**
 * Procesar configuraciones administrativas
 */
private function process_admin_settings()
{
    $post_data = $this->input->post();
    
    // Mapeo de campos del formulario a opciones de Perfex
    $settings_map = [
        'alegra_email' => 'alegra_cr_email',
        'alegra_token' => 'alegra_cr_token',
        'auto_transmit_enabled' => 'alegra_cr_auto_transmit_enabled',
        'auto_transmit_payment_methods' => 'alegra_cr_auto_transmit_payment_methods',
        'auto_transmit_medical_only' => 'alegra_cr_auto_transmit_medical_only',
        'auto_detect_medical_services' => 'alegra_cr_auto_detect_medical_services',
        'notify_auto_transmit' => 'alegra_cr_notify_auto_transmit',
        'medical_keywords' => 'alegra_cr_medical_keywords',
        'auto_transmit_delay' => 'alegra_cr_auto_transmit_delay'
    ];
    
    $processed_count = 0;
    
    foreach ($settings_map as $form_field => $option_name) {
        if (isset($post_data[$form_field])) {
            $value = $post_data[$form_field];
            
            // Procesar arrays
            if (is_array($value)) {
                $value = json_encode(array_filter($value));
            }
            
            // No guardar token vacío
            if ($form_field === 'alegra_token' && empty($value)) {
                continue;
            }
            
            update_option($option_name, $value);
            $processed_count++;
        } else {
            // Checkboxes no marcados
            $checkbox_fields = ['auto_transmit_enabled', 'auto_transmit_medical_only', 'auto_detect_medical_services', 'notify_auto_transmit'];
            if (in_array($form_field, $checkbox_fields)) {
                update_option($option_name, '0');
                $processed_count++;
            }
        }
    }
    
    // Procesar métodos de pago
    if (isset($post_data['card_payment_methods']) || isset($post_data['cash_payment_methods'])) {
        $this->load->model('alegra_cr_model');
        
        $payment_config = [
            'card_payment_methods' => isset($post_data['card_payment_methods']) ? array_filter($post_data['card_payment_methods']) : [],
            'cash_payment_methods' => isset($post_data['cash_payment_methods']) ? array_filter($post_data['cash_payment_methods']) : []
        ];
        
        $this->alegra_cr_model->save_payment_methods_config($payment_config);
    }
    
    if ($processed_count > 0) {
        set_alert('success', "Configuración guardada exitosamente ({$processed_count} elementos)");
    } else {
        set_alert('warning', 'No se procesaron cambios');
    }
    
    redirect(admin_url('alegra_facturacion_cr/admin_settings'));
}

/**
 * Obtener todos los datos necesarios para la configuración
 */
private function get_settings_data()
{
    // Obtener configuraciones desde las opciones de Perfex
    $settings = [
        'alegra_email' => get_option('alegra_cr_email'),
        'alegra_token' => '', // Por seguridad no mostrar
        'auto_transmit_enabled' => get_option('alegra_cr_auto_transmit_enabled'),
        'auto_transmit_payment_methods' => get_option('alegra_cr_auto_transmit_payment_methods'),
        'auto_transmit_medical_only' => get_option('alegra_cr_auto_transmit_medical_only'),
        'auto_detect_medical_services' => get_option('alegra_cr_auto_detect_medical_services'),
        'notify_auto_transmit' => get_option('alegra_cr_notify_auto_transmit'),
        'medical_keywords' => get_option('alegra_cr_medical_keywords'),
        'auto_transmit_delay' => get_option('alegra_cr_auto_transmit_delay')
    ];
    
    // Obtener métodos de pago
    $perfex_payment_modes = $this->db->get_where('payment_modes', ['active' => 1])->result_array();
    
    // Obtener configuración de métodos de pago del módulo
    $payment_config = [];
    try {
        $this->load->model('alegra_cr_model');
        $payment_config = $this->alegra_cr_model->get_payment_methods_config();
    } catch (Exception $e) {
        // Si no se puede cargar el modelo, usar configuración vacía
        $payment_config = ['card_payment_methods' => [], 'cash_payment_methods' => []];
    }
    
    return [
        'settings' => $settings,
        'perfex_payment_modes' => $perfex_payment_modes,
        'payment_config' => $payment_config
    ];
}

/**
 * Verificar estado del sistema
 */
public function system_status()
{
    if (!has_permission('alegra_cr', '', 'view')) {
        access_denied('alegra_cr');
    }
    
    $status = [];
    
    // Verificar tablas
    $tables = ['alegra_cr_settings', 'alegra_cr_invoices_map', 'alegra_cr_payment_methods_config'];
    foreach ($tables as $table) {
        $status['tables'][$table] = $this->db->table_exists(db_prefix() . $table);
    }
    
    // Verificar opciones
    $options = ['alegra_cr_email', 'alegra_cr_token', 'alegra_cr_auto_transmit_enabled'];
    foreach ($options as $option) {
        $status['options'][$option] = get_option($option) !== false;
    }
    
    // Verificar configuración API
    $status['api_configured'] = !empty(get_option('alegra_cr_email')) && !empty(get_option('alegra_cr_token'));
    
    header('Content-Type: application/json');
    echo json_encode($status, JSON_PRETTY_PRINT);
}

/**
 * Forzar recreación de opciones
 */
public function recreate_options()
{
    if (!has_permission('alegra_cr', '', 'edit')) {
        access_denied('alegra_cr');
    }
    
    $options = [
        'alegra_cr_email' => '',
        'alegra_cr_token' => '',
        'alegra_cr_auto_transmit_enabled' => '0',
        'alegra_cr_auto_transmit_payment_methods' => '[]',
        'alegra_cr_auto_transmit_medical_only' => '0',
        'alegra_cr_auto_detect_medical_services' => '1',
        'alegra_cr_notify_auto_transmit' => '1',
        'alegra_cr_medical_keywords' => 'consulta,examen,chequeo,revisión,diagnóstico,cirugía,operación,procedimiento,terapia,sesión,doctor,médico,especialista,evaluación',
        'alegra_cr_auto_transmit_delay' => '0'
    ];
    
    $created = 0;
    $updated = 0;
    
    foreach ($options as $name => $default_value) {
        if (get_option($name) === false) {
            add_option($name, $default_value);
            $created++;
        } else {
            // Actualizar si está vacío
            if (empty(get_option($name)) && !empty($default_value)) {
                update_option($name, $default_value);
                $updated++;
            }
        }
    }
    
    set_alert('success', "Opciones recreadas: {$created} creadas, {$updated} actualizadas");
    redirect(admin_url('alegra_facturacion_cr/admin_settings'));
}

    public function index()
    {
        redirect(admin_url('alegra_facturacion_cr/invoices'));
    }

    // Función mejorada para sincronizar productos
    public function sync($itemid)
    {
        if (!has_permission('alegra_cr', '', 'create')) {
            access_denied('alegra_cr');
        }

        $this->load->model('invoice_items_model');
        $this->load->helper('alegra_cr');

        $product = $this->invoice_items_model->get($itemid);

        // Verificar si ya está sincronizado
        $existing_map = $this->alegra_cr_model->get_product_map($itemid);
        if ($existing_map) {
            set_alert('info', 'Este producto ya está sincronizado con Alegra. ID: ' . $existing_map['alegra_item_id']);
            redirect(admin_url('alegra_facturacion_cr/products'));
        }

        // Preparar datos para Alegra
        $item_data = [
            'name' => $product->description,
            'price' => (float) $product->rate,
            'inventory' => [
                'unit' => 'service'
            ],
            'productKey' => isset($product->custom_fields['CABYS']) ? $product->custom_fields['CABYS'] : '',
        ];

        $result = alegra_cr_api_request('POST', 'items', $item_data);

        if (isset($result['id'])) {
            $this->alegra_cr_model->add_product_map($itemid, $result['id']);
            set_alert('success', 'Producto sincronizado exitosamente. ID de Alegra: ' . $result['id']);
        } else {
            $error = isset($result['error']) ? $result['error'] : (isset($result['message']) ? $result['message'] : 'Error desconocido');
            set_alert('danger', 'Error al sincronizar producto: ' . $error);
        }

        redirect(admin_url('alegra_facturacion_cr/products'));
    }

    /**
     * Detecta el tipo de producto basado en descripción y código CABYS
     */
    private function get_product_type($item)
    {
        $description = strtolower($item['description']);

        // Palabras clave para servicios médicos
        $medical_service_keywords = [
            'consulta',
            'examen',
            'chequeo',
            'revisión',
            'diagnóstico',
            'cirugía',
            'operación',
            'procedimiento',
            'terapia',
            'sesión',
            'doctor',
            'médico',
            'especialista',
            'evaluación'
        ];

        // Palabras clave para medicamentos
        $medicine_keywords = [
            'acetaminofen',
            'paracetamol',
            'ibuprofeno',
            'aspirina',
            'medicamento',
            'medicina',
            'fármaco',
            'droga',
            'pastilla',
            'cápsula',
            'jarabe',
            'inyección',
            'ampolla',
            'tableta'
        ];

        // Verificar por palabras clave
        foreach ($medical_service_keywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                return 'medical_service';
            }
        }

        foreach ($medicine_keywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                return 'medicine';
            }
        }

        // Verificar por código CABYS si está disponible
        if (isset($item['custom_fields']['CABYS']) && !empty($item['custom_fields']['CABYS'])) {
            $cabys_code = $item['custom_fields']['CABYS'];
            $prefix = substr($cabys_code, 0, 4);

            // Códigos CABYS para servicios médicos
            $medical_service_cabys = ['8610', '8620', '8630', '8690'];

            // Códigos CABYS para medicamentos
            $medicine_cabys = ['2103', '2104', '2105', '4772'];

            if (in_array($prefix, $medical_service_cabys)) {
                return 'medical_service';
            }

            if (in_array($prefix, $medicine_cabys)) {
                return 'medicine';
            }
        }

        return 'other'; // Productos normales
    }


    // Función para sincronizar todos los productos no sincronizados
    public function sync_all_products()
    {
        if (!has_permission('alegra_cr', '', 'create')) {
            access_denied('alegra_cr');
        }

        $this->load->model('invoice_items_model');
        $this->load->helper('alegra_cr');

        $all_products = $this->invoice_items_model->get();
        $products_map = $this->alegra_cr_model->get_products_map();

        $synced = 0;
        $errors = 0;

        foreach ($all_products as $product) {
            $itemid = $product['id'];

            // Saltar si ya está sincronizado
            if (isset($products_map[$itemid])) {
                continue;
            }

            // Preparar datos para Alegra
            $item_data = [
                'name' => $product['description'],
                'price' => (float) $product['rate'],
                'inventory' => [
                    'unit' => 'service'
                ],
                'productKey' => isset($product['custom_fields']['CABYS']) ? $product['custom_fields']['CABYS'] : '',
            ];

            $result = alegra_cr_api_request('POST', 'items', $item_data);

            if (isset($result['id'])) {
                $this->alegra_cr_model->add_product_map($itemid, $result['id']);
                $synced++;
            } else {
                $errors++;
                log_message('error', 'Error sincronizando producto ID ' . $itemid . ': ' . json_encode($result));
            }
        }

        if ($errors > 0) {
            set_alert('warning', $synced . ' productos sincronizados, ' . $errors . ' errores. Ver logs para detalles.');
        } else {
            set_alert('success', $synced . ' productos sincronizados exitosamente.');
        }

        redirect(admin_url('alegra_facturacion_cr/products'));
    }

    public function products()
    {
        if (!has_permission('alegra_cr', '', 'view')) {
            access_denied('alegra_cr');
        }

        $this->load->model('invoice_items_model');

        $all_products = $this->invoice_items_model->get();
        $data['products_map'] = $this->alegra_cr_model->get_products_map();

        $synced_items_map = [];
        foreach ($data['products_map'] as $perfex_id => $alegra_id) {
            $synced_items_map[$perfex_id] = true;
        }

        $display_items = [];
        foreach ($all_products as $product) {
            $itemId = $product['itemid'];
            $is_sync = isset($synced_items_map[$itemId]);

            $display_items[] = [
                'id' => $itemId,
                'description' => $product['description'],
                'rate' => $product['rate'],
                'is_sync' => $is_sync,
                'alegra_id' => $is_sync ? $data['products_map'][$itemId] : null
            ];
        }

        $data['title'] = _l('alegra_cr_products');
        $data['display_items'] = $display_items;

        log_message('error', json_encode($data));

        $this->load->view('alegra_facturacion_cr/products', $data);
    }

    //Vista de las facturas
    public function invoices()
    {
        if (!has_permission('alegra_cr', '', 'view')) {
            access_denied('alegra_cr');
        }

        $this->load->model('invoices_model');
        $this->load->model('clients_model');

        $invoices = $this->invoices_model->get();
        $alegra_map = $this->alegra_cr_model->get_invoices_map();

        $invoice_data = [];
        foreach ($invoices as $invoice) {
            $client = $this->clients_model->get($invoice['clientid']);

            // Verificar estado en Alegra
            $alegra_status = 'not_synced';
            $alegra_id = null;
            $can_sync = true;

            if (isset($alegra_map[$invoice['id']])) {
                $alegra_status = $alegra_map[$invoice['id']]['status'];
                $alegra_id = $alegra_map[$invoice['id']]['alegra_invoice_id'];
                $can_sync = ($alegra_status === 'error'); // Solo permitir sync si hubo error
            }

            $invoice_data[] = [
                'id' => $invoice['id'],
                'customer' => $client ? $client->company : 'N/A',
                'customerid' => $client ? $client->userid : 'N/A',
                'date' => $invoice['date'],
                'amount' => $invoice['total'],
                'status' => $invoice['status'],
                'alegra_status' => $alegra_status,
                'alegra_id' => $alegra_id,
                'can_sync' => $can_sync
            ];
        }

        $data['invoices'] = $invoice_data;
        $data['title'] = _l('alegra_cr_invoices');

        $this->load->view('alegra_facturacion_cr/invoices', $data);
    }

    // Función mejorada para crear factura electrónica
    public function create_electronic_invoice($invoice_id, $type = 'normal')
    {
        $this->load->model('invoices_model');
        $this->load->model('clients_model');
        $this->load->model('invoice_items_model');
        $this->load->helper('alegra_cr');

        $invoice = $this->invoices_model->get($invoice_id);

        if (!$invoice) {
            return [
                'success' => false,
                'error' => 'Factura no encontrada'
            ];
        }

        $client = $this->clients_model->get($invoice->clientid);
        $contact = $this->clients_model->get_contact($client->userid);

        // 1. Buscar o crear el cliente en Alegra
        $alegra_client = $this->find_or_create_client($client, $contact);
        if (!$alegra_client) {
            return [
                'success' => false,
                'error' => 'Error al procesar el cliente en Alegra'
            ];
        }

        // 2. Determinar el método de pago
        $payment_method = $this->get_payment_method($invoice);

        // 3. Preparar items de la factura
        $items = [];
        $iva_return_items = [];

        foreach ($invoice->items as $item) {
            $alegra_item_id = $this->get_alegra_item_id($item);

            if (!$alegra_item_id) {
                return [
                    'success' => false,
                    'error' => 'Error al procesar el item: ' . $item['description']
                ];
            }

            $product_type = $this->get_product_type($item);

            $formatted_item = [
                'id' => $alegra_item_id,
                'price' => floatval($item['rate']),
                'quantity' => floatval($item['qty']),
                'tax' => $this->get_costa_rica_taxes($item, $payment_method),
            ];

            // Verificar si este item es elegible para devolución de IVA
            if ($product_type === 'medical_service' && $payment_method === 'CARD') {
                $iva_return_items[] = [
                    'item_id' => $alegra_item_id,
                    'price' => floatval($item['rate']),
                    'quantity' => floatval($item['qty']),
                    'tax_rate' => 4,
                    'return_amount' => floatval($item['rate']) * floatval($item['qty']) * 0.04
                ];
            }

            // Agregar descuentos si existen
            if (isset($item['discount']) && $item['discount'] > 0) {
                $formatted_item['discount'] = [
                    'type' => 'percentage',
                    'value' => floatval($item['discount'])
                ];
            }

            $items[] = $formatted_item;
        }

        // 4. Construir datos de la factura
        $invoice_data = [
            'date' => $invoice->date,
            'dueDate' => $invoice->duedate,
            'client' => $alegra_client['id'],
            'items' => $items,
            'status' => 'open',
            'paymentMethod' => $payment_method,
            'saleCondition' => $this->get_payment_condition($invoice),
            'stamp' => [
                'generate' => true,
                'send' => true,
            ],
            'costarica' => [
                'condicionVenta' => $this->get_payment_condition($invoice),
                'medioPago' => [$payment_method],
                'plazo' => $this->get_payment_term($invoice)
            ],
        ];

        // 5. Aplicar devolución de IVA si aplica
        if (!empty($iva_return_items)) {
            $total_return_amount = array_sum(array_column($iva_return_items, 'return_amount'));

            $invoice_data['applyVATRefund'] = true;
            $invoice_data['vatRefundDetails'] = [
                'eligible_items' => $iva_return_items,
                'total_return_amount' => $total_return_amount,
                'refund_reason' => 'Servicios médicos pagados con tarjeta - Ley Costa Rica'
            ];
        } else {
            $invoice_data['applyVATRefund'] = false;
        }

        // 6. Agregar bandera de contingencia si es necesario
        if ($type === 'contingency') {
            $invoice_data['type'] = 'contingency';
            $invoice_data['stamp']['generate'] = true;
        }

        // 7. Llamar a la API de Alegra para crear la factura
        $result = alegra_cr_api_request('POST', 'invoices', $invoice_data);

        if (isset($result['id'])) {
            // Guardar información de devolución de IVA si aplica
            if (!empty($iva_return_items)) {
                $this->save_iva_return_info($invoice_id, $result['id'], $iva_return_items);
            }

            // Guardar el ID de Alegra
            $this->save_alegra_invoice_data($invoice_id, $result);

            $message = 'Factura creada exitosamente en Alegra. ID: ' . $result['id'];

            if (!empty($iva_return_items)) {
                $total_return = array_sum(array_column($iva_return_items, 'return_amount'));
                $message .= ' - Devolución de IVA aplicada: ¢' . number_format($total_return, 2);
            }

            if (isset($result['stamp']['status']) && $result['stamp']['status'] === 'received') {
                $message .= ' - Comprobante electrónico generado';
            }

            return [
                'success' => true,
                'alegra_id' => $result['id'],
                'message' => $message,
                'iva_return_applied' => !empty($iva_return_items),
                'total_iva_return' => !empty($iva_return_items) ? array_sum(array_column($iva_return_items, 'return_amount')) : 0
            ];

        } else {
            $this->alegra_cr_model->update_invoice_map_status(
                $invoice_id,
                'error',
                $result
            );

            $error = isset($result['error']) ? $result['error'] : (isset($result['message']) ? $result['message'] : 'Error desconocido');

            return [
                'success' => false,
                'error' => $error,
                'api_response' => $result
            ];
        }
    }

    /**
     * Guarda información de devolución de IVA
     */
    private function save_iva_return_info($perfex_invoice_id, $alegra_invoice_id, $iva_return_items)
    {
        $return_data = [
            'perfex_invoice_id' => $perfex_invoice_id,
            'alegra_invoice_id' => $alegra_invoice_id,
            'return_items' => json_encode($iva_return_items),
            'total_return_amount' => array_sum(array_column($iva_return_items, 'return_amount')),
            'return_type' => 'medical_service_card',
            'status' => 'processed',
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Si tienes una tabla para devoluciones de IVA
        // $this->alegra_cr_model->save_iva_return($return_data);

        log_message('error', 'IVA return info saved: ' . json_encode($return_data));
    }


    /**
     * Función mejorada para detectar método de pago
     */
    private function get_payment_method($invoice)
    {
        // Obtener configuración de métodos de pago
        $payment_config = $this->alegra_cr_model->get_payment_methods_config();

        log_message('error', 'Payment config: ' . json_encode($payment_config));
        log_message('error', 'Invoice allowed_payment_modes: ' . $invoice->allowed_payment_modes);

        // Verificar los métodos de pago permitidos en la factura
        if (isset($invoice->allowed_payment_modes) && !empty($invoice->allowed_payment_modes)) {
            $allowed_modes = is_string($invoice->allowed_payment_modes) ?
                unserialize($invoice->allowed_payment_modes) :
                $invoice->allowed_payment_modes;

            log_message('error', 'Unserialized payment modes: ' . json_encode($allowed_modes));

            if (is_array($allowed_modes)) {
                foreach ($allowed_modes as $mode_id) {
                    // Verificar si este método de pago está configurado como tarjeta
                    if (
                        isset($payment_config['card_payment_methods']) &&
                        in_array($mode_id, $payment_config['card_payment_methods'])
                    ) {
                        log_message('error', 'Found CARD payment method: ' . $mode_id);
                        return 'CARD';
                    }
                }
            }
        }

        // Verificar campo paymentmethod si existe
        if (isset($invoice->paymentmethod) && !empty($invoice->paymentmethod)) {
            $payment_method = strtolower($invoice->paymentmethod);

            if (
                strpos($payment_method, 'tarjeta') !== false ||
                strpos($payment_method, 'card') !== false ||
                strpos($payment_method, 'credit') !== false ||
                strpos($payment_method, 'débito') !== false ||
                strpos($payment_method, 'debito') !== false
            ) {
                log_message('error', 'Found CARD payment by name: ' . $payment_method);
                return 'CARD';
            }
        }

        log_message('error', 'Defaulting to CASH payment method');
        return 'CASH'; // Por defecto efectivo
    }

    /**
     * Vista para configurar métodos de pago
     */
    // public function payment_methods_settings()
    // {
    //     if (!has_permission('alegra_cr', '', 'view')) {
    //         access_denied('alegra_cr');
    //     }

    //     if ($this->input->post()) {
    //         $card_methods = $this->input->post('card_payment_methods') ?: [];
    //         $cash_methods = $this->input->post('cash_payment_methods') ?: [];

    //         $config = [
    //             'card_payment_methods' => array_filter($card_methods),
    //             'cash_payment_methods' => array_filter($cash_methods)
    //         ];

    //         if ($this->alegra_cr_model->save_payment_methods_config($config)) {
    //             set_alert('success', 'Configuración de métodos de pago guardada exitosamente');
    //         } else {
    //             set_alert('danger', 'Error al guardar la configuración');
    //         }

    //         redirect(admin_url('alegra_facturacion_cr/payment_methods_settings'));
    //     }

    //     $data['title'] = 'Configuración de Métodos de Pago - Alegra CR';
    //     $data['payment_config'] = $this->alegra_cr_model->get_payment_methods_config();
    //     $data['perfex_payment_modes'] = $this->alegra_cr_model->get_perfex_payment_modes();

    //     $this->load->view('alegra_facturacion_cr/payment_methods_settings', $data);
    // }


    /**
     * Parsea un impuesto de Perfex y lo convierte al formato de Alegra
     */
    private function parse_perfex_tax($tax_name, $tax_rate)
    {
        // Mapear nombres de impuestos comunes en Costa Rica
        $tax_mapping = [
            'IVA' => 'IVA',
            'Impuesto de Ventas' => 'IVA',
            'Sales Tax' => 'IVA',
            'IVA Reducido' => 'IVA_REDUCIDO',
            'IVA 4%' => 'IVA_REDUCIDO',
            'Exento' => 'EXENTO'
        ];

        $normalized_name = $this->normalize_tax_name($tax_name);
        $alegra_tax_type = isset($tax_mapping[$normalized_name]) ? $tax_mapping[$normalized_name] : 'IVA';

        return [
            'id' => $this->get_alegra_tax_id($alegra_tax_type, $tax_rate),
            'percentage' => floatval($tax_rate)
        ];
    }

    /**
     * Normaliza el nombre del impuesto
     */
    private function normalize_tax_name($tax_name)
    {
        // Remover caracteres especiales y normalizar
        $normalized = trim(str_replace(['%', '(', ')'], '', $tax_name));

        // Casos específicos para Costa Rica
        if (stripos($normalized, 'iva') !== false) {
            if (stripos($normalized, '4') !== false || stripos($normalized, 'reducido') !== false) {
                return 'IVA 4%';
            }
            return 'IVA';
        }

        if (stripos($normalized, 'exento') !== false || stripos($normalized, 'exempt') !== false) {
            return 'Exento';
        }

        return $normalized;
    }

    /**
     * Obtiene el ID del impuesto en Alegra
     */
    private function get_alegra_tax_id($tax_type, $rate)
    {
        $tax_ids = [
            'IVA_13' => 1,      // IVA estándar 13%
            'IVA_4' => 2,       // IVA reducido 4% (servicios médicos)
            'IVA_2' => 5,       // IVA súper reducido 2% (medicamentos)
            'EXENTO' => null    // Sin impuesto
        ];

        // Determinar el ID basado en la tasa
        switch (intval($rate)) {
            case 13:
                return $tax_ids['IVA_13'];
            case 4:
                return $tax_ids['IVA_4'];
            case 2:
                return $tax_ids['IVA_2'];
            case 0:
                return null; // Exento
            default:
                return $tax_ids['IVA_13']; // Por defecto IVA 13%
        }
    }

    /**
     * Función mejorada para preparar items con impuestos correctos
     */
    private function format_items_for_costa_rica($items)
    {
        $formatted_items = [];

        foreach ($items as $item) {
            $taxes = $this->get_costa_rica_taxes($item);

            $formatted_item = [
                'id' => $this->get_alegra_item_id($item),
                'price' => floatval($item['rate']),
                'quantity' => floatval($item['qty']),
                'tax' => $taxes,
            ];

            // Agregar descuentos si existen
            if (isset($item['discount']) && $item['discount'] > 0) {
                $formatted_item['discount'] = [
                    'type' => 'percentage', // o 'value' según el tipo de descuento en Perfex
                    'value' => floatval($item['discount'])
                ];
            }

            $formatted_items[] = $formatted_item;
        }

        return $formatted_items;
    }

    /**
     * Función para validar y aplicar el IVA del 4% específico de Costa Rica
     */
    private function apply_reduced_iva_if_applicable($client, $items)
    {
        // Lógica para determinar si aplica el IVA reducido del 4%
        // En Costa Rica, algunos productos específicos pueden tener IVA del 4%

        foreach ($items as &$item) {
            log_message('error', 'Tax Item: ' . json_encode($item));

            // Verificar si el producto califica para IVA reducido
            if ($this->qualifies_for_reduced_iva($item)) {
                // Actualizar el impuesto al 4%
                $item['tax'] = [
                    [
                        'id' => $this->get_alegra_tax_id('IVA_4', 4),
                        'percentage' => 4
                    ]
                ];
            }
        }

        return $items;
    }

    /**
     * Determina si un producto califica para IVA reducido del 4%
     */
    private function qualifies_for_reduced_iva($item)
    {
        // Implementar lógica específica según los productos que califican en Costa Rica
        // Por ejemplo, algunos productos básicos, medicamentos, etc.

        $reduced_iva_keywords = [
            'medicamento',
            'medicina',
            'alimento básico',
            'producto básico',
            // Agregar más según la legislación de Costa Rica
        ];

        $item_description = strtolower($item['description']);

        foreach ($reduced_iva_keywords as $keyword) {
            if (stripos($item_description, $keyword) !== false) {
                return true;
            }
        }

        // Verificar por código CABYS si está disponible
        if (isset($item['custom_fields']['CABYS'])) {
            return $this->cabys_qualifies_for_reduced_iva($item['custom_fields']['CABYS']);
        }

        return false;
    }

    /**
     * Verifica si un código CABYS califica para IVA reducido
     */
    private function cabys_qualifies_for_reduced_iva($cabys_code)
    {
        // Lista de códigos CABYS que califican para IVA del 4%
        // Debes completar esta lista según la legislación vigente de Costa Rica
        $reduced_iva_cabys = [
            // Medicamentos
            '2103',
            '2104',
            '2105',
            // Alimentos básicos  
            '1001',
            '1002',
            '1003',
            // Agregar más códigos según corresponda
        ];

        $prefix = substr($cabys_code, 0, 4);
        return in_array($prefix, $reduced_iva_cabys);
    }

    private function add_invoice_note($invoice_id, $note)
    {
        $this->load->model('invoices_model');
        $this->invoices_model->add_note([
            'rel_id' => $invoice_id,
            'rel_type' => 'invoice',
            'description' => $note,
            'addedfrom' => get_staff_user_id()
        ]);
    }

    // Mejora la función find_or_create_client para Costa Rica
    private function find_or_create_client($client, $contact = null)
    {
        $this->load->helper('alegra_cr');

        // Usar el email del contacto principal si está disponible
        $email = '';
        if ($contact && isset($contact->email)) {
            $email = $contact->email;
        } elseif (isset($client->email)) {
            $email = $client->email;
        }

        if (empty($email)) {
            log_message('error', 'No se encontró email para el cliente ID: ' . $client->userid);
            return false;
        }

        // Buscar cliente por email o identificación
        $response = alegra_cr_api_request('GET', 'contacts?search=' . urlencode($email));

        if (is_array($response) && count($response) > 0) {
            foreach ($response as $contact_found) {
                if (
                    $contact_found['email'] === $email ||
                    (isset($contact_found['identificationObject']['number']) &&
                        $contact_found['identificationObject']['number'] === $client->vat)
                ) {
                    return $contact_found;
                }
            }
        }

        // Si no existe, crear nuevo cliente con datos de Costa Rica
        $identification = alegra_cr_format_identification($client->vat);

        $client_name = !empty($client->company) ? $client->company : '';
        if (empty($client_name) && $contact) {
            $client_name = trim($contact->firstname . ' ' . $contact->lastname);
        }

        $new_client_data = [
            'name' => $client_name,
            'email' => $email,
            'identificationObject' => $identification,
            'address' => [
                'description' => $client->address ?: '',
                'city' => $client->city ?: '',
                'country' => 'CR' // Costa Rica
            ],
            'phonePrimary' => $client->phonenumber ?: '',
            'type' => ['client'],
            'costarica' => [
                'taxpayerType' => $this->get_taxpayer_type($identification['type'])
            ]
        ];

        $result = alegra_cr_api_request('POST', 'contacts', $new_client_data);

        if (isset($result['id'])) {
            return $result;
        }

        log_message('error', 'Error creating client in Alegra: ' . json_encode($result));
        return false;
    }

    private function get_taxpayer_type($identification_type)
    {
        // Mapear tipo de identificador a tipo de contribuyente
        $map = [
            '01' => 'physical', // Cédula física
            '02' => 'legal',    // Cédula jurídica  
            '03' => 'dimex',    // DIMEX
            'CF' => 'consumer'  // Consumidor final
        ];

        return isset($map[$identification_type]) ? $map[$identification_type] : 'consumer';
    }

    // Función auxiliar para obtener ID de item en Alegra
    private function get_alegra_item_id($item)
    {
        $this->load->helper('alegra_cr');

        // Verificar si ya está mapeado
        $existing_map = $this->alegra_cr_model->get_product_map($item['id']);
        if ($existing_map) {
            return $existing_map['alegra_item_id'];
        }

        // Si no está mapeado, crear el producto en Alegra
        $item_data = [
            'name' => $item['description'],
            'price' => (float) $item['rate'],
            'inventory' => [
                'unit' => 'service'
            ],
            'productKey' => isset($item['custom_fields']['CABYS']) ? $item['custom_fields']['CABYS'] : '',
        ];

        $result = alegra_cr_api_request('POST', 'items', $item_data);

        if (isset($result['id'])) {
            $this->alegra_cr_model->add_product_map($item['id'], $result['id']);
            return $result['id'];
        }

        log_message('error', 'Error creating item in Alegra: ' . json_encode($result));
        return false;
    }

    // Otras funciones auxiliares (calculate_taxes, get_payment_condition, etc.)
    private function calculate_taxes($item)
    {
        // Implementar lógica de cálculo de impuestos según necesidades
        return [];
    }

    private function get_payment_condition($invoice)
    {
        $status = $invoice->status;
        if ($status == 1) {
            return 'CASH';
        } else {
            return 'CREDIT';
        }
    }

    private function get_payment_term($invoice)
    {
        $date = new DateTime($invoice->date);
        $dueDate = new DateTime($invoice->duedate);
        $interval = $date->diff($dueDate);
        return $interval->days;
    }

    private function get_identification_type($vat)
    {
        if (empty($vat))
            log_message('error', 'VAT is empty, defaulting to CF');
        return 'CF';

        // Lógica simple para determinar tipo de identificación
        if (strlen($vat) == 9) {
            log_message('error', 'VAT is == 9 return 01');
            return '01'; // Cédula física
        } elseif (strlen($vat) == 10) {
            log_message('error', 'VAT is == 10 return 02');
            return '02'; // Cédula jurídica
        } else {
            log_message('error', 'VAT is unrecognized, defaulting to CF');
            return 'CF'; // Consumidor final
        }
    }

    /**
     * Determina el impuesto correcto según las reglas de Costa Rica
     */
    private function get_costa_rica_taxes($item, $payment_method = 'CASH')
    {
        $taxes = [];
        $product_type = $this->get_product_type($item);

        log_message('error', 'Tax Item: ' . json_encode($item));
        log_message('error', 'Payment Method: ' . $payment_method);
        log_message('error', 'Product Type: ' . $product_type);

        // 1. Verificar si ya tiene impuestos definidos en Perfex (prioridad)
        if (isset($item['taxname_1']) && !empty($item['taxname_1'])) {
            $tax_data = $this->parse_perfex_tax($item['taxname_1'], $item['taxrate_1']);
            if ($tax_data) {
                $taxes[] = $tax_data;
                log_message('error', 'Using Perfex tax: ' . json_encode($tax_data));
                return $taxes;
            }
        }

        // 2. Aplicar reglas específicas de Costa Rica
        switch ($product_type) {
            case 'medical_service':
                // Servicios médicos siempre usan IVA del 4%
                $taxes[] = [
                    'id' => 6, // ID para IVA 4% en Alegra
                    'percentage' => 4
                ];
                log_message('error', 'Applied medical service tax: 4%');
                break;

            case 'medicine':
                // Medicamentos usan IVA del 2%
                $taxes[] = [
                    'id' => $this->get_alegra_tax_id('IVA', 2), // Necesitas configurar el ID para 2%
                    'percentage' => 2
                ];
                log_message('error', 'Applied medicine tax: 2%');
                break;

            default:
                // Productos normales usan IVA del 13%
                $taxes[] = [
                    'id' => $this->get_alegra_tax_id('IVA', 13),
                    'percentage' => 13
                ];
                log_message('error', 'Applied standard tax: 13%');
                break;
        }

        return $taxes;
    }
    private function is_medical_item($item)
    {
        $medical_keywords = [
            'medicamento',
            'medicina',
            'farmacia',
            'hospital',
            'clínica',
            'consulta',
            'médico',
            'doctor',
            'enfermería',
            'tratamiento',
            'fármaco',
            'receta',
            'droguería',
            'laboratorio',
            'análisis',
            'acetaminofen', // Específico del log
            'paracetamol',
            'ibuprofeno',
            'aspirina'
        ];

        $description = strtolower($item['description']);

        foreach ($medical_keywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                log_message('error', 'Medical keyword found: ' . $keyword . ' in: ' . $description);
                return true;
            }
        }

        // Verificar por código CABYS si está disponible
        if (isset($item['custom_fields']['CABYS'])) {
            $is_medical_cabys = $this->is_medical_cabys($item['custom_fields']['CABYS']);
            log_message('error', 'CABYS medical check: ' . ($is_medical_cabys ? 'yes' : 'no'));
            return $is_medical_cabys;
        }

        log_message('error', 'Item not detected as medical: ' . $description);
        return false;
    }
    private function is_medical_cabys($cabys_code)
    {
        // Códigos CABYS relacionados con medicina/salud
        $medical_cabys_prefixes = [
            '2103',
            '2104',
            '2105', // Medicamentos
            '8610',
            '8620',
            '8630', // Servicios médicos
            '4772' // Comercio de medicamentos
        ];

        $prefix = substr($cabys_code, 0, 4);
        return in_array($prefix, $medical_cabys_prefixes);
    }
    private function save_alegra_invoice_data($perfex_invoice_id, $alegra_invoice_data)
    {
        if (!isset($alegra_invoice_data['id'])) {
            log_message('error', 'Datos de factura de Alegra incompletos: ' . json_encode($alegra_invoice_data));
            return false;
        }

        $data = [
            'perfex_invoice_id' => $perfex_invoice_id,
            'alegra_invoice_id' => $alegra_invoice_data['id'],
            'sync_date' => date('Y-m-d H:i:s'),
            'status' => 'completed'
        ];

        // Agregar número de factura si está disponible
        if (isset($alegra_invoice_data['number'])) {
            $data['alegra_invoice_number'] = $alegra_invoice_data['number'];
        }

        // Agregar clave electrónica si está disponible
        if (isset($alegra_invoice_data['stamp']) && isset($alegra_invoice_data['stamp']['number'])) {
            $data['alegra_invoice_key'] = $alegra_invoice_data['stamp']['number'];
        }

        // Guardar datos completos de respuesta para referencia
        $data['response_data'] = json_encode($alegra_invoice_data);

        return $this->alegra_cr_model->save_invoice_map($data);
    }

    /**
     * Endpoint para obtener estadísticas de auto-transmisión
     */
    public function get_auto_transmit_stats()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $days = $this->input->get('days') ?: 30;
        $stats = $this->alegra_cr_model->get_auto_transmit_stats($days);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Guarda la configuración de impuestos
     */
    public function save_tax_settings()
    {
        if (!has_permission('alegra_cr', '', 'edit_settings')) {
            access_denied('alegra_cr');
        }

        $tax_config = $this->input->post('tax_config');

        if (!$tax_config || !is_array($tax_config)) {
            set_alert('danger', 'Datos de configuración inválidos');
            redirect(admin_url('alegra_facturacion_cr/tax_settings'));
            return;
        }

        $success = true;
        $saved_count = 0;

        // Limpiar configuración existente
        $this->alegra_cr_model->clear_tax_config();

        foreach ($tax_config as $index => $tax) {
            if (empty($tax['tax_name']) || empty($tax['tax_code']) || !isset($tax['tax_rate'])) {
                continue;
            }

            $tax_data = [
                'tax_name' => trim($tax['tax_name']),
                'tax_code' => trim(strtoupper($tax['tax_code'])),
                'alegra_tax_id' => !empty($tax['alegra_tax_id']) ? (int) $tax['alegra_tax_id'] : null,
                'tax_rate' => (float) $tax['tax_rate'],
                'is_active' => isset($tax['is_active']) ? 1 : 0
            ];

            if ($this->alegra_cr_model->save_tax_config($tax_data)) {
                $saved_count++;
            } else {
                $success = false;
            }
        }

        if ($success && $saved_count > 0) {
            set_alert('success', "Configuración de impuestos guardada. {$saved_count} impuestos configurados.");
        } else {
            set_alert('danger', 'Error al guardar la configuración de impuestos');
        }

        redirect(admin_url('alegra_facturacion_cr/tax_settings'));
    }

    /**
     * Testa la conexión con Alegra
     */
    public function test_connection()
    {
        if (!has_permission('alegra_cr', '', 'view')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sin permisos']);
            return;
        }

        $this->load->helper('alegra_cr');

        $result = alegra_cr_api_request('GET', 'company');

        header('Content-Type: application/json');

        if (isset($result['id'])) {
            echo json_encode([
                'success' => true,
                'message' => "Conexión exitosa con Alegra"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => isset($result['error']) ? $result['error'] : 'Error al conectar con Alegra'
            ]);
        }
    }

    /**
     * Procesa una factura aplicando las reglas de IVA mejoradas
     */
    public function process_invoice_with_smart_tax($invoice_id)
    {
        if (!has_permission('alegra_cr', '', 'create')) {
            access_denied('alegra_cr');
        }

        $this->load->model('invoices_model');
        $this->load->helper('alegra_cr');

        $invoice = $this->invoices_model->get($invoice_id);

        if (!$invoice) {
            set_alert('danger', 'Factura no encontrada');
            redirect(admin_url('alegra_facturacion_cr/invoices'));
            return;
        }

        // Analizar cada item y aplicar impuestos inteligentes
        $processed_items = [];
        $tax_changes = [];

        foreach ($invoice->items as $item) {
            $smart_tax = $this->determine_smart_tax_for_item($item);

            // Si el impuesto determinado es diferente al actual, registrar el cambio
            $current_tax_rate = isset($item['taxrate_1']) ? $item['taxrate_1'] : 0;

            if ($smart_tax['rate'] != $current_tax_rate) {
                $tax_changes[] = [
                    'item_id' => $item['id'],
                    'item_description' => $item['description'],
                    'old_rate' => $current_tax_rate,
                    'new_rate' => $smart_tax['rate'],
                    'reason' => $smart_tax['reason']
                ];
            }

            $processed_items[] = array_merge($item, [
                'smart_tax_rate' => $smart_tax['rate'],
                'smart_tax_reason' => $smart_tax['reason'],
                'alegra_tax_id' => $smart_tax['alegra_tax_id']
            ]);
        }

        // Mostrar cambios propuestos antes de aplicar
        if (!empty($tax_changes)) {
            $data['title'] = 'Revisión de Impuestos Aplicados';
            $data['invoice'] = $invoice;
            $data['tax_changes'] = $tax_changes;
            $data['processed_items'] = $processed_items;

            $this->load->view('alegra_facturacion_cr/tax_review', $data);
        } else {
            // No hay cambios, proceder con la factura normal
            $this->create_electronic_invoice($invoice_id);
        }
    }

    /**
     * Determina el impuesto inteligente para un item específico
     */
    private function determine_smart_tax_for_item($item)
    {
        $tax_config = $this->alegra_cr_model->get_all_tax_config();

        // Verificar por código CABYS si está disponible
        if (isset($item['custom_fields']['CABYS']) && !empty($item['custom_fields']['CABYS'])) {
            $cabys_tax = $this->get_tax_by_cabys($item['custom_fields']['CABYS'], $tax_config);
            if ($cabys_tax) {
                return $cabys_tax;
            }
        }

        // Verificar por palabras clave en la descripción
        $description = strtolower($item['description']);

        foreach ($tax_config as $tax) {
            if ($tax['applies_to'] === 'specific' && !empty($tax['criteria'])) {
                $criteria = json_decode($tax['criteria'], true);

                if (isset($criteria['keywords']) && is_array($criteria['keywords'])) {
                    foreach ($criteria['keywords'] as $keyword) {
                        if (stripos($description, $keyword) !== false) {
                            return [
                                'rate' => $tax['tax_rate'],
                                'alegra_tax_id' => $tax['alegra_tax_id'],
                                'reason' => "Palabra clave encontrada: '{$keyword}'"
                            ];
                        }
                    }
                }
            }
        }

        // Por defecto, usar IVA estándar
        $standard_tax = array_filter($tax_config, function ($tax) {
            return $tax['tax_code'] === 'IVA_13' || $tax['tax_rate'] == 13;
        });

        if (!empty($standard_tax)) {
            $standard = reset($standard_tax);
            return [
                'rate' => $standard['tax_rate'],
                'alegra_tax_id' => $standard['alegra_tax_id'],
                'reason' => 'IVA estándar aplicado por defecto'
            ];
        }

        return [
            'rate' => 13,
            'alegra_tax_id' => 1,
            'reason' => 'IVA estándar por defecto'
        ];
    }

    /**
     * Obtiene impuesto basado en código CABYS
     */
    private function get_tax_by_cabys($cabys_code, $tax_config)
    {
        $prefix = substr($cabys_code, 0, 4);

        foreach ($tax_config as $tax) {
            if (!empty($tax['criteria'])) {
                $criteria = json_decode($tax['criteria'], true);

                if (isset($criteria['cabys_prefixes']) && is_array($criteria['cabys_prefixes'])) {
                    if (in_array($prefix, $criteria['cabys_prefixes'])) {
                        return [
                            'rate' => $tax['tax_rate'],
                            'alegra_tax_id' => $tax['alegra_tax_id'],
                            'reason' => "Código CABYS {$cabys_code} califica para {$tax['tax_name']}"
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Procesa devolución de IVA para un cliente elegible
     */
    public function process_iva_return($invoice_id)
    {
        if (!has_permission('alegra_cr', '', 'create')) {
            access_denied('alegra_cr');
        }

        $this->load->model('invoices_model');
        $this->load->model('clients_model');

        $invoice = $this->invoices_model->get($invoice_id);
        $client = $this->clients_model->get($invoice->clientid);

        // Verificar elegibilidad del cliente
        $eligibility = $this->alegra_cr_model->check_client_iva_eligibility($client->userid);

        if (!$eligibility['eligible']) {
            set_alert('info', 'Este cliente no es elegible para devolución de IVA');
            redirect(admin_url('alegra_facturacion_cr/invoices'));
            return;
        }

        // Calcular IVA pagado en la factura
        $iva_breakdown = $this->alegra_cr_model->calculate_invoice_iva_breakdown($invoice->items);
        $total_iva = $iva_breakdown['total_tax'];

        if ($total_iva <= 0) {
            set_alert('info', 'Esta factura no tiene IVA para devolver');
            redirect(admin_url('alegra_facturacion_cr/invoices'));
            return;
        }

        // Calcular devolución
        $this->load->helper('alegra_cr');
        $return_calculation = alegra_cr_calculate_iva_return(
            $invoice->total,
            $total_iva,
            $eligibility['type']
        );

        if ($return_calculation['eligible']) {
            // Registrar devolución
            $alegra_invoice_map = $this->alegra_cr_model->get_invoice_map($invoice_id);

            $return_data = [
                'client_id' => $client->userid,
                'invoice_id' => $invoice_id,
                'alegra_invoice_id' => $alegra_invoice_map ? $alegra_invoice_map['alegra_invoice_id'] : null,
                'return_amount' => $return_calculation['return_amount'],
                'return_percentage' => $return_calculation['return_percentage'],
                'client_type' => $eligibility['type'],
                'notes' => $return_calculation['notes']
            ];

            if ($this->alegra_cr_model->register_iva_return($return_data)) {
                set_alert('success', 'Devolución de IVA registrada por ₡' . number_format($return_calculation['return_amount'], 2));
            } else {
                set_alert('danger', 'Error al registrar la devolución de IVA');
            }
        } else {
            set_alert('info', 'No se puede procesar la devolución de IVA para este cliente');
        }

        redirect(admin_url('alegra_facturacion_cr/invoices'));
    }

    /**
     * Aprueba una devolución de IVA
     */
    public function approve_iva_return($return_id)
    {
        if (!has_permission('alegra_cr', '', 'edit_settings')) {
            access_denied('alegra_cr');
        }

        $notes = ['Devolución aprobada por ' . get_staff_full_name(), 'Fecha: ' . date('Y-m-d H:i:s')];

        if ($this->alegra_cr_model->update_iva_return_status($return_id, 'approved', $notes)) {
            set_alert('success', 'Devolución de IVA aprobada');
        } else {
            set_alert('danger', 'Error al aprobar la devolución');
        }

        redirect(admin_url('alegra_facturacion_cr/tax_settings'));
    }

    /**
     * Rechaza una devolución de IVA
     */
    public function reject_iva_return($return_id)
    {
        if (!has_permission('alegra_cr', '', 'edit_settings')) {
            access_denied('alegra_cr');
        }

        $notes = ['Devolución rechazada por ' . get_staff_full_name(), 'Fecha: ' . date('Y-m-d H:i:s')];

        if ($this->alegra_cr_model->update_iva_return_status($return_id, 'rejected', $notes)) {
            set_alert('success', 'Devolución de IVA rechazada');
        } else {
            set_alert('danger', 'Error al rechazar la devolución');
        }

        redirect(admin_url('alegra_facturacion_cr/tax_settings'));
    }

    /**
     * Sincroniza impuestos desde Alegra
     */
    public function sync_taxes_from_alegra()
    {
        if (!has_permission('alegra_cr', '', 'edit_settings')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sin permisos']);
            return;
        }

        $this->load->helper('alegra_cr');

        try {
            $result = alegra_cr_api_request('GET', 'taxes');

            header('Content-Type: application/json');

            if (is_array($result)) {
                $synced = 0;
                $errors = [];

                foreach ($result as $tax) {
                    // Validar que tenga los campos requeridos
                    if (!isset($tax['id'], $tax['name'], $tax['percentage'])) {
                        $errors[] = "Impuesto incompleto encontrado: " . json_encode($tax);
                        continue;
                    }

                    // Validar que el porcentaje sea numérico
                    if (!is_numeric($tax['percentage'])) {
                        $errors[] = "Porcentaje inválido para impuesto '{$tax['name']}': {$tax['percentage']}";
                        continue;
                    }

                    $tax_data = [
                        'tax_name' => trim($tax['name']),
                        'tax_code' => strtoupper(str_replace([' ', '-', '.'], '_', trim($tax['name']))),
                        'alegra_tax_id' => (int) $tax['id'],
                        'tax_rate' => (float) $tax['percentage'],
                        'is_active' => 1,
                        'applies_to' => 'all', // Por defecto aplica a todos
                        'criteria' => null
                    ];

                    // Agregar criterios específicos para impuestos conocidos de Costa Rica
                    if ($tax_data['tax_rate'] == 4) {
                        $tax_data['applies_to'] = 'specific';
                        $tax_data['criteria'] = json_encode([
                            'keywords' => ['medicamento', 'medicina', 'alimento básico', 'producto básico'],
                            'cabys_prefixes' => ['2103', '2104', '1001', '1002']
                        ]);
                    } elseif ($tax_data['tax_rate'] == 0) {
                        $tax_data['applies_to'] = 'specific';
                        $tax_data['criteria'] = json_encode([
                            'keywords' => ['exento', 'educación', 'salud pública', 'servicio público']
                        ]);
                    }

                    if ($this->alegra_cr_model->save_or_update_tax_config($tax_data)) {
                        $synced++;
                        log_message('info', "Impuesto sincronizado: {$tax['name']} ({$tax['percentage']}%)");
                    } else {
                        $errors[] = "Error al guardar impuesto: {$tax['name']}";
                        log_message('error', "Error al sincronizar impuesto: " . json_encode($tax_data));
                    }
                }

                $response = [
                    'success' => true,
                    'message' => "Se sincronizaron {$synced} impuestos desde Alegra",
                    'synced_count' => $synced
                ];

                if (!empty($errors)) {
                    $response['warnings'] = $errors;
                    $response['message'] .= ". Se encontraron " . count($errors) . " advertencias.";
                }

                echo json_encode($response);

            } else {
                $error_message = 'Error al obtener impuestos de Alegra';

                if (isset($result['error'])) {
                    $error_message = $result['error'];
                } elseif (isset($result['message'])) {
                    $error_message = $result['message'];
                }

                log_message('error', 'Error en sync_taxes_from_alegra: ' . json_encode($result));

                echo json_encode([
                    'success' => false,
                    'message' => $error_message,
                    'details' => is_array($result) ? $result : []
                ]);
            }

        } catch (Exception $e) {
            log_message('error', 'Excepción en sync_taxes_from_alegra: ' . $e->getMessage());

            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Función para validar y limpiar la configuración de impuestos después de la sincronización
     */
    public function validate_synced_taxes()
    {
        if (!has_permission('alegra_cr', '', 'view')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sin permisos']);
            return;
        }

        header('Content-Type: application/json');

        try {
            $tax_config = $this->alegra_cr_model->get_all_tax_config();
            $validation_results = [];
            $issues = [];

            // Validar tasas de impuesto válidas para Costa Rica
            $valid_cr_rates = [0, 4, 13];

            foreach ($tax_config as $tax) {
                $tax_validation = [
                    'id' => $tax['id'],
                    'name' => $tax['tax_name'],
                    'rate' => $tax['tax_rate'],
                    'valid' => true,
                    'warnings' => []
                ];

                // Validar tasa
                if (!in_array($tax['tax_rate'], $valid_cr_rates)) {
                    $tax_validation['valid'] = false;
                    $tax_validation['warnings'][] = "Tasa {$tax['tax_rate']}% no es estándar en Costa Rica";
                    $issues[] = $tax['tax_name'] . ": tasa inválida";
                }

                // Validar ID de Alegra
                if (empty($tax['alegra_tax_id']) && $tax['tax_rate'] > 0) {
                    $tax_validation['warnings'][] = "Falta ID de Alegra para impuesto con tasa > 0%";
                }

                // Validar duplicados por tasa
                $duplicates = array_filter($tax_config, function ($other) use ($tax) {
                    return $other['tax_rate'] == $tax['tax_rate'] && $other['id'] != $tax['id'];
                });

                if (count($duplicates) > 0) {
                    $tax_validation['warnings'][] = "Existen múltiples impuestos con la misma tasa";
                }

                $validation_results[] = $tax_validation;
            }

            echo json_encode([
                'success' => true,
                'validation_results' => $validation_results,
                'total_taxes' => count($tax_config),
                'issues_found' => count($issues),
                'issues' => $issues
            ]);

        } catch (Exception $e) {
            log_message('error', 'Error en validate_synced_taxes: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Error al validar impuestos: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Endpoint para probar la configuración de auto-transmisión
     */
    public function test_auto_transmit_config()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        header('Content-Type: application/json');

        try {
            // Obtener configuración actual
            $debug_info = $this->alegra_cr_model->get_auto_transmit_debug_info();

            // Obtener nombres de métodos de pago configurados
            $method_names = [];
            if (!empty($debug_info['configured_methods'])) {
                foreach ($debug_info['configured_methods'] as $method_id) {
                    $method = $this->db->get_where('payment_modes', ['id' => $method_id])->row();
                    $method_names[] = $method ? $method->name : 'Método #' . $method_id;
                }
            }

            // Simular evaluación en facturas recientes
            $recent_invoices = $this->db->select('id, clientid, total, allowed_payment_modes')
                ->from('invoices')
                ->order_by('id', 'DESC')
                ->limit(5)
                ->get()
                ->result();

            $test_results = [];
            foreach ($recent_invoices as $invoice) {
                $should_transmit = $this->alegra_cr_model->should_auto_transmit_invoice($invoice);

                $test_results[] = [
                    'invoice_id' => $invoice->id,
                    'total' => $invoice->total,
                    'should_transmit' => $should_transmit['should_transmit'],
                    'reason' => $should_transmit['reason'],
                    'payment_methods' => isset($should_transmit['invoice_methods']) ?
                        $should_transmit['invoice_methods'] : []
                ];
            }

            echo json_encode([
                'success' => true,
                'config' => $debug_info,
                'method_names' => $method_names,
                'test_results' => $test_results,
                'total_configured_methods' => count($debug_info['configured_methods'])
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Endpoint para simular auto-transmisión en una factura específica
     */
    public function simulate_auto_transmit($invoice_id)
    {
        if (!has_permission('alegra_cr', '', 'view')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sin permisos']);
            return;
        }

        header('Content-Type: application/json');

        try {
            $this->load->model('invoices_model');
            $invoice = $this->invoices_model->get($invoice_id);

            if (!$invoice) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Factura no encontrada'
                ]);
                return;
            }

            // Evaluar si debería auto-transmitirse
            $evaluation = $this->alegra_cr_model->should_auto_transmit_invoice($invoice);

            // Obtener información adicional para debugging
            $payment_methods = [];
            if (isset($invoice->allowed_payment_modes) && !empty($invoice->allowed_payment_modes)) {
                $allowed_modes = is_string($invoice->allowed_payment_modes) ?
                    unserialize($invoice->allowed_payment_modes) :
                    $invoice->allowed_payment_modes;

                if (is_array($allowed_modes)) {
                    foreach ($allowed_modes as $mode_id) {
                        $method = $this->db->get_where('payment_modes', ['id' => $mode_id])->row();
                        $payment_methods[] = [
                            'id' => $mode_id,
                            'name' => $method ? $method->name : 'Desconocido',
                            'configured_for_auto' => $this->alegra_cr_model->is_payment_method_auto_transmit($mode_id)
                        ];
                    }
                }
            }

            // Verificar servicios médicos si está configurado
            $medical_check = null;
            if ($this->alegra_cr_model->is_medical_only_auto_transmit()) {
                $medical_check = $this->alegra_cr_model->invoice_has_medical_services($invoice_id);
            }

            echo json_encode([
                'success' => true,
                'invoice_id' => $invoice_id,
                'invoice_total' => $invoice->total,
                'evaluation' => $evaluation,
                'payment_methods' => $payment_methods,
                'medical_services_check' => $medical_check,
                'config' => $this->alegra_cr_model->get_auto_transmit_debug_info()
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Endpoint actualizado para test de detección automática
     */
    public function test_auto_detection()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        header('Content-Type: application/json');

        $test_items = [
            'Consulta médica general',
            'Examen de laboratorio completo',
            'Medicamento acetaminofen 500mg',
            'Terapia física rehabilitación',
            'Servicio de limpieza oficina',
            'Reparación de equipo computacional',
            'Diagnóstico por imágenes',
            'Procedimiento quirúrgico menor'
        ];

        $results = [];

        foreach ($test_items as $item) {
            $is_medical = $this->alegra_cr_model->is_medical_service_item($item);

            $results[] = [
                'item' => $item,
                'detected' => $is_medical,
                'iva_rate' => $is_medical ? 4 : 13,
                'type' => $is_medical ? 'medical_service' : 'standard'
            ];
        }

        echo json_encode([
            'success' => true,
            'results' => $results,
            'medical_keywords' => $this->alegra_cr_model->get_medical_keywords(),
            'config' => $this->alegra_cr_model->get_auto_transmit_debug_info()
        ]);
    }

    /**
     * Método settings() redireccionado a configuración general
     */
    public function settings()
    {
        // Redirigir a la pestaña de Alegra CR en configuración general
        redirect(admin_url('settings?group=alegra_cr'));
    }

    /**
     * Método payment_methods_settings() redireccionado
     */
    public function payment_methods_settings()
    {
        // Redirigir a la pestaña de Alegra CR en configuración general
        redirect(admin_url('settings?group=alegra_cr'));
    }

    /**
     * Método tax_settings() redireccionado 
     */
    public function tax_settings()
    {
        // Redirigir a la pestaña de Alegra CR en configuración general
        redirect(admin_url('settings?group=alegra_cr'));
    }

    /**
     * Endpoint AJAX para obtener configuración de métodos de pago
     */
    public function get_payment_methods_config()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        header('Content-Type: application/json');

        $config = $this->alegra_cr_model->get_payment_methods_config();
        $payment_modes = $this->alegra_cr_model->get_perfex_payment_modes();

        echo json_encode([
            'success' => true,
            'config' => $config,
            'payment_modes' => $payment_modes
        ]);
    }

    /**
     * Endpoint AJAX para guardar configuración de métodos de pago
     */
    public function save_payment_methods_config()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        header('Content-Type: application/json');

        $card_methods = $this->input->post('card_payment_methods') ?: [];
        $cash_methods = $this->input->post('cash_payment_methods') ?: [];

        $config = [
            'card_payment_methods' => array_filter($card_methods),
            'cash_payment_methods' => array_filter($cash_methods)
        ];

        $success = $this->alegra_cr_model->save_payment_methods_config($config);

        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Configuración guardada exitosamente' : 'Error al guardar configuración'
        ]);
    }

    /**
     * Endpoint para obtener todas las configuraciones para la vista integrada
     */
    public function get_all_settings()
    {
        if (!has_permission('alegra_cr', '', 'view')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sin permisos']);
            return;
        }

        header('Content-Type: application/json');

        try {
            $settings = $this->alegra_cr_model->get_settings();
            $payment_config = $this->alegra_cr_model->get_payment_methods_config();
            $payment_modes = $this->alegra_cr_model->get_perfex_payment_modes();
            $tax_config = $this->alegra_cr_model->get_all_tax_config();

            echo json_encode([
                'success' => true,
                'settings' => $settings,
                'payment_config' => $payment_config,
                'payment_modes' => $payment_modes,
                'tax_config' => $tax_config,
                'stats' => $this->alegra_cr_model->get_auto_transmit_stats()
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Método para manejar guardado unificado de todas las configuraciones
     */
    public function save_unified_settings()
    {
        if (!has_permission('alegra_cr', '', 'edit_settings')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sin permisos']);
            return;
        }

        header('Content-Type: application/json');

        try {
            $post_data = $this->input->post();

            // Procesar checkboxes que pueden no estar en POST
            $checkbox_fields = [
                'auto_transmit_enabled',
                'auto_detect_medical_services',
                'notify_auto_transmit',
                'auto_transmit_medical_only'
            ];

            foreach ($checkbox_fields as $field) {
                if (!isset($post_data[$field])) {
                    $post_data[$field] = '0';
                }
            }

            // Procesar arrays
            if (isset($post_data['auto_transmit_payment_methods'])) {
                $post_data['auto_transmit_payment_methods'] = json_encode($post_data['auto_transmit_payment_methods']);
            } else {
                $post_data['auto_transmit_payment_methods'] = json_encode([]);
            }

            // No guardar token vacío
            if (empty($post_data['alegra_token'])) {
                unset($post_data['alegra_token']);
            }

            // Guardar settings principales
            $settings_success = $this->alegra_cr_model->save_settings($post_data);

            // Guardar configuración de métodos de pago si está presente
            $payment_success = true;
            if (isset($post_data['card_payment_methods']) || isset($post_data['cash_payment_methods'])) {
                $payment_config = [
                    'card_payment_methods' => isset($post_data['card_payment_methods']) ? array_filter($post_data['card_payment_methods']) : [],
                    'cash_payment_methods' => isset($post_data['cash_payment_methods']) ? array_filter($post_data['cash_payment_methods']) : []
                ];

                $payment_success = $this->alegra_cr_model->save_payment_methods_config($payment_config);
            }

            $success = $settings_success && $payment_success;

            echo json_encode([
                'success' => $success,
                'message' => $success ?
                    'Configuración de Alegra Costa Rica guardada exitosamente' :
                    'Error al guardar la configuración'
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}