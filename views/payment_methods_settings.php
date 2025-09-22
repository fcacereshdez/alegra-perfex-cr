<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <i class="fa fa-credit-card"></i> Configuración de Métodos de Pago - Alegra CR
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <div class="alert alert-info">
                            <strong>Información:</strong> Configure qué métodos de pago de Perfex corresponden a tarjeta o efectivo para la facturación electrónica en Costa Rica.
                        </div>
                        
                        <?php echo form_open(admin_url('alegra_facturacion_cr/payment_methods_settings')); ?>
                        
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
                                                               name="card_payment_methods[]" 
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
                                                               name="cash_payment_methods[]" 
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
                            
                            <div class="text-right">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fa fa-save"></i> Guardar Configuración
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <strong>Atención:</strong> No se encontraron métodos de pago activos en el sistema. 
                                Por favor, configure al menos un método de pago en 
                                <a href="<?php echo admin_url('paymentmodes'); ?>" target="_blank">Configuración → Métodos de Pago</a>.
                            </div>
                        <?php endif; ?>
                        
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
            
            <!-- Panel de ayuda -->
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <i class="fa fa-info-circle"></i> Estado Actual
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <div id="config-status">
                            <?php if (!empty($payment_config['card_payment_methods'])): ?>
                                <h6 class="text-danger"><i class="fa fa-credit-card"></i> Métodos TARJETA:</h6>
                                <ul class="list-unstyled">
                                    <?php foreach ($perfex_payment_modes as $mode): ?>
                                        <?php if (in_array($mode['id'], $payment_config['card_payment_methods'])): ?>
                                            <li><span class="label label-danger"><?php echo $mode['name']; ?></span></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <?php if (!empty($payment_config['cash_payment_methods'])): ?>
                                <h6 class="text-success"><i class="fa fa-money"></i> Métodos EFECTIVO:</h6>
                                <ul class="list-unstyled">
                                    <?php foreach ($perfex_payment_modes as $mode): ?>
                                        <?php if (in_array($mode['id'], $payment_config['cash_payment_methods'])): ?>
                                            <li><span class="label label-success"><?php echo $mode['name']; ?></span></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <?php if (empty($payment_config['card_payment_methods']) && empty($payment_config['cash_payment_methods'])): ?>
                                <div class="alert alert-warning">
                                    <i class="fa fa-exclamation-triangle"></i> No hay métodos configurados.<br>
                                    Todos los pagos se tratarán como <strong>EFECTIVO</strong> por defecto.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <i class="fa fa-question-circle"></i> Guía
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <div class="alert alert-success">
                            <strong>Tarjeta + Médico = 4% IVA</strong><br>
                            Servicios médicos pagados con tarjeta aplican IVA reducido del 4%.
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>Efectivo + Médico = 13% IVA</strong><br>
                            Servicios médicos pagados en efectivo aplican IVA estándar del 13%.
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Otros servicios = 13% IVA</strong><br>
                            Servicios no médicos siempre aplican 13% sin importar el método de pago.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>


<script>
$(document).ready(function() {
    console.log('Payment methods config loaded');
    
    // Prevenir que un método esté en ambas categorías
    $('.card-method-checkbox').change(function() {
        if ($(this).is(':checked')) {
            var methodId = $(this).val();
            $('.cash-method-checkbox[value="' + methodId + '"]').prop('checked', false);
            console.log('Selected card method:', methodId);
        }
        updateStatus();
    });
    
    $('.cash-method-checkbox').change(function() {
        if ($(this).is(':checked')) {
            var methodId = $(this).val();
            $('.card-method-checkbox[value="' + methodId + '"]').prop('checked', false);
            console.log('Selected cash method:', methodId);
        }
        updateStatus();
    });
    
    // Validación antes de enviar
    $('form').submit(function(e) {
        var cardMethods = $('.card-method-checkbox:checked').length;
        var cashMethods = $('.cash-method-checkbox:checked').length;
        
        console.log('Form submit - Card methods:', cardMethods, 'Cash methods:', cashMethods);
        
        if (cardMethods === 0 && cashMethods === 0) {
            alert('Debe configurar al menos un método de pago como tarjeta o efectivo.');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
    
    function updateStatus() {
        // Actualizar visualmente el estado actual
        // Esto es opcional, solo para feedback visual
    }
});
</script>