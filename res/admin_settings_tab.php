<?php defined('BASEPATH') or exit('No direct script access allowed');

// DEBUG: Verificar disponibilidad de funciones
$functions_available = function_exists('alegra_cr_get_all_settings');

if (!$functions_available) {
    echo '<div class="alert alert-danger">
        <strong>Error:</strong> Las funciones del módulo no están disponibles. 
        Recargue la página o contacte al administrador.
    </div>';
    
    // Intentar cargar funciones manualmente
    $module_path = FCPATH . 'modules/alegra_facturacion_cr/alegra_facturacion_cr.php';
    if (file_exists($module_path)) {
        require_once($module_path);
    }
}

// Obtener configuraciones de forma segura

// Obtener configuraciones de forma segura
try {
    $settings = function_exists('alegra_cr_get_all_settings') ? 
        alegra_cr_get_all_settings() : 
        [
            'alegra_email' => get_option('alegra_cr_email'),
            'alegra_token' => '',
            'auto_transmit_enabled' => get_option('alegra_cr_auto_transmit_enabled'),
            'auto_transmit_payment_methods' => get_option('alegra_cr_auto_transmit_payment_methods'),
            'auto_transmit_medical_only' => get_option('alegra_cr_auto_transmit_medical_only'),
            'auto_detect_medical_services' => get_option('alegra_cr_auto_detect_medical_services'),
            'notify_auto_transmit' => get_option('alegra_cr_notify_auto_transmit'),
            'medical_keywords' => get_option('alegra_cr_medical_keywords'),
            'auto_transmit_delay' => get_option('alegra_cr_auto_transmit_delay'),
            'card_payment_methods' => [],
            'cash_payment_methods' => []
        ];
    
    $perfex_payment_modes = function_exists('alegra_cr_get_payment_modes') ? 
        alegra_cr_get_payment_modes() : 
        get_instance()->db->get_where('payment_modes', ['active' => 1])->result_array();
    
    // CRÍTICO: Decodificar payment_config correctamente
    if (function_exists('alegra_cr_get_payment_config')) {
        $payment_config = alegra_cr_get_payment_config();
    } else {
        // Obtener y decodificar desde options
        $card_methods = get_option('alegra_cr_card_payment_methods');
        $cash_methods = get_option('alegra_cr_cash_payment_methods');
        
        // Decodificar si es string JSON
        if (is_string($card_methods)) {
            $card_methods = json_decode($card_methods, true);
        }
        if (is_string($cash_methods)) {
            $cash_methods = json_decode($cash_methods, true);
        }
        
        $payment_config = [
            'card_payment_methods' => is_array($card_methods) ? $card_methods : [],
            'cash_payment_methods' => is_array($cash_methods) ? $cash_methods : []
        ];
    }
    
    // Asegurar que sean arrays
    if (!is_array($payment_config['card_payment_methods'])) {
        $payment_config['card_payment_methods'] = [];
    }
    if (!is_array($payment_config['cash_payment_methods'])) {
        $payment_config['cash_payment_methods'] = [];
    }
        
} catch (Exception $e) {
    log_message('error', 'Alegra CR Settings Tab Error: ' . $e->getMessage());
    echo '<div class="alert alert-danger">Error cargando configuraciones: ' . $e->getMessage() . '</div>';
    
    // Fallback seguro
    $settings = [
        'alegra_email' => '',
        'alegra_token' => '',
        'auto_transmit_enabled' => '0',
        'auto_transmit_payment_methods' => [],
        'auto_transmit_medical_only' => '0',
        'auto_detect_medical_services' => '1',
        'notify_auto_transmit' => '1',
        'medical_keywords' => '',
        'auto_transmit_delay' => '0',
        'card_payment_methods' => [],
        'cash_payment_methods' => []
    ];
    $perfex_payment_modes = [];
    $payment_config = [
        'card_payment_methods' => [],
        'cash_payment_methods' => []
    ];
}

?>

<div class="horizontal-scrollable-tabs">
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
                
                <?php echo render_input('settings[alegra_cr_email]', 'Email de Alegra', 
                    $settings['alegra_email'], 
                    'email', ['required' => true]); ?>

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
                                                       name="settings[card_payment_methods][]" 
                                                       value="<?php echo $mode['id']; ?>"
                                                       class="card-method-checkbox"
                                                       <?php echo (isset($payment_config['card_payment_methods']) && in_array($mode['id'], $payment_config['card_payment_methods'])) ? 'checked' : ''; ?>>
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
                                                       name="settings[cash_payment_methods][]" 
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
                               name="auto_transmit_enabled" value="1"
                               <?php echo ($settings['auto_transmit_enabled'] == '1') ? 'checked' : ''; ?>>
                        <label for="auto_transmit_enabled">
                            <strong>Activar auto-transmisión de facturas</strong>
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        Si está marcado, solo se auto-transmitirán facturas que contengan servicios médicos
                    </small>
                </div>

                <div class="form-group">
                    <div class="checkbox checkbox-info">
                        <input type="checkbox" id="auto_detect_medical_services" 
                               name="auto_detect_medical_services" value="1"
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
                               name="notify_auto_transmit" value="1"
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
                    <textarea class="form-control" name="medical_keywords" id="medical_keywords" rows="3" 
                              placeholder="consulta, examen, chequeo, revisión, diagnóstico, cirugía..."><?php echo $settings['medical_keywords']; ?></textarea>
                    <small class="form-text text-muted">
                        Palabras clave separadas por comas para detectar servicios médicos
                    </small>
                </div>

                <div class="form-group">
                    <label for="auto_transmit_delay">Retraso para auto-transmisión (minutos)</label>
                    <input type="number" class="form-control" name="auto_transmit_delay" id="auto_transmit_delay" 
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
</div>

<!-- Scripts acá -->
<?php init_tail(); ?>


<!-- JavaScript específico -->
<script>
var admin_url = '<?php echo admin_url(); ?>';

$(document).ready(function() {
    console.log('Alegra CR: Configuración cargada');
    
    // Mostrar/ocultar configuraciones de auto-transmisión
    $('#auto_transmit_enabled').change(function() {
        if ($(this).is(':checked')) {
            $('#auto-transmit-config, #additional-auto-config').slideDown();
        } else {
            $('#auto-transmit-config, #additional-auto-config').slideUp();
            $('#auto-transmit-config input[type="checkbox"]').prop('checked', false);
        }
    });

    // Prevenir que un método esté en ambas categorías
    $('.card-method-checkbox').change(function() {
        if ($(this).is(':checked')) {
            var methodId = $(this).val();
            $('.cash-method-checkbox[value="' + methodId + '"]').prop('checked', false);
        }
    });

    $('.cash-method-checkbox').change(function() {
        if ($(this).is(':checked')) {
            var methodId = $(this).val();
            $('.card-method-checkbox[value="' + methodId + '"]').prop('checked', false);
        }
    });

    // Test de conexión
    $('#test-alegra-connection').click(function() {
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
});
</script>