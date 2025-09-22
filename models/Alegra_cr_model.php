<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Alegra_cr_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('encryption');
    }

    public function get_medical_tax_configuration()
    {
        return [
            'medical_keywords' => [
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
                'análisis'
            ],
            'medical_cabys' => [
                '2103',
                '2104',
                '2105', // Medicamentos
                '8610',
                '8620',
                '8630', // Servicios médicos
                '4772' // Comercio de medicamentos
            ],
            'tax_rates' => [
                'standard' => 13,
                'medical_card' => 4,
                'medical_cash' => 13
            ]
        ];
    }

    /**
     * Guarda todas las configuraciones del módulo
     */
    public function save_settings($settings_data)
    {
        $success = true;

        foreach ($settings_data as $key => $value) {
            // Encriptar el token si es necesario
            if ($key === 'alegra_token' && !empty($value)) {
                $value = $this->encryption->encrypt($value);
            }

            // Convertir checkbox values
            if (in_array($key, ['auto_transmit_enabled', 'auto_detect_medical_services', 'notify_auto_transmit', 'auto_transmit_medical_only'])) {
                $value = isset($settings_data[$key]) ? '1' : '0';
            }

            // Manejar arrays (ya viene como JSON del controlador)
            if ($key === 'auto_transmit_payment_methods' && is_string($value)) {
                // Ya viene como JSON del controlador, no modificar
            }

            $existing = $this->db->get_where('alegra_cr_settings', ['setting_name' => $key])->row();

            if ($existing) {
            $this->db->where('setting_name', $key);
            if (!$this->db->update('alegra_cr_settings', [
                'setting_value' => $value, // ← Si $value es array, falla
                'updated_at' => date('Y-m-d H:i:s')
            ])) {
                $success = false;
            }
        } else {
                if (
                    !$this->db->insert('alegra_cr_settings', [
                        'setting_name' => $key,
                        'setting_value' => $value,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ])
                ) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Obtiene todas las configuraciones
     */
    public function get_settings()
    {
        $settings = [];
        $results = $this->db->get('alegra_cr_settings')->result_array();

        foreach ($results as $row) {
            $value = $row['setting_value'];

            // Desencriptar el token
            if ($row['setting_name'] === 'alegra_token' && !empty($value)) {
                try {
                    $value = $this->encryption->decrypt($value);
                } catch (Exception $e) {
                    $value = ''; // Si no se puede desencriptar, usar valor vacío
                }
            }

            // Convertir valores booleanos
            if (in_array($row['setting_name'], ['auto_transmit_enabled', 'auto_detect_medical_services', 'notify_auto_transmit', 'auto_transmit_medical_only'])) {
                $value = ($value === '1' || $value === 1) ? true : false;
            }

            // Decodificar arrays JSON
            if ($row['setting_name'] === 'auto_transmit_payment_methods') {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? $decoded : [];
            }

            $settings[$row['setting_name']] = $value;
        }

        return $settings;
    }

    /**
     * Obtiene una configuración específica
     */
    public function get_setting($setting_name, $default = null)
    {
        $result = $this->db->get_where('alegra_cr_settings', ['setting_name' => $setting_name])->row();

        if (!$result) {
            return $default;
        }

        $value = $result->setting_value;

        // Desencriptar el token
        if ($setting_name === 'alegra_token' && !empty($value)) {
            try {
                $value = $this->encryption->decrypt($value);
            } catch (Exception $e) {
                return $default;
            }
        }

        // Convertir valores booleanos
        if (in_array($setting_name, ['auto_transmit_card_payments', 'auto_detect_medical_services', 'notify_auto_transmit'])) {
            $value = ($value === '1' || $value === 1) ? true : false;
        }

        return $value;
    }

    /**
     * Verifica si la auto-transmisión está habilitada
     */
    public function is_auto_transmit_enabled()
    {
        return $this->get_setting('auto_transmit_card_payments', false);
    }

    /**
     * Obtiene las palabras clave para servicios médicos
     */
    public function get_medical_keywords()
    {
        $keywords_string = $this->get_setting('medical_keywords', 'consulta,examen,chequeo,revisión,diagnóstico,cirugía,operación,procedimiento,terapia,sesión,doctor,médico,especialista,evaluación');

        return array_map('trim', explode(',', $keywords_string));
    }

    /**
     * Verifica si un item contiene servicios médicos basado en las palabras clave configuradas
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


    public function get_invoice_map($perfex_invoice_id)
    {
        $this->db->where('perfex_invoice_id', $perfex_invoice_id);
        $query = $this->db->get(db_prefix() . 'alegra_cr_invoices_map');
        return $query->row_array();
    }

    public function save_invoice_map($data)
    {
        // Verificar si ya existe un mapeo
        $existing = $this->get_invoice_map($data['perfex_invoice_id']);

        if ($existing) {
            $this->db->where('perfex_invoice_id', $data['perfex_invoice_id']);
            return $this->db->update(db_prefix() . 'alegra_cr_invoices_map', $data);
        } else {
            return $this->db->insert(db_prefix() . 'alegra_cr_invoices_map', $data);
        }
    }

    public function update_invoice_map_status($perfex_invoice_id, $status, $response_data = null)
    {
        $data = [
            'status' => $status,
            'sync_date' => date('Y-m-d H:i:s')
        ];

        if ($response_data) {
            $data['response_data'] = is_array($response_data) ? json_encode($response_data) : $response_data;
        }

        $this->db->where('perfex_invoice_id', $perfex_invoice_id);
        return $this->db->update(db_prefix() . 'alegra_cr_invoices_map', $data);
    }

    public function get_alegra_invoice_id($perfex_invoice_id)
    {
        $map = $this->get_invoice_map($perfex_invoice_id);
        return $map ? $map['alegra_invoice_id'] : null;
    }

    public function get_invoices_map()
    {
        $map = [];
        $query = $this->db->get(db_prefix() . 'alegra_cr_invoices_map');
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

    public function get_products_map()
    {
        $map = [];
        $query = $this->db->get(db_prefix() . 'alegra_cr_products_map');
        foreach ($query->result() as $row) {
            $map[$row->perfex_item_id] = $row->alegra_item_id;
        }
        return $map;
    }

    public function get_product_map($perfex_item_id)
    {
        $this->db->where('perfex_item_id', $perfex_item_id);
        $query = $this->db->get(db_prefix() . 'alegra_cr_products_map');
        return $query->row_array();
    }

    public function add_product_map($itemid, $alegra_item_id)
    {
        // Verificar si ya existe
        $existing = $this->get_product_map($itemid);
        if ($existing) {
            $this->db->where('perfex_item_id', $itemid);
            return $this->db->update(db_prefix() . 'alegra_cr_products_map', [
                'alegra_item_id' => $alegra_item_id,
                'last_sync_date' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $data = [
                'perfex_item_id' => $itemid,
                'alegra_item_id' => $alegra_item_id,
                'last_sync_date' => date('Y-m-d H:i:s'),
            ];
            return $this->db->insert(db_prefix() . 'alegra_cr_products_map', $data);
        }
    }

    public function get_api_credentials()
    {
        $credentials = [
            'alegra_email' => null,
            'alegra_token' => null
        ];

        // Obtener email
        $email_setting = $this->get_setting('alegra_email');
        if (!empty($email_setting)) {
            $credentials['alegra_email'] = $email_setting;
        }

        // Obtener token (ya viene desencriptado del método get_setting)
        $token_setting = $this->get_setting('alegra_token');
        if (!empty($token_setting)) {
            $credentials['alegra_token'] = $token_setting;
        }

        // Verificar que ambos estén configurados
        if (empty($credentials['alegra_email']) || empty($credentials['alegra_token'])) {
            return null; // Credenciales incompletas
        }

        return $credentials;
    }
    /**
     * Obtiene la configuración de impuestos para Costa Rica
     */
    /**
 * Obtiene la configuración de impuestos para Costa Rica
 */
public function get_tax_configuration()
{
    // Usar la tabla correcta de impuestos
    $this->db->where('is_active', 1);
    $this->db->order_by('tax_rate', 'DESC');
    $query = $this->db->get(db_prefix() . 'alegra_cr_tax_config');

    $config = [];
    foreach ($query->result_array() as $row) {
        $config[$row['tax_code']] = [
            'alegra_id' => $row['alegra_tax_id'],
            'rate' => $row['tax_rate'],
            'name' => $row['tax_name']
        ];
    }

    // Si no hay configuración, retornar valores por defecto
    if (empty($config)) {
        return [
            'iva_standard' => [
                'alegra_id' => 1,
                'rate' => 13,
                'name' => 'IVA 13%'
            ],
            'iva_reduced' => [
                'alegra_id' => 6,
                'rate' => 4,
                'name' => 'IVA 4%'
            ],
            'exempt' => [
                'alegra_id' => null,
                'rate' => 0,
                'name' => 'Exento'
            ]
        ];
    }

    return $config;
}

    /**
     * Guarda la configuración de impuestos
     */
   /**
 * Guarda la configuración de impuestos
 */
public function save_tax_configuration($config)
{
    // Esta función ahora usa la tabla alegra_cr_tax_config directamente
    // No necesita cambios si usas save_or_update_tax_config()
    
    foreach ($config as $tax_code => $tax_data) {
        $this->save_or_update_tax_config([
            'tax_name' => $tax_data['name'],
            'tax_code' => strtoupper($tax_code),
            'alegra_tax_id' => $tax_data['alegra_id'],
            'tax_rate' => $tax_data['rate'],
            'is_active' => 1
        ]);
    }
    
    return true;
}

    /**
     * Obtiene el mapeo de un impuesto específico por su tasa
     */
    public function get_tax_mapping_by_rate($rate)
    {
        $config = $this->get_tax_configuration();

        foreach ($config as $tax_type => $tax_data) {
            if ($tax_data['rate'] == $rate) {
                return $tax_data;
            }
        }

        return null;
    }

    /**
     * Verifica si un cliente es elegible para devolución de IVA
     */
    public function check_client_iva_eligibility($client_id)
    {
        $this->db->where('clientid', $client_id);
        $this->db->where('fieldname', 'iva_return_type');
        $query = $this->db->get(db_prefix() . 'customfieldsvalues');

        $result = $query->row();

        if ($result) {
            return [
                'eligible' => in_array($result->value, ['exportador', 'exonerado', 'diplomatico']),
                'type' => $result->value
            ];
        }

        return [
            'eligible' => false,
            'type' => 'regular'
        ];
    }

    /**
     * Calcula el IVA total de una factura con diferentes tasas
     */
    public function calculate_invoice_iva_breakdown($invoice_items)
    {
        $iva_breakdown = [
            'iva_13' => ['base' => 0, 'tax' => 0],
            'iva_4' => ['base' => 0, 'tax' => 0],
            'exempt' => ['base' => 0, 'tax' => 0],
            'total_base' => 0,
            'total_tax' => 0
        ];

        foreach ($invoice_items as $item) {
            $subtotal = $item['rate'] * $item['qty'];

            // Aplicar descuentos si existen
            if (isset($item['discount']) && $item['discount'] > 0) {
                $subtotal = $subtotal * (1 - ($item['discount'] / 100));
            }

            $iva_breakdown['total_base'] += $subtotal;

            // Determinar la tasa de IVA aplicada
            $tax_rate = 0;

            if (isset($item['taxrate_1']) && $item['taxrate_1'] > 0) {
                $tax_rate = $item['taxrate_1'];
            }

            $tax_amount = $subtotal * ($tax_rate / 100);

            // Clasificar por tipo de IVA
            if ($tax_rate == 13) {
                $iva_breakdown['iva_13']['base'] += $subtotal;
                $iva_breakdown['iva_13']['tax'] += $tax_amount;
            } elseif ($tax_rate == 4) {
                $iva_breakdown['iva_4']['base'] += $subtotal;
                $iva_breakdown['iva_4']['tax'] += $tax_amount;
            } else {
                $iva_breakdown['exempt']['base'] += $subtotal;
            }

            $iva_breakdown['total_tax'] += $tax_amount;
        }

        return $iva_breakdown;
    }

    /**
     * Obtiene el historial de devoluciones de IVA para un cliente
     */
    public function get_client_iva_return_history($client_id)
    {
        $this->db->where('perfex_client_id', $client_id);
        $this->db->order_by('created_at', 'DESC');
        $query = $this->db->get(db_prefix() . 'alegra_cr_iva_returns');

        return $query->result_array();
    }

    /**
     * Registra una devolución de IVA
     */
    public function register_iva_return($data)
    {
        $return_data = [
            'perfex_client_id' => $data['client_id'],
            'perfex_invoice_id' => $data['invoice_id'],
            'alegra_invoice_id' => $data['alegra_invoice_id'],
            'return_amount' => $data['return_amount'],
            'return_percentage' => $data['return_percentage'],
            'client_type' => $data['client_type'],
            'status' => 'pending',
            'notes' => isset($data['notes']) ? json_encode($data['notes']) : null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->db->insert(db_prefix() . 'alegra_cr_iva_returns', $return_data);
    }

    /**
     * Actualiza el estado de una devolución de IVA
     */
    public function update_iva_return_status($return_id, $status, $notes = null)
    {
        $data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($notes) {
            $existing_notes = $this->get_iva_return_notes($return_id);
            $all_notes = array_merge($existing_notes, (array) $notes);
            $data['notes'] = json_encode($all_notes);
        }

        $this->db->where('id', $return_id);
        return $this->db->update(db_prefix() . 'alegra_cr_iva_returns', $data);
    }

    /**
     * Obtiene las notas de una devolución de IVA
     */
    private function get_iva_return_notes($return_id)
    {
        $this->db->select('notes');
        $this->db->where('id', $return_id);
        $result = $this->db->get(db_prefix() . 'alegra_cr_iva_returns')->row();

        if ($result && $result->notes) {
            return json_decode($result->notes, true);
        }

        return [];
    }

    // En Alegra_cr_model.php

    /**
     * Obtiene toda la configuración de impuestos
     */
    public function get_all_tax_config()
    {
        $this->db->order_by('tax_rate', 'DESC');
        $this->db->order_by('tax_name', 'ASC');
        $query = $this->db->get(db_prefix() . 'alegra_cr_tax_config');

        $tax_config = [];

        foreach ($query->result_array() as $row) {
            // Decodificar criteria si existe
            if (!empty($row['criteria'])) {
                $row['criteria'] = json_decode($row['criteria'], true);
            } else {
                $row['criteria'] = [
                    'keywords' => [],
                    'cabys_prefixes' => []
                ];
            }

            $tax_config[] = $row;
        }

        // Si no hay configuración, retornar valores por defecto para Costa Rica
        if (empty($tax_config)) {
            $tax_config = $this->get_default_cr_tax_config();
        }

        return $tax_config;
    }

    /**
     * Configuración por defecto de impuestos para Costa Rica
     */
    private function get_default_cr_tax_config()
    {
        return [
            [
                'id' => 1,
                'tax_name' => 'IVA Estándar',
                'tax_code' => 'IVA_13',
                'alegra_tax_id' => 1,
                'tax_rate' => 13.00,
                'applies_to' => 'all',
                'is_active' => 1,
                'criteria' => [
                    'keywords' => [],
                    'cabys_prefixes' => []
                ],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 2,
                'tax_name' => 'IVA Reducido',
                'tax_code' => 'IVA_4',
                'alegra_tax_id' => 6, // ID 6 según el payload de Alegra
                'tax_rate' => 4.00,
                'applies_to' => 'specific',
                'is_active' => 1,
                'criteria' => [
                    'keywords' => [
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
                        'análisis'
                    ],
                    'cabys_prefixes' => [
                        '2103',
                        '2104',
                        '2105', // Medicamentos
                        '8610',
                        '8620',
                        '8630', // Servicios médicos
                        '4772' // Comercio de medicamentos
                    ]
                ],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 3,
                'tax_name' => 'Exento',
                'tax_code' => 'EXEMPT',
                'alegra_tax_id' => null,
                'tax_rate' => 0.00,
                'applies_to' => 'specific',
                'is_active' => 1,
                'criteria' => [
                    'keywords' => [
                        'exento',
                        'educación',
                        'salud pública',
                        'servicio público',
                        'libro',
                        'revista',
                        'periódico',
                        'cultural'
                    ],
                    'cabys_prefixes' => [
                        '8512', // Servicios de educación
                        '8610', // Servicios de salud humana
                        '4901' // Servicios postales
                    ]
                ],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * Guarda o actualiza la configuración de un impuesto
     */
    public function save_or_update_tax_config($tax_data)
    {
        if (empty($tax_data['tax_code'])) {
            return false;
        }

        // Verificar si ya existe
        $this->db->where('tax_code', $tax_data['tax_code']);
        $existing = $this->db->get(db_prefix() . 'alegra_cr_tax_config')->row();

        // Preparar datos para guardar
        $db_data = [
            'tax_name' => $tax_data['tax_name'],
            'tax_code' => $tax_data['tax_code'],
            'alegra_tax_id' => isset($tax_data['alegra_tax_id']) ? $tax_data['alegra_tax_id'] : null,
            'tax_rate' => $tax_data['tax_rate'],
            'applies_to' => isset($tax_data['applies_to']) ? $tax_data['applies_to'] : 'all',
            'is_active' => isset($tax_data['is_active']) ? $tax_data['is_active'] : 1,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Procesar criteria si existe
        if (isset($tax_data['criteria']) && is_array($tax_data['criteria'])) {
            $db_data['criteria'] = json_encode($tax_data['criteria']);
        }

        if ($existing) {
            // Actualizar existente
            $this->db->where('id', $existing->id);
            return $this->db->update(db_prefix() . 'alegra_cr_tax_config', $db_data);
        } else {
            // Insertar nuevo
            $db_data['created_at'] = date('Y-m-d H:i:s');
            return $this->db->insert(db_prefix() . 'alegra_cr_tax_config', $db_data);
        }
    }


    /**
     * Obtiene la configuración de un impuesto específico por código
     */
    public function get_tax_by_code($tax_code)
    {
        $this->db->where('tax_code', $tax_code);
        $this->db->where('is_active', 1);
        $query = $this->db->get(db_prefix() . 'alegra_cr_tax_config');

        $result = $query->row_array();

        if ($result && !empty($result['criteria'])) {
            $result['criteria'] = json_decode($result['criteria'], true);
        }

        return $result;
    }

    /**
     * Obtiene impuestos por tasa
     */
    public function get_taxes_by_rate($tax_rate)
    {
        $this->db->where('tax_rate', $tax_rate);
        $this->db->where('is_active', 1);
        $query = $this->db->get(db_prefix() . 'alegra_cr_tax_config');

        $results = $query->result_array();

        foreach ($results as &$result) {
            if (!empty($result['criteria'])) {
                $result['criteria'] = json_decode($result['criteria'], true);
            }
        }

        return $results;
    }

    /**
     * Obtiene el ID de Alegra para una tasa específica
     */
    public function get_alegra_tax_id_by_rate($tax_rate)
    {
        $this->db->select('alegra_tax_id');
        $this->db->where('tax_rate', $tax_rate);
        $this->db->where('is_active', 1);
        $this->db->order_by('id', 'ASC');
        $query = $this->db->get(db_prefix() . 'alegra_cr_tax_config');

        $result = $query->row();

        return $result ? $result->alegra_tax_id : null;
    }

    /**
     * Verifica si un item califica para un impuesto específico
     */
    public function item_qualifies_for_tax($item, $tax_code)
    {
        $tax_config = $this->get_tax_by_code($tax_code);

        if (!$tax_config || $tax_config['applies_to'] === 'all') {
            return true;
        }

        if ($tax_config['applies_to'] === 'specific' && !empty($tax_config['criteria'])) {
            // Verificar por palabras clave en la descripción
            $description = strtolower($item['description']);
            foreach ($tax_config['criteria']['keywords'] as $keyword) {
                if (stripos($description, $keyword) !== false) {
                    return true;
                }
            }

            // Verificar por código CABYS
            if (isset($item['custom_fields']['CABYS']) && !empty($item['custom_fields']['CABYS'])) {
                $cabys_code = $item['custom_fields']['CABYS'];
                $prefix = substr($cabys_code, 0, 4);

                if (in_array($prefix, $tax_config['criteria']['cabys_prefixes'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Obtiene las devoluciones de IVA recientes
     */
    public function get_recent_iva_returns($limit = 50)
    {
        $this->db->select([
            'ivr.*',
            'c.company as client_name',
            'c.vat as client_identification',
            'i.number as invoice_number'
        ]);

        $this->db->from(db_prefix() . 'alegra_cr_iva_returns ivr');
        $this->db->join(db_prefix() . 'clients c', 'c.userid = ivr.perfex_client_id', 'left');
        $this->db->join(db_prefix() . 'invoices i', 'i.id = ivr.perfex_invoice_id', 'left');

        $this->db->order_by('ivr.created_at', 'DESC');
        $this->db->limit($limit);

        $query = $this->db->get();
        $results = $query->result_array();

        // Procesar notas JSON
        foreach ($results as &$result) {
            if (!empty($result['notes'])) {
                $result['notes'] = json_decode($result['notes'], true);
                if (!is_array($result['notes'])) {
                    $result['notes'] = [];
                }
            } else {
                $result['notes'] = [];
            }

            // Formatear montos
            $result['return_amount_formatted'] = app_format_money($result['return_amount'], 'CRC');
            $result['return_percentage_formatted'] = $result['return_percentage'] . '%';
        }

        return $results;
    }

    /**
     * Obtiene devoluciones de IVA por cliente
     */
    public function get_iva_returns_by_client($client_id, $limit = 20)
    {
        $this->db->where('perfex_client_id', $client_id);
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit);

        $query = $this->db->get(db_prefix() . 'alegra_cr_iva_returns');
        return $query->result_array();
    }

    /**
     * Obtiene devoluciones de IVA por estado
     */
    public function get_iva_returns_by_status($status, $limit = 50)
    {
        $this->db->where('status', $status);
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit);

        $query = $this->db->get(db_prefix() . 'alegra_cr_iva_returns');
        return $query->result_array();
    }

    /**
     * Obtiene estadísticas de devoluciones de IVA
     */
    public function get_iva_returns_stats()
    {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'completed' => 0,
            'total_amount' => 0,
            'pending_amount' => 0,
            'approved_amount' => 0
        ];

        // Contar por estado
        $this->db->select('status, COUNT(*) as count, SUM(return_amount) as total_amount');
        $this->db->group_by('status');
        $query = $this->db->get(db_prefix() . 'alegra_cr_iva_returns');

        foreach ($query->result_array() as $row) {
            $stats['total'] += $row['count'];
            $stats['total_amount'] += $row['total_amount'];

            switch ($row['status']) {
                case 'pending':
                    $stats['pending'] = $row['count'];
                    $stats['pending_amount'] = $row['total_amount'];
                    break;
                case 'approved':
                    $stats['approved'] = $row['count'];
                    $stats['approved_amount'] = $row['total_amount'];
                    break;
                case 'rejected':
                    $stats['rejected'] = $row['count'];
                    break;
                case 'completed':
                    $stats['completed'] = $row['count'];
                    break;
            }
        }

        return $stats;
    }

    /**
     * Obtiene devoluciones de IVA con filtros avanzados
     */
    public function get_filtered_iva_returns($filters = [])
    {
        $this->db->select([
            'ivr.*',
            'c.company as client_name',
            'c.vat as client_identification',
            'i.number as invoice_number',
            'i.total as invoice_amount'
        ]);

        $this->db->from(db_prefix() . 'alegra_cr_iva_returns ivr');
        $this->db->join(db_prefix() . 'clients c', 'c.userid = ivr.perfex_client_id', 'left');
        $this->db->join(db_prefix() . 'invoices i', 'i.id = ivr.perfex_invoice_id', 'left');

        // Aplicar filtros
        if (!empty($filters['status'])) {
            $this->db->where('ivr.status', $filters['status']);
        }

        if (!empty($filters['client_id'])) {
            $this->db->where('ivr.perfex_client_id', $filters['client_id']);
        }

        if (!empty($filters['date_from'])) {
            $this->db->where('ivr.created_at >=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $this->db->where('ivr.created_at <=', $filters['date_to'] . ' 23:59:59');
        }

        if (!empty($filters['client_type'])) {
            $this->db->where('ivr.client_type', $filters['client_type']);
        }

        // Ordenamiento
        $order_by = !empty($filters['order_by']) ? $filters['order_by'] : 'ivr.created_at';
        $order_dir = !empty($filters['order_dir']) ? $filters['order_dir'] : 'DESC';
        $this->db->order_by($order_by, $order_dir);

        // Límite
        $limit = !empty($filters['limit']) ? $filters['limit'] : 50;
        $this->db->limit($limit);

        // Paginación
        if (!empty($filters['offset'])) {
            $this->db->offset($filters['offset']);
        }

        $query = $this->db->get();
        $results = $query->result_array();

        // Procesar datos
        foreach ($results as &$result) {
            if (!empty($result['notes'])) {
                $result['notes'] = json_decode($result['notes'], true);
            } else {
                $result['notes'] = [];
            }

            // Calcular porcentaje real si es necesario
            if ($result['return_percentage'] == 0 && $result['invoice_amount'] > 0) {
                $result['calculated_percentage'] = ($result['return_amount'] / $result['invoice_amount']) * 100;
            } else {
                $result['calculated_percentage'] = $result['return_percentage'];
            }
        }

        return $results;
    }

    /**
     * Obtiene el total de devoluciones con filtros
     */
    public function count_filtered_iva_returns($filters = [])
    {
        $this->db->from(db_prefix() . 'alegra_cr_iva_returns ivr');

        // Aplicar los mismos filtros que get_filtered_iva_returns
        if (!empty($filters['status'])) {
            $this->db->where('ivr.status', $filters['status']);
        }

        if (!empty($filters['client_id'])) {
            $this->db->where('ivr.perfex_client_id', $filters['client_id']);
        }

        if (!empty($filters['date_from'])) {
            $this->db->where('ivr.created_at >=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $this->db->where('ivr.created_at <=', $filters['date_to'] . ' 23:59:59');
        }

        if (!empty($filters['client_type'])) {
            $this->db->where('ivr.client_type', $filters['client_type']);
        }

        return $this->db->count_all_results();
    }

    /**
     * Obtiene el resumen de devoluciones por mes
     */
    public function get_iva_returns_monthly_summary($months = 12)
    {
        $this->db->select([
            'DATE_FORMAT(created_at, "%Y-%m") as month',
            'COUNT(*) as total_returns',
            'SUM(return_amount) as total_amount',
            'status',
            'client_type'
        ]);

        $this->db->where('created_at >=', date('Y-m-01', strtotime("-$months months")));
        $this->db->group_by('DATE_FORMAT(created_at, "%Y-%m"), status, client_type');
        $this->db->order_by('month', 'DESC');

        $query = $this->db->get(db_prefix() . 'alegra_cr_iva_returns');
        $results = $query->result_array();

        // Organizar por mes
        $monthly_summary = [];
        foreach ($results as $row) {
            if (!isset($monthly_summary[$row['month']])) {
                $monthly_summary[$row['month']] = [
                    'month' => $row['month'],
                    'total_returns' => 0,
                    'total_amount' => 0,
                    'by_status' => [],
                    'by_client_type' => []
                ];
            }

            $monthly_summary[$row['month']]['total_returns'] += $row['total_returns'];
            $monthly_summary[$row['month']]['total_amount'] += $row['total_amount'];

            // Por estado
            if (!isset($monthly_summary[$row['month']]['by_status'][$row['status']])) {
                $monthly_summary[$row['month']]['by_status'][$row['status']] = 0;
            }
            $monthly_summary[$row['month']]['by_status'][$row['status']] += $row['total_returns'];

            // Por tipo de cliente
            if (!isset($monthly_summary[$row['month']]['by_client_type'][$row['client_type']])) {
                $monthly_summary[$row['month']]['by_client_type'][$row['client_type']] = 0;
            }
            $monthly_summary[$row['month']]['by_client_type'][$row['client_type']] += $row['total_returns'];
        }

        return array_values($monthly_summary);
    }

    /**
     * Obtiene la configuración de métodos de pago
     */
/**
 * Obtiene la configuración de métodos de pago
 */
public function get_payment_methods_config()
{
    $config = [
        'card_payment_methods' => [],
        'cash_payment_methods' => []
    ];

    try {
        // Obtener métodos de tarjeta
        $card_config = $this->db->get_where(db_prefix() . 'alegra_cr_payment_methods_config', 
            ['config_type' => 'card_payment_methods'])->row();
        
        if ($card_config) {
            $card_methods = json_decode($card_config->payment_method_ids, true);
            $config['card_payment_methods'] = is_array($card_methods) ? $card_methods : [];
        }

        // Obtener métodos de efectivo
        $cash_config = $this->db->get_where(db_prefix() . 'alegra_cr_payment_methods_config', 
            ['config_type' => 'cash_payment_methods'])->row();
        
        if ($cash_config) {
            $cash_methods = json_decode($cash_config->payment_method_ids, true);
            $config['cash_payment_methods'] = is_array($cash_methods) ? $cash_methods : [];
        }

        return $config;
    } catch (Exception $e) {
        log_message('error', 'Error obteniendo configuración de métodos de pago: ' . $e->getMessage());
        return $config;
    }
}

    /**
     * Guarda la configuración de métodos de pago
     */
/**
 * Guarda la configuración de métodos de pago
 */
public function save_payment_methods_config($config)
{
    try {
        // Actualizar métodos de tarjeta
        $this->db->where('config_type', 'card_payment_methods');
        $card_exists = $this->db->get(db_prefix() . 'alegra_cr_payment_methods_config')->row();
        
        $card_data = [
            'payment_method_ids' => json_encode($config['card_payment_methods']),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($card_exists) {
            $this->db->where('config_type', 'card_payment_methods');
            $this->db->update(db_prefix() . 'alegra_cr_payment_methods_config', $card_data);
        } else {
            $card_data['config_type'] = 'card_payment_methods';
            $card_data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert(db_prefix() . 'alegra_cr_payment_methods_config', $card_data);
        }

        // Actualizar métodos de efectivo
        $this->db->where('config_type', 'cash_payment_methods');
        $cash_exists = $this->db->get(db_prefix() . 'alegra_cr_payment_methods_config')->row();
        
        $cash_data = [
            'payment_method_ids' => json_encode($config['cash_payment_methods']),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($cash_exists) {
            $this->db->where('config_type', 'cash_payment_methods');
            $this->db->update(db_prefix() . 'alegra_cr_payment_methods_config', $cash_data);
        } else {
            $cash_data['config_type'] = 'cash_payment_methods';
            $cash_data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert(db_prefix() . 'alegra_cr_payment_methods_config', $cash_data);
        }

        return true;
    } catch (Exception $e) {
        log_message('error', 'Error guardando configuración de métodos de pago: ' . $e->getMessage());
        return false;
    }
}

    /**
     * Obtiene todos los métodos de pago de Perfex
     */
    public function get_perfex_payment_modes()
    {
        $this->db->where('active', 1);
        $this->db->order_by('name', 'ASC');
        $query = $this->db->get(db_prefix() . 'payment_modes');

        return $query->result_array();
    }

    /**
     * Obtiene todos los métodos de pago configurados para auto-transmisión
     */
    public function get_auto_transmit_payment_methods()
    {
        $setting = $this->get_setting('auto_transmit_payment_methods', '[]');
        return json_decode($setting, true) ?: [];
    }

    /**
     * Obtener estadísticas de auto-transmisión para el dashboard
     */
    public function get_auto_transmit_stats($days = 30)
    {
        $date_from = date('Y-m-d', strtotime('-' . $days . ' days'));

        $stats = [];

        // Total de facturas auto-transmitidas
        $this->db->select('COUNT(*) as total');
        $this->db->from('alegra_cr_invoices_map');
        $this->db->where('status', 'completed');
        $this->db->where('sync_date >=', $date_from);
        $auto_transmitted = $this->db->get()->row();
        $stats['auto_transmitted'] = $auto_transmitted ? $auto_transmitted->total : 0;

        // Total de facturas creadas en el período
        $this->db->select('COUNT(*) as total');
        $this->db->from('invoices');
        $this->db->where('date >=', $date_from);
        $total_invoices = $this->db->get()->row();
        $stats['total_invoices'] = $total_invoices ? $total_invoices->total : 0;

        // Porcentaje de auto-transmisión
        $stats['auto_transmit_percentage'] = $stats['total_invoices'] > 0 ?
            round(($stats['auto_transmitted'] / $stats['total_invoices']) * 100, 2) : 0;

        // Errores en auto-transmisión
        $this->db->select('COUNT(*) as total');
        $this->db->from('alegra_cr_invoices_map');
        $this->db->where('status', 'error');
        $this->db->where('sync_date >=', $date_from);
        $errors = $this->db->get()->row();
        $stats['errors'] = $errors ? $errors->total : 0;

        return $stats;
    }

    /**
     * Obtener información de debug para auto-transmisión
     */
    public function get_auto_transmit_debug_info()
    {
        $settings = $this->get_settings();

        $configured_methods = [];
        if (isset($settings['auto_transmit_payment_methods'])) {
            $methods = json_decode($settings['auto_transmit_payment_methods'], true);
            $configured_methods = is_array($methods) ? $methods : [];
        }

        return [
            'enabled' => isset($settings['auto_transmit_enabled']) ? (bool) $settings['auto_transmit_enabled'] : false,
            'medical_only' => isset($settings['auto_transmit_medical_only']) ? (bool) $settings['auto_transmit_medical_only'] : false,
            'configured_methods' => $configured_methods,
            'medical_keywords' => isset($settings['medical_keywords']) ? $settings['medical_keywords'] : '',
            'delay' => isset($settings['auto_transmit_delay']) ? (int) $settings['auto_transmit_delay'] : 0,
            'notifications' => isset($settings['notify_auto_transmit']) ? (bool) $settings['notify_auto_transmit'] : false
        ];
    }

    /**
     * Verificar si un método de pago está configurado para auto-transmisión
     */
    public function is_payment_method_auto_transmit($method_id)
    {
        $settings = $this->get_settings();

        if (!isset($settings['auto_transmit_payment_methods'])) {
            return false;
        }

        $methods = json_decode($settings['auto_transmit_payment_methods'], true);
        return is_array($methods) && in_array((string) $method_id, $methods);
    }

    /**
     * Verificar si solo se deben auto-transmitir facturas con servicios médicos
     */
    public function is_medical_only_auto_transmit()
    {
        $settings = $this->get_settings();
        return isset($settings['auto_transmit_medical_only']) && $settings['auto_transmit_medical_only'] == '1';
    }

    /**
     * Verificar si una factura tiene servicios médicos
     */
    public function invoice_has_medical_services($invoice_id)
    {
        $this->db->select('description');
        $this->db->from('itemable');
        $this->db->where('rel_id', $invoice_id);
        $this->db->where('rel_type', 'invoice');
        $items = $this->db->get()->result_array();

        foreach ($items as $item) {
            if ($this->is_medical_service_item($item['description'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluar si una factura debe auto-transmitirse
     */
    public function should_auto_transmit_invoice($invoice)
    {
        // 1. Verificar si auto-transmisión está habilitada
        $settings = $this->get_settings();

        if (!isset($settings['auto_transmit_enabled']) || $settings['auto_transmit_enabled'] != '1') {
            return [
                'should_transmit' => false,
                'reason' => 'Auto-transmisión deshabilitada'
            ];
        }

        // 2. Verificar métodos de pago
        $configured_methods = [];
        if (isset($settings['auto_transmit_payment_methods'])) {
            $methods = json_decode($settings['auto_transmit_payment_methods'], true);
            $configured_methods = is_array($methods) ? $methods : [];
        }

        if (empty($configured_methods)) {
            return [
                'should_transmit' => false,
                'reason' => 'No hay métodos de pago configurados'
            ];
        }

        // 3. Verificar si el método de pago de la factura coincide
        $invoice_methods = $this->get_invoice_payment_methods($invoice);
        $method_match = false;

        foreach ($invoice_methods as $method_id) {
            if (in_array((string) $method_id, $configured_methods)) {
                $method_match = true;
                break;
            }
        }

        if (!$method_match) {
            return [
                'should_transmit' => false,
                'reason' => 'Método de pago no configurado para auto-transmisión',
                'invoice_methods' => $invoice_methods,
                'configured_methods' => $configured_methods
            ];
        }

        // 4. Verificar servicios médicos si está configurado
        if (isset($settings['auto_transmit_medical_only']) && $settings['auto_transmit_medical_only'] == '1') {
            if (!$this->invoice_has_medical_services($invoice->id)) {
                return [
                    'should_transmit' => false,
                    'reason' => 'Solo servicios médicos habilitado y esta factura no los tiene'
                ];
            }
        }

        return [
            'should_transmit' => true,
            'reason' => 'Cumple todos los criterios de auto-transmisión'
        ];
    }

    /**
     * Obtener métodos de pago de una factura
     */
    private function get_invoice_payment_methods($invoice)
    {
        $methods = [];

        // Verificar allowed_payment_modes
        if (isset($invoice->allowed_payment_modes) && !empty($invoice->allowed_payment_modes)) {
            $allowed_modes = is_string($invoice->allowed_payment_modes) ?
                unserialize($invoice->allowed_payment_modes) :
                $invoice->allowed_payment_modes;

            if (is_array($allowed_modes)) {
                $methods = array_map('strval', $allowed_modes);
            }
        }

        // Si no hay métodos específicos, verificar paymentmethod
        if (empty($methods) && isset($invoice->paymentmethod)) {
            $methods[] = (string) $invoice->paymentmethod;
        }

        return $methods;
    }


    /**
     * Limpiar configuración de impuestos
     */
    public function clear_tax_config()
    {
        return $this->db->truncate('alegra_cr_tax_config');
    }

    /**
     * Obtener todas las configuraciones para la vista unificada
     */
    public function get_unified_settings()
    {
        return [
            'general_settings' => $this->get_settings(),
            'payment_config' => $this->get_payment_methods_config(),
            'payment_modes' => $this->get_perfex_payment_modes(),
            'tax_config' => $this->get_all_tax_config(),
            'stats' => $this->get_auto_transmit_stats()
        ];
    }
}