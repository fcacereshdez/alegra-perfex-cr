<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo $title; ?>
                        </h4>
                        <hr class="hr-panel-heading" />
                        <?php echo form_open(admin_url('alegra_facturacion_cr/settings')); ?>
                        
                        <!-- Credenciales API -->
                        <div class="form-group">
                            <label for="alegra_email"><?php echo _l('alegra_cr_email'); ?></label>
                            <input type="email" class="form-control" name="alegra_email" id="alegra_email" 
                                   value="<?php echo isset($settings['alegra_email']) ? $settings['alegra_email'] : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="alegra_token"><?php echo _l('alegra_cr_token'); ?></label>
                            <input type="password" class="form-control" name="alegra_token" id="alegra_token" 
                                   value="" placeholder="********">
                            <small class="form-text text-muted"><?php echo _l('alegra_cr_token_help'); ?></small>
                        </div>
                        
                        <hr>
                        
                        <!-- Configuraciones Automáticas -->
                        <h5><i class="fa fa-magic"></i> Configuraciones Automáticas</h5>
                        
                        <div class="form-group">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="auto_transmit_enabled" 
                                       name="auto_transmit_enabled" value="1"
                                       <?php echo (isset($settings['auto_transmit_enabled']) && $settings['auto_transmit_enabled']) ? 'checked' : ''; ?>>
                                <label for="auto_transmit_enabled">
                                    <strong>Activar auto-transmisión de facturas</strong>
                                </label>
                            </div>
                            <small class="form-text text-muted">
                                Cuando esté activada, las facturas que cumplan las condiciones configuradas 
                                se transmitirán automáticamente a Alegra.
                            </small>
                        </div>
                        
                        <!-- Configuración de métodos de pago para auto-transmisión -->
                        <div id="auto-transmit-config" class="form-group" style="<?php echo (isset($settings['auto_transmit_enabled']) && $settings['auto_transmit_enabled']) ? '' : 'display:none;'; ?>">
                            <label>Métodos de pago que activan auto-transmisión</label>
                            <div class="panel panel-default">
                                <div class="panel-body">
                                    <?php if (!empty($perfex_payment_modes)): ?>
                                        <div class="row">
                                            <?php foreach ($perfex_payment_modes as $mode): ?>
                                                <div class="col-md-6">
                                                    <div class="checkbox checkbox-success">
                                                        <input type="checkbox" 
                                                               id="auto_transmit_method_<?php echo $mode['id']; ?>"
                                                               name="auto_transmit_payment_methods[]" 
                                                               value="<?php echo $mode['id']; ?>"
                                                               <?php echo (isset($settings['auto_transmit_payment_methods']) && is_array($settings['auto_transmit_payment_methods']) && in_array($mode['id'], $settings['auto_transmit_payment_methods'])) ? 'checked' : ''; ?>>
                                                        <label for="auto_transmit_method_<?php echo $mode['id']; ?>">
                                                            <strong><?php echo $mode['name']; ?></strong>
                                                            <?php if (!empty($mode['description'])): ?>
                                                                <br><small class="text-muted"><?php echo strip_tags($mode['description']); ?></small>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <small class="form-text text-info">
                                            <i class="fa fa-info-circle"></i> Solo las facturas con estos métodos de pago se auto-transmitirán.
                                        </small>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            No hay métodos de pago configurados. 
                                            <a href="<?php echo admin_url('paymentmodes'); ?>" target="_blank">Configure métodos de pago</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group" id="additional-auto-config" style="<?php echo (isset($settings['auto_transmit_enabled']) && $settings['auto_transmit_enabled']) ? '' : 'display:none;'; ?>">
                            <div class="checkbox checkbox-warning">
                                <input type="checkbox" id="auto_transmit_medical_only" 
                                       name="auto_transmit_medical_only" value="1"
                                       <?php echo (isset($settings['auto_transmit_medical_only']) && $settings['auto_transmit_medical_only']) ? 'checked' : ''; ?>>
                                <label for="auto_transmit_medical_only">
                                    <strong>Solo auto-transmitir facturas con servicios médicos</strong>
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
                                       <?php echo (isset($settings['auto_detect_medical_services']) && $settings['auto_detect_medical_services']) ? 'checked' : ''; ?>>
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
                                       <?php echo (isset($settings['notify_auto_transmit']) && $settings['notify_auto_transmit']) ? 'checked' : ''; ?>>
                                <label for="notify_auto_transmit">
                                    <strong>Notificar auto-transmisiones</strong>
                                </label>
                            </div>
                            <small class="form-text text-muted">
                                Agregar notas a las facturas cuando se transmitan automáticamente
                            </small>
                        </div>
                        
                        <hr>
                        
                        <!-- Configuraciones avanzadas -->
                        <h5><i class="fa fa-cog"></i> Configuraciones Avanzadas</h5>
                        
                        <div class="form-group">
                            <label for="medical_keywords">Palabras clave para servicios médicos</label>
                            <textarea class="form-control" name="medical_keywords" id="medical_keywords" rows="3" 
                                      placeholder="consulta, examen, chequeo, revisión, diagnóstico, cirugía..."><?php echo isset($settings['medical_keywords']) ? $settings['medical_keywords'] : 'consulta,examen,chequeo,revisión,diagnóstico,cirugía,operación,procedimiento,terapia,sesión,doctor,médico,especialista,evaluación'; ?></textarea>
                            <small class="form-text text-muted">
                                Palabras clave separadas por comas para detectar servicios médicos
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="auto_transmit_delay">Retraso para auto-transmisión (minutos)</label>
                            <input type="number" class="form-control" name="auto_transmit_delay" id="auto_transmit_delay" 
                                   value="<?php echo isset($settings['auto_transmit_delay']) ? $settings['auto_transmit_delay'] : '0'; ?>" 
                                   min="0" max="60">
                            <small class="form-text text-muted">
                                Tiempo de espera antes de auto-transmitir (0 = inmediato)
                            </small>
                        </div>
                        
                        <div class="text-right">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fa fa-save"></i> <?php echo _l('alegra_cr_save_settings'); ?>
                            </button>
                        </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
            
            <!-- Panel de información -->
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <i class="fa fa-info-circle"></i> Estado Actual
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <?php if (isset($settings['auto_transmit_enabled']) && $settings['auto_transmit_enabled']): ?>
                            <div class="alert alert-success">
                                <i class="fa fa-check"></i> <strong>Auto-transmisión ACTIVADA</strong><br>
                                <?php if (!empty($settings['auto_transmit_payment_methods'])): ?>
                                    <small>Métodos configurados: <?php echo count($settings['auto_transmit_payment_methods']); ?></small>
                                <?php else: ?>
                                    <small class="text-warning">⚠️ No hay métodos de pago configurados</small>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fa fa-pause"></i> <strong>Auto-transmisión DESACTIVADA</strong><br>
                                Las facturas deben transmitirse manualmente.
                            </div>
                        <?php endif; ?>
                        
                        <!-- Mostrar métodos configurados -->
                        <?php if (!empty($settings['auto_transmit_payment_methods']) && !empty($perfex_payment_modes)): ?>
                            <h6><i class="fa fa-credit-card"></i> Métodos que activan auto-transmisión:</h6>
                            <ul class="list-unstyled">
                                <?php foreach ($perfex_payment_modes as $mode): ?>
                                    <?php if (in_array($mode['id'], $settings['auto_transmit_payment_methods'])): ?>
                                        <li><span class="label label-success"><?php echo $mode['name']; ?></span></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                        <!-- Mostrar configuración adicional -->
                        <?php if (isset($settings['auto_transmit_medical_only']) && $settings['auto_transmit_medical_only']): ?>
                            <div class="alert alert-info">
                                <i class="fa fa-stethoscope"></i> <strong>Solo servicios médicos</strong><br>
                                <small>Solo se auto-transmitirán facturas con servicios médicos detectados</small>
                            </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <h6><i class="fa fa-lightbulb-o"></i> Cómo funciona:</h6>
                        <ol class="small">
                            <li>Se crea una factura en Perfex</li>
                            <li>El sistema verifica si el método de pago está en la lista configurada</li>
                            <li>Si está configurado "Solo médicos", verifica si hay servicios médicos</li>
                            <li>Si todas las condiciones se cumplen, transmite automáticamente a Alegra</li>
                            <li>Aplica los impuestos correspondientes según las reglas de Costa Rica</li>
                        </ol>
                        
                        <div class="alert alert-info">
                            <small>
                                <strong>Nota:</strong> Configure cuidadosamente los métodos de pago para 
                                controlar cuándo se activa la auto-transmisión.
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Test de funcionalidad -->
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <i class="fa fa-flask"></i> Probar Configuración
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <button type="button" id="test-auto-detection" class="btn btn-info btn-block">
                            <i class="fa fa-search"></i> Probar Detección Automática
                        </button>
                        
                        <button type="button" id="test-payment-config" class="btn btn-warning btn-block" style="margin-top: 10px;">
                            <i class="fa fa-credit-card"></i> Verificar Config. Pagos
                        </button>
                        
                        <div id="test-results" style="margin-top: 15px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
