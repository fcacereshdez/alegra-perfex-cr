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

// Hook para el menú de administración
hooks()->add_action('admin_init', 'alegra_cr_admin_init');

// Hook para cargar la configuración del módulo
hooks()->add_action('pre_activate_module', 'alegra_cr_preactivate');

/**
 * Registrar el módulo en el menú de administración
 */
function alegra_cr_admin_init()
{
    if (has_permission('alegra_cr', '', 'view')) {
        $CI = &get_instance();

        include_once(__DIR__ . '/hooks.php');

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

        $CI->app_menu->add_sidebar_children_item('alegra-cr', [
            'slug'     => 'alegra-cr-settings',
            'name'     => _l('alegra_cr_settings'),
            'href'     => admin_url('alegra_facturacion_cr/settings'),
            'position' => 3,
        ]);

        $CI->app_menu->add_sidebar_children_item('alegra-cr', [
            'slug'     => 'alegra-cr-tax-settings',
            'name'     => _l('alegra_cr_tax_settings'),
            'href'     => admin_url('alegra_facturacion_cr/tax_settings'),
            'position' => 4,
        ]);

        $CI->app_menu->add_sidebar_children_item('alegra-cr', [
            'slug'     => 'alegra-cr-payment-methods-settings',
            'name'     => _l('alegra_cr_payment_methods_settings'),
            'href'     => admin_url('alegra_facturacion_cr/payment_methods_settings'),
            'position' => 4,
        ]);
    }
}

/**
 * Pre-activación del módulo
 */
function alegra_cr_preactivate($module_name)
{
    if ($module_name['system_name'] == ALEGRA_CR_MODULE_NAME) {
        require_once(__DIR__ . '/install.php');
    }
}

/**
 * Registro de permisos del módulo
 */
register_activation_hook(ALEGRA_CR_MODULE_NAME, 'alegra_cr_module_activation_hook');

function alegra_cr_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}
