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
        PRIMARY KEY (`id`),
        UNIQUE KEY `perfex_invoice_id` (`perfex_invoice_id`),
        UNIQUE KEY `alegra_invoice_id` (`alegra_invoice_id`)
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