$(document).ready(function() {
    // Mostrar/ocultar configuraciones de auto-transmisión
    $('#auto_transmit_enabled').change(function() {
        if ($(this).is(':checked')) {
            $('#auto-transmit-config, #additional-auto-config').slideDown();
        } else {
            $('#auto-transmit-config, #additional-auto-config').slideUp();
            // Limpiar selecciones
            $('#auto-transmit-config input[type="checkbox"]').prop('checked', false);
        }
    });
    
    // Validación del formulario
    $('form').submit(function(e) {
        var email = $('#alegra_email').val();
        
        if (!email) {
            alert('El email de Alegra es requerido');
            e.preventDefault();
            return false;
        }
        
        // Validar que si está activada la auto-transmisión, tenga al menos un método seleccionado
        if ($('#auto_transmit_enabled').is(':checked')) {
            var selectedMethods = $('#auto-transmit-config input[type="checkbox"]:checked').length;
            if (selectedMethods === 0) {
                alert('Debe seleccionar al menos un método de pago para auto-transmisión');
                e.preventDefault();
                return false;
            }
        }
        
        return true;
    });
    
    // Probar detección automática
    $('#test-auto-detection').click(function() {
        var btn = $(this);
        var originalText = btn.html();
        
        btn.html('<i class="fa fa-spinner fa-spin"></i> Probando...');
        btn.prop('disabled', true);
        
        $.get('<?php echo admin_url("alegra_facturacion_cr/test_auto_detection"); ?>', function(data) {
            if (data.success) {
                var resultHtml = '<div class="alert alert-info"><strong>Resultados de Detección:</strong></div>';
                data.results.forEach(function(result) {
                    var badgeClass = result.detected ? 'success' : 'default';
                    resultHtml += '<div style="margin-bottom: 5px;">' +
                                 '<span class="label label-' + badgeClass + '">' + result.iva_rate + '%</span> ' +
                                 result.item +
                                 '</div>';
                });
                
                $('#test-results').html(resultHtml);
            } else {
                $('#test-results').html('<div class="alert alert-danger">Error en la prueba</div>');
            }
        }, 'json').fail(function() {
            $('#test-results').html('<div class="alert alert-danger">Error de conexión</div>');
        }).always(function() {
            btn.html(originalText);
            btn.prop('disabled', false);
        });
    });
    
    // Probar configuración de pagos
    $('#test-payment-config').click(function() {
        var btn = $(this);
        var originalText = btn.html();
        
        btn.html('<i class="fa fa-spinner fa-spin"></i> Verificando...');
        btn.prop('disabled', true);
        
        $.get('<?php echo admin_url("alegra_facturacion_cr/test_auto_transmit_config"); ?>', function(data) {
            if (data.success) {
                var resultHtml = '<div class="alert alert-info"><strong>Configuración Actual:</strong></div>';
                
                resultHtml += '<strong>Estado:</strong> ';
                resultHtml += '<span class="label label-' + (data.config.enabled ? 'success' : 'default') + '">';
                resultHtml += data.config.enabled ? 'Habilitada' : 'Deshabilitada';
                resultHtml += '</span><br><br>';
                
                if (data.config.medical_only) {
                    resultHtml += '<span class="label label-info">Solo servicios médicos</span><br><br>';
                }
                
                resultHtml += '<strong>Métodos configurados:</strong><br>';
                
                if (data.method_names && data.method_names.length > 0) {
                    data.method_names.forEach(function(name) {
                        resultHtml += '<span class="label label-success">' + name + '</span> ';
                    });
                } else {
                    resultHtml += '<span class="text-warning">Ninguno configurado</span>';
                }
                
                // Mostrar resultados de test en facturas recientes
                if (data.test_results && data.test_results.length > 0) {
                    resultHtml += '<hr><strong>Test en facturas recientes:</strong><br>';
                    resultHtml += '<table class="table table-condensed" style="font-size: 12px;">';
                    resultHtml += '<thead><tr><th>Factura</th><th>¿Se transmitiría?</th><th>Razón</th></tr></thead><tbody>';
                    
                    data.test_results.forEach(function(test) {
                        var badgeClass = test.should_transmit ? 'success' : 'default';
                        resultHtml += '<tr>';
                        resultHtml += '<td>#' + test.invoice_id + '</td>';
                        resultHtml += '<td><span class="label label-' + badgeClass + '">' + 
                                     (test.should_transmit ? 'SÍ' : 'NO') + '</span></td>';
                        resultHtml += '<td style="font-size: 10px;">' + test.reason + '</td>';
                        resultHtml += '</tr>';
                    });
                    
                    resultHtml += '</tbody></table>';
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
});
</script>