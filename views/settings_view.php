<?php defined('BASEPATH') or exit('No direct script access allowed');

// Obtener configuraciones actuales
$settings = [
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

// Obtener métodos de pago
$perfex_payment_modes = $this->db->get_where('payment_modes', ['active' => 1])->result_array();

// Obtener configuración de métodos de pago del módulo
$payment_config = ['card_payment_methods' => [], 'cash_payment_methods' => []];
try {
    if ($this->db->table_exists(db_prefix() . 'alegra_cr_payment_methods_config')) {
        $card_methods = $this->db->get_where(db_prefix() . 'alegra_cr_payment_methods_config', ['config_type' => 'card_payment_methods'])->row();
        $cash_methods = $this->db->get_where(db_prefix() . 'alegra_cr_payment_methods_config', ['config_type' => 'cash_payment_methods'])->row();
        
        $payment_config = [
            'card_payment_methods' => $card_methods ? json_decode($card_methods->payment_method_ids, true) : [],
            'cash_payment_methods' => $cash_methods ? json_decode($cash_methods->payment_method_ids, true) : []
        ];
    }
} catch (Exception $e) {
    // Ignorar errores y usar configuración vacía
}

?>

<!-- Tabs de navegación -->
<ul class="nav nav-tabs" role="tablist">
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
</ul>

<!-- Contenido de las tabs -->
<div class="tab-content" style="margin-top: 20px;">
    
    <!-- Pestaña 1: Credenciales API -->
    <div role="tabpanel" class="tab-pane active" id="alegra_credentials">
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    <strong>Información:</strong> Configura las credenciales para conectar con la API de Alegra.
                </div>
                
                <?php echo render_input('alegra_email', 'Email de Alegra', $settings['alegra_email'], 'email', ['required' => true]); ?>

                <?php echo render_input('alegra_token', 'Token de API', '', 'password', [
                    'placeholder' => '********',
                    'data-toggle' => 'tooltip',
                    'title' => 'Dejar vacío para mantener el token actual'
                ]); ?>
                
                <div class="form-group">
                    <button type="button" id="test-alegra-connection" class="btn btn-info">
                        <i class="fa fa-plug"></i> Probar Conexión
                    </button>
                    <div id="connection-result" style="margin-top: 10px;"></div>
                </div>
                
                <!-- Estado actual -->
                <div class="well well-sm">
                    <h5><i class="fa fa-info-circle"></i> Estado Actual</h5>
                    <p><strong>Email:</strong> 
                        <span class="label label-<?php echo !empty($settings['alegra_email']) ? 'success' : 'warning'; ?>">
                            <?php echo !empty($settings['alegra_email']) ? $settings['alegra_email'] : 'No configurado'; ?>
                        </span>
                    </p>
                    <p><strong>Token API:</strong> 
                        <span class="label label-<?php echo !empty(get_option('alegra_cr_token')) ? 'success' : 'warning'; ?>">
                            <?php echo !empty(get_option('alegra_cr_token')) ? 'Configurado' : 'No configurado'; ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pestaña 2: Métodos de Pago -->
    <div role="tabpanel" class="tab-pane" id="alegra_payment_methods">
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <strong>Información:</strong> Configure qué métodos de pago corresponden a tarjeta o efectivo según las reglas fiscales de Costa Rica.
                </div>
                
                <?php if (!empty($perfex_payment_modes)): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h5 class="panel-title">
                                        <i class="fa fa-credit-card text-danger"></i> Métodos TARJETA
                                    </h5>
                                    <small>IVA 4% para servicios médicos</small>
                                </div>
                                <div class="panel-body">
                                    <?php foreach ($perfex_payment_modes as $mode): ?>
                                        <div class="checkbox">
                                            <input type="checkbox" 
                                                   id="card_method_<?php echo $mode['id']; ?>"
                                                   name="card_payment_methods[]" 
                                                   value="<?php echo $mode['id']; ?>"
                                                   class="card-method-checkbox"
                                                   <?php echo in_array($mode['id'], $payment_config['card_payment_methods']) ? 'checked' : ''; ?>>
                                            <label for="card_method_<?php echo $mode['id']; ?>">
                                                <strong><?php echo $mode['name']; ?></strong>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="panel panel-success">
                                <div class="panel-heading">
                                    <h5 class="panel-title">
                                        <i class="fa fa-money text-success"></i> Métodos EFECTIVO
                                    </h5>
                                    <small>IVA 13% para servicios médicos</small>
                                </div>
                                <div class="panel-body">
                                    <?php foreach ($perfex_payment_modes as $mode): ?>
                                        <div class="checkbox">
                                            <input type="checkbox" 
                                                   id="cash_method_<?php echo $mode['id']; ?>"
                                                   name="cash_payment_methods[]" 
                                                   value="<?php echo $mode['id']; ?>"
                                                   class="cash-method-checkbox"
                                                   <?php echo in_array($mode['id'], $payment_config['cash_payment_methods']) ? 'checked' : ''; ?>>
                                            <label for="cash_method_<?php echo $mode['id']; ?>">
                                                <strong><?php echo $mode['name']; ?></strong>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        No se encontraron métodos de pago. Configure métodos de pago primero en 
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
                <div class="form-group">
                    <div class="checkbox">
                        <input type="checkbox" id="auto_transmit_enabled" 
                               name="auto_transmit_enabled" value="1"
                               <?php echo ($settings['auto_transmit_enabled'] == '1') ? 'checked' : ''; ?>>
                        <label for="auto_transmit_enabled">
                            <strong>Activar auto-transmisión de facturas</strong>
                        </label>
                    </div>
                    <small class="help-block">
                        Las facturas se transmitirán automáticamente a Alegra según los criterios configurados.
                    </small>
                </div>

                <div id="auto-transmit-config" style="<?php echo ($settings['auto_transmit_enabled'] == '1') ? '' : 'display:none;'; ?>">
                    <div class="form-group">
                        <label>Métodos de pago que activan auto-transmisión</label>
                        <?php if (!empty($perfex_payment_modes)): ?>
                            <div class="row">
                                <?php 
                                $auto_transmit_methods = json_decode($settings['auto_transmit_payment_methods'], true) ?: [];
                                foreach ($perfex_payment_modes as $mode): ?>
                                    <div class="col-md-6">
                                        <div class="checkbox">
                                            <input type="checkbox" 
                                                   id="auto_transmit_method_<?php echo $mode['id']; ?>"
                                                   name="auto_transmit_payment_methods[]" 
                                                   value="<?php echo $mode['id']; ?>"
                                                   <?php echo in_array($mode['id'], $auto_transmit_methods) ? 'checked' : ''; ?>>
                                            <label for="auto_transmit_method_<?php echo $mode['id']; ?>">
                                                <?php echo $mode['name']; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <div class="checkbox">
                            <input type="checkbox" id="auto_transmit_medical_only" 
                                   name="auto_transmit_medical_only" value="1"
                                   <?php echo ($settings['auto_transmit_medical_only'] == '1') ? 'checked' : ''; ?>>
                            <label for="auto_transmit_medical_only">
                                Solo auto-transmitir facturas con servicios médicos
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox">
                        <input type="checkbox" id="notify_auto_transmit" 
                               name="notify_auto_transmit" value="1"
                               <?php echo ($settings['notify_auto_transmit'] == '1') ? 'checked' : ''; ?>>
                        <label for="notify_auto_transmit">
                            Agregar nota cuando se auto-transmita una factura
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pestaña 4: Configuración Avanzada -->
    <div role="tabpanel" class="tab-pane" id="alegra_advanced">
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <div class="checkbox">
                        <input type="checkbox" id="auto_detect_medical_services" 
                               name="auto_detect_medical_services" value="1"
                               <?php echo ($settings['auto_detect_medical_services'] == '1') ? 'checked' : ''; ?>>
                        <label for="auto_detect_medical_services">
                            Detección automática de servicios médicos
                        </label>
                    </div>
                    <small class="help-block">
                        Detectar automáticamente servicios médicos por palabras clave y códigos CABYS
                    </small>
                </div>

                <div class="form-group">
                    <label for="medical_keywords">Palabras clave para servicios médicos</label>
                    <textarea class="form-control" name="medical_keywords" id="medical_keywords" rows="3"><?php echo $settings['medical_keywords']; ?></textarea>
                    <small class="help-block">Palabras clave separadas por comas</small>
                </div>

                <div class="form-group">
                    <label for="auto_transmit_delay">Retraso para auto-transmisión (minutos)</label>
                    <input type="number" class="form-control" name="auto_transmit_delay" id="auto_transmit_delay" 
                           value="<?php echo $settings['auto_transmit_delay']; ?>" min="0" max="60" style="width: 120px;">
                    <small class="help-block">Tiempo de espera antes de auto-transmitir (0 = inmediato)</small>
                </div>
                
                <div class="form-group">
                    <button type="button" id="test-configuration" class="btn btn-info">
                        <i class="fa fa-flask"></i> Probar Configuración
                    </button>
                    <div id="test-results" style="margin-top: 10px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Variable global para admin_url
    var admin_url = '<?php echo admin_url(); ?>';
    
    // Mostrar/ocultar configuraciones de auto-transmisión
    $('#auto_transmit_enabled').change(function() {
        if ($(this).is(':checked')) {
            $('#auto-transmit-config').slideDown();
        } else {
            $('#auto-transmit-config').slideUp();
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
                result.html('<div class="alert alert-success"><i class="fa fa-check"></i> ' + data.message + '</div>');
            } else {
                result.html('<div class="alert alert-danger"><i class="fa fa-times"></i> Error: ' + data.message + '</div>');
            }
        }, 'json').fail(function() {
            $('#connection-result').html('<div class="alert alert-danger"><i class="fa fa-times"></i> Error al probar la conexión</div>');
        }).always(function() {
            btn.html(originalText);
            btn.prop('disabled', false);
        });
    });

    // Test de configuración
    $('#test-configuration').click(function() {
        var btn = $(this);
        var originalText = btn.html();
        
        btn.html('<i class="fa fa-spinner fa-spin"></i> Probando...');
        btn.prop('disabled', true);
        
        $.get(admin_url + 'alegra_facturacion_cr/test_auto_transmit_config', function(data) {
            if (data.success) {
                var resultHtml = '<div class="alert alert-info"><strong>Estado de la Configuración:</strong></div>';
                
                resultHtml += '<p><strong>Auto-transmisión:</strong> ';
                resultHtml += '<span class="label label-' + (data.config.enabled ? 'success' : 'default') + '">';
                resultHtml += data.config.enabled ? 'Activada' : 'Desactivada';
                resultHtml += '</span></p>';
                
                if (data.config.medical_only) {
                    resultHtml += '<p><span class="label label-info">Solo servicios médicos</span></p>';
                }
                
                if (data.method_names && data.method_names.length > 0) {
                    resultHtml += '<p><strong>Métodos configurados:</strong><br>';
                    data.method_names.forEach(function(name) {
                        resultHtml += '<span class="label label-success" style="margin-right: 5px;">' + name + '</span>';
                    });
                    resultHtml += '</p>';
                } else {
                    resultHtml += '<p><span class="text-warning">No hay métodos configurados para auto-transmisión</span></p>';
                }
                
                $('#test-results').html(resultHtml);
            } else {
                $('#test-results').html('<div class="alert alert-danger">Error en la prueba: ' + (data.error || 'Desconocido') + '</div>');
            }
        }, 'json').fail(function() {
            $('#test-results').html('<div class="alert alert-danger">Error de conexión</div>');
        }).always(function() {
            btn.html(originalText);
            btn.prop('disabled', false);
        });
    });

    // Validación del formulario
    $('#settings-form').submit(function(e) {
        var email = $('#alegra_email').val();
        if (!email) {
            alert('El email de Alegra es requerido');
            $('#alegra_email').focus();
            e.preventDefault();
            return false;
        }
        
        // Si auto-transmisión está activada, validar que hay métodos seleccionados
        if ($('#auto_transmit_enabled').is(':checked')) {
            var selectedMethods = $('input[name="auto_transmit_payment_methods[]"]:checked').length;
            if (selectedMethods === 0) {
                alert('Debe seleccionar al menos un método de pago para auto-transmisión');
                $('a[href="#alegra_auto_transmit"]').tab('show');
                e.preventDefault();
                return false;
            }
        }
        
        return true;
    });
});
</script>