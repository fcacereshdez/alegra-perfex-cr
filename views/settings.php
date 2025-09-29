<?php defined('BASEPATH') or exit('No direct script access allowed');

// Cargar funciones del módulo de forma segura
$module_file = FCPATH . 'modules/alegra_facturacion_cr/alegra_facturacion_cr.php';
if (file_exists($module_file)) {
    require_once($module_file);
}

// Inicializar variables con valores seguros
$settings = [];
$perfex_payment_modes = [];
$payment_config = ['card_payment_methods' => [], 'cash_payment_methods' => []];

try {
    // 1. Obtener settings generales
    if (function_exists('alegra_cr_get_current_settings')) {
        $settings = alegra_cr_get_current_settings();
    } else {
        $settings = [
            'alegra_email' => get_option('alegra_cr_email') ?: '',
            'auto_transmit_enabled' => get_option('alegra_cr_auto_transmit_enabled') ?: '0',
            'auto_transmit_medical_only' => get_option('alegra_cr_auto_transmit_medical_only') ?: '0',
            'auto_detect_medical_services' => get_option('alegra_cr_auto_detect_medical_services') ?: '1',
            'notify_auto_transmit' => get_option('alegra_cr_notify_auto_transmit') ?: '1',
            'medical_keywords' => get_option('alegra_cr_medical_keywords') ?: '',
            'auto_transmit_delay' => get_option('alegra_cr_auto_transmit_delay') ?: '0',
            'default_printer_type' => get_option('alegra_cr_default_printer_type') ?: 'web',
            'thermal_printer_ip' => get_option('alegra_cr_thermal_printer_ip') ?: 'web',
            'thermal_printer_port' => get_option('alegra_cr_thermal_printer_port') ?: '9100',
            'ticket_width' => get_option('alegra_cr_ticket_width') ?: '48',
            'auto_print' => get_option('alegra_cr_auto_print') ?: '1',
            'logo_width' => get_option('alegra_cr_logo_width') ?: '120',
            'logo_height' => get_option('alegra_cr_logo_height') ?: '70',
            'print_logo' => get_option('alegra_cr_print_logo') ?: '1',
            'company_logo_path' => get_option('alegra_cr_company_logo_path') ?: '1',
            'print_footer_message' => get_option('alegra_cr_print_footer_message') ?: '',
            'footer_conditions' => get_option('alegra_cr_footer_conditions') ?: '',
            'show_footer_conditions' => get_option('alegra_cr_show_footer_conditions') ?: '1',
            'show_footer_conditions_ticket' => get_option('alegra_cr_show_footer_conditions_ticket') ?: '1',
            'footer_legal_text' => get_option('alegra_cr_footer_legal_text') ?: ''
        ];
    }

    // 2. Obtener métodos de pago de Perfex (con manejo de errores)
    if (function_exists('alegra_cr_get_payment_modes')) {
        $perfex_payment_modes = alegra_cr_get_payment_modes();
    } else {
        $CI = &get_instance();
        $CI->db->select('id, name, description');
        $CI->db->where('active', 1);
        $result = $CI->db->get(db_prefix() . 'payment_modes');
        $perfex_payment_modes = $result ? $result->result_array() : [];
    }

    // 3. Obtener configuración de métodos de pago (con decodificación segura)
    if (function_exists('alegra_cr_get_payment_methods_config')) {
        $payment_config = alegra_cr_get_payment_methods_config();
    } else {
        $card_option = get_option('alegra_cr_card_payment_methods');
        $cash_option = get_option('alegra_cr_cash_payment_methods');

        // Decodificar de forma segura
        $card_methods = [];
        if (!empty($card_option) && $card_option !== '[]') {
            $decoded = json_decode($card_option, true);
            if (is_array($decoded)) {
                $card_methods = $decoded;
            }
        }

        $cash_methods = [];
        if (!empty($cash_option) && $cash_option !== '[]') {
            $decoded = json_decode($cash_option, true);
            if (is_array($decoded)) {
                $cash_methods = $decoded;
            }
        }

        $payment_config = [
            'card_payment_methods' => $card_methods,
            'cash_payment_methods' => $cash_methods
        ];
    }

    // 4. Garantizar que payment_config contenga arrays válidos
    if (!isset($payment_config['card_payment_methods']) || !is_array($payment_config['card_payment_methods'])) {
        $payment_config['card_payment_methods'] = [];
    }
    if (!isset($payment_config['cash_payment_methods']) || !is_array($payment_config['cash_payment_methods'])) {
        $payment_config['cash_payment_methods'] = [];
    }
} catch (Exception $e) {
    log_message('error', 'Alegra CR Settings Load Error: ' . $e->getMessage());
    log_message('error', 'Alegra CR Stack Trace: ' . $e->getTraceAsString());

    // Fallback completo en caso de error
    $settings = [
        'alegra_email' => '',
        'auto_transmit_enabled' => '0',
        'auto_transmit_medical_only' => '0',
        'auto_detect_medical_services' => '1',
        'notify_auto_transmit' => '1',
        'medical_keywords' => '',
        'auto_transmit_delay' => '0'
    ];
    $perfex_payment_modes = [];
    $payment_config = ['card_payment_methods' => [], 'cash_payment_methods' => []];
}

