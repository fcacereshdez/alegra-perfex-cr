<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Hook corregido con carga correcta del modelo
 */

// Registrar los hooks principales
hooks()->add_action('after_invoice_added', 'alegra_cr_auto_transmit_invoice_flexible');
hooks()->add_action('after_invoice_updated', 'alegra_cr_auto_transmit_invoice_flexible');

function alegra_cr_auto_transmit_invoice_flexible($invoice_id)
{
    // Log inicial
    log_message('error', '=== ALEGRA CR HOOK FLEXIBLE INICIADO === Factura ID: ' . $invoice_id);
    
    // Verificar que tenemos un ID válido
    if (empty($invoice_id) || !is_numeric($invoice_id)) {
        log_message('error', 'Alegra CR: ID de factura inválido: ' . var_export($invoice_id, true));
        return;
    }
    
    try {
        // 1. Verificar CI y estructura básica
        if (!function_exists('get_instance')) {
            log_message('error', 'Alegra CR: get_instance() no disponible');
            return;
        }
        
        $CI = &get_instance();
        
        if (!isset($CI->db)) {
            log_message('error', 'Alegra CR: Base de datos no disponible');
            return;
        }
        
        // 2. CARGAR EL MODELO CORRECTAMENTE
        // Primero verificar si ya está cargado
        if (!isset($CI->alegra_cr_model)) {
            // Intentar cargar desde el directorio de módulos
            $model_path = APPPATH . 'modules/alegra_facturacion_cr/models/Alegra_cr_model.php';
            
            if (file_exists($model_path)) {
                log_message('error', 'Alegra CR: Cargando modelo desde módulos...');
                require_once($model_path);
                $CI->alegra_cr_model = new Alegra_cr_model();
            } else {
                // Intentar cargar de forma estándar
                log_message('error', 'Alegra CR: Intentando carga estándar del modelo...');
                $CI->load->model('alegra_facturacion_cr/alegra_cr_model', 'alegra_cr_model');
            }
        }
        
        // Verificar que el modelo se cargó correctamente
        if (!isset($CI->alegra_cr_model)) {
            log_message('error', 'Alegra CR: No se pudo cargar el modelo Alegra_cr_model');
            return;
        }
        
        log_message('error', 'Alegra CR: ✓ Modelo cargado correctamente');
        
        // 3. Obtener la factura
        $invoice = alegra_cr_get_invoice_from_db_debug($CI, $invoice_id);
        
        if (!$invoice) {
            log_message('error', 'Alegra CR: Factura no encontrada ID: ' . $invoice_id);
            return;
        }
        
        log_message('error', 'Alegra CR: ✓ Factura encontrada. Total: ' . $invoice->total . ', Cliente: ' . $invoice->clientid);
        
        // 4. Verificar si ya está procesada
        if (alegra_cr_invoice_already_processed_debug($CI, $invoice_id)) {
            log_message('error', 'Alegra CR: Factura ya procesada ID: ' . $invoice_id);
            return;
        }
        
        log_message('error', 'Alegra CR: ✓ Factura no procesada anteriormente');
        
        // 5. Verificar si debe auto-transmitirse usando funciones directas (sin modelo)
        $should_transmit = alegra_cr_should_auto_transmit_direct($CI, $invoice);
        
        log_message('error', 'Alegra CR: Evaluación de auto-transmisión: ' . json_encode($should_transmit));
        
        if (!$should_transmit['should_transmit']) {
            log_message('error', 'Alegra CR: No se auto-transmite. Razón: ' . $should_transmit['reason']);
            return;
        }
        
        log_message('error', 'Alegra CR: ✓✓✓ TODAS LAS CONDICIONES CUMPLIDAS - INICIANDO AUTO-TRANSMISIÓN ✓✓✓');
        log_message('error', 'Alegra CR: Método de pago coincidente: ' . $should_transmit['matched_method']);
        
        // 6. Ejecutar transmisión
        $result = alegra_cr_execute_transmit_debug($invoice_id);
        
        if ($result && isset($result['success']) && $result['success']) {
            log_message('error', 'Alegra CR: ✓✓✓ AUTO-TRANSMISIÓN EXITOSA ✓✓✓');
            
            // Agregar nota si está configurado
            $notify_setting = alegra_cr_get_setting_direct($CI, 'notify_auto_transmit');
            if ($notify_setting === '1') {
                alegra_cr_add_auto_transmit_note($CI, $invoice_id, $should_transmit['matched_method']);
            }
            
        } else {
            $error = isset($result['error']) ? $result['error'] : 'Error desconocido';
            log_message('error', 'Alegra CR: ❌❌❌ ERROR EN AUTO-TRANSMISIÓN: ' . $error);
        }
        
    } catch (Exception $e) {
        log_message('error', 'Alegra CR: ❌ EXCEPCIÓN EN HOOK: ' . $e->getMessage());
        log_message('error', 'Alegra CR: Stack trace: ' . $e->getTraceAsString());
    }
    
    log_message('error', '=== ALEGRA CR HOOK FLEXIBLE TERMINADO === Factura ID: ' . $invoice_id);
}


