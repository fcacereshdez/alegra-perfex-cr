<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Alegra Facturacion Costa Rica Language File (English)
 */

// Menú y navegación
$lang['alegra_cr_module_name'] = 'Alegra Facturación CR';
$lang['alegra_cr'] = 'Alegra Invoicing (CR)';
$lang['alegra_cr_invoices'] = 'Invoices';
$lang['alegra_cr_products'] = 'Products';
$lang['alegra_cr_settings'] = 'Settings';
$lang['alegra_cr_tax_settings'] = 'Tax Settings';
$lang['alegra_cr_tax_configuration'] = 'Tax Configuration';
$lang['alegra_cr_payment_methods_settings'] = 'Payment Methods Settings';
$lang['alegra_cr_payment_methods_config'] = 'Payment Methods Configuration';

// Líneas para la vista de facturas
$lang['alegra_cr_transmit'] = 'Transmit';
$lang['alegra_cr_transmit_normal'] = 'Normal Transmission';
$lang['alegra_cr_transmit_contingency'] = 'Contingency Transmission';
$lang['alegra_cr_already_synced'] = 'Already Synced';
$lang['alegra_cr_view_in_alegra'] = 'View in Alegra';
$lang['alegra_cr_sync_completed'] = 'Sync Completed';
$lang['alegra_cr_sync_pending'] = 'Sync Pending';
$lang['alegra_cr_sync_error'] = 'Sync Error';
$lang['alegra_cr_sync_not_synced'] = 'Not Synced';
$lang['alegra_cr_status'] = 'Alegra Status';

// Líneas para productos
$lang['alegra_cr_products_info'] = 'Products will be automatically synced to Alegra when creating invoices.';
$lang['alegra_cr_sync_status'] = 'Sync Status';
$lang['alegra_cr_not_synced'] = 'Not Synced';
$lang['alegra_cr_synced'] = 'Synced';
$lang['alegra_cr_sync_now'] = 'Sync Now';
$lang['alegra_cr_sync_no_requiere'] = 'No Sync Required';

// Líneas para configuración
$lang['alegra_cr_email'] = 'Alegra Email';
$lang['alegra_cr_token'] = 'Alegra Token';
$lang['alegra_cr_token_help'] = 'API Token from your Alegra account';
$lang['alegra_cr_save_settings'] = 'Save Settings';
$lang['alegra_cr_settings_saved'] = 'Settings saved successfully';
$lang['alegra_cr_settings_not_saved'] = 'Error saving settings';

$lang['alegra_cr_payment_methods_settings'] = 'Métodos de Pago';
$lang['alegra_cr_unified_settings'] = 'Configuración Unificada';
$lang['alegra_cr_settings_tab_credentials'] = 'Credenciales API';
$lang['alegra_cr_settings_tab_payment_methods'] = 'Métodos de Pago';
$lang['alegra_cr_settings_tab_auto_transmit'] = 'Auto-transmisión';
$lang['alegra_cr_settings_tab_advanced'] = 'Configuración Avanzada';
$lang['alegra_cr_settings_tab_testing'] = 'Pruebas';

// Mensajes de configuración
$lang['alegra_cr_settings_saved_success'] = 'Configuración de Alegra Costa Rica guardada exitosamente';
$lang['alegra_cr_settings_save_error'] = 'Error al guardar la configuración de Alegra Costa Rica';
$lang['alegra_cr_connection_test_success'] = 'Conexión exitosa con Alegra';
$lang['alegra_cr_connection_test_error'] = 'Error de conexión con Alegra';

// Configuración centralizada
$lang['alegra_cr_centralized_config'] = 'Toda la configuración de Alegra Costa Rica ahora está disponible en esta pestaña';
$lang['alegra_cr_old_settings_redirect'] = 'Las páginas de configuración anteriores han sido redirigidas aquí';

// Tooltips y ayuda
$lang['alegra_cr_api_token_tooltip'] = 'Obtenga su token desde Alegra → Configuración → Integraciones → API';
$lang['alegra_cr_payment_methods_tooltip'] = 'Configure qué métodos de pago corresponden a tarjeta o efectivo para Costa Rica';
$lang['alegra_cr_auto_transmit_tooltip'] = 'Las facturas se transmitirán automáticamente según los criterios configurados';
$lang['alegra_cr_medical_keywords_tooltip'] = 'Palabras clave para detectar servicios médicos (separadas por comas)';