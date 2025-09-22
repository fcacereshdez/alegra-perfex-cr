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
 * Agregar enlaces de acción adicionales para este módulo en la lista de módulos
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
    
    // Agregar sección de settings al sistema de configuración general
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
 * Agregar sección de configuración al sistema general de settings
 */
function alegra_cr_add_settings_section()
{
    $CI = &get_instance();
    
    // Agregar sección principal para "Integrations"
    $CI->app->add_settings_section('integrations', [
        'name'     => _l('integrations'),
        'position' => 50,
        'icon'     => 'fa fa-plug',
    ]);
    
    // Agregar subsección para Alegra CR
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
    
    // Hook para procesar el formulario cuando se guarde
    hooks()->add_action('settings_updated', 'alegra_cr_process_settings_save');
}

/**
 * Procesa el guardado de configuraciones desde el sistema de settings
 */
/**
 * Procesa el guardado de configuraciones desde el sistema de settings
 */
function alegra_cr_process_settings_save($data)
{
    if (!isset($data['group']) || $data['group'] !== 'alegra_cr') {
        return;
    }
    
    $CI = &get_instance();
    $post_data = $CI->input->post();
    
    log_message('error', 'Alegra CR: POST data recibida: ' . json_encode($post_data));
    
    // Procesar configuraciones de métodos de pago que no se manejan por opciones estándar
    if (isset($post_data['settings'])) {
        $settings = $post_data['settings'];
        
        if (isset($settings['card_payment_methods']) || isset($settings['cash_payment_methods'])) {
            
            $payment_config = [
                'card_payment_methods' => isset($settings['card_payment_methods']) && is_array($settings['card_payment_methods']) ? 
                    array_filter($settings['card_payment_methods']) : [],
                'cash_payment_methods' => isset($settings['cash_payment_methods']) && is_array($settings['cash_payment_methods']) ? 
                    array_filter($settings['cash_payment_methods']) : []
            ];
            
            log_message('error', 'Alegra CR: Payment config a guardar: ' . json_encode($payment_config));
            
            // Usar la función auxiliar
            alegra_cr_save_payment_methods_config($payment_config);
        }
    }
    
    log_message('info', 'Alegra CR: Configuraciones procesadas desde sistema de settings');
}

/**
 * Hook de activación del módulo
 */
function alegra_cr_module_activation_hook()
{
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
    
    foreach ($options as $option_name => $default_value) {
        add_option($option_name, $default_value);
    }
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

    $CI->app_menu->add_sidebar_children_item('alegra-cr', [
        'slug'     => 'alegra-cr-products',
        'name'     => _l('alegra_cr_products'),
        'href'     => admin_url('alegra_facturacion_cr/products'),
        'position' => 2,
    ]);
    
    // Enlace a configuración (redirige al sistema general de settings)
    $CI->app_menu->add_sidebar_children_item('alegra-cr', [
        'slug'     => 'alegra-cr-settings',
        'name'     => 'Configuración',
        'href'     => admin_url('settings?group=alegra_cr'),
        'position' => 3,
    ]);
}

/**
 * Registrar todas las opciones del módulo
 */