/**
 * Función para evaluar auto-transmisión sin usar el modelo (acceso directo a BD)
 */
function alegra_cr_should_auto_transmit_direct($CI, $invoice)
{
    try {
        // 1. Verificar si auto-transmisión está habilitada
        $auto_transmit_enabled = alegra_cr_get_setting_direct($CI, 'auto_transmit_enabled');
        
        if ($auto_transmit_enabled !== '1') {
            return [
                'should_transmit' => false,
                'reason' => 'Auto-transmisión deshabilitada (valor: ' . $auto_transmit_enabled . ')'
            ];
        }
        
        // 2. Obtener métodos de pago configurados para auto-transmisión
        $auto_transmit_methods_json = alegra_cr_get_setting_direct($CI, 'auto_transmit_payment_methods');
        $auto_transmit_methods = json_decode($auto_transmit_methods_json, true);
        
        if (empty($auto_transmit_methods) || !is_array($auto_transmit_methods)) {
            return [
                'should_transmit' => false,
                'reason' => 'No hay métodos de pago configurados para auto-transmisión'
            ];
        }
        
        log_message('error', 'Alegra CR: Métodos configurados para auto-transmisión: ' . json_encode($auto_transmit_methods));
        
        // 3. Verificar si el método de pago de la factura está en la lista
        $invoice_payment_methods = alegra_cr_get_invoice_payment_methods_direct($invoice);
        $method_match = false;
        $matched_method = null;
        
        log_message('error', 'Alegra CR: Métodos de pago de la factura: ' . json_encode($invoice_payment_methods));
        
        foreach ($invoice_payment_methods as $method_id) {
            if (in_array((string)$method_id, $auto_transmit_methods)) {
                $method_match = true;
                $matched_method = $method_id;
                break;
            }
        }
        
        if (!$method_match) {
            return [
                'should_transmit' => false,
                'reason' => 'Método de pago no configurado para auto-transmisión',
                'invoice_methods' => $invoice_payment_methods,
                'configured_methods' => $auto_transmit_methods
            ];
        }
        
        // 4. Si está configurado solo para servicios médicos, verificar
        $medical_only = alegra_cr_get_setting_direct($CI, 'auto_transmit_medical_only');
        
        if ($medical_only === '1') {
            $has_medical = alegra_cr_invoice_has_medical_services_direct($CI, $invoice->id);
            
            if (!$has_medical) {
                return [
                    'should_transmit' => false,
                    'reason' => 'Configurado solo para servicios médicos y esta factura no los tiene'
                ];
            }
        }
        
        return [
            'should_transmit' => true,
            'reason' => 'Cumple todas las condiciones para auto-transmisión',
            'matched_method' => $matched_method
        ];
        
    } catch (Exception $e) {
        log_message('error', 'Alegra CR: Error en evaluación directa: ' . $e->getMessage());
        return [
            'should_transmit' => false,
            'reason' => 'Error en evaluación: ' . $e->getMessage()
        ];
    }
}

/**
 * Obtener configuración directamente de la BD
 */
function alegra_cr_get_setting_direct($CI, $setting_name)
{
    return alegra_cr_get_option($setting_name);
}
/**
 * Obtener métodos de pago de una factura directamente
 */
