<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo $title; ?>
                        </h4>
                        <hr class="hr-panel-heading" />
                        <table class="table dt-table">
                            <thead>
                                <th><?php echo _l('invoice_dt_table_heading_number'); ?></th>
                                <th><?php echo _l('invoice_dt_table_heading_amount'); ?></th>
                                <th><?php echo _l('invoice_dt_table_heading_date'); ?></th>
                                <th><?php echo _l('invoice_dt_table_heading_client'); ?></th>
                                <th><?php echo _l('invoice_dt_table_heading_status'); ?></th>
                                <th><?php echo _l('alegra_cr_status'); ?></th>
                                <th><?php echo _l('options'); ?></th>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice) { ?>
                                    <tr>
                                        <td><?php echo format_invoice_number($invoice['id']); ?></td>
                                        <td><?php echo app_format_money($invoice['amount'], 'USD'); ?></td>
                                        <td><?php echo _d($invoice['date']); ?></td>
                                        <td><a href="<?php echo admin_url('clients/client/' . $invoice['customerid']); ?>"><?php echo $invoice['customer'] ?? 'No procesado'; ?></a></td>
                                        <td><?php echo format_invoice_status($invoice['status']); ?></td>
                                        <td>
                                            <?php
                                            $status_badge = '';
                                            switch ($invoice['alegra_status']) {
                                                case 'completed':
                                                    $status_badge = '<span class="label label-success">';
                                                    $status_text = _l('alegra_cr_sync_completed');
                                                    if ($invoice['alegra_id']) {
                                                        $status_text .= ' #' . $invoice['alegra_id'];
                                                    }
                                                    $status_badge .= $status_text . '</span>';
                                                    break;
                                                case 'pending':
                                                    $status_badge = '<span class="label label-warning">' . _l('alegra_cr_sync_pending') . '</span>';
                                                    break;
                                                case 'error':
                                                    $status_badge = '<span class="label label-danger">' . _l('alegra_cr_sync_error') . '</span>';
                                                    break;
                                                default:
                                                    $status_badge = '<span class="label label-default">' . _l('alegra_cr_sync_not_synced') . '</span>';
                                                    break;
                                            }
                                            echo $status_badge;
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <?php echo _l('alegra_cr_transmit'); ?> <span class="caret"></span>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-right">
                                                    <?php if ($invoice['can_sync'] || $invoice['alegra_status'] == 'error') { ?>
                                                        <li>
                                                            <a href="<?php echo admin_url('alegra_facturacion_cr/create_electronic_invoice/' . $invoice['id'] . '/normal'); ?>">
                                                                <?php echo _l('alegra_cr_transmit_normal'); ?>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a href="<?php echo admin_url('alegra_facturacion_cr/create_electronic_invoice/' . $invoice['id'] . '/contingency'); ?>">
                                                                <?php echo _l('alegra_cr_transmit_contingency'); ?>
                                                            </a>
                                                        </li>
                                                    <?php } else { ?>
                                                        <li class="disabled">
                                                            <a href="javascript:void(0);" style="color: #999; cursor: not-allowed;">
                                                                <?php echo _l('alegra_cr_already_synced'); ?>
                                                            </a>
                                                        </li>
                                                        <?php if ($invoice['alegra_id']) { ?>
                                                            <li>
                                                                <a href="https://app.alegra.com/invoices/view/<?php echo $invoice['alegra_id']; ?>" target="_blank" rel="noopener noreferrer">
                                                                    <?php echo _l('alegra_cr_view_in_alegra'); ?>
                                                                </a>
                                                            </li>
                                                        <?php } ?>
                                                    <?php } ?>
                                                </ul>
                                            </div>
                                             <button type="button" class="btn btn-info dropdown-toggle btn-sm" data-toggle="dropdown">
        <i class="fa fa-print"></i> Imprimir <span class="caret"></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-right">
        <li>
            <a href="javascript:void(0)" onclick="printInvoice(<?php echo $invoice['id']; ?>)">
                <i class="fa fa-file-text"></i> Factura Completa
            </a>
        </li>
        <li>
            <a href="javascript:void(0)" onclick="printTicket(<?php echo $invoice['id']; ?>)">
                <i class="fa fa-receipt"></i> Ticket Simple
            </a>
        </li>
        <li class="divider"></li>
        <li>
            <a href="javascript:void(0)" onclick="autoPrint(<?php echo $invoice['id']; ?>, 'invoice')">
                <i class="fa fa-magic"></i> Auto-Imprimir
            </a>
        </li>
    </ul>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
$(function(){
    $('.dt-table').DataTable({
        "order": [[ 5, "asc" ]], // Ordenar por estado de Alegra por defecto
        "columnDefs": [
            { "orderable": false, "targets": [6] } // Columna de opciones no ordenable
        ],
        "language": {
            "url": "<?php echo base_url('assets/plugins/datatables/'); ?>Spanish.json"
        }
    });
});
</script>

<script>
// Cargar el print manager
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si hay factura para auto-imprimir
    const autoPrintInvoice = '<?php echo $this->session->userdata('auto_print_invoice'); ?>';
    
    if (autoPrintInvoice) {
        <?php $this->session->unset_userdata('auto_print_invoice'); ?>
        
        setTimeout(function() {
            if (confirm('¿Imprimir la factura recién creada?')) {
                autoPrint(autoPrintInvoice);
            }
        }, 1000);
    }
});

