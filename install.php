<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Instalación del módulo Alegra Facturacion Costa Rica
 */

$CI = &get_instance();

// Crear tabla para las integraciones de Alegra (configuración global)
if (!$CI->db->table_exists(db_prefix() . 'alegra_cr_integrations')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'alegra_cr_integrations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `alegra_email` varchar(255) NOT NULL,
        `alegra_token` text NOT NULL,
        `default_payment_method` varchar(50) DEFAULT "CASH",
        `default_sale_condition` varchar(50) DEFAULT "CREDIT",
        `default_tax_id` int(11) DEFAULT 1,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ' COLLATE=' . $CI->db->dbcollat . ';');

    // Insertar registro por defecto
    $CI->db->insert(db_prefix() . 'alegra_cr_integrations', [
        'alegra_email' => '',
        'alegra_token' => '',
        'is_active' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

// Crear tabla para el mapeo de productos
if (!$CI->db->table_exists(db_prefix() . 'alegra_cr_products_map')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'alegra_cr_products_map` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `perfex_item_id` int(11) NOT NULL,
        `alegra_item_id` int(11) NOT NULL,
        `last_sync_date` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `perfex_item_id` (`perfex_item_id`),
        UNIQUE KEY `alegra_item_id` (`alegra_item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ' COLLATE=' . $CI->db->dbcollat . ';');
}

// Crear tabla para el mapeo de clientes
if (!$CI->db->table_exists(db_prefix() . 'alegra_cr_clients_map')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'alegra_cr_clients_map` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `perfex_client_id` int(11) NOT NULL,
        `alegra_client_id` int(11) NOT NULL,
        `last_sync_date` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `perfex_client_id` (`perfex_client_id`),
        UNIQUE KEY `alegra_client_id` (`alegra_client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ' COLLATE=' . $CI->db->dbcollat . ';');
}

// Crear tabla para el mapeo de facturas
if (!$CI->db->table_exists(db_prefix() . 'alegra_cr_invoices_map')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'alegra_cr_invoices_map` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `perfex_invoice_id` int(11) NOT NULL,
        `alegra_invoice_id` int(11) NOT NULL,
        `alegra_invoice_number` varchar(50) DEFAULT NULL,
        `alegra_invoice_key` varchar(100) DEFAULT NULL,
        `sync_date` datetime NOT NULL,
        `status` varchar(20) DEFAULT "pending",
        `response_data` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `perfex_invoice_id` (`perfex_invoice_id`),
        UNIQUE KEY `alegra_invoice_id` (`alegra_invoice_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ' COLLATE=' . $CI->db->dbcollat . ';');
}

// Crear tabla para las settings
if (!$CI->db->table_exists(db_prefix() . 'alegra_cr_settings')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'alegra_cr_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `setting_name` varchar(255) NOT NULL,
        `setting_value` text,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_name` (`setting_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ' COLLATE=' . $CI->db->dbcollat . ';');
}

// Crear tabla para las devoluciones de IVA
if (!$CI->db->table_exists(db_prefix() . 'alegra_cr_iva_returns')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'alegra_cr_iva_returns` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `perfex_client_id` int(11) NOT NULL,
        `perfex_invoice_id` int(11) NOT NULL,
        `alegra_invoice_id` int(11) NOT NULL,
        `return_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
        `return_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
        `client_type` varchar(50) NOT NULL,
        `status` varchar(20) NOT NULL DEFAULT "pending",
        `notes` text DEFAULT NULL,
        `processed_date` datetime DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `perfex_client_id` (`perfex_client_id`),
        KEY `perfex_invoice_id` (`perfex_invoice_id`),
        KEY `alegra_invoice_id` (`alegra_invoice_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ' COLLATE=' . $CI->db->dbcollat . ';');
}

// Agregar campos personalizados para manejar tipos de clientes para devolución de IVA
if (!$CI->db->field_exists('iva_return_eligible', db_prefix() . 'clients')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'clients` 
        ADD `iva_return_eligible` tinyint(1) DEFAULT 0,
        ADD `iva_return_type` varchar(50) DEFAULT NULL,
        ADD `tax_id_type` varchar(10) DEFAULT NULL');
}

// Crear tabla para configuración extendida de impuestos
if (!$CI->db->table_exists(db_prefix() . 'alegra_cr_tax_config')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'alegra_cr_tax_config` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tax_name` varchar(100) NOT NULL,
        `tax_code` varchar(20) NOT NULL,
        `alegra_tax_id` int(11) DEFAULT NULL,
        `tax_rate` decimal(5,2) NOT NULL,
        `applies_to` enum("all","specific","cabys") NOT NULL DEFAULT "all",
        `criteria` text DEFAULT NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `tax_code` (`tax_code`),
        KEY `tax_rate` (`tax_rate`),
        KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ' COLLATE=' . $CI->db->dbcollat . ';');

    // Insertar configuraciones de impuestos por defecto para Costa Rica
    $default_taxes = [
        [
            'tax_name' => 'IVA Estándar',
            'tax_code' => 'IVA_13',
            'alegra_tax_id' => 1,
            'tax_rate' => 13.00,
            'applies_to' => 'all',
            'criteria' => null
        ],
        [
            'tax_name' => 'IVA Reducido',
            'tax_code' => 'IVA_4', 
            'alegra_tax_id' => 2,
            'tax_rate' => 4.00,
            'applies_to' => 'specific',
            'criteria' => json_encode([
                'keywords' => ['medicamento', 'medicina', 'alimento básico'],
                'cabys_prefixes' => ['2103', '2104', '1001', '1002']
            ])
        ],
        [
            'tax_name' => 'Exento',
            'tax_code' => 'EXEMPT',
            'alegra_tax_id' => null,
            'tax_rate' => 0.00,
            'applies_to' => 'specific',
            'criteria' => json_encode([
                'keywords' => ['exento', 'educación', 'salud pública']
            ])
        ]
    ];

    foreach ($default_taxes as $tax) {
        $CI->db->insert(db_prefix() . 'alegra_cr_tax_config', $tax);
    }
}

// Crear tabla para auditoría de impuestos aplicados
if (!$CI->db->table_exists(db_prefix() . 'alegra_cr_tax_audit')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'alegra_cr_tax_audit` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `invoice_id` int(11) NOT NULL,
        `item_id` int(11) NOT NULL,
        `original_tax_rate` decimal(5,2) DEFAULT NULL,
        `applied_tax_rate` decimal(5,2) NOT NULL,
        `tax_reason` varchar(200) DEFAULT NULL,
        `alegra_tax_id` int(11) DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `invoice_id` (`invoice_id`),
        KEY `item_id` (`item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ' COLLATE=' . $CI->db->dbcollat . ';');
}

// Crear permisos para el módulo
$permissions_table_exists = $CI->db->table_exists(db_prefix() . 'permissions');

if ($permissions_table_exists) {
    // Permisos específicos
    $permissions = [
        'view' => 'View Alegra Integration',
        'create' => 'Create Electronic Invoices',
        'edit_settings' => 'Edit Alegra Settings',
    ];

    foreach ($permissions as $permission => $description) {
        $existing_permission = $CI->db->get_where(
            db_prefix() . 'permissions',
            array('name' => 'alegra_cr_' . $permission)
        )->row();
        if (!$existing_permission) {
            $CI->db->insert(db_prefix() . 'permissions', array(
                'name' => 'alegra_cr_' . $permission,
                'shortname' => 'alegra_cr_' . $permission,
            ));
        }
    }
}

// Insertar configuraciones iniciales
$options_table_exists = $CI->db->table_exists(db_prefix() . 'options');

if ($options_table_exists) {
    // Verificar si ya está instalado
    $existing_option = $CI->db->get_where(db_prefix() . 'options', array('name' => 'alegra_cr_installed'))->row();

    if (!$existing_option) {
        $CI->db->insert(db_prefix() . 'options', array(
            'name' => 'alegra_cr_installed',
            'value' => date('Y-m-d H:i:s'),
            'autoload' => 1
        ));

        $CI->db->insert(db_prefix() . 'options', array(
            'name' => 'alegra_cr_version',
            'value' => '1.0.0',
            'autoload' => 1
        ));
    }
}
