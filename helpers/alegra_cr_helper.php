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

        if (empty($credentials) || empty($credentials['alegra_email']) || empty($credentials['alegra_token'])) {
            log_message('error', 'Alegra API credentials not configured or not found.');
            return ['error' => 'Alegra API credentials not configured.'];
        }

        $auth_string = $credentials['alegra_email'] . ':' . $credentials['alegra_token'];
        $auth_header = 'Basic ' . base64_encode($auth_string);

        $api_url = 'https://api.alegra.com/api/v1/' . $endpoint;

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
        log_message('debug', 'Alegra API Request: ' . $method . ' ' . $endpoint);
        log_message('debug', 'Alegra API Status: ' . $http_code);
        log_message('debug', 'Alegra API Response: ' . $response);

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