function alegra_cr_register_options()
{
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
    
    foreach ($options as $option_name => $default_value) {
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
    $CI = &get_instance();
    
    try {
        // Tabla de configuraciones del módulo
        if (!$CI->db->table_exists(db_prefix() . 'alegra_cr_settings')) {
            $sql = "CREATE TABLE `" . db_prefix() . "alegra_cr_settings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `setting_name` varchar(255) NOT NULL,
                `setting_value` longtext,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `setting_name` (`setting_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set;
            
            $CI->db->query($sql);
        }
        
        // Tabla de mapeo de facturas
        if (!$CI->db->table_exists(db_prefix() . 'alegra_cr_invoices_map')) {
            $sql = "CREATE TABLE `" . db_prefix() . "alegra_cr_invoices_map` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `perfex_invoice_id` int(11) NOT NULL,
                `alegra_invoice_id` varchar(255) DEFAULT NULL,
                `status` varchar(50) DEFAULT 'pending',
                `sync_date` timestamp DEFAULT CURRENT_TIMESTAMP,
                `response_data` longtext,
                PRIMARY KEY (`id`),
                UNIQUE KEY `perfex_invoice_id` (`perfex_invoice_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set;
            
            $CI->db->query($sql);
        }
        
        // Tabla de configuración de métodos de pago
        if (!$CI->db->table_exists(db_prefix() . 'alegra_cr_payment_methods_config')) {
            $sql = "CREATE TABLE `" . db_prefix() . "alegra_cr_payment_methods_config` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `config_type` varchar(50) NOT NULL,
                `payment_method_ids` longtext,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `config_type` (`config_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set;
            
            $CI->db->query($sql);
            
            // Configuraciones por defecto
            $CI->db->insert(db_prefix() . 'alegra_cr_payment_methods_config', [
                'config_type' => 'card_payment_methods',
                'payment_method_ids' => '[]'
            ]);
            
            $CI->db->insert(db_prefix() . 'alegra_cr_payment_methods_config', [
                'config_type' => 'cash_payment_methods',
                'payment_method_ids' => '[]'
            ]);
        }
        
        // Marcar instalación como completada
        add_option('alegra_cr_installed', date('Y-m-d H:i:s'));
        add_option('alegra_cr_version', ALEGRA_CR_MODULE_VERSION);
        
        log_message('info', 'Alegra CR: Instalación completada exitosamente');
        
    } catch (Exception $e) {
        log_message('error', 'Alegra CR: Error en instalación: ' . $e->getMessage());
    }
}

// ============================================================================
// FUNCIONES AUXILIARES
// ============================================================================

/**
 * Obtiene la configuración actual de Alegra CR
 */
function alegra_cr_get_current_settings()
{
    return [
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
}

/**
 * Obtiene los métodos de pago activos de Perfex
 */
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

/**
 * Guardar configuración de métodos de pago directamente
 */
function alegra_cr_save_payment_methods_config($config)
{
    $CI = &get_instance();
    
    try {
        // Actualizar métodos de tarjeta
        $CI->db->where('config_type', 'card_payment_methods');
        $CI->db->update(db_prefix() . 'alegra_cr_payment_methods_config', [
            'payment_method_ids' => json_encode($config['card_payment_methods'])
        ]);
        
        // Actualizar métodos de efectivo
        $CI->db->where('config_type', 'cash_payment_methods');
        $CI->db->update(db_prefix() . 'alegra_cr_payment_methods_config', [
            'payment_method_ids' => json_encode($config['cash_payment_methods'])
        ]);
        
        return true;
    } catch (Exception $e) {
        log_message('error', 'Alegra CR: Error guardando configuración de pagos: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtener configuración de métodos de pago
 */
function alegra_cr_get_payment_methods_config()
{
    $CI = &get_instance();
    $payment_config = ['card_payment_methods' => [], 'cash_payment_methods' => []];
    
    try {
        if ($CI->db->table_exists(db_prefix() . 'alegra_cr_payment_methods_config')) {
            $card_methods = $CI->db->get_where(db_prefix() . 'alegra_cr_payment_methods_config', ['config_type' => 'card_payment_methods'])->row();
            $cash_methods = $CI->db->get_where(db_prefix() . 'alegra_cr_payment_methods_config', ['config_type' => 'cash_payment_methods'])->row();
            
            $payment_config = [
                'card_payment_methods' => $card_methods ? json_decode($card_methods->payment_method_ids, true) : [],
                'cash_payment_methods' => $cash_methods ? json_decode($cash_methods->payment_method_ids, true) : []
            ];
        }
    } catch (Exception $e) {
        log_message('error', 'Alegra CR: Error obteniendo configuración de métodos de pago: ' . $e->getMessage());
    }
    
    return $payment_config;
}

/**
 * Verifica si el módulo está completamente configurado
 */
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

/**
 * Obtiene estadísticas básicas del módulo
 */
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