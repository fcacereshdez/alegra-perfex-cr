<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Alegra Facturacion Costa Rica
Description: Integración con Alegra para facturación electrónica de Costa Rica.
Version: 1.0.0
Author: Francisco Caceres
Requires at least: 2.3.0
*/

define('ALEGRA_CR_MODULE_NAME', 'alegra_facturacion_cr');
define('ALEGRA_CR_MODULE_VERSION', '1.0.0');

// Instalación automática
$CI = &get_instance();
if (!$CI->db->table_exists(db_prefix() . 'alegra_cr_settings')) {
    alegra_cr_install_now();
}

// Hooks principales de admin_init
hooks()->add_action('admin_init', 'alegra_cr_admin_init');
hooks()->add_action('admin_init', 'alegra_cr_module_activation_hook');

// Hook para los action links del módulo
hooks()->add_filter('module_alegra_facturacion_cr_action_links', 'module_alegra_cr_action_links');

/**
 * Agregar enlaces de acción adicionales
 */
function module_alegra_cr_action_links($actions)
{
    if (get_instance()->app_modules->is_active('alegra_facturacion_cr')) {
        $actions[] = '<a href="' . admin_url('settings?group=alegra_cr') . '">' . _l('settings') . '</a>';
    }
    
    return $actions;
}

/**
 * Inicialización del administrador
 */
function alegra_cr_admin_init()
{
    // Registrar opciones en el sistema
    alegra_cr_register_options();
    
    // Agregar sección de settings
    alegra_cr_add_settings_section();
    
    // Cargar hooks del módulo
    $hooks_file = __DIR__ . '/hooks.php';
    if (file_exists($hooks_file)) {
        include_once($hooks_file);
    }
    
    // Crear menús del módulo
    if (has_permission('alegra_cr', '', 'view')) {
        alegra_cr_create_admin_menus();
    }
}

/**
 * Agregar sección de configuración
 */
function alegra_cr_add_settings_section()
{
    $CI = &get_instance();
    
    $CI->app->add_settings_section('integrations', [
        'name'     => _l('integrations'),
        'position' => 50,
        'icon'     => 'fa fa-plug',
    ]);
    
    $CI->app->add_settings_section_child(
        'integrations',
        'alegra_cr',
        [
            'name'     => 'Alegra CR',
            'view'     => 'alegra_facturacion_cr/settings',
            'position' => 10,
            'icon'     => 'fa fa-server',
        ]
    );
    
    hooks()->add_action('settings_updated', 'alegra_cr_process_settings_save_v2');
}


/**
 * Procesar checkboxes de métodos de pago
 */
function alegra_cr_process_payment_methods_checkboxes($post_data)
{
    $card_methods = [];
    $cash_methods = [];
    
    // Extraer métodos de pago desde los checkboxes
    if (isset($post_data['settings']) && is_array($post_data['settings'])) {
        foreach ($post_data['settings'] as $key => $value) {
            // Checkboxes de tarjeta
            if (strpos($key, 'alegra_cr_card_payment_methods_') === 0) {
                $method_id = str_replace('alegra_cr_card_payment_methods_', '', $key);
                $card_methods[] = $method_id;
            }
            
            // Checkboxes de efectivo
            if (strpos($key, 'alegra_cr_cash_payment_methods_') === 0) {
                $method_id = str_replace('alegra_cr_cash_payment_methods_', '', $key);
                $cash_methods[] = $method_id;
            }
        }
    }
    
    // Guardar como JSON
    update_option('alegra_cr_card_payment_methods', json_encode($card_methods));
    update_option('alegra_cr_cash_payment_methods', json_encode($cash_methods));
    
    // También guardar en auto_transmit_payment_methods (todos los métodos marcados)
    $all_methods = array_merge($card_methods, $cash_methods);
    update_option('alegra_cr_auto_transmit_payment_methods', json_encode($all_methods));
    
    log_message('error', 'Alegra CR: Métodos tarjeta: ' . json_encode($card_methods));
    log_message('error', 'Alegra CR: Métodos efectivo: ' . json_encode($cash_methods));
}

/**
 * Hook de activación del módulo
 */
function alegra_cr_module_activation_hook()
{
    alegra_cr_register_options();
}

/**
 * Crear menús de administración
 */