function alegra_cr_get_invoice_payment_methods_direct($invoice)
{
    $payment_methods = [];
    
    // Verificar allowed_payment_modes
    if (isset($invoice->allowed_payment_modes) && !empty($invoice->allowed_payment_modes)) {
        $allowed_modes = is_string($invoice->allowed_payment_modes) ? 
            unserialize($invoice->allowed_payment_modes) : 
            $invoice->allowed_payment_modes;
            
        if (is_array($allowed_modes)) {
            // Convertir todos los elementos a string
            $payment_methods = array_map(function($item) {
                return (string)$item;
            }, $allowed_modes);
        }
    }
    
    // Si no hay métodos específicos, verificar si hay un método de pago asignado
    if (empty($payment_methods) && isset($invoice->paymentmethod)) {
        $payment_methods[] = (string)$invoice->paymentmethod;
    }
    
    return $payment_methods;
}

/**
 * Verificar si una factura tiene servicios médicos directamente
 */
function alegra_cr_invoice_has_medical_services_direct($CI, $invoice_id)
{
    try {
        $items = $CI->db->get_where('itemable', [
            'rel_id' => $invoice_id,
            'rel_type' => 'invoice'
        ])->result_array();
        
        // Obtener keywords desde options
        $medical_keywords_setting = alegra_cr_get_option('medical_keywords');
        $medical_keywords = $medical_keywords_setting ? 
            array_map('trim', explode(',', $medical_keywords_setting)) : 
            ['consulta', 'examen', 'chequeo', 'médico', 'doctor'];
        
        foreach ($items as $item) {
            $description = strtolower($item['description']);
            
            foreach ($medical_keywords as $keyword) {
                if (strpos($description, strtolower($keyword)) !== false) {
                    return true;
                }
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        log_message('error', 'Alegra CR: Error verificando servicios médicos: ' . $e->getMessage());
        return false;
    }
}

/**
 * Función auxiliar para agregar nota de auto-transmisión
 */
function alegra_cr_add_auto_transmit_note($CI, $invoice_id, $payment_method_id)
{
    try {
        // Obtener el nombre del método de pago
        $payment_mode = $CI->db->get_where('payment_modes', ['id' => $payment_method_id])->row();
        $payment_name = $payment_mode ? $payment_mode->name : 'Método #' . $payment_method_id;
        
        $note_text = sprintf(
            'Factura transmitida automáticamente a Alegra CR por método de pago: %s (ID: %s) el %s',
            $payment_name,
            $payment_method_id,
            date('Y-m-d H:i:s')
        );
        
        $note_data = [
            'rel_id' => $invoice_id,
            'rel_type' => 'invoice',
            'description' => $note_text,
            'addedfrom' => 0, // Sistema
            'dateadded' => date('Y-m-d H:i:s')
        ];
        
        $CI->db->insert('notes', $note_data);
        log_message('error', 'Alegra CR: Nota de auto-transmisión agregada');
        
    } catch (Exception $e) {
        log_message('error', 'Alegra CR: Error agregando nota: ' . $e->getMessage());
    }
}

/**
 * Obtener configuraciones con debug
 */
function alegra_cr_get_settings_from_db_debug($CI)
{
    try {
        $results = $CI->db->get('alegra_cr_settings')->result_array();
        log_message('error', 'Alegra CR: Configuraciones BD raw: ' . json_encode($results));
        
        $settings = [];
        foreach ($results as $row) {
            $value = $row['setting_value'];
            
            // Decodificar arrays JSON
            if ($row['setting_name'] === 'auto_transmit_payment_methods') {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? $decoded : [];
            }
            
            $settings[$row['setting_name']] = $value;
        }
        
        return $settings;
        
    } catch (Exception $e) {
        log_message('error', 'Alegra CR: Error obteniendo configuraciones: ' . $e->getMessage());
        return null;
    }
}

/**
 * Obtener factura con debug (reutilizar función existente)
 */
function alegra_cr_get_invoice_from_db_debug($CI, $invoice_id)
{
    try {
        $invoice = $CI->db->get_where('invoices', ['id' => $invoice_id])->row();
        
        if ($invoice) {
            // Obtener items
            $items = $CI->db->get_where('itemable', [
                'rel_id' => $invoice_id,
                'rel_type' => 'invoice'
            ])->result_array();
            
            $invoice->items = $items;
            log_message('error', 'Alegra CR: Factura tiene ' . count($items) . ' items');
        }
        
        return $invoice;
        
    } catch (Exception $e) {
        log_message('error', 'Alegra CR: Error obteniendo factura: ' . $e->getMessage());
        return null;
    }
}


/**
 * Verificar si ya está procesada (reutilizar función existente)
 */
function alegra_cr_invoice_already_processed_debug($CI, $invoice_id)
{
    try {
        // Crear tabla si no existe
        if (!$CI->db->table_exists('alegra_cr_invoices_map')) {
            log_message('error', 'Alegra CR: Tabla alegra_cr_invoices_map no existe, creando...');
            $CI->db->query("
                CREATE TABLE IF NOT EXISTS alegra_cr_invoices_map (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    perfex_invoice_id int(11) NOT NULL,
                    alegra_invoice_id varchar(255) DEFAULT NULL,
                    alegra_invoice_number varchar(50) DEFAULT NULL,
                    status varchar(50) DEFAULT 'pending',
                    sync_date timestamp DEFAULT CURRENT_TIMESTAMP,
                    response_data text,
                    PRIMARY KEY (id),
                    UNIQUE KEY perfex_invoice_id (perfex_invoice_id)
                )
            ");
            return false;
        }
        
        $result = $CI->db->get_where('alegra_cr_invoices_map', [
            'perfex_invoice_id' => $invoice_id
        ])->row();
        
        if ($result) {
            log_message('error', 'Alegra CR: Estado actual de la factura: ' . $result->status);
            return ($result->status === 'completed');
        }
        
        return false;
        
    } catch (Exception $e) {
        log_message('error', 'Alegra CR: Error verificando estado: ' . $e->getMessage());
        return false;
    }
}


/**
 * Ejecutar transmisión (reutilizar función existente pero con mejor logging)
 */
function alegra_cr_execute_transmit_debug($invoice_id)
{
    try {
        log_message('error', 'Alegra CR: Iniciando transmisión para factura ' . $invoice_id);
        
        // Construir URL del endpoint
        $base_url = rtrim(site_url(), '/');
        $endpoint_url = $base_url . '/admin/alegra_facturacion_cr/create_electronic_invoice/' . $invoice_id . '/normal';
        
        log_message('error', 'Alegra CR: URL del endpoint: ' . $endpoint_url);
        
        // Configurar cURL
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml',
                'User-Agent: Alegra-AutoTransmit-Flexible/1.0'
            ]
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        
        curl_close($ch);
        
        log_message('error', 'Alegra CR: HTTP Code: ' . $http_code);
        log_message('error', 'Alegra CR: Final URL: ' . $final_url);
        
        if ($curl_error) {
            log_message('error', 'Alegra CR: ❌ cURL Error: ' . $curl_error);
            return ['success' => false, 'error' => 'cURL Error: ' . $curl_error];
        }
        
        // Analizar respuesta
        if ($http_code >= 200 && $http_code < 400) {
            log_message('error', 'Alegra CR: ✓ HTTP exitoso');
            
            // Marcar como completado en BD
            $CI = &get_instance();
            $CI->db->replace('alegra_cr_invoices_map', [
                'perfex_invoice_id' => $invoice_id,
                'status' => 'completed',
                'sync_date' => date('Y-m-d H:i:s'),
                'response_data' => json_encode([
                    'auto_transmit_flexible' => true,
                    'http_code' => $http_code,
                    'final_url' => $final_url,
                    'timestamp' => date('Y-m-d H:i:s')
                ])
            ]);
            
            return [
                'success' => true,
                'message' => 'Transmisión exitosa via configuración flexible',
                'http_code' => $http_code
            ];
            
        } else {
            log_message('error', 'Alegra CR: ❌ HTTP Error ' . $http_code);
            return [
                'success' => false, 
                'error' => 'HTTP Error ' . $http_code
            ];
        }
        
    } catch (Exception $e) {
        log_message('error', 'Alegra CR: ❌ Excepción en transmisión: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}