// Debug en desarrollo (comentar en producción)
log_message('error', 'Alegra CR Settings: ' . json_encode($settings));
log_message('error', 'Alegra CR Payment Modes: ' . json_encode($perfex_payment_modes));
log_message('error', 'Alegra CR Payment Config: ' . json_encode($payment_config));
?>

<div class="horizontal-scrollable-tabs panel-full-width-tabs">
    <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
    <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
    <div class="horizontal-tabs">
        <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
            <li role="presentation" class="active">
                <a href="#alegra_credentials" aria-controls="alegra_credentials" role="tab" data-toggle="tab">
                    <i class="fa fa-key"></i> Credenciales API
                </a>
            </li>
            <li role="presentation">
                <a href="#alegra_payment_methods" aria-controls="alegra_payment_methods" role="tab" data-toggle="tab">
                    <i class="fa fa-credit-card"></i> Métodos de Pago
                </a>
            </li>
            <li role="presentation">
                <a href="#alegra_auto_transmit" aria-controls="alegra_auto_transmit" role="tab" data-toggle="tab">
                    <i class="fa fa-magic"></i> Auto-transmisión
                </a>
            </li>
            <li role="presentation">
                <a href="#alegra_advanced" aria-controls="alegra_advanced" role="tab" data-toggle="tab">
                    <i class="fa fa-cogs"></i> Configuración Avanzada
                </a>
            </li>
            <li role="presentation">
                <a href="#alegra_testing" aria-controls="alegra_testing" role="tab" data-toggle="tab">
                    <i class="fa fa-flask"></i> Pruebas
                </a>
            </li>
            <li role="presentation">
                <a href="#alegra_printing" aria-controls="alegra_testing" role="tab" data-toggle="tab">
                    <i class="fa fa-print"></i> Impresión
                </a>
            </li>
        </ul>
    </div>
</div>