function alegra_cr_create_admin_menus()
{
    $CI = &get_instance();

    // Menú principal
    $CI->app_menu->add_sidebar_menu_item('alegra-cr', [
        'name'     => _l('alegra_cr'),
        'href'     => admin_url('alegra_facturacion_cr/invoices'),
        'position' => 35,
        'icon'     => 'fa fa-server'
    ]);

    // Submenús
    $CI->app_menu->add_sidebar_children_item('alegra-cr', [
        'slug'     => 'alegra-cr-invoices',
        'name'     => _l('alegra_cr_invoices'),
        'href'     => admin_url('alegra_facturacion_cr/invoices'),
        'position' => 1,
    ]);

}

/**
 * Registrar TODAS las opciones del módulo
 */
function alegra_cr_register_options()
{
    $all_options = [
        // Credenciales API
        'alegra_cr_email' => '',
        'alegra_cr_token' => '',
        
        // Auto-transmisión
        'alegra_cr_auto_transmit_enabled' => '0',
        'alegra_cr_auto_transmit_payment_methods' => '[]',
        'alegra_cr_auto_transmit_medical_only' => '0',
        'alegra_cr_auto_detect_medical_services' => '1',
        'alegra_cr_notify_auto_transmit' => '1',
        'alegra_cr_medical_keywords' => 'consulta,examen,chequeo,revisión,diagnóstico,cirugía,operación,procedimiento,terapia,sesión,doctor,médico,especialista,evaluación',
        'alegra_cr_auto_transmit_delay' => '0',
        
        // Métodos de pago
        'alegra_cr_card_payment_methods' => '[]',
        'alegra_cr_cash_payment_methods' => '[]',
        
        // Configuración de impresión
        'alegra_cr_default_printer_type' => 'web',
        'alegra_cr_thermal_printer_ip' => '192.168.1.100',
        'alegra_cr_thermal_printer_port' => '9100',
        'alegra_cr_ticket_width' => '48',
        'alegra_cr_auto_print' => '1',
        
        // Logo y branding
        'alegra_cr_print_logo' => '1',
        'alegra_cr_company_logo_path' => '',
        'alegra_cr_logo_width' => '120',
        'alegra_cr_logo_height' => '70',
        
        // Footer
        'alegra_cr_print_footer_message' => 'Gracias por su compra',
        'alegra_cr_footer_conditions' => '',
        'alegra_cr_show_footer_conditions' => '1',
        'alegra_cr_show_footer_conditions_ticket' => '1',
        'alegra_cr_footer_legal_text' => '',
        
        // Metadatos
        'alegra_cr_installed' => '',
        'alegra_cr_version' => '',
        'alegra_cr_last_update' => ''
    ];
    
    foreach ($all_options as $option_name => $default_value) {
        if (get_option($option_name) === false) {
            add_option($option_name, $default_value);
        }
    }
}

/**
 * Instalación inmediata del módulo
 */
function alegra_cr_install_now()
{
    // Cargar el instalador completo
    $installer_file = __DIR__ . '/install.php';
    if (file_exists($installer_file)) {
        require_once($installer_file);
        if (function_exists('alegra_cr_install_database')) {
            alegra_cr_install_database();
        }
    }
}

// ============================================================================
// FUNCIONES AUXILIARES
// ============================================================================

