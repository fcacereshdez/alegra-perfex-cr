<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <!-- Configuración de Impuestos -->
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo _l('alegra_cr_tax_configuration'); ?>
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <div class="alert alert-info">
                            <strong>Información:</strong> Configure los impuestos que se aplicarán automáticamente según las tasas de impuesto de Costa Rica.
                        </div>
                        
                        <?php echo form_open(admin_url('alegra_facturacion_cr/save_tax_settings')); ?>
                        
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Nombre del Impuesto</th>
                                    <th>Código</th>
                                    <th>ID en Alegra</th>
                                    <th>Tasa (%)</th>
                                    <th>Activo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tax-configuration-table">
                                <?php if (isset($tax_config) && !empty($tax_config)): ?>
                                    <?php foreach ($tax_config as $index => $tax): ?>
                                        <tr data-index="<?php echo $index; ?>">
                                            <td>
                                                <input type="text" class="form-control" 
                                                       name="tax_config[<?php echo $index; ?>][tax_name]" 
                                                       value="<?php echo $tax['tax_name']; ?>" required>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" 
                                                       name="tax_config[<?php echo $index; ?>][tax_code]" 
                                                       value="<?php echo $tax['tax_code']; ?>" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" 
                                                       name="tax_config[<?php echo $index; ?>][alegra_tax_id]" 
                                                       value="<?php echo $tax['alegra_tax_id']; ?>" 
                                                       min="1">
                                                <small class="text-muted">Dejar vacío para exento</small>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" 
                                                       name="tax_config[<?php echo $index; ?>][tax_rate]" 
                                                       value="<?php echo $tax['tax_rate']; ?>" 
                                                       min="0" max="100" step="0.01" required>
                                            </td>
                                            <td>
                                                <input type="checkbox" 
                                                       name="tax_config[<?php echo $index; ?>][is_active]" 
                                                       value="1" 
                                                       <?php echo $tax['is_active'] ? 'checked' : ''; ?>>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm remove-tax-row">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr data-index="0">
                                        <td>
                                            <input type="text" class="form-control" 
                                                   name="tax_config[0][tax_name]" 
                                                   value="IVA Estándar" required>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" 
                                                   name="tax_config[0][tax_code]" 
                                                   value="IVA_13" required>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control" 
                                                   name="tax_config[0][alegra_tax_id]" 
                                                   value="1" min="1">
                                        </td>
                                        <td>
                                            <input type="number" class="form-control" 
                                                   name="tax_config[0][tax_rate]" 
                                                   value="13" min="0" max="100" step="0.01" required>
                                        </td>
                                        <td>
                                            <input type="checkbox" 
                                                   name="tax_config[0][is_active]" 
                                                   value="1" checked>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm remove-tax-row">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <button type="button" id="add-tax-row" class="btn btn-info">
                                    <i class="fa fa-plus"></i> Agregar Impuesto
                                </button>
                            </div>
                            <div class="col-md-6 text-right">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-save"></i> Guardar Configuración
                                </button>
                            </div>
                        </div>
                        
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
            
            <!-- Panel de Ayuda -->
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <i class="fa fa-info-circle"></i> Guía de Impuestos Costa Rica
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <div class="alert alert-success">
                            <strong>IVA Estándar (13%):</strong><br>
                            Aplica a la mayoría de productos y servicios.
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>IVA Reducido (4%):</strong><br>
                            Aplica a medicamentos, alimentos básicos y productos específicos según CABYS.
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Exento (0%):</strong><br>
                            Productos y servicios exentos de impuesto según ley.
                        </div>
                        
                        <h5>Configuración de IDs en Alegra:</h5>
                        <ul class="list-unstyled">
                            <li><strong>1:</strong> IVA 13% (Estándar)</li>
                            <li><strong>2:</strong> IVA 4% (Reducido)</li>
                            <li><strong>Vacío:</strong> Exento</li>
                        </ul>
                        
                        <div class="alert alert-danger">
                            <strong>Importante:</strong> Los IDs deben coincidir con los configurados en su cuenta de Alegra.
                        </div>
                    </div>
                </div>
                
                <!-- Test de Conexión con Alegra -->
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <i class="fa fa-plug"></i> Test de Conexión
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <button type="button" id="test-alegra-connection" class="btn btn-success btn-block">
                            <i class="fa fa-check"></i> Verificar Conexión con Alegra
                        </button>
                        
                        <div id="connection-result" class="mt-2"></div>
                        
                        <hr>
                        
                        <button type="button" id="sync-alegra-taxes" class="btn btn-info btn-block">
                            <i class="fa fa-refresh"></i> Sincronizar Impuestos desde Alegra
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sección de Devoluciones de IVA -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <i class="fa fa-money"></i> Devoluciones de IVA
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <div class="alert alert-info">
                            <strong>Información:</strong> Los clientes exportadores, exonerados y diplomáticos pueden ser elegibles para devolución de IVA.
                        </div>
                        
                        <?php if (isset($iva_returns) && !empty($iva_returns)): ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Factura</th>
                                        <th>Tipo Cliente</th>
                                        <th>Monto Devolución</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($iva_returns as $return): ?>
                                        <tr>
                                            <td><?php echo $return['client_name']; ?></td>
                                            <td>
                                                <a href="<?php echo admin_url('invoices/list_invoices/' . $return['perfex_invoice_id']); ?>">
                                                    #<?php echo format_invoice_number($return['perfex_invoice_id']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="label label-info">
                                                    <?php echo ucfirst($return['client_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo app_format_money($return['return_amount'], 'CRC'); ?></td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                switch ($return['status']) {
                                                    case 'completed':
                                                        $status_class = 'success';
                                                        break;
                                                    case 'pending':
                                                        $status_class = 'warning';
                                                        break;
                                                    case 'rejected':
                                                        $status_class = 'danger';
                                                        break;
                                                    default:
                                                        $status_class = 'default';
                                                }
                                                ?>
                                                <span class="label label-<?php echo $status_class; ?>">
                                                    <?php echo ucfirst($return['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo _d($return['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-default dropdown-toggle btn-sm" data-toggle="dropdown">
                                                        Opciones <span class="caret"></span>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-right">
                                                        <?php if ($return['status'] == 'pending'): ?>
                                                            <li>
                                                                <a href="<?php echo admin_url('alegra_facturacion_cr/approve_iva_return/' . $return['id']); ?>">
                                                                    Aprobar
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="<?php echo admin_url('alegra_facturacion_cr/reject_iva_return/' . $return['id']); ?>">
                                                                    Rechazar
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a href="<?php echo admin_url('alegra_facturacion_cr/view_iva_return/' . $return['id']); ?>">
                                                                Ver Detalles
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                No hay devoluciones de IVA registradas.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
$(document).ready(function() {
    var taxRowIndex = <?php echo isset($tax_config) ? count($tax_config) : 1; ?>;
    
    // Agregar nueva fila de impuesto
    $('#add-tax-row').click(function() {
        var newRow = `
            <tr data-index="${taxRowIndex}">
                <td>
                    <input type="text" class="form-control" 
                           name="tax_config[${taxRowIndex}][tax_name]" 
                           value="" required>
                </td>
                <td>
                    <input type="text" class="form-control" 
                           name="tax_config[${taxRowIndex}][tax_code]" 
                           value="" required>
                </td>
                <td>
                    <input type="number" class="form-control" 
                           name="tax_config[${taxRowIndex}][alegra_tax_id]" 
                           value="" min="1">
                    <small class="text-muted">Dejar vacío para exento</small>
                </td>
                <td>
                    <input type="number" class="form-control" 
                           name="tax_config[${taxRowIndex}][tax_rate]" 
                           value="0" min="0" max="100" step="0.01" required>
                </td>
                <td>
                    <input type="checkbox" 
                           name="tax_config[${taxRowIndex}][is_active]" 
                           value="1" checked>
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm remove-tax-row">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        
        $('#tax-configuration-table').append(newRow);
        taxRowIndex++;
    });
    
    // Eliminar fila de impuesto
    $(document).on('click', '.remove-tax-row', function() {
        if ($('#tax-configuration-table tr').length > 1) {
            $(this).closest('tr').remove();
        } else {
            alert('Debe mantener al menos una configuración de impuesto.');
        }
    });
    
    // Test de conexión con Alegra
    $('#test-alegra-connection').click(function() {
        var btn = $(this);
        var originalText = btn.html();
        
        btn.html('<i class="fa fa-spinner fa-spin"></i> Verificando...');
        btn.prop('disabled', true);
        
        $.post('<?php echo admin_url("alegra_facturacion_cr/test_connection"); ?>', function(data) {
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
    
    // Sincronizar impuestos desde Alegra
    $('#sync-alegra-taxes').click(function() {
        var btn = $(this);
        var originalText = btn.html();
        
        btn.html('<i class="fa fa-spinner fa-spin"></i> Sincronizando...');
        btn.prop('disabled', true);
        
        $.post('<?php echo admin_url("alegra_facturacion_cr/sync_taxes_from_alegra"); ?>', function(data) {
            if (data.success) {
                alert('Impuestos sincronizados exitosamente. La página se recargará.');
                location.reload();
            } else {
                alert('Error al sincronizar impuestos: ' + data.message);
            }
        }, 'json').fail(function() {
            alert('Error al sincronizar impuestos');
        }).always(function() {
            btn.html(originalText);
            btn.prop('disabled', false);
        });
    });
});
</script>