// Funciones globales de impresión
function printInvoice(invoiceId) {
    const url = '<?php echo admin_url("factura_printer/print_invoice/"); ?>' + invoiceId;
    const printWindow = window.open(url, 'print_invoice', 'width=800,height=600,scrollbars=yes');
    
    printWindow.focus();
}

function printTicket(invoiceId) {
    const url = '<?php echo admin_url("factura_printer/print_ticket/"); ?>' + invoiceId;
    const printWindow = window.open(url, 'print_ticket', 'width=350,height=500,scrollbars=yes');
    
    printWindow.focus();
}

function autoPrint(invoiceId, type = 'invoice') {
    // Obtener configuración del usuario
    $.get('<?php echo admin_url("factura_printer/get_settings"); ?>', function(settings) {
        switch(settings.default_printer_type) {
            case 'thermal':
                printThermal(invoiceId, type);
                break;
            case 'usb':
                printUSB(invoiceId, type);
                break;
            default:
                if (type === 'ticket') {
                    printTicket(invoiceId);
                } else {
                    printInvoice(invoiceId);
                }
        }
    }, 'json');
}

function printThermal(invoiceId, type) {
    $.post('<?php echo admin_url("factura_printer/print_"); ?>' + type + '/' + invoiceId + '/thermal', 
        function(response) {
            if (response.success) {
                alert_float('success', 'Impreso en impresora térmica');
            } else {
                alert_float('danger', 'Error: ' + response.message);
            }
        }, 'json'
    ).fail(function() {
        alert_float('danger', 'Error de conexión con impresora térmica');
    });
}

function printUSB(invoiceId, type) {
    if (!('serial' in navigator)) {
        alert_float('warning', 'Su navegador no soporta impresión USB. Usando impresión web...');
        if (type === 'ticket') {
            printTicket(invoiceId);
        } else {
            printInvoice(invoiceId);
        }
        return;
    }
    
    // Implementación de impresión USB usando Web Serial API
    navigator.serial.requestPort().then(function(port) {
        return port.open({ baudRate: 9600 });
    }).then(function(port) {
        // Obtener datos de la factura
        return $.get('<?php echo admin_url("factura_printer/api_print_invoice/"); ?>' + invoiceId);
    }).then(function(data) {
        if (data.success) {
            alert_float('success', 'Impreso por USB correctamente');
        }
    }).catch(function(error) {
        alert_float('danger', 'Error de impresión USB: ' + error.message);
    });
}
</script>
