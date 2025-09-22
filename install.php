<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Script de instalación para Alegra CR
 * Este archivo se ejecuta cuando Perfex detecta el módulo por primera vez
 */

$CI = &get_instance();

if (!function_exists('alegra_cr_install_database')) {
    function alegra_cr_install_database()
    {
        $CI = &get_instance();
        
        log_message('info', 'Alegra CR: Iniciando instalación de base de datos...');
        
        try {
            // Verificar que no estén ya creadas las tablas
            if ($CI->db->table_exists(db_prefix() . 'alegra_cr_settings')) {
                log_message('info', 'Alegra CR: Las tablas ya existen, omitiendo instalación');
                return true;
            }
            
            // 1. Tabla de configuraciones principales
            $CI->db->query("
                CREATE TABLE `" . db_prefix() . "alegra_cr_settings` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `setting_name` varchar(255) NOT NULL,
                    `setting_value` longtext,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `setting_name` (`setting_name`),
                    KEY `setting_name_idx` (`setting_name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . " COLLATE=" . $CI->db->dbcollat
            );
            
            // 2. Tabla de mapeo de productos Perfex <-> Alegra
            $CI->db->query("
                CREATE TABLE `" . db_prefix() . "alegra_cr_products_map` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . " COLLATE=" . $CI->db->dbcollat
            );
            
            // 3. Tabla de mapeo de facturas Perfex <-> Alegra
            $CI->db->query("
                CREATE TABLE `" . db_prefix() . "alegra_cr_invoices_map` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . " COLLATE=" . $CI->db->dbcollat
            );
            
            // 4. Tabla de configuración de métodos de pago
            $CI->db->query("
                CREATE TABLE `" . db_prefix() . "alegra_cr_payment_methods_config` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `config_type` varchar(50) NOT NULL,
                    `payment_method_ids` longtext,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `config_type` (`config_type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . " COLLATE=" . $CI->db->dbcollat
            );
            
            // 5. Tabla de configuración de impuestos (opcional)
            $CI->db->query("
                CREATE TABLE `" . db_prefix() . "alegra_cr_tax_config` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . " COLLATE=" . $CI->db->dbcollat
            );
            
            // 6. Tabla de logs de auto-transmisión (opcional)
            $CI->db->query("
                CREATE TABLE `" . db_prefix() . "alegra_cr_logs` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . " COLLATE=" . $CI->db->dbcollat
            );
            
            log_message('info', 'Alegra CR: Tablas creadas exitosamente');
            
            // Insertar configuraciones por defecto
            alegra_cr_insert_default_settings($CI);
            
            // Insertar configuraciones de métodos de pago por defecto
            alegra_cr_insert_default_payment_config($CI);
            
            // Insertar configuraciones de impuestos por defecto
            alegra_cr_insert_default_tax_config($CI);
            
            log_message('info', 'Alegra CR: Instalación completada exitosamente');
            
            return true;
            
        } catch (Exception $e) {
            log_message('error', 'Alegra CR: Error durante instalación: ' . $e->getMessage());
            return false;
        }
    }
    
    function alegra_cr_insert_default_settings($CI)
    {
        $default_settings = [
            'alegra_email' => '',
            'alegra_token' => '',
            'auto_transmit_enabled' => '0',
            'auto_transmit_payment_methods' => '[]',
            'auto_transmit_medical_only' => '0',
            'auto_detect_medical_services' => '1',
            'notify_auto_transmit' => '1',
            'medical_keywords' => 'consulta,examen,chequeo,revisión,diagnóstico,cirugía,operación,procedimiento,terapia,sesión,doctor,médico,especialista,evaluación',
            'auto_transmit_delay' => '0',
            'module_version' => '1.0.0',
            'installation_date' => date('Y-m-d H:i:s'),
            'last_update_date' => date('Y-m-d H:i:s')
        ];
        
        foreach ($default_settings as $setting_name => $default_value) {
            $CI->db->insert(db_prefix() . 'alegra_cr_settings', [
                'setting_name' => $setting_name,
                'setting_value' => $default_value
            ]);
        }
        
        // También crear las opciones en el sistema de Perfex para la integración
        foreach ($default_settings as $setting_name => $default_value) {
            $perfex_option_name = 'alegra_cr_' . $setting_name;
            if ($perfex_option_name === 'alegra_cr_alegra_email') {
                $perfex_option_name = 'alegra_cr_email';
            }
            if ($perfex_option_name === 'alegra_cr_alegra_token') {
                $perfex_option_name = 'alegra_cr_token';
            }
            
            // Evitar duplicados de prefijo
            if (strpos($setting_name, 'alegra_cr_') === 0) {
                $perfex_option_name = $setting_name;
            } else {
                $perfex_option_name = 'alegra_cr_' . $setting_name;
            }
            
            add_option($perfex_option_name, $default_value);
        }
        
        log_message('info', 'Alegra CR: Configuraciones por defecto insertadas');
    }
    
    function alegra_cr_insert_default_payment_config($CI)
    {
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
            $CI->db->insert(db_prefix() . 'alegra_cr_payment_methods_config', $config);
        }
        
        log_message('info', 'Alegra CR: Configuraciones de métodos de pago insertadas');
    }
    
    function alegra_cr_insert_default_tax_config($CI)
    {
        $default_taxes = [
            [
                'tax_name' => 'IVA Estándar',
                'tax_code' => 'IVA_13',
                'alegra_tax_id' => 1,
                'tax_rate' => 13.00,
                'is_active' => 1,
                'applies_to' => 'all'
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
            $CI->db->insert(db_prefix() . 'alegra_cr_tax_config', $tax_data);
        }
        
        log_message('info', 'Alegra CR: Configuraciones de impuestos por defecto insertadas');
    }
}

// Ejecutar instalación si se incluye este archivo
if (!$CI->db->table_exists(db_prefix() . 'alegra_cr_settings')) {
    alegra_cr_install_database();
}