if (!function_exists('alegra_cr_get_option')) {
    function alegra_cr_get_option($key, $default = null)
    {
        if (strpos($key, 'alegra_cr_') !== 0) {
            $key = 'alegra_cr_' . $key;
        }
        
        $value = get_option($key);
        
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}

if (!function_exists('alegra_cr_get_all_settings')) {
    function alegra_cr_get_all_settings()
    {
        return [
            'alegra_email' => alegra_cr_get_option('email', ''),
            'alegra_token' => alegra_cr_get_option('token', ''),
            'auto_transmit_enabled' => alegra_cr_get_option('auto_transmit_enabled', '0'),
            'auto_transmit_payment_methods' => alegra_cr_get_option('auto_transmit_payment_methods', []),
            'auto_transmit_medical_only' => alegra_cr_get_option('auto_transmit_medical_only', '0'),
            'auto_detect_medical_services' => alegra_cr_get_option('auto_detect_medical_services', '1'),
            'notify_auto_transmit' => alegra_cr_get_option('notify_auto_transmit', '1'),
            'medical_keywords' => alegra_cr_get_option('medical_keywords', 'consulta,examen,chequeo,revisión,diagnóstico,cirugía,operación,procedimiento,terapia,sesión,doctor,médico,especialista,evaluación'),
            'auto_transmit_delay' => alegra_cr_get_option('auto_transmit_delay', '0'),
            'card_payment_methods' => alegra_cr_get_option('card_payment_methods', []),
            'cash_payment_methods' => alegra_cr_get_option('cash_payment_methods', []),
            'default_printer_type' => alegra_cr_get_option('default_printer_type', 'web'),
            'thermal_printer_ip' => alegra_cr_get_option('thermal_printer_ip', '192.168.1.100'),
            'thermal_printer_port' => alegra_cr_get_option('thermal_printer_port', '9100'),
            'ticket_width' => alegra_cr_get_option('ticket_width', '48'),
            'auto_print' => alegra_cr_get_option('auto_print', '1'),
            'logo_width' => alegra_cr_get_option('logo_width', '120'),
            'logo_height' => alegra_cr_get_option('logo_height', '70'),
            'print_logo' => alegra_cr_get_option('print_logo', '1'),
            'company_logo_path' => alegra_cr_get_option('company_logo_path', ''),
            'print_footer_message' => alegra_cr_get_option('print_footer_message', ''),
            'footer_conditions' => alegra_cr_get_option('footer_conditions', ''),
            'show_footer_conditions' => alegra_cr_get_option('show_footer_conditions', '1'),
            'show_footer_conditions_ticket' => alegra_cr_get_option('show_footer_conditions_ticket', '1'),
            'footer_legal_text' => alegra_cr_get_option('footer_legal_text', '')
        ];
    }
}

if (!function_exists('alegra_cr_get_payment_config')) {
    function alegra_cr_get_payment_config()
    {
        $card_methods = alegra_cr_get_option('card_payment_methods', []);
        $cash_methods = alegra_cr_get_option('cash_payment_methods', []);
        
        // Asegurar que sean arrays
        if (is_string($card_methods)) {
            $card_methods = json_decode($card_methods, true);
        }
        if (is_string($cash_methods)) {
            $cash_methods = json_decode($cash_methods, true);
        }
        
        return [
            'card_payment_methods' => is_array($card_methods) ? $card_methods : [],
            'cash_payment_methods' => is_array($cash_methods) ? $cash_methods : []
        ];
    }
}

if (!function_exists('alegra_cr_get_current_settings')) {
    function alegra_cr_get_current_settings()
    {
        return alegra_cr_get_all_settings();
    }
}

if (!function_exists('alegra_cr_get_payment_modes')) {
    function alegra_cr_get_payment_modes()
    {
        $CI = &get_instance();
        $payment_modes = [];
        
        $result = $CI->db->get_where('payment_modes', ['active' => 1])->result_array();
        
        foreach ($result as $mode) {
            $payment_modes[] = [
                'id' => $mode['id'],
                'name' => $mode['name'],
                'description' => $mode['description'] ?? ''
            ];
        }
        
        return $payment_modes;
    }
}

if (!function_exists('alegra_cr_is_configured')) {
    function alegra_cr_is_configured()
    {
        $required_settings = ['alegra_cr_email', 'alegra_cr_token'];
        
        foreach ($required_settings as $setting) {
            if (empty(get_option($setting))) {
                return false;
            }
        }
        
        return true;
    }
}

if (!function_exists('alegra_cr_get_stats')) {
    function alegra_cr_get_stats()
    {
        $CI = &get_instance();
        $stats = [];
        
        try {
            // Contar facturas sincronizadas
            if ($CI->db->table_exists(db_prefix() . 'alegra_cr_invoices_map')) {
                $stats['synced_invoices'] = $CI->db->count_all(db_prefix() . 'alegra_cr_invoices_map');
            }
            
            // Estado de configuración
            $stats['is_configured'] = alegra_cr_is_configured();
            $stats['auto_transmit_enabled'] = get_option('alegra_cr_auto_transmit_enabled') == '1';
            
        } catch (Exception $e) {
            log_message('error', 'Error obteniendo estadísticas de Alegra CR: ' . $e->getMessage());
            $stats['error'] = true;
        }
        
        return $stats;
    }

    /**
 * Procesar checkboxes de métodos de pago V2
 * Ahora separa auto-transmisión de devolución IVA
 */
function alegra_cr_process_payment_methods_checkboxes_v2($post_data)
{
    $auto_transmit_methods = [];
    $iva_return_methods = [];
    
    // Extraer métodos de pago desde los checkboxes
    if (isset($post_data['settings']) && is_array($post_data['settings'])) {
        foreach ($post_data['settings'] as $key => $value) {
            // Checkboxes de auto-transmisión
            if (strpos($key, 'alegra_cr_auto_transmit_methods_') === 0) {
                $method_id = str_replace('alegra_cr_auto_transmit_methods_', '', $key);
                $auto_transmit_methods[] = $method_id;
            }
            
            // Checkboxes de devolución IVA
            if (strpos($key, 'alegra_cr_iva_return_methods_') === 0) {
                $method_id = str_replace('alegra_cr_iva_return_methods_', '', $key);
                $iva_return_methods[] = $method_id;
            }
        }
    }
    
    // Guardar como JSON
    update_option('alegra_cr_auto_transmit_payment_methods', json_encode($auto_transmit_methods));
    update_option('alegra_cr_iva_return_payment_methods', json_encode($iva_return_methods));
    
    log_message('error', 'Alegra CR: Métodos auto-transmisión: ' . json_encode($auto_transmit_methods));
    log_message('error', 'Alegra CR: Métodos devolución IVA: ' . json_encode($iva_return_methods));
}

/**
 * Obtener configuración de métodos de pago V2
 */
if (!function_exists('alegra_cr_get_payment_config_v2')) {
    function alegra_cr_get_payment_config_v2()
    {
        $auto_transmit = alegra_cr_get_option('auto_transmit_payment_methods', []);
        $iva_return = alegra_cr_get_option('iva_return_payment_methods', []);
        
        // Asegurar que sean arrays
        if (is_string($auto_transmit)) {
            $auto_transmit = json_decode($auto_transmit, true);
        }
        if (is_string($iva_return)) {
            $iva_return = json_decode($iva_return, true);
        }
        
        return [
            'auto_transmit_methods' => is_array($auto_transmit) ? $auto_transmit : [],
            'iva_return_methods' => is_array($iva_return) ? $iva_return : []
        ];
    }
}

/**
 * Verificar si un método de pago tiene auto-transmisión
 */
if (!function_exists('alegra_cr_has_auto_transmit')) {
    function alegra_cr_has_auto_transmit($payment_method_id)
    {
        $config = alegra_cr_get_payment_config_v2();
        return in_array($payment_method_id, $config['auto_transmit_methods']);
    }
}

/**
 * Verificar si un método de pago aplica devolución IVA 4%
 */
if (!function_exists('alegra_cr_has_iva_return')) {
    function alegra_cr_has_iva_return($payment_method_id)
    {
        $config = alegra_cr_get_payment_config_v2();
        return in_array($payment_method_id, $config['iva_return_methods']);
    }
}

/**
 * Determinar el tipo de método de pago para IVA
 * @return 'CARD' si tiene devolución IVA, 'CASH' si no
 */
if (!function_exists('alegra_cr_get_payment_type_for_tax')) {
    function alegra_cr_get_payment_type_for_tax($invoice)
    {
        $config = alegra_cr_get_payment_config_v2();
        
        // Verificar los métodos de pago permitidos en la factura
        if (isset($invoice->allowed_payment_modes) && !empty($invoice->allowed_payment_modes)) {
            $allowed_modes = is_string($invoice->allowed_payment_modes) ?
                unserialize($invoice->allowed_payment_modes) :
                $invoice->allowed_payment_modes;

            if (is_array($allowed_modes)) {
                foreach ($allowed_modes as $mode_id) {
                    // Si el método tiene devolución IVA, es tipo CARD
                    if (in_array($mode_id, $config['iva_return_methods'])) {
                        log_message('error', 'Alegra CR: Método ' . $mode_id . ' tiene devolución IVA - Tipo CARD');
                        return 'CARD';
                    }
                }
            }
        }
        
        log_message('error', 'Alegra CR: Sin devolución IVA - Tipo CASH');
        return 'CASH';
    }
}

/**
 * Procesar guardado de configuraciones V2
 * Compatible con el sistema de settings de Perfex
 */
function alegra_cr_process_settings_save($data)
{
    // Solo procesar si es nuestro grupo
    if (!isset($data['group']) || $data['group'] !== 'alegra_cr') {
        return;
    }
    
    $CI = &get_instance();
    $post_data = $CI->input->post();
    
    log_message('debug', 'Alegra CR: Procesando settings POST: ' . json_encode($post_data));
    
    // Arrays para almacenar métodos de pago
    $auto_transmit_methods = [];
    $iva_return_methods = [];
    
    // Procesar campos del formulario
    if (isset($post_data['settings']) && is_array($post_data['settings'])) {
        foreach ($post_data['settings'] as $key => $value) {
            
            // Recolectar checkboxes de auto-transmisión
            if (strpos($key, 'alegra_cr_auto_transmit_methods_') === 0) {
                $method_id = str_replace('alegra_cr_auto_transmit_methods_', '', $key);
                $auto_transmit_methods[] = $method_id;
                continue; // No guardar individualmente
            }
            
            // Recolectar checkboxes de devolución IVA
            if (strpos($key, 'alegra_cr_iva_return_methods_') === 0) {
                $method_id = str_replace('alegra_cr_iva_return_methods_', '', $key);
                $iva_return_methods[] = $method_id;
                continue; // No guardar individualmente
            }
            
            // No guardar token vacío (mantener el existente)
            if ($key === 'alegra_cr_token' && empty($value)) {
                log_message('debug', 'Alegra CR: Token vacío, manteniendo el existente');
                continue;
            }
            
            // Guardar usando la función de Perfex
            update_option($key, $value);
            log_message('debug', "Alegra CR: Guardado {$key}");
        }
    }
    
    // Guardar arrays de métodos de pago como JSON
    update_option('alegra_cr_auto_transmit_payment_methods', json_encode($auto_transmit_methods));
    update_option('alegra_cr_iva_return_payment_methods', json_encode($iva_return_methods));
    
    log_message('error', 'Alegra CR: Métodos auto-transmit guardados: ' . json_encode($auto_transmit_methods));
    log_message('error', 'Alegra CR: Métodos IVA return guardados: ' . json_encode($iva_return_methods));
    
    // Procesar checkboxes no marcados (guardar como '0')
    $checkboxes = [
        'alegra_cr_auto_transmit_enabled',
        'alegra_cr_auto_transmit_medical_only',
        'alegra_cr_auto_detect_medical_services',
        'alegra_cr_notify_auto_transmit',
        'alegra_cr_iva_return_notifications',
        'alegra_cr_auto_print',
        'alegra_cr_print_logo',
        'alegra_cr_show_footer_conditions',
        'alegra_cr_show_footer_conditions_ticket'
    ];
    
    foreach ($checkboxes as $checkbox_key) {
        if (!isset($post_data['settings'][$checkbox_key])) {
            update_option($checkbox_key, '0');
            log_message('debug', "Alegra CR: Checkbox {$checkbox_key} = 0");
        }
    }
    
    log_message('error', 'Alegra CR: Configuraciones guardadas exitosamente');
}

/**
 * Obtener configuración de métodos de pago V2
 * Lee desde tbloptions usando get_option de Perfex
 */
if (!function_exists('alegra_cr_get_payment_config_v2')) {
    function alegra_cr_get_payment_config_v2()
    {
        // Obtener desde tbloptions
        $auto_transmit_json = get_option('alegra_cr_auto_transmit_payment_methods');
        $iva_return_json = get_option('alegra_cr_iva_return_payment_methods');
        
        // Decodificar JSON
        $auto_transmit = [];
        $iva_return = [];
        
        if (!empty($auto_transmit_json)) {
            $decoded = json_decode($auto_transmit_json, true);
            if (is_array($decoded)) {
                $auto_transmit = $decoded;
            }
        }
        
        if (!empty($iva_return_json)) {
            $decoded = json_decode($iva_return_json, true);
            if (is_array($decoded)) {
                $iva_return = $decoded;
            }
        }
        
        log_message('debug', 'Alegra CR: Config métodos auto-transmit: ' . json_encode($auto_transmit));
        log_message('debug', 'Alegra CR: Config métodos IVA return: ' . json_encode($iva_return));
        
        return [
            'auto_transmit_methods' => $auto_transmit,
            'iva_return_methods' => $iva_return
        ];
    }
}

/**
 * Obtener todas las configuraciones
 * Mapeo correcto con lo que muestra settings.php
 */
if (!function_exists('alegra_cr_get_current_settings')) {
    function alegra_cr_get_current_settings()
    {
        // Obtener configuración de métodos
        $payment_config = alegra_cr_get_payment_config_v2();
        
        return [
            // Credenciales (nota: el array usa 'alegra_email' sin el prefijo 'cr')
            'alegra_email' => get_option('alegra_cr_email'),
            'alegra_token' => get_option('alegra_cr_token'),
            
            // Auto-transmisión
            'auto_transmit_enabled' => get_option('alegra_cr_auto_transmit_enabled'),
            'auto_transmit_medical_only' => get_option('alegra_cr_auto_transmit_medical_only'),
            'notify_auto_transmit' => get_option('alegra_cr_notify_auto_transmit'),
            'auto_transmit_delay' => get_option('alegra_cr_auto_transmit_delay'),
            
            // Devolución IVA
            'auto_detect_medical_services' => get_option('alegra_cr_auto_detect_medical_services'),
            'medical_keywords' => get_option('alegra_cr_medical_keywords'),
            'iva_return_notifications' => get_option('alegra_cr_iva_return_notifications'),
            
            // Impresión
            'default_printer_type' => get_option('alegra_cr_default_printer_type'),
            'thermal_printer_ip' => get_option('alegra_cr_thermal_printer_ip'),
            'thermal_printer_port' => get_option('alegra_cr_thermal_printer_port'),
            'auto_print' => get_option('alegra_cr_auto_print'),
            
            // Logo y branding
            'print_logo' => get_option('alegra_cr_print_logo'),
            'company_logo_path' => get_option('alegra_cr_company_logo_path'),
            'logo_width' => get_option('alegra_cr_logo_width'),
            'logo_height' => get_option('alegra_cr_logo_height'),
            
            // Footer
            'print_footer_message' => get_option('alegra_cr_print_footer_message'),
            'footer_conditions' => get_option('alegra_cr_footer_conditions'),
            'show_footer_conditions' => get_option('alegra_cr_show_footer_conditions'),
            'show_footer_conditions_ticket' => get_option('alegra_cr_show_footer_conditions_ticket'),
            'footer_legal_text' => get_option('alegra_cr_footer_legal_text')
        ];
    }
}

/**
 * Wrapper mejorado para get_option con defaults
 */
if (!function_exists('alegra_cr_get_option')) {
    function alegra_cr_get_option($key, $default = null)
    {
        // Si no tiene prefijo, agregarlo
        if (strpos($key, 'alegra_cr_') !== 0) {
            $key = 'alegra_cr_' . $key;
        }
        
        $value = get_option($key);
        
        // Si es false o null, retornar default
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        
        // Si parece JSON, intentar decodificar
        if (is_string($value) && (
            (substr($value, 0, 1) === '[' && substr($value, -1) === ']') ||
            (substr($value, 0, 1) === '{' && substr($value, -1) === '}')
        )) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        
        return $value;
    }
}

/**
 * Verificar si un método tiene auto-transmisión
 */
if (!function_exists('alegra_cr_method_has_auto_transmit')) {
    function alegra_cr_method_has_auto_transmit($method_id)
    {
        $config = alegra_cr_get_payment_config_v2();
        return in_array((string)$method_id, $config['auto_transmit_methods']);
    }
}

/**
 * Verificar si un método tiene devolución IVA
 */
if (!function_exists('alegra_cr_method_has_iva_return')) {
    function alegra_cr_method_has_iva_return($method_id)
    {
        $config = alegra_cr_get_payment_config_v2();
        return in_array((string)$method_id, $config['iva_return_methods']);
    }
}

/**
 * Determinar tipo de pago para efectos de IVA
 * @return 'CARD' si tiene devolución IVA, 'CASH' si no
 */
if (!function_exists('alegra_cr_get_payment_type_for_iva')) {
    function alegra_cr_get_payment_type_for_iva($invoice)
    {
        // Obtener métodos de pago de la factura
        $invoice_methods = [];
        
        if (isset($invoice->allowed_payment_modes) && !empty($invoice->allowed_payment_modes)) {
            $allowed_modes = is_string($invoice->allowed_payment_modes) ?
                unserialize($invoice->allowed_payment_modes) :
                $invoice->allowed_payment_modes;
            
            if (is_array($allowed_modes)) {
                $invoice_methods = $allowed_modes;
            }
        }
        
        // Verificar si alguno tiene devolución IVA
        foreach ($invoice_methods as $method_id) {
            if (alegra_cr_method_has_iva_return($method_id)) {
                log_message('debug', "Alegra CR: Método {$method_id} tiene devolución IVA - Tipo CARD");
                return 'CARD';
            }
        }
        
        log_message('debug', 'Alegra CR: Sin devolución IVA - Tipo CASH');
        return 'CASH';
    }
}

/**
 * Determinar IVA correcto basado en servicio y método de pago
 */
function alegra_cr_get_correct_tax_rate($item, $payment_type)
{
    $is_medical = alegra_cr_is_medical_service($item);
    
    if ($is_medical && $payment_type === 'CARD') {
        // Servicios médicos pagados con tarjeta: IVA 4%
        return [
            'rate' => 4,
            'reason' => 'Servicio médico con tarjeta',
            'alegra_tax_id' => 6 // ID para IVA 4% en Alegra
        ];
    } elseif ($is_medical && $payment_type === 'CASH') {
        // Servicios médicos pagados en efectivo: IVA 13%
        return [
            'rate' => 13,
            'reason' => 'Servicio médico con efectivo',
            'alegra_tax_id' => 1 // ID para IVA 13% en Alegra
        ];
    } elseif (alegra_cr_is_medicine($item)) {
        // Medicamentos siempre IVA 2%
        return [
            'rate' => 2,
            'reason' => 'Medicamento',
            'alegra_tax_id' => 5 // ID para IVA 2% en Alegra
        ];
    } else {
        // Otros productos: IVA 13%
        return [
            'rate' => 13,
            'reason' => 'Producto estándar',
            'alegra_tax_id' => 1
        ];
    }
}

/**
 * Verificar si es servicio médico
 */
function alegra_cr_is_medical_service($item)
{
    $medical_keywords = alegra_cr_get_option('medical_keywords', 
        'consulta,examen,chequeo,revisión,diagnóstico,cirugía,operación,procedimiento,terapia,sesión,doctor,médico,especialista,evaluación');
    
    $keywords = array_map('trim', explode(',', $medical_keywords));
    $description = strtolower($item['description'] ?? '');
    
    foreach ($keywords as $keyword) {
        if (strpos($description, strtolower($keyword)) !== false) {
            return true;
        }
    }
    
    // Verificar por código CABYS
    if (isset($item['custom_fields']['CABYS'])) {
        $cabys_code = $item['custom_fields']['CABYS'];
        $medical_cabys_prefixes = ['8610', '8620', '8630', '8690'];
        $prefix = substr($cabys_code, 0, 4);
        
        if (in_array($prefix, $medical_cabys_prefixes)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Verificar si es medicamento
 */
function alegra_cr_is_medicine($item)
{
    $medicine_keywords = [
        'acetaminofen', 'paracetamol', 'ibuprofeno', 'aspirina',
        'medicamento', 'medicina', 'fármaco', 'droga',
        'pastilla', 'cápsula', 'jarabe', 'inyección',
        'ampolla', 'tableta'
    ];
    
    $description = strtolower($item['description'] ?? '');
    
    foreach ($medicine_keywords as $keyword) {
        if (strpos($description, $keyword) !== false) {
            return true;
        }
    }
    
    // Verificar por código CABYS
    if (isset($item['custom_fields']['CABYS'])) {
        $cabys_code = $item['custom_fields']['CABYS'];
        $medicine_cabys_prefixes = ['2103', '2104', '2105', '4772'];
        $prefix = substr($cabys_code, 0, 4);
        
        if (in_array($prefix, $medicine_cabys_prefixes)) {
            return true;
        }
    }
    
    return false;
}
}