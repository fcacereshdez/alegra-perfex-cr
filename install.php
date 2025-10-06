<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Script de instalación completo para Alegra CR
 * Genera todas las opciones y tablas necesarias
 */

$CI = &get_instance();

if (!function_exists('alegra_cr_install_database')) {
    function alegra_cr_install_database()
    {
        $CI = &get_instance();

        log_message('info', 'Alegra CR: Iniciando instalación completa...');

        try {
            // 1. CREAR TODAS LAS OPCIONES EN LA TABLA options
            alegra_cr_create_all_options();
            
            // 2. CREAR TABLAS DEL MÓDULO
            alegra_cr_create_module_tables($CI);
            
            // 3. INSERTAR DATOS INICIALES
            alegra_cr_insert_initial_data($CI);
            
            // 4. MARCAR INSTALACIÓN COMPLETADA
            update_option('alegra_cr_installed', date('Y-m-d H:i:s'));
            update_option('alegra_cr_version', '1.0.0');
            
            log_message('info', 'Alegra CR: Instalación completada exitosamente');
            
            return true;
        } catch (Exception $e) {
            log_message('error', 'Alegra CR: Error en instalación: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Crea TODAS las opciones necesarias en la tabla options de Perfex
     */
    function alegra_cr_create_all_options()
    {
        log_message('info', 'Alegra CR: Creando opciones en tabla options...');
        
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
            
            // Métodos de pago (estos se crearán dinámicamente también)
            'alegra_cr_card_payment_methods' => '[]',
            'alegra_cr_cash_payment_methods' => '[]',
            
            // Configuración de impresión
            'alegra_cr_default_printer_type' => 'web',
            'alegra_cr_thermal_printer_ip' => '192.168.1.100',
            'alegra_cr_thermal_printer_port' => '9100',
            'alegra_cr_ticket_width' => '48',
            'alegra_cr_auto_print' => '1',
            
            // Configuración de logo
            'alegra_cr_print_logo' => '1',
            'alegra_cr_company_logo_path' => '',
            'alegra_cr_logo_width' => '120',
            'alegra_cr_logo_height' => '70',
            
            // Configuración de footer
            'alegra_cr_print_footer_message' => 'Gracias por su compra',
            'alegra_cr_footer_conditions' => '',
            'alegra_cr_show_footer_conditions' => '1',
            'alegra_cr_show_footer_conditions_ticket' => '1',
            'alegra_cr_footer_legal_text' => '',
            
            // Metadatos del módulo
            'alegra_cr_installed' => date('Y-m-d H:i:s'),
            'alegra_cr_version' => '1.0.0',
            'alegra_cr_last_update' => date('Y-m-d H:i:s')
        ];
        
        foreach ($all_options as $option_name => $default_value) {
            // Usar add_option que solo crea si no existe
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
                log_message('info', "Alegra CR: Opción creada: {$option_name}");
            } else {
                log_message('info', "Alegra CR: Opción ya existe: {$option_name}");
            }
        }
        
        log_message('info', 'Alegra CR: ' . count($all_options) . ' opciones procesadas');
    }

    /**
     * Crea todas las tablas necesarias del módulo
     */
    function alegra_cr_create_module_tables($CI)
    {
        log_message('info', 'Alegra CR: Creando tablas del módulo...');
        
        $prefix = db_prefix();
        $charset = $CI->db->char_set;
        $collat = $CI->db->dbcollat;
        
        // 1. Tabla de configuraciones (legacy, por si acaso)
        if (!$CI->db->table_exists($prefix . 'alegra_cr_settings')) {
            $CI->db->query("
                CREATE TABLE `{$prefix}alegra_cr_settings` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `setting_name` varchar(255) NOT NULL,
                    `setting_value` longtext,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `setting_name` (`setting_name`),
                    KEY `setting_name_idx` (`setting_name`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collat}
            ");
            log_message('info', 'Alegra CR: Tabla alegra_cr_settings creada');
        }
        
        // 2. Tabla de mapeo de productos Perfex <-> Alegra
        if (!$CI->db->table_exists($prefix . 'alegra_cr_products_map')) {
            $CI->db->query("
                CREATE TABLE `{$prefix}alegra_cr_products_map` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `perfex_item_id` int(11) NOT NULL,
                    `alegra_item_id` varchar(255) NOT NULL,
                    `sync_date` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `status` varchar(50) DEFAULT 'active',
                    `last_sync_date` timestamp NULL,
                    `sync_errors` text,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `perfex_item_id` (`perfex_item_id`),
                    KEY `alegra_item_id` (`alegra_item_id`),
                    KEY `status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collat}
            ");
            log_message('info', 'Alegra CR: Tabla alegra_cr_products_map creada');
        }
        
        // 3. Tabla de mapeo de facturas Perfex <-> Alegra
        if (!$CI->db->table_exists($prefix . 'alegra_cr_invoices_map')) {
            $CI->db->query("
                CREATE TABLE `{$prefix}alegra_cr_invoices_map` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `perfex_invoice_id` int(11) NOT NULL,
                    `alegra_invoice_id` varchar(255) DEFAULT NULL,
                    `alegra_invoice_number` varchar(50) DEFAULT NULL,
                    `alegra_invoice_key` varchar(255) DEFAULT NULL,
                    `status` varchar(50) DEFAULT 'pending',
                    `sync_date` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `response_data` longtext,
                    `error_message` text,
                    `retry_count` int(11) DEFAULT 0,
                    `last_retry_date` timestamp NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `perfex_invoice_id` (`perfex_invoice_id`),
                    KEY `alegra_invoice_id` (`alegra_invoice_id`),
                    KEY `status` (`status`),
                    KEY `sync_date` (`sync_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collat}
            ");
            log_message('info', 'Alegra CR: Tabla alegra_cr_invoices_map creada');
        }
        
        // 4. Tabla de configuración de métodos de pago
        if (!$CI->db->table_exists($prefix . 'alegra_cr_payment_methods_config')) {
            $CI->db->query("
                CREATE TABLE `{$prefix}alegra_cr_payment_methods_config` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `config_type` varchar(50) NOT NULL,
                    `payment_method_ids` longtext,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `config_type` (`config_type`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collat}
            ");
            log_message('info', 'Alegra CR: Tabla alegra_cr_payment_methods_config creada');
        }
        
        // 5. Tabla de configuración de impuestos
        if (!$CI->db->table_exists($prefix . 'alegra_cr_tax_config')) {
            $CI->db->query("
                CREATE TABLE `{$prefix}alegra_cr_tax_config` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `tax_name` varchar(255) NOT NULL,
                    `tax_code` varchar(50) NOT NULL,
                    `alegra_tax_id` int(11) DEFAULT NULL,
                    `tax_rate` decimal(5,2) NOT NULL,
                    `is_active` tinyint(1) DEFAULT 1,
                    `applies_to` enum('all', 'specific') DEFAULT 'all',
                    `criteria` longtext,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `tax_code` (`tax_code`),
                    KEY `alegra_tax_id` (`alegra_tax_id`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collat}
            ");
            log_message('info', 'Alegra CR: Tabla alegra_cr_tax_config creada');
        }
        
        // 6. Tabla de logs del módulo
        if (!$CI->db->table_exists($prefix . 'alegra_cr_logs')) {
            $CI->db->query("
                CREATE TABLE `{$prefix}alegra_cr_logs` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `invoice_id` int(11) DEFAULT NULL,
                    `action` varchar(100) NOT NULL,
                    `status` varchar(50) NOT NULL,
                    `message` text,
                    `data` longtext,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `invoice_id` (`invoice_id`),
                    KEY `action` (`action`),
                    KEY `status` (`status`),
                    KEY `created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collat}
            ");
            log_message('info', 'Alegra CR: Tabla alegra_cr_logs creada');
        }
        
        // 7. Tabla para IVA devuelto (returns)
        if (!$CI->db->table_exists($prefix . 'alegra_cr_iva_returns')) {
            $CI->db->query("
                CREATE TABLE `{$prefix}alegra_cr_iva_returns` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `invoice_id` int(11) NOT NULL,
                    `period` varchar(20) NOT NULL,
                    `iva_amount` decimal(10,2) NOT NULL,
                    `returned_amount` decimal(10,2) DEFAULT 0.00,
                    `status` enum('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
                    `notes` text,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `invoice_id` (`invoice_id`),
                    KEY `period` (`period`),
                    KEY `status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collat}
            ");
            log_message('info', 'Alegra CR: Tabla alegra_cr_iva_returns creada');
        }

        // 8. Tabla de historial de impresiones
        if (!$CI->db->table_exists($prefix . 'alegra_cr_print_history')) {
            $CI->db->query("
                CREATE TABLE `{$prefix}alegra_cr_print_history` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `invoice_id` int(11) NOT NULL,
                    `print_type` enum('invoice', 'ticket') NOT NULL DEFAULT 'invoice',
                    `printer_type` enum('web', 'thermal', 'usb') NOT NULL DEFAULT 'web',
                    `success` tinyint(1) DEFAULT 1,
                    `printed_by` int(11) DEFAULT NULL,
                    `error_message` text,
                    `print_settings` longtext,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `invoice_id` (`invoice_id`),
                    KEY `print_type` (`print_type`),
                    KEY `printer_type` (`printer_type`),
                    KEY `printed_by` (`printed_by`),
                    KEY `created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collat}
            ");
            log_message('info', 'Alegra CR: Tabla alegra_cr_print_history creada');
        }
    }

    /**
     * Inserta datos iniciales en las tablas
     */
    function alegra_cr_insert_initial_data($CI)
    {
        log_message('info', 'Alegra CR: Insertando datos iniciales...');
        
        $prefix = db_prefix();
        
        // 1. Configuración inicial de métodos de pago
        $existing_payment_config = $CI->db->get($prefix . 'alegra_cr_payment_methods_config')->num_rows();
        
        if ($existing_payment_config === 0) {
            $payment_configs = [
                [
                    'config_type' => 'card_payment_methods',
                    'payment_method_ids' => '[]'
                ],
                [
                    'config_type' => 'cash_payment_methods',
                    'payment_method_ids' => '[]'
                ]
            ];
            
            foreach ($payment_configs as $config) {
                $CI->db->insert($prefix . 'alegra_cr_payment_methods_config', $config);
            }
            
            log_message('info', 'Alegra CR: Configuración de métodos de pago insertada');
        }
        
        // 2. Configuración inicial de impuestos para Costa Rica
        $existing_tax_config = $CI->db->get($prefix . 'alegra_cr_tax_config')->num_rows();
        
        if ($existing_tax_config === 0) {
            $default_taxes = [
                [
                    'tax_name' => 'IVA Estándar',
                    'tax_code' => 'IVA_13',
                    'alegra_tax_id' => 1,
                    'tax_rate' => 13.00,
                    'is_active' => 1,
                    'applies_to' => 'all',
                    'criteria' => null
                ],
                [
                    'tax_name' => 'IVA Reducido Servicios Médicos',
                    'tax_code' => 'IVA_4_MEDICAL',
                    'alegra_tax_id' => 6,
                    'tax_rate' => 4.00,
                    'is_active' => 1,
                    'applies_to' => 'specific',
                    'criteria' => json_encode([
                        'keywords' => ['consulta', 'examen', 'médico', 'doctor', 'terapia', 'diagnóstico'],
                        'cabys_prefixes' => ['8610', '8620', '8630']
                    ])
                ],
                [
                    'tax_name' => 'IVA Reducido Medicamentos',
                    'tax_code' => 'IVA_2_MEDICINE',
                    'alegra_tax_id' => 5,
                    'tax_rate' => 2.00,
                    'is_active' => 1,
                    'applies_to' => 'specific',
                    'criteria' => json_encode([
                        'keywords' => ['medicamento', 'medicina', 'fármaco', 'pastilla', 'jarabe'],
                        'cabys_prefixes' => ['2103', '2104', '2105']
                    ])
                ],
                [
                    'tax_name' => 'Exento',
                    'tax_code' => 'EXENTO',
                    'alegra_tax_id' => null,
                    'tax_rate' => 0.00,
                    'is_active' => 1,
                    'applies_to' => 'specific',
                    'criteria' => json_encode([
                        'keywords' => ['exento', 'educación', 'servicio público']
                    ])
                ]
            ];
            
            foreach ($default_taxes as $tax_data) {
                $CI->db->insert($prefix . 'alegra_cr_tax_config', $tax_data);
            }
            
            log_message('info', 'Alegra CR: Configuración de impuestos insertada');
        }
    }

    /**
     * Función de reparación - Ejecuta la instalación incluso si ya existe
     */
    function alegra_cr_repair_installation()
    {
        log_message('info', 'Alegra CR: Ejecutando reparación de instalación...');
        return alegra_cr_install_database();
    }
}

// Ejecutar instalación automática
if (!$CI->db->table_exists(db_prefix() . 'alegra_cr_settings')) {
    alegra_cr_install_database();
}