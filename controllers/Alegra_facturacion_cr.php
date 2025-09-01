<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Alegra_facturacion_cr extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('alegra_cr_model');
        $this->lang->load('alegra_facturacion_cr', 'english');
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
            'price' => (float)$product->rate,
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
                'price' => (float)$product['rate'],
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
            $itemId = $product['id'];
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
        if (!has_permission('alegra_cr', '', 'create')) {
            access_denied('alegra_cr');
        }

        $this->load->model('invoices_model');
        $this->load->model('clients_model');
        $this->load->model('invoice_items_model');
        $this->load->helper('alegra_cr');

        $invoice = $this->invoices_model->get($invoice_id);
        $client = $this->clients_model->get($invoice->clientid);
        $contact = $this->clients_model->get_contact($invoice->clientid);


        // 1. Buscar o crear el cliente en Alegra
        $alegra_client = $this->find_or_create_client($client, $contact);
        if (!$alegra_client) {
            set_alert('danger', 'Error al procesar el cliente en Alegra');
            redirect(admin_url('alegra_facturacion_cr/invoices'));
        }

        // 2. Preparar items de la factura
        $items = [];
        foreach ($invoice->items as $item) {
            $alegra_item_id = $this->get_alegra_item_id($item);
            
            if (!$alegra_item_id) {
                set_alert('danger', 'Error al procesar el item: ' . $item['description']);
                redirect(admin_url('alegra_facturacion_cr/invoices'));
            }

            $items[] = [
                'id' => $alegra_item_id,
                'price' => $item['rate'],
                'quantity' => $item['qty'],
                'tax' => $this->calculate_taxes($item),
            ];
        }

        // 3. Construir datos de la factura
        $invoice_data = [
            'date' => $invoice->date,
            'dueDate' => $invoice->duedate,
            'client' => $alegra_client['id'],
            'items' => $items,
            'stamp' => [
                'generate' => true,
            ],
            'costarica' => [
                'condicionVenta' => $this->get_payment_condition($invoice),
                'medioPago' => $this->get_payment_method($invoice),
            ],
        ];

        // 4. Agregar bandera de contingencia si es necesario
        if ($type === 'contingency') {
            $invoice_data['type'] = 'contingency';
        }

        // 5. Llamar a la API de Alegra para crear la factura
        $result = alegra_cr_api_request('POST', 'invoices', $invoice_data);

        if (isset($result['id'])) {
            // Guardar el ID de Alegra en un campo personalizado de la factura de Perfex
            $this->save_alegra_invoice_data($invoice_id, $result);
        //    $this->add_invoice_note($invoice_id, 'Factura electrónica creada en Alegra. ID: ' . $result['id']);
            set_alert('success', 'Factura electrónica creada exitosamente en Alegra. ID: ' . $result['id']);
        } else {
            $this->alegra_cr_model->update_invoice_map_status(
                $invoice_id, 
                'error', 
                $result
            );

            $error = isset($result['error']) ? $result['error'] : (isset($result['message']) ? $result['message'] : 'Error desconocido');
            set_alert('danger', 'Error creando factura electrónica: ' . $error);
        }

        redirect(admin_url('alegra_facturacion_cr/invoices'));
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

    // Función auxiliar para buscar o crear cliente
    private function find_or_create_client($client, $contact)
    {
        $this->load->helper('alegra_cr');
        
        // Buscar cliente por email
        $response = alegra_cr_api_request('GET', 'contacts?email=' . urlencode($contact->email));
        
        if (is_array($response) && count($response) > 0 && isset($response[0]['id'])) {
            return $response[0];
        }
        
        log_message('error', json_encode($contact));
        log_message('error', json_encode($client));


        
        // Si no existe, crear nuevo cliente
        $new_client_data = [
            'name' => !empty($client->company) ? $client->company : $client->firstname . ' ' . $client->lastname,
            'email' => $contact->email,
            'identificationObject' => [
                'type' => $this->get_identification_type((int)$client->vat),
                'number' => (int)$client->vat ?: 0
            ],
            'address' => [
                'description' => $client->address ?: ''
            ],
            'phonePrimary' => $client->phonenumber ?: '',
        ];
        
        log_message('error', json_encode($new_client_data));
        $result = alegra_cr_api_request('POST', 'contacts', $new_client_data);
        
        if (isset($result['id'])) {
            return $result;
        }
        
        log_message('error', 'Error creating client in Alegra: ' . json_encode($result));
        return false;
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
            'price' => (float)$item['rate'],
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

    private function get_payment_method($invoice)
    {
        return ['CASH'];
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

    public function settings()
    {
        if ($this->input->post()) {
            $post_data = $this->input->post();
            $success = $this->alegra_cr_model->save_settings($post_data);

            if ($success) {
                set_alert('success', _l('alegra_cr_settings_saved'));
            } else {
                set_alert('danger', _l('alegra_cr_settings_not_saved'));
            }
            redirect(admin_url('alegra_facturacion_cr/settings'));
        }

        $data['title'] = _l('alegra_cr_settings');
        $data['settings'] = $this->alegra_cr_model->get_settings();
        $this->load->view('alegra_facturacion_cr/settings', $data);
    }
}
