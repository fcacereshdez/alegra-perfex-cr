<?php defined('BASEPATH') or exit('No direct script access allowed');

// Cargar funciones del módulo
$module_file = FCPATH . 'modules/alegra_facturacion_cr/alegra_facturacion_cr.php';
if (file_exists($module_file)) {
    require_once($module_file);
}

// Obtener configuraciones de forma segura
$settings = [];
$perfex_payment_modes = [];
$payment_config = [
    'auto_transmit_methods' => [], 
    'iva_return_methods' => []
];

try {
    // Obtener todas las configuraciones
    if (function_exists('alegra_cr_get_current_settings')) {
        $settings = alegra_cr_get_current_settings();
    }
    
    // Obtener métodos de pago de Perfex
    if (function_exists('alegra_cr_get_payment_modes')) {
        $perfex_payment_modes = alegra_cr_get_payment_modes();
    }
    
    // Obtener configuración actual de métodos de pago
    if (function_exists('alegra_cr_get_payment_config_v2')) {
        $payment_config = alegra_cr_get_payment_config_v2();
    }
    
    // Asegurar que sean arrays
    if (!is_array($payment_config['auto_transmit_methods'])) {
        $payment_config['auto_transmit_methods'] = [];
    }
    if (!is_array($payment_config['iva_return_methods'])) {
        $payment_config['iva_return_methods'] = [];
    }
    
} catch (Exception $e) {
    log_message('error', 'Alegra CR Settings Error: ' . $e->getMessage());
}
?>

<div class="horizontal-scrollable-tabs panel-full-width-tabs">
    <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
    <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
    <div class="horizontal-tabs">
        <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
            <li role="presentation" class="active">
                <a href="#alegra_credentials" role="tab" data-toggle="tab">
                    <i class="fa fa-key"></i> Credenciales API
                </a>
            </li>
            <li role="presentation">
                <a href="#alegra_payment_methods" role="tab" data-toggle="tab">
                    <i class="fa fa-credit-card"></i> Métodos de Pago
                </a>
            </li>
            <li role="presentation">
                <a href="#alegra_auto_transmit" role="tab" data-toggle="tab">
                    <i class="fa fa-magic"></i> Auto-transmisión
                </a>
            </li>
            <li role="presentation">
                <a href="#alegra_iva_return" role="tab" data-toggle="tab">
                    <i class="fa fa-percent"></i> Devolución IVA
                </a>
            </li>
            <li role="presentation">
                <a href="#alegra_printing" role="tab" data-toggle="tab">
                    <i class="fa fa-print"></i> Impresión
                </a>
            </li>
            <li role="presentation">
                <a href="#alegra_advanced" role="tab" data-toggle="tab">
                    <i class="fa fa-cogs"></i> Avanzado
                </a>
            </li>
        </ul>
    </div>
</div>

