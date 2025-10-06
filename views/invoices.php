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
                                        <td><?php echo app_format_money($invoice['amount'], get_base_currency()->name); ?></td>
                                        <td><?php echo _d($invoice['date']); ?></td>
                                        <td>
                                            <a href="<?php echo admin_url('clients/client/' . $invoice['customerid']); ?>">
                                                <?php echo $invoice['customer'] ?? 'No procesado'; ?>
                                            </a>
                                        </td>
                                        <td><?php echo format_invoice_status($invoice['status']); ?></td>
                                        <td>
                                            <?php
                                            $status_badge = '';
                                            switch ($invoice['alegra_status']) {
                                                case 'completed':
                                                    $status_badge = '<span class="label label-success">';
                                                    $status_text = 'Transmitida';
                                                    if ($invoice['alegra_id']) {
                                                        $status_text .= ' #' . $invoice['alegra_id'];
                                                    }
                                                    $status_badge .= $status_text . '</span>';
                                                    break;
                                                case 'pending':
                                                    $status_badge = '<span class="label label-warning">Pendiente</span>';
                                                    break;
                                                case 'error':
                                                    $status_badge = '<span class="label label-danger">Error</span>';
                                                    break;
                                                default:
                                                    $status_badge = '<span class="label label-default">No transmitida</span>';
                                                    break;
                                            }
                                            echo $status_badge;
                                            ?>
                                        </td>
                                        <td>
                                            <!-- Botón de Transmisión -->
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-default dropdown-toggle btn-sm" data-toggle="dropdown">
                                                    <i class="fa fa-paper-plane"></i> Transmitir <span class="caret"></span>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($invoice['can_sync'] || $invoice['alegra_status'] == 'error') { ?>
                                                        <li>
                                                            <a href="<?php echo admin_url('alegra_facturacion_cr/create_electronic_invoice/' . $invoice['id'] . '/normal'); ?>">
                                                                <i class="fa fa-check"></i> Transmisión Normal
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a href="<?php echo admin_url('alegra_facturacion_cr/create_electronic_invoice/' . $invoice['id'] . '/contingency'); ?>">
                                                                <i class="fa fa-exclamation-triangle"></i> Contingencia
                                                            </a>
                                                        </li>
                                                    <?php } else { ?>
                                                        <li class="disabled">
                                                            <a href="javascript:void(0);" style="color: #999;">
                                                                <i class="fa fa-check-circle"></i> Ya transmitida
                                                            </a>
                                                        </li>
                                                        <?php if ($invoice['alegra_id']) { ?>
                                                            <li>
                                                                <a href="https://app.alegra.com/invoices/view/<?php echo $invoice['alegra_id']; ?>" target="_blank">
                                                                    <i class="fa fa-external-link"></i> Ver en Alegra
                                                                </a>
                                                            </li>
                                                        <?php } ?>
                                                    <?php } ?>
                                                </ul>
                                            </div>
                                            
                                            <!-- Botón de Impresión -->
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-info dropdown-toggle btn-sm" data-toggle="dropdown">
                                                    <i class="fa fa-print"></i> Imprimir <span class="caret"></span>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-right">
                                                    <li>
                                                        <a href="javascript:void(0)" onclick="printInvoice(<?php echo $invoice['id']; ?>)">
                                                            <i class="fa fa-file-text-o"></i> Factura Completa
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="javascript:void(0)" onclick="printTicket(<?php echo $invoice['id']; ?>)">
                                                            <i class="fa fa-receipt"></i> Ticket Simple
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
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
        "order": [[ 2, "desc" ]],
        "columnDefs": [
            { "orderable": false, "targets": [6] }
        ],
        "language": {
            "url": "<?php echo base_url('assets/plugins/datatables/'); ?>Spanish.json"
        }
    });
});

// Funciones de impresión
function printInvoice(invoiceId) {
    const url = '<?php echo admin_url("alegra_facturacion_cr/print_invoice/"); ?>' + invoiceId;
    const printWindow = window.open(url, 'print_invoice', 'width=800,height=600,scrollbars=yes');
    printWindow.focus();
}

function printTicket(invoiceId) {
    const url = '<?php echo admin_url("alegra_facturacion_cr/print_ticket/"); ?>' + invoiceId;
    const printWindow = window.open(url, 'print_ticket', 'width=350,height=600,scrollbars=yes');
    printWindow.focus();
}
</script>