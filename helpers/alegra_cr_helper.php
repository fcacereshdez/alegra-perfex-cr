<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Helper para la integración con Alegra
 */

if (!function_exists('alegra_cr_api_request')) {
    /**
     * Realiza una solicitud a la API de Alegra.
     *
     * @param string $method 'GET', 'POST', 'PUT', 'DELETE'
     * @param string $endpoint El endpoint de la API a llamar
     * @param array $data Datos para enviar en la solicitud (para POST y PUT)
     * @return array La respuesta de la API decodificada
     */
    function alegra_cr_api_request($method, $endpoint, $data = [])
    {
        $CI = &get_instance();
        $CI->load->model('alegra_cr_model');

        $credentials = $CI->alegra_cr_model->get_api_credentials();
        log_message('error', json_encode($credentials));

        if (empty($credentials) || empty($credentials['alegra_email']) || empty($credentials['alegra_token'])) {
            log_message('error', 'Alegra API credentials not configured or not found.');
            return ['error' => 'Alegra API credentials not configured.'];
        }

        $auth_string = $credentials['alegra_email'] . ':' . $credentials['alegra_token'];
        $auth_header = 'Basic ' . base64_encode($auth_string);
        log_message('error', json_encode($auth_header));

        $api_url = 'https://api.alegra.com/api/v1/' . $endpoint;
        log_message('error', json_encode($api_url));

        $ch = curl_init();

        $options = [
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $auth_header,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
        ];

        if (in_array($method, ['POST', 'PUT']) && !empty($data)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        if ($curl_error) {
            log_message('error', 'cURL Error: ' . $curl_error);
            curl_close($ch);
            return ['error' => 'cURL Error: ' . $curl_error];
        }

        curl_close($ch);

        $decoded_response = json_decode($response, true);

        // Log para debugging
        log_message('error', 'Alegra API Request: ' . $method . ' ' . $endpoint);
        log_message('error', 'Alegra API Status: ' . $http_code);
        log_message('error', 'Alegra API Response: ' . $response);

        if ($http_code >= 400) {
            $error_message = 'HTTP Error ' . $http_code;
            if (isset($decoded_response['message'])) {
                $error_message .= ': ' . $decoded_response['message'];
            } elseif (isset($decoded_response['error'])) {
                $error_message .= ': ' . $decoded_response['error'];
            } elseif (!empty($response)) {
                $error_message .= ': ' . substr($response, 0, 200);
            }
            
            log_message('error', 'Alegra API Error: ' . $error_message);
            return ['error' => $error_message, 'status_code' => $http_code];
        }

        return $decoded_response;
    }
}

if (!function_exists('alegra_cr_get_tax_settings')) {
    /**
     * Obtiene la configuración de impuestos para Alegra
     */
    function alegra_cr_get_tax_settings()
    {
        // Por defecto, impuesto de venta de Costa Rica (13%)
        return [
            [
                'id' => 1, // ID del impuesto en Alegra (debe configurarse)
                'percentage' => 13
            ]
        ];
    }
}

if (!function_exists('alegra_cr_format_identification')) {
    /**
     * Formatea la identificación para Alegra
     */
    function alegra_cr_format_identification($vat)
    {
        if (empty($vat)) {
            return [
                'type' => 'CF', // Consumidor Final
                'number' => '0'
            ];
        }

        // Limpiar caracteres no numéricos
        $clean_vat = preg_replace('/[^0-9]/', '', $vat);
        
        // Determinar tipo basado en la longitud
        if (strlen($clean_vat) == 9) {
            return [
                'type' => '01', // Cédula Física
                'number' => $clean_vat
            ];
        } elseif (strlen($clean_vat) == 10) {
            return [
                'type' => '02', // Cédula Jurídica
                'number' => $clean_vat
            ];
        } else {
            return [
                'type' => '03', // DIMEX
                'number' => $clean_vat
            ];
        }
    }
}

if (!function_exists('alegra_cr_get_tax_configuration')) {
    /**
     * Obtiene la configuración de impuestos específica para Costa Rica
     */
    function alegra_cr_get_tax_configuration()
    {
        return [
            'IVA_STANDARD' => [
                'alegra_id' => 1,
                'rate' => 13,
                'name' => 'Impuesto de Ventas',
                'description' => 'IVA estándar del 13%'
            ],
            'IVA_REDUCED' => [
                'alegra_id' => 2,
                'rate' => 4,
                'name' => 'IVA Reducido',
                'description' => 'IVA reducido del 4% para productos específicos'
            ],
            'EXEMPT' => [
                'alegra_id' => null,
                'rate' => 0,
                'name' => 'Exento',
                'description' => 'Productos exentos de impuesto'
            ]
        ];
    }
}

if (!function_exists('alegra_cr_map_perfex_tax_to_alegra')) {
    /**
     * Mapea un impuesto de Perfex al formato de Alegra para Costa Rica
     */
    function alegra_cr_map_perfex_tax_to_alegra($tax_name, $tax_rate)
    {
        $tax_config = alegra_cr_get_tax_configuration();
        
        // Normalizar nombre del impuesto
        $normalized_name = strtolower(trim($tax_name));
        
        // Determinar el tipo de impuesto basado en la tasa
        if ($tax_rate == 0) {
            return $tax_config['EXEMPT'];
        } elseif ($tax_rate == 4) {
            return $tax_config['IVA_REDUCED'];
        } elseif ($tax_rate == 13) {
            return $tax_config['IVA_STANDARD'];
        }
        
        // Mapeo por nombre
        $name_mappings = [
            'iva' => 'IVA_STANDARD',
            'impuesto de ventas' => 'IVA_STANDARD',
            'sales tax' => 'IVA_STANDARD',
            'iva reducido' => 'IVA_REDUCED',
            'iva 4%' => 'IVA_REDUCED',
            'exento' => 'EXEMPT',
            'exempt' => 'EXEMPT'
        ];
        
        foreach ($name_mappings as $pattern => $type) {
            if (stripos($normalized_name, $pattern) !== false) {
                return $tax_config[$type];
            }
        }
        
        // Por defecto, usar IVA estándar
        return $tax_config['IVA_STANDARD'];
    }
}

if (!function_exists('alegra_cr_get_products_with_reduced_iva')) {
    /**
     * Obtiene lista de productos que califican para IVA reducido del 4%
     */
    function alegra_cr_get_products_with_reduced_iva()
    {
        return [
            // Códigos CABYS que califican para IVA del 4%
            'cabys_codes' => [
                // Medicamentos y productos farmacéuticos
                '21030', '21031', '21032', '21033', '21034',
                '21040', '21041', '21042', '21043', '21044',
                
                // Alimentos básicos
                '10010', '10011', '10012', '10013',
                '10020', '10021', '10022', '10023',
                
                // Material médico
                '33110', '33111', '33112',
                
                // Productos de higiene básica
                '39221', '39222', '39223',
            ],
            
            // Palabras clave en descripciones
            'keywords' => [
                'medicamento', 'medicina', 'fármaco', 'droga médica',
                'arroz', 'frijoles', 'maíz', 'pan', 'leche',
                'aceite comestible', 'azúcar', 'sal',
                'material médico', 'jeringa', 'vendaje',
                'jabón', 'champú', 'pasta dental', 'papel higiénico'
            ]
        ];
    }
}

if (!function_exists('alegra_cr_calculate_iva_return')) {
    /**
     * Calcula el IVA que se puede devolver al usuario (específico para Costa Rica)
     */
    function alegra_cr_calculate_iva_return($invoice_total, $iva_paid, $client_type = 'individual')
    {
        $return_data = [
            'eligible' => false,
            'return_amount' => 0,
            'return_percentage' => 0,
            'notes' => []
        ];
        
        // Verificar si el cliente es elegible para devolución de IVA
        // En Costa Rica, ciertos tipos de clientes pueden recibir devoluciones
        
        $eligible_client_types = ['exportador', 'exonerado', 'diplomático'];
        
        if (in_array($client_type, $eligible_client_types)) {
            $return_data['eligible'] = true;
            
            // Calcular porcentaje de devolución según tipo de cliente
            switch ($client_type) {
                case 'exportador':
                    $return_data['return_percentage'] = 100; // 100% del IVA
                    $return_data['notes'][] = 'Devolución completa por actividad exportadora';
                    break;
                    
                case 'exonerado':
                    $return_data['return_percentage'] = 100; // 100% del IVA
                    $return_data['notes'][] = 'Devolución por exoneración institucional';
                    break;
                    
                case 'diplomático':
                    $return_data['return_percentage'] = 100; // 100% del IVA
                    $return_data['notes'][] = 'Devolución por inmunidad diplomática';
                    break;
            }
            
            $return_data['return_amount'] = ($iva_paid * $return_data['return_percentage']) / 100;
        } else {
            $return_data['notes'][] = 'Cliente no elegible para devolución de IVA';
        }
        
        return $return_data;
    }
}

if (!function_exists('alegra_cr_validate_tax_rates')) {
    /**
     * Valida que las tasas de impuesto sean correctas para Costa Rica
     */
    function alegra_cr_validate_tax_rates($items)
    {
        $valid_rates = [0, 4, 13]; // Tasas válidas en Costa Rica
        $errors = [];
        
        foreach ($items as $index => $item) {
            if (isset($item['tax_rate'])) {
                $rate = floatval($item['tax_rate']);
                
                if (!in_array($rate, $valid_rates)) {
                    $errors[] = [
                        'item_index' => $index,
                        'item_description' => $item['description'] ?? 'Item ' . ($index + 1),
                        'invalid_rate' => $rate,
                        'message' => "Tasa de impuesto {$rate}% no es válida en Costa Rica. Tasas válidas: " . implode(', ', $valid_rates) . "%"
                    ];
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

if (!function_exists('alegra_cr_format_tax_for_invoice')) {
    /**
     * Formatea los impuestos para una factura específica en el formato de Alegra
     */
    function alegra_cr_format_tax_for_invoice($perfex_item, $force_rate = null)
    {
        $taxes = [];
        
        // Si se fuerza una tasa específica
        if ($force_rate !== null) {
            $tax_config = alegra_cr_get_tax_configuration();
            
            if ($force_rate == 0) {
                return []; // Sin impuestos
            } elseif ($force_rate == 4) {
                $config = $tax_config['IVA_REDUCED'];
            } else {
                $config = $tax_config['IVA_STANDARD'];
            }
            
            return [[
                'id' => $config['alegra_id'],
                'percentage' => $force_rate
            ]];
        }
        
        // Procesar impuestos existentes del item de Perfex
        for ($i = 1; $i <= 2; $i++) {
            $tax_name_field = 'taxname_' . $i;
            $tax_rate_field = 'taxrate_' . $i;
            
            if (isset($perfex_item[$tax_name_field]) && !empty($perfex_item[$tax_name_field])) {
                $tax_name = $perfex_item[$tax_name_field];
                $tax_rate = floatval($perfex_item[$tax_rate_field]);
                
                $alegra_tax = alegra_cr_map_perfex_tax_to_alegra($tax_name, $tax_rate);
                
                if ($alegra_tax['alegra_id'] !== null) {
                    $taxes[] = [
                        'id' => $alegra_tax['alegra_id'],
                        'percentage' => $tax_rate
                    ];
                }
            }
        }
        
        // Si no hay impuestos definidos, usar IVA estándar
        if (empty($taxes)) {
            $tax_config = alegra_cr_get_tax_configuration();
            $standard_tax = $tax_config['IVA_STANDARD'];
            
            $taxes[] = [
                'id' => $standard_tax['alegra_id'],
                'percentage' => $standard_tax['rate']
            ];
        }
        
        return $taxes;
    }

    if (!function_exists('get_datatables_language_url')) {
    function get_datatables_language_url() {
        // Detectar idioma del sistema
        $language = get_option('active_language');
        
        switch ($language) {
            case 'spanish':
                return 'Spanish.json';
            case 'english':
            default:
                return 'English.json';
        }
    }
}

}