<div class="tab-content">
    <!-- TAB 1: CREDENCIALES API -->
    <div role="tabpanel" class="tab-pane active" id="alegra_credentials">
        <div class="row">
            <div class="col-md-12">
                <h4><i class="fa fa-key"></i> Credenciales de Alegra API</h4>
                <hr>

                <?php echo render_input(
                    'settings[alegra_cr_email]',
                    'Email de Alegra',
                    $settings['alegra_email'] ?? '',
                    'email',
                    ['required' => true]
                ); ?>

                <?php echo render_input(
                    'settings[alegra_cr_token]',
                    'Token de API',
                    '',
                    'password',
                    [
                        'placeholder' => '********',
                        'data-toggle' => 'tooltip',
                        'title' => 'Dejar vacío para mantener el token actual'
                    ]
                ); ?>

                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    <strong>Información:</strong> Obtén tu token desde Alegra → Configuración → Integraciones → API.
                </div>

                <div class="form-group">
                    <button type="button" id="test-alegra-connection" class="btn btn-info">
                        <i class="fa fa-plug"></i> Probar Conexión
                    </button>
                    <div id="connection-result" style="margin-top: 10px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: MÉTODOS DE PAGO -->
    <div role="tabpanel" class="tab-pane" id="alegra_payment_methods">
        <div class="row">
            <div class="col-md-12">
                <h4><i class="fa fa-credit-card"></i> Configuración de Métodos de Pago</h4>
                <hr>

                <div class="alert alert-info">
                    <strong>Importante:</strong> Configure qué métodos de pago activan funcionalidades específicas:
                    <ul>
                        <li><strong>Auto-transmisión:</strong> Métodos que activarán la transmisión automática de facturas</li>
                        <li><strong>Devolución IVA 4%:</strong> Métodos de tarjeta que aplican IVA reducido para servicios médicos</li>
                    </ul>
                </div>

                <?php if (!empty($perfex_payment_modes)): ?>
                    <div class="row">
                        <!-- MÉTODOS CON AUTO-TRANSMISIÓN -->
                        <div class="col-md-6">
                            <div class="panel panel-primary">
                                <div class="panel-heading">
                                    <h5 class="panel-title">
                                        <i class="fa fa-magic"></i> Activar Auto-transmisión
                                    </h5>
                                    <small>Estos métodos transmitirán facturas automáticamente</small>
                                </div>
                                <div class="panel-body">
                                    <?php foreach ($perfex_payment_modes as $mode): ?>
                                        <div class="form-group">
                                            <div class="checkbox checkbox-primary">
                                                <input type="checkbox"
                                                    id="auto_transmit_method_<?php echo $mode['id']; ?>"
                                                    name="settings[alegra_cr_auto_transmit_methods_<?php echo $mode['id']; ?>]"
                                                    value="<?php echo $mode['id']; ?>"
                                                    class="auto-transmit-checkbox"
                                                    <?php echo in_array($mode['id'], $payment_config['auto_transmit_methods']) ? 'checked' : ''; ?>>
                                                <label for="auto_transmit_method_<?php echo $mode['id']; ?>">
                                                    <strong><?php echo htmlspecialchars($mode['name']); ?></strong>
                                                    <?php if (!empty($mode['description'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(strip_tags($mode['description'])); ?></small>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- MÉTODOS CON DEVOLUCIÓN IVA -->
                        <div class="col-md-6">
                            <div class="panel panel-success">
                                <div class="panel-heading">
                                    <h5 class="panel-title">
                                        <i class="fa fa-percent"></i> Devolución IVA 4%
                                    </h5>
                                    <small>Métodos de tarjeta con IVA reducido (servicios médicos)</small>
                                </div>
                                <div class="panel-body">
                                    <?php foreach ($perfex_payment_modes as $mode): ?>
                                        <div class="form-group">
                                            <div class="checkbox checkbox-success">
                                                <input type="checkbox"
                                                    id="iva_return_method_<?php echo $mode['id']; ?>"
                                                    name="settings[alegra_cr_iva_return_methods_<?php echo $mode['id']; ?>]"
                                                    value="<?php echo $mode['id']; ?>"
                                                    class="iva-return-checkbox"
                                                    <?php echo in_array($mode['id'], $payment_config['iva_return_methods']) ? 'checked' : ''; ?>>
                                                <label for="iva_return_method_<?php echo $mode['id']; ?>">
                                                    <strong><?php echo htmlspecialchars($mode['name']); ?></strong>
                                                    <?php if (!empty($mode['description'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(strip_tags($mode['description'])); ?></small>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="alert alert-warning" style="margin-top: 15px;">
                                        <i class="fa fa-exclamation-triangle"></i>
                                        <small>Solo configure métodos de TARJETA aquí. El IVA 4% aplica únicamente a pagos con tarjeta.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RESUMEN DE CONFIGURACIÓN -->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h5 class="panel-title">
                                <i class="fa fa-info-circle"></i> Resumen de Configuración
                            </h5>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Auto-transmisión activada para:</h5>
                                    <ul id="auto-transmit-summary">
                                        <?php foreach ($perfex_payment_modes as $mode): ?>
                                            <?php if (in_array($mode['id'], $payment_config['auto_transmit_methods'])): ?>
                                                <li><?php echo htmlspecialchars($mode['name']); ?></li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h5>IVA 4% aplicará con:</h5>
                                    <ul id="iva-return-summary">
                                        <?php foreach ($perfex_payment_modes as $mode): ?>
                                            <?php if (in_array($mode['id'], $payment_config['iva_return_methods'])): ?>
                                                <li><?php echo htmlspecialchars($mode['name']); ?> (servicios médicos)</li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <strong>Atención:</strong> No hay métodos de pago activos.
                        <a href="<?php echo admin_url('paymentmodes'); ?>" target="_blank">Configúralos aquí</a>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB 3: AUTO-TRANSMISIÓN -->
    <div role="tabpanel" class="tab-pane" id="alegra_auto_transmit">
        <div class="row">
            <div class="col-md-12">
                <h4><i class="fa fa-magic"></i> Auto-transmisión de Facturas</h4>
                <hr>

                <div class="form-group">
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" id="auto_transmit_enabled"
                            name="settings[alegra_cr_auto_transmit_enabled]" value="1"
                            <?php echo ($settings['auto_transmit_enabled'] == '1') ? 'checked' : ''; ?>>
                        <label for="auto_transmit_enabled">
                            <strong>Activar auto-transmisión de facturas</strong>
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        Las facturas se transmitirán automáticamente según los métodos de pago configurados
                    </small>
                </div>

                <div class="form-group">
                    <div class="checkbox checkbox-info">
                        <input type="checkbox" id="auto_transmit_medical_only"
                            name="settings[alegra_cr_auto_transmit_medical_only]" value="1"
                            <?php echo ($settings['auto_transmit_medical_only'] == '1') ? 'checked' : ''; ?>>
                        <label for="auto_transmit_medical_only">
                            <strong>Solo servicios médicos</strong>
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        Transmitir automáticamente solo facturas que contengan servicios médicos
                    </small>
                </div>

                <div class="form-group">
                    <div class="checkbox checkbox-warning">
                        <input type="checkbox" id="notify_auto_transmit"
                            name="settings[alegra_cr_notify_auto_transmit]" value="1"
                            <?php echo ($settings['notify_auto_transmit'] == '1') ? 'checked' : ''; ?>>
                        <label for="notify_auto_transmit">
                            <strong>Notificar auto-transmisiones</strong>
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        Agregar notas a las facturas cuando se transmitan automáticamente
                    </small>
                </div>

                <div class="form-group">
                    <label for="auto_transmit_delay">Retraso para auto-transmisión (minutos)</label>
                    <input type="number" class="form-control" 
                        name="settings[alegra_cr_auto_transmit_delay]" 
                        id="auto_transmit_delay"
                        value="<?php echo $settings['auto_transmit_delay'] ?? '0'; ?>"
                        min="0" max="60">
                    <small class="form-text text-muted">
                        Tiempo de espera antes de auto-transmitir (0 = inmediato)
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 4: DEVOLUCIÓN IVA -->
    <div role="tabpanel" class="tab-pane" id="alegra_iva_return">
        <div class="row">
            <div class="col-md-12">
                <h4><i class="fa fa-percent"></i> Configuración de Devolución de IVA</h4>
                <hr>

                <div class="alert alert-info">
                    <strong>Ley de Costa Rica:</strong> Los servicios médicos pagados con TARJETA aplican IVA del 4% en lugar del 13%.
                    Los pagos en EFECTIVO mantienen el IVA estándar del 13%.
                </div>

                <div class="form-group">
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" id="auto_detect_medical_services"
                            name="settings[alegra_cr_auto_detect_medical_services]" value="1"
                            <?php echo ($settings['auto_detect_medical_services'] == '1') ? 'checked' : ''; ?>>
                        <label for="auto_detect_medical_services">
                            <strong>Detección automática de servicios médicos</strong>
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        Detectar automáticamente servicios médicos por palabras clave
                    </small>
                </div>

                <div class="form-group">
                    <label for="medical_keywords">Palabras clave para servicios médicos</label>
                    <textarea class="form-control" name="settings[alegra_cr_medical_keywords]" 
                        id="medical_keywords" rows="3"><?php echo $settings['medical_keywords'] ?? ''; ?></textarea>
                    <small class="form-text text-muted">
                        Palabras clave separadas por comas (ej: consulta, examen, chequeo, revisión)
                    </small>
                </div>

                <div class="form-group">
                    <div class="checkbox checkbox-info">
                        <input type="checkbox" id="iva_return_notifications"
                            name="settings[alegra_cr_iva_return_notifications]" value="1"
                            <?php echo ($settings['iva_return_notifications'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <label for="iva_return_notifications">
                            <strong>Notificar devoluciones de IVA aplicadas</strong>
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        Agregar información sobre el IVA aplicado en las notas de la factura
                    </small>
                </div>
            </div>
        </div>
    </div>

      <div role="tabpanel" class="tab-pane" id="alegra_printing">
        <div class="row">
            <div class="col-md-12">
                <h4><i class="fa fa-print"></i> Configuración de Impresión</h4>
                <hr>

                <div class="form-group">
                    <label for="default_printer_type">Tipo de Impresora por Defecto</label>
                    <select name="settings[alegra_cr_default_printer_type]" class="form-control" id="default_printer_type">
                        <option value="web" <?php echo ($settings['default_printer_type'] ?? 'web') == 'web' ? 'selected' : ''; ?>>
                            Navegador Web
                        </option>
                        <option value="thermal" <?php echo ($settings['default_printer_type'] ?? '') == 'thermal' ? 'selected' : ''; ?>>
                            Impresora Térmica (IP)
                        </option>
                        <option value="usb" <?php echo ($settings['default_printer_type'] ?? '') == 'usb' ? 'selected' : ''; ?>>
                            USB/Local
                        </option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="thermal_printer_ip">IP de Impresora Térmica</label>
                            <input type="text" name="settings[alegra_cr_thermal_printer_ip]" 
                                class="form-control" 
                                value="<?php echo $settings['thermal_printer_ip'] ?? '192.168.1.100'; ?>" 
                                placeholder="192.168.1.100" id="thermal_printer_ip">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="thermal_printer_port">Puerto</label>
                            <input type="number" name="settings[alegra_cr_thermal_printer_port]" 
                                class="form-control" 
                                value="<?php echo $settings['thermal_printer_port'] ?? '9100'; ?>" 
                                placeholder="9100" id="thermal_printer_port">
                        </div>
                    </div>
                </div>

                <!-- Logo y Branding -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">Logo y Branding</h4>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" name="settings[alegra_cr_print_logo]" 
                                    id="print_logo" value="1" 
                                    <?php echo ($settings['print_logo'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <label for="print_logo">Imprimir Logo de Empresa</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="company_logo_path">Ruta del Logo</label>
                            <input type="text" name="settings[alegra_cr_company_logo_path]" 
                                class="form-control" 
                                value="<?php echo $settings['company_logo_path'] ?? ''; ?>" 
                                placeholder="uploads/company/logo.png">
                            <small class="text-muted">
                                Ruta relativa desde la raíz del sitio
                            </small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="logo_width">Ancho del Logo (px)</label>
                                    <input type="number" name="settings[alegra_cr_logo_width]" 
                                        class="form-control" 
                                        value="<?php echo $settings['logo_width'] ?? '120'; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="logo_height">Alto del Logo (px)</label>
                                    <input type="number" name="settings[alegra_cr_logo_height]" 
                                        class="form-control" 
                                        value="<?php echo $settings['logo_height'] ?? '70'; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Personalizado -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">Configuración de Footer</h4>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label for="print_footer_message">Mensaje de Agradecimiento</label>
                            <input type="text" name="settings[alegra_cr_print_footer_message]" 
                                class="form-control" 
                                value="<?php echo $settings['print_footer_message'] ?? 'Gracias por su compra'; ?>">
                        </div>

                        <div class="form-group">
                            <label for="footer_conditions">Condiciones de Venta</label>
                            <textarea name="settings[alegra_cr_footer_conditions]" 
                                class="form-control" rows="4"><?php echo $settings['footer_conditions'] ?? ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" name="settings[alegra_cr_show_footer_conditions]" 
                                    id="show_footer_conditions" value="1" 
                                    <?php echo ($settings['show_footer_conditions'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <label for="show_footer_conditions">Mostrar en facturas completas</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" name="settings[alegra_cr_show_footer_conditions_ticket]" 
                                    id="show_footer_conditions_ticket" value="1" 
                                    <?php echo ($settings['show_footer_conditions_ticket'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <label for="show_footer_conditions_ticket">Mostrar en tickets</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="footer_legal_text">Texto Legal</label>
                            <textarea name="settings[alegra_cr_footer_legal_text]" 
                                class="form-control" rows="2"><?php echo $settings['footer_legal_text'] ?? ''; ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="settings[alegra_cr_auto_print]" 
                            id="auto_print" value="1" 
                            <?php echo ($settings['auto_print'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <label for="auto_print">Impresión Automática al crear factura</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 5: AVANZADO -->
    <div role="tabpanel" class="tab-pane" id="alegra_advanced">
        <div class="row">
            <div class="col-md-12">
                <h4><i class="fa fa-cogs"></i> Información del Sistema</h4>
                <hr>

                <div class="well">
                    <h5>Estado de Configuración</h5>
                    <table class="table table-bordered">
                        <tr>
                            <td><strong>Email configurado:</strong></td>
                            <td>
                                <span class="label label-<?php echo !empty($settings['alegra_email']) ? 'success' : 'warning'; ?>">
                                    <?php echo !empty($settings['alegra_email']) ? htmlspecialchars($settings['alegra_email']) : 'No configurado'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Token API:</strong></td>
                            <td>
                                <span class="label label-<?php echo !empty(get_option('alegra_cr_token')) ? 'success' : 'warning'; ?>">
                                    <?php echo !empty(get_option('alegra_cr_token')) ? 'Configurado' : 'No configurado'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Auto-transmisión:</strong></td>
                            <td>
                                <span class="label label-<?php echo ($settings['auto_transmit_enabled'] == '1') ? 'success' : 'default'; ?>">
                                    <?php echo ($settings['auto_transmit_enabled'] == '1') ? 'Activada' : 'Desactivada'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Versión del módulo:</strong></td>
                            <td><?php echo get_option('alegra_cr_version') ?: '1.0.0'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Instalado:</strong></td>
                            <td><?php echo get_option('alegra_cr_installed') ?: 'N/A'; ?></td>
                        </tr>
                    </table>
                </div>

                <div class="form-group">
                    <a href="<?php echo admin_url('alegra_facturacion_cr/repair_installation'); ?>" 
                        class="btn btn-warning" target="_blank">
                        <i class="fa fa-wrench"></i> Reparar Instalación
                    </a>
                    <small class="text-muted">
                        Si experimentas problemas, ejecuta la reparación de la instalación
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    window.admin_url = '<?php echo admin_url(); ?>';

    function initAlegraSettings() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initAlegraSettings, 100);
            return;
        }

        var $ = jQuery;

        // Actualizar resumen en tiempo real
        function updateSummaries() {
            // Auto-transmisión
            var autoTransmitList = [];
            $('.auto-transmit-checkbox:checked').each(function() {
                var label = $(this).siblings('label').find('strong').text();
                autoTransmitList.push('<li>' + label + '</li>');
            });
            $('#auto-transmit-summary').html(autoTransmitList.length > 0 ? autoTransmitList.join('') : '<li class="text-muted">Ninguno configurado</li>');

            // IVA return
            var ivaReturnList = [];
            $('.iva-return-checkbox:checked').each(function() {
                var label = $(this).siblings('label').find('strong').text();
                ivaReturnList.push('<li>' + label + ' (servicios médicos)</li>');
            });
            $('#iva-return-summary').html(ivaReturnList.length > 0 ? ivaReturnList.join('') : '<li class="text-muted">Ninguno configurado</li>');
        }

        // Eventos para actualizar resumen
        $(document).on('change', '.auto-transmit-checkbox, .iva-return-checkbox', updateSummaries);

        // Test de conexión
        $(document).on('click', '#test-alegra-connection', function() {
            var btn = $(this);
            var originalText = btn.html();

            btn.html('<i class="fa fa-spinner fa-spin"></i> Verificando...');
            btn.prop('disabled', true);

            $.post(admin_url + 'alegra_facturacion_cr/test_connection', function(data) {
                var result = $('#connection-result');

                if (data.success) {
                    result.html('<div class="alert alert-success"><i class="fa fa-check"></i> Conexión exitosa</div>');
                } else {
                    result.html('<div class="alert alert-danger"><i class="fa fa-times"></i> Error: ' + data.message + '</div>');
                }
            }, 'json').fail(function() {
                $('#connection-result').html('<div class="alert alert-danger"><i class="fa fa-times"></i> Error de conexión</div>');
            }).always(function() {
                btn.html(originalText);
                btn.prop('disabled', false);
            });
        });

        // Inicializar resúmenes
        updateSummaries();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAlegraSettings);
    } else {
        initAlegraSettings();
    }
})();
</script>