<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Alegra_cr_model extends App_Model
{
    private $settings_table;
    private $invoices_map_table;
    private $products_map_table;

    public function __construct()
    {
        parent::__construct();

        // Definir nombres de tablas
        $this->settings_table = db_prefix() . 'alegra_cr_settings';
        $this->invoices_map_table = db_prefix() . 'alegra_cr_invoices_map';
        $this->products_map_table = db_prefix() . 'alegra_cr_products_map';

        $this->load->library('encryption');
    }

    // ============================================================================
    // GESTIÓN DE CONFIGURACIÓN UNIFICADA
    // ============================================================================

    /**
     * Obtiene TODAS las configuraciones de la tabla unificada
     */
    public function get_all_settings()
    {
        $settings = [];
        $query = $this->db->get($this->settings_table);

        foreach ($query->result() as $row) {
            $value = $row->setting_value;

            // Desencriptar token si es necesario
            if ($row->setting_name === 'alegra_token' && !empty($value)) {
                try {
                    $value = $this->encryption->decrypt($value);
                } catch (Exception $e) {
                    $value = '';
                }
            }

            // Decodificar JSON si es necesario
            if ($this->is_json($value)) {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? $decoded : $value;
            }

            $settings[$row->setting_name] = $value;
        }

        return $settings;
    }

    /**
     * Obtiene una configuración específica
     */
    public function get_setting($setting_name, $default = null)
    {
        $result = $this->db->get_where($this->settings_table, [
            'setting_name' => $setting_name
        ])->row();

        if (!$result) {
            return $default;
        }

        $value = $result->setting_value;

        // Desencriptar token
        if ($setting_name === 'alegra_token' && !empty($value)) {
            try {
                $value = $this->encryption->decrypt($value);
            } catch (Exception $e) {
                return $default;
            }
        }

        // Decodificar JSON
        if ($this->is_json($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : $value;
        }

        return $value;
    }

    /**
     * Guarda o actualiza una configuración
     */
    public function save_setting($setting_name, $value)
    {
        // Encriptar token si es necesario
        if ($setting_name === 'alegra_token' && !empty($value)) {
            $value = $this->encryption->encrypt($value);
        }

        // Convertir arrays a JSON
        if (is_array($value)) {
            $value = json_encode($value);
        }

        // Verificar si existe
        $exists = $this->db->get_where($this->settings_table, [
            'setting_name' => $setting_name
        ])->row();

        if ($exists) {
            // Actualizar
            $this->db->where('setting_name', $setting_name);
            return $this->db->update($this->settings_table, [
                'setting_value' => $value,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            // Insertar
            return $this->db->insert($this->settings_table, [
                'setting_name' => $setting_name,
                'setting_value' => $value,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Guarda múltiples configuraciones
     */
    public function save_settings($settings_array)
    {
        $success = true;

        foreach ($settings_array as $key => $value) {
            if (!$this->save_setting($key, $value)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Elimina una configuración
     */
    public function delete_setting($setting_name)
    {
        return $this->db->delete($this->settings_table, [
            'setting_name' => $setting_name
        ]);
    }

    // ============================================================================
    // CONFIGURACIONES ESPECÍFICAS
    // ============================================================================

    /**
     * Obtiene configuración de métodos de pago
     * AHORA desde la tabla unificada
     */
    public function get_payment_methods_config()
    {
        $card_methods = $this->get_setting('card_payment_methods', []);
        $cash_methods = $this->get_setting('cash_payment_methods', []);

        return [
            'card_payment_methods' => is_array($card_methods) ? $card_methods : [],
            'cash_payment_methods' => is_array($cash_methods) ? $cash_methods : []
        ];
    }

    /**
     * Guarda configuración de métodos de pago
     * AHORA en la tabla unificada
     */
    public function save_payment_methods_config($config)
    {
        $success = true;

        if (isset($config['card_payment_methods'])) {
            $success = $success && $this->save_setting(
                'card_payment_methods',
                $config['card_payment_methods']
            );
        }

        if (isset($config['cash_payment_methods'])) {
            $success = $success && $this->save_setting(
                'cash_payment_methods',
                $config['cash_payment_methods']
            );
        }

        return $success;
    }

    /**
     * Obtiene credenciales de API
     */
    public function get_api_credentials()
    {
        $email = $this->get_setting('alegra_email');
        $token = $this->get_setting('alegra_token');

        if (empty($email) || empty($token)) {
            return null;
        }

        return [
            'alegra_email' => $email,
            'alegra_token' => $token
        ];
    }

    /**
     * Obtiene keywords médicas
     */
    public function get_medical_keywords()
    {
        $keywords_string = $this->get_setting(
            'medical_keywords',
            'consulta,examen,chequeo,revisión,diagnóstico,cirugía,operación,procedimiento,terapia,sesión,doctor,médico,especialista,evaluación'
        );

        return array_map('trim', explode(',', $keywords_string));
    }

    /**
     * Verifica si un item es servicio médico
     */
    public function is_medical_service_item($item_description)
    {
        $keywords = $this->get_medical_keywords();
        $description = strtolower($item_description);

        foreach ($keywords as $keyword) {
            if (stripos($description, trim($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica si auto-transmisión está habilitada
     */
    public function is_auto_transmit_enabled()
    {
        return $this->get_setting('auto_transmit_enabled', '0') === '1';
    }

    /**
     * Obtiene métodos de pago configurados para auto-transmisión
     */
    public function get_auto_transmit_payment_methods()
    {
        return $this->get_setting('auto_transmit_payment_methods', []);
    }

    /**
     * Verifica si solo servicios médicos deben auto-transmitirse
     */
    public function is_medical_only_auto_transmit()
    {
        return $this->get_setting('auto_transmit_medical_only', '0') === '1';
    }

    /**
     * Verifica si una factura tiene servicios médicos
     */
    public function invoice_has_medical_services($invoice_id)
    {
        $items = $this->db->get_where('itemable', [
            'rel_id' => $invoice_id,
            'rel_type' => 'invoice'
        ])->result_array();

        foreach ($items as $item) {
            if ($this->is_medical_service_item($item['description'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evalúa si una factura debe auto-transmitirse
     */
    public function should_auto_transmit_invoice($invoice)
    {
        // 1. Verificar si está habilitado
        if (!$this->is_auto_transmit_enabled()) {
            return [
                'should_transmit' => false,
                'reason' => 'Auto-transmisión deshabilitada'
            ];
        }

        // 2. Obtener métodos configurados
        $configured_methods = $this->get_auto_transmit_payment_methods();

        if (empty($configured_methods)) {
            return [
                'should_transmit' => false,
                'reason' => 'No hay métodos de pago configurados'
            ];
        }

        // 3. Verificar métodos de pago de la factura
        $invoice_methods = $this->get_invoice_payment_methods($invoice);
        $method_match = false;
        $matched_method = null;

        foreach ($invoice_methods as $method_id) {
            if (in_array((string)$method_id, $configured_methods)) {
                $method_match = true;
                $matched_method = $method_id;
                break;
            }
        }

        if (!$method_match) {
            return [
                'should_transmit' => false,
                'reason' => 'Método de pago no configurado',
                'invoice_methods' => $invoice_methods,
                'configured_methods' => $configured_methods
            ];
        }

        // 4. Verificar servicios médicos si está configurado
        if ($this->is_medical_only_auto_transmit()) {
            if (!$this->invoice_has_medical_services($invoice->id)) {
                return [
                    'should_transmit' => false,
                    'reason' => 'Solo médicos habilitado y factura no los tiene'
                ];
            }
        }

        return [
            'should_transmit' => true,
            'reason' => 'Cumple todos los criterios',
            'matched_method' => $matched_method
        ];
    }

    /**
     * Obtiene métodos de pago de una factura
     */
    private function get_invoice_payment_methods($invoice)
    {
        $methods = [];

        if (isset($invoice->allowed_payment_modes) && !empty($invoice->allowed_payment_modes)) {
            $allowed_modes = is_string($invoice->allowed_payment_modes) ?
                unserialize($invoice->allowed_payment_modes) :
                $invoice->allowed_payment_modes;

            if (is_array($allowed_modes)) {
                $methods = array_map('strval', $allowed_modes);
            }
        }

        if (empty($methods) && isset($invoice->paymentmethod)) {
            $methods[] = (string)$invoice->paymentmethod;
        }

        return $methods;
    }

    /**
     * Obtiene métodos de pago de Perfex
     */
    public function get_perfex_payment_modes()
    {
        return $this->db->get_where('payment_modes', ['active' => 1])
            ->result_array();
    }

    /**
     * Obtiene información de debug
     */
    public function get_auto_transmit_debug_info()
    {
        return [
            'enabled' => $this->is_auto_transmit_enabled(),
            'medical_only' => $this->is_medical_only_auto_transmit(),
            'configured_methods' => $this->get_auto_transmit_payment_methods(),
            'medical_keywords' => implode(', ', $this->get_medical_keywords()),
            'delay' => $this->get_setting('auto_transmit_delay', '0'),
            'notifications' => $this->get_setting('notify_auto_transmit', '0') === '1'
        ];
    }

    /**
     * Obtiene estadísticas de auto-transmisión
     */
    public function get_auto_transmit_stats($days = 30)
    {
        $date_from = date('Y-m-d', strtotime('-' . $days . ' days'));

        $stats = [];

        // Facturas auto-transmitidas
        $this->db->select('COUNT(*) as total');
        $this->db->from($this->invoices_map_table);
        $this->db->where('status', 'completed');
        $this->db->where('sync_date >=', $date_from);
        $auto_transmitted = $this->db->get()->row();
        $stats['auto_transmitted'] = $auto_transmitted ? $auto_transmitted->total : 0;

        // Total de facturas
        $this->db->select('COUNT(*) as total');
        $this->db->from('invoices');
        $this->db->where('date >=', $date_from);
        $total_invoices = $this->db->get()->row();
        $stats['total_invoices'] = $total_invoices ? $total_invoices->total : 0;

        // Porcentaje
        $stats['auto_transmit_percentage'] = $stats['total_invoices'] > 0 ?
            round(($stats['auto_transmitted'] / $stats['total_invoices']) * 100, 2) : 0;

        // Errores
        $this->db->select('COUNT(*) as total');
        $this->db->from($this->invoices_map_table);
        $this->db->where('status', 'error');
        $this->db->where('sync_date >=', $date_from);
        $errors = $this->db->get()->row();
        $stats['errors'] = $errors ? $errors->total : 0;

        return $stats;
    }

    // ============================================================================
    // GESTIÓN DE MAPEOS (FACTURAS Y PRODUCTOS)
    // ============================================================================

    public function get_invoice_map($perfex_invoice_id)
    {
        return $this->db->get_where($this->invoices_map_table, [
            'perfex_invoice_id' => $perfex_invoice_id
        ])->row_array();
    }

    public function save_invoice_map($data)
    {
        $existing = $this->get_invoice_map($data['perfex_invoice_id']);

        if ($existing) {
            $this->db->where('perfex_invoice_id', $data['perfex_invoice_id']);
            return $this->db->update($this->invoices_map_table, $data);
        } else {
            return $this->db->insert($this->invoices_map_table, $data);
        }
    }

    public function get_invoices_map()
    {
        $map = [];
        $query = $this->db->get($this->invoices_map_table);

        foreach ($query->result() as $row) {
            $map[$row->perfex_invoice_id] = [
                'alegra_invoice_id' => $row->alegra_invoice_id,
                'alegra_invoice_number' => $row->alegra_invoice_number,
                'status' => $row->status,
                'sync_date' => $row->sync_date
            ];
        }

        return $map;
    }

    public function get_product_map($perfex_item_id)
    {
        return $this->db->get_where($this->products_map_table, [
            'perfex_item_id' => $perfex_item_id
        ])->row_array();
    }

    public function add_product_map($itemid, $alegra_item_id)
    {
        $existing = $this->get_product_map($itemid);

        if ($existing) {
            $this->db->where('perfex_item_id', $itemid);
            return $this->db->update($this->products_map_table, [
                'alegra_item_id' => $alegra_item_id,
                'last_sync_date' => date('Y-m-d H:i:s')
            ]);
        } else {
            return $this->db->insert($this->products_map_table, [
                'perfex_item_id' => $itemid,
                'alegra_item_id' => $alegra_item_id,
                'last_sync_date' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function get_products_map()
    {
        $map = [];
        $query = $this->db->get($this->products_map_table);

        foreach ($query->result() as $row) {
            $map[$row->perfex_item_id] = $row->alegra_item_id;
        }

        return $map;
    }

    // ============================================================================
    // UTILIDADES
    // ============================================================================

    /**
     * Verifica si un string es JSON válido
     */
    private function is_json($string)
    {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}