<div class="tab-content">
    <!-- Pestaña 1: Credenciales API -->
    <div role="tabpanel" class="tab-pane active" id="alegra_credentials">
        <div class="row">
            <div class="col-md-12">
                <h4><i class="fa fa-key"></i> Credenciales de Alegra API</h4>
                <hr>

                <?php echo render_input(
                    'settings[alegra_cr_email]',
                    'Email de Alegra',
                    $settings['alegra_email'],
                    'email',
                    ['required' => true]
                ); ?>

                <?php echo render_input('settings[alegra_cr_token]', 'Token de API', '', 'password', [
                    'placeholder' => '********',
                    'data-toggle' => 'tooltip',
                    'title' => 'Dejar vacío para mantener el token actual'
                ]); ?>

                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    <strong>Información:</strong> Puedes obtener tu token de API desde tu cuenta de Alegra en Configuración → Integraciones → API.
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

    <!-- Pestaña 2: Métodos de Pago -->
    <div role="tabpanel" class="tab-pane" id="alegra_payment_methods">
        <div class="row">
            <div class="col-md-12">
                <h4><i class="fa fa-credit-card"></i> Configuración de Métodos de Pago</h4>
                <hr>

                <div class="alert alert-info">
                    <strong>Información:</strong> Configure qué métodos de pago de Perfex corresponden a tarjeta o efectivo para la facturación electrónica en Costa Rica.
                </div>

                <?php if (!empty($perfex_payment_modes)): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h5 class="panel-title">
                                        <i class="fa fa-credit-card text-danger"></i> Métodos de Pago con TARJETA
                                    </h5>
                                    <small>IVA 4% para servicios médicos</small>
                                </div>
                                <div class="panel-body">
                                    <?php foreach ($perfex_payment_modes as $mode): ?>
                                        <div class="form-group">
                                            <div class="checkbox checkbox-primary">
                                                <input type="checkbox"
                                                    id="card_method_<?php echo $mode['id']; ?>"
                                                    name="settings[alegra_cr_card_payment_methods_<?php echo $mode['id']; ?>]"
                                                    value="<?php echo $mode['id']; ?>"
                                                    class="card-method-checkbox"
                                                    <?php echo (in_array($mode['id'], $payment_config['card_payment_methods'])) ? 'checked' : ''; ?>>
                                                <label for="card_method_<?php echo $mode['id']; ?>">
                                                    <strong><?php echo $mode['name']; ?></strong>
                                                    <?php if (!empty($mode['description'])): ?>
                                                        <br><small class="text-muted"><?php echo strip_tags($mode['description']); ?></small>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="panel panel-success">
                                <div class="panel-heading">
                                    <h5 class="panel-title">
                                        <i class="fa fa-money text-success"></i> Métodos de Pago en EFECTIVO
                                    </h5>
                                    <small>IVA 13% para servicios médicos</small>
                                </div>
                                <div class="panel-body">
                                    <?php foreach ($perfex_payment_modes as $mode): ?>
                                        <div class="form-group">
                                            <div class="checkbox checkbox-success">
                                                <input type="checkbox"
                                                    id="cash_method_<?php echo $mode['id']; ?>"
                                                    name="settings[alegra_cr_cash_payment_methods_<?php echo $mode['id']; ?>]"
                                                    value="<?php echo $mode['id']; ?>"
                                                    class="cash-method-checkbox"
                                                    <?php echo (isset($payment_config['cash_payment_methods']) && in_array($mode['id'], $payment_config['cash_payment_methods'])) ? 'checked' : ''; ?>>
                                                <label for="cash_method_<?php echo $mode['id']; ?>">
                                                    <strong><?php echo $mode['name']; ?></strong>
                                                    <?php if (!empty($mode['description'])): ?>
                                                        <br><small class="text-muted"><?php echo strip_tags($mode['description']); ?></small>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <strong>Atención:</strong> No se encontraron métodos de pago activos en el sistema.
                        Por favor, configure al menos un método de pago en
                        <a href="<?php echo admin_url('paymentmodes'); ?>" target="_blank">Configuración → Métodos de Pago</a>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pestaña 3: Auto-transmisión -->
    <div role="tabpanel" class="tab-pane" id="alegra_auto_transmit">
        <div class="row">
            <div class="col-md-12">
                <h4><i class="fa fa-magic"></i> Configuraciones Automáticas</h4>
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
                        Si está marcado, se auto-transmitirán facturas según los métodos de pago configurados
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
                        Transmitir automáticamente solo facturas con servicios médicos
                    </small>
                </div>

                <div class="form-group">
                    <div class="checkbox checkbox-info">
                        <input type="checkbox" id="auto_detect_medical_services"
                            name="settings[alegra_cr_auto_detect_medical_services]" value="1"
                            <?php echo ($settings['auto_detect_medical_services'] == '1') ? 'checked' : ''; ?>>
                        <label for="auto_detect_medical_services">
                            <strong>Detección automática de servicios médicos</strong>
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        Detectar automáticamente servicios médicos por palabras clave y códigos CABYS
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
            </div>
        </div>
    </div>

    <!-- Pestaña 4: Configuración Avanzada -->
    <div role="tabpanel" class="tab-pane" id="alegra_advanced">
        <div class="row">
            <div class="col-md-12">
                <h4><i class="fa fa-cogs"></i> Configuraciones Avanzadas</h4>
                <hr>

                <div class="form-group">
                    <label for="medical_keywords">Palabras clave para servicios médicos</label>
                    <textarea class="form-control" name="settings[alegra_cr_medical_keywords]" id="medical_keywords" rows="3" placeholder="consulta, examen, chequeo, revisión, diagnóstico, cirugía..."><?php echo $settings['medical_keywords']; ?></textarea>
                    <small class="form-text text-muted">
                        Palabras clave separadas por comas para detectar servicios médicos
                    </small>
                </div>

                <div class="form-group">
                    <label for="auto_transmit_delay">Retraso para auto-transmisión (minutos)</label>
                    <input type="number" class="form-control" name="settings[alegra_cr_auto_transmit_delay]" id="auto_transmit_delay"
                        value="<?php echo $settings['auto_transmit_delay']; ?>"
                        min="0" max="60">
                    <small class="form-text text-muted">
                        Tiempo de espera antes de auto-transmitir (0 = inmediato)
                    </small>
                </div>

                <div class="form-group">
                    <h5><i class="fa fa-info-circle"></i> Estado del Sistema</h5>
                    <div class="well well-sm">
                        <p><strong>Email configurado:</strong>
                            <span class="label label-<?php echo !empty($settings['alegra_email']) ? 'success' : 'warning'; ?>">
                                <?php echo !empty($settings['alegra_email']) ? $settings['alegra_email'] : 'No configurado'; ?>
                            </span>
                        </p>
                        <p><strong>Token API:</strong>
                            <span class="label label-<?php echo !empty(get_option('alegra_cr_token')) ? 'success' : 'warning'; ?>">
                                <?php echo !empty(get_option('alegra_cr_token')) ? 'Configurado' : 'No configurado'; ?>
                            </span>
                        </p>
                        <p><strong>Auto-transmisión:</strong>
                            <span class="label label-<?php echo ($settings['auto_transmit_enabled'] == '1') ? 'success' : 'default'; ?>">
                                <?php echo ($settings['auto_transmit_enabled'] == '1') ? 'Activada' : 'Desactivada'; ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pestaña 5: Pruebas -->
    <div role="tabpanel" class="tab-pane" id="alegra_testing">
        <div class="row">
            <div class="col-md-12">
                <h4><i class="fa fa-flask"></i> Herramientas de Prueba</h4>
                <hr>

                <div class="form-group">
                    <button type="button" id="test-auto-detection" class="btn btn-info">
                        <i class="fa fa-search"></i> Probar Detección Automática
                    </button>
                    <small class="form-text text-muted">
                        Prueba la detección automática de servicios médicos
                    </small>
                </div>

                <div class="form-group">
                    <button type="button" id="test-payment-config" class="btn btn-warning">
                        <i class="fa fa-credit-card"></i> Verificar Config. Pagos
                    </button>
                    <small class="form-text text-muted">
                        Verifica la configuración actual de métodos de pago
                    </small>
                </div>

                <div class="form-group">
                    <button type="button" id="debug-form-data" class="btn btn-default">
                        <i class="fa fa-bug"></i> Debug Formulario
                    </button>
                    <small class="form-text text-muted">
                        Mostrar datos del formulario en la consola (para desarrollo)
                    </small>
                </div>

                <div id="test-results" style="margin-top: 15px;"></div>
            </div>
        </div>
    </div>

    <!-- Pestaña 6: Setings de impresión -->
    <div role="tabpanel" class="tab-pane" id="alegra_printing">
        <div class="row">
            <div class="col-md-12">
                <h4><i class="fa fa-print"></i> Impresión de Ticket y de Factura</h4>
                <hr>

                <div class="form-group">
                            <label for="default_printer_type">Tipo de Impresora por Defecto</label>
                            <select name="default_printer_type" class="form-control">
                                <option value="web" <?php echo $settings['default_printer_type'] == 'web' ? 'selected' : ''; ?>>
                                    Navegador Web
                                </option>
                                <option value="thermal" <?php echo $settings['default_printer_type'] == 'thermal' ? 'selected' : ''; ?>>
                                    Impresora Térmica (IP)
                                </option>
                                <option value="usb" <?php echo $settings['default_printer_type'] == 'usb' ? 'selected' : ''; ?>>
                                    USB/Local
                                </option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="thermal_printer_ip">IP de Impresora Térmica</label>
                                    <input type="text" name="settings[alegra_cr_thermal_printer_ip]" class="form-control" 
                                           value="<?php echo $settings['thermal_printer_ip']; ?>" 
                                           placeholder="192.168.1.100" id="thermal_printer_ip">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="thermal_printer_port">Puerto</label>
                                    <input type="number" name="settings[alegra_cr_thermal_printer_port]" class="form-control" 
                                           value="<?php echo $settings['thermal_printer_port']; ?>" 
                                           placeholder="9100" id="thermal_printer_port">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="ticket_width">Ancho de Ticket (caracteres)</label>
                            <select name="ticket_width" class="form-control">
                                <option value="32" <?php echo $settings['ticket_width'] == '32' ? 'selected' : ''; ?>>32 caracteres</option>
                                <option value="40" <?php echo $settings['ticket_width'] == '40' ? 'selected' : ''; ?>>40 caracteres</option>
                                <option value="48" <?php echo $settings['ticket_width'] == '48' ? 'selected' : ''; ?>>48 caracteres</option>
                            </select>
                        </div>

                        <!-- NUEVA SECCIÓN: LOGO Y BRANDING -->
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4 class="panel-title">Configuración de Logo y Branding</h4>
                            </div>
                            <div class="panel-body">
                                <div class="form-group">
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" name="settings[alegra_cr_print_logo]" id="print_logo" value="1" 
                                               <?php echo $settings['print_logo'] ? 'checked' : ''; ?>>
                                        <label for="print_logo">Imprimir Logo de Empresa</label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="company_logo_path">Ruta del Logo</label>
                                    <div class="input-group">
                                        <input type="text" name="settings[alegra_cr_company_logo_path]" class="form-control" 
                                               value="<?php echo $settings['company_logo_path']; ?>" 
                                               placeholder="uploads/company/logo.png">
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-default" onclick="selectLogo()">
                                                <i class="fa fa-folder-open"></i> Seleccionar
                                            </button>
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        Ruta relativa desde la raíz del sitio. Ej: uploads/company/logo.png
                                    </small>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="logo_width">Ancho del Logo (px)</label>
                                            <input type="number" name="settings[alegra_cr_logo_width]" class="form-control" 
                                                   value="<?php echo $settings['logo_width'] ?? '120'; ?>" 
                                                   placeholder="120">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="logo_height">Alto del Logo (px)</label>
                                            <input type="number" name="settings[alegra_cr_logo_height]" class="form-control" 
                                                   value="<?php echo $settings['logo_height'] ?? '80'; ?>" 
                                                   placeholder="80">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- NUEVA SECCIÓN: FOOTER PERSONALIZADO -->
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4 class="panel-title">Configuración de Footer</h4>
                            </div>
                            <div class="panel-body">
                                <div class="form-group">
                                    <label for="print_footer_message">Mensaje de Agradecimiento</label>
                                    <input type="text" name="settings[alegra_cr_print_footer_message]" class="form-control" 
                                           value="<?php echo $settings['print_footer_message']; ?>" 
                                           placeholder="Gracias por su compra">
                                </div>

                                <div class="form-group">
                                    <label for="footer_conditions">Condiciones de Venta y Términos Legales</label>
                                    <textarea name="settings[alegra_cr_footer_conditions]" class="form-control" rows="4" 
                                              placeholder="Ingrese aquí las condiciones de venta, términos legales, política de devoluciones, etc."><?php echo $settings['footer_conditions'] ?? ''; ?></textarea>
                                    <small class="text-muted">
                                        Este texto aparecerá al final de todas las facturas impresas. 
                                        Use saltos de línea para separar diferentes condiciones.
                                    </small>
                                </div>

                                <div class="form-group">
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" name="settings[alegra_cr_show_footer_conditions]" id="show_footer_conditions" value="1" 
                                               <?php echo ($settings['show_footer_conditions'] ?? '1') ? 'checked' : ''; ?>>
                                        <label for="show_footer_conditions">Mostrar condiciones en facturas completas</label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" name="settings[alegra_cr_show_footer_conditions_ticket]" id="show_footer_conditions_ticket" value="1" 
                                               <?php echo ($settings['show_footer_conditions_ticket'] ?? '0') ? 'checked' : ''; ?>>
                                        <label for="show_footer_conditions_ticket">Mostrar condiciones en tickets (versión corta)</label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="footer_legal_text">Texto Legal (Autorización HACIENDA, etc.)</label>
                                    <textarea name="footer_legal_text" class="form-control" rows="2" 
                                              placeholder="Ej: Factura electrónica autorizada por el Ministerio de Hacienda de Costa Rica"><?php echo $settings['footer_legal_text'] ?? ''; ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" name="auto_print" id="auto_print" value="1" 
                                       <?php echo $settings['auto_print'] ? 'checked' : ''; ?>>
                                <label for="auto_print">Impresión Automática al crear factura</label>
                            </div>
                        </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>


<!-- JavaScript específico para esta configuración -->
<script>
    (function() {
        // Variable global
        window.admin_url = '<?php echo admin_url(); ?>';

        function initAlegraSettings() {
            if (typeof jQuery === 'undefined') {
                setTimeout(initAlegraSettings, 100);
                return;
            }

            var $ = jQuery;

            console.log('Alegra CR: Configuración cargada');

            // Prevenir que un método esté en ambas categorías
            $(document).on('change', '.card-method-checkbox', function() {
                if ($(this).is(':checked')) {
                    var methodId = $(this).val();
                    $('.cash-method-checkbox[value="' + methodId + '"]').prop('checked', false);
                }
            });

            $(document).on('change', '.cash-method-checkbox', function() {
                if ($(this).is(':checked')) {
                    var methodId = $(this).val();
                    $('.card-method-checkbox[value="' + methodId + '"]').prop('checked', false);
                }
            });

            // Test de conexión
            $(document).on('click', '#test-alegra-connection', function() {
                var btn = $(this);
                var originalText = btn.html();

                btn.html('<i class="fa fa-spinner fa-spin"></i> Verificando...');
                btn.prop('disabled', true);

                $.post(admin_url + 'alegra_facturacion_cr/test_connection', function(data) {
                    var result = $('#connection-result');

                    if (data.success) {
                        result.html('<div class="alert alert-success"><i class="fa fa-check"></i> Conexión exitosa con Alegra</div>');
                    } else {
                        result.html('<div class="alert alert-danger"><i class="fa fa-times"></i> Error de conexión: ' + data.message + '</div>');
                    }
                }, 'json').fail(function() {
                    $('#connection-result').html('<div class="alert alert-danger"><i class="fa fa-times"></i> Error al probar la conexión</div>');
                }).always(function() {
                    btn.html(originalText);
                    btn.prop('disabled', false);
                });
            });

            // Debug del formulario
            $(document).on('click', '#debug-form-data', function() {
                var formData = {};
                $('input, textarea, select').each(function() {
                    var $field = $(this);
                    var name = $field.attr('name');
                    if (!name) return;

                    if ($field.is(':checkbox')) {
                        if (!formData[name]) formData[name] = [];
                        if ($field.is(':checked')) {
                            formData[name].push($field.val());
                        }
                    } else {
                        formData[name] = $field.val();
                    }
                });

                console.log('Datos del formulario:', formData);
                $('#test-results').html('<div class="alert alert-info">Datos mostrados en la consola. Presiona F12 para verlos.</div>');
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAlegraSettings);
        } else {
            initAlegraSettings();
        }
    })();
</script>