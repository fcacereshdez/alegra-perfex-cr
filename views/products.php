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
                        <div class="alert alert-info">
                            <?php echo _l('alegra_cr_products_info'); ?>
                        </div>
                        <table class="table dt-table">
                            <thead>
                                <th><?php echo _l('invoice_table_item_heading'); ?></th>
                                <th><?php echo _l('invoice_table_rate_heading'); ?></th>
                                <th><?php echo _l('alegra_cr_sync_status'); ?></th>
                                <th><?php echo _l('options'); ?></th>
                            </thead>
                            <tbody>
                                <?php foreach ($display_items as $item) { ?>
                                    <tr>
                                        <td>
                                            <?php
                                                echo $item['description'];
                                            ?>
                                        </td>
                                        <td><?php echo app_format_money($item['rate'], get_base_currency()); ?></td>
                                        <td>
                                            <?php if(!$item['is_sync']) {  ?>
                                                <span class="label label-danger"><?php echo _l('alegra_cr_not_synced'); ?></span>
                                            <?php }else{ ?>
                                                 <span class="label label-success"><?php echo _l('alegra_cr_synced'); ?></span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if(!$item['is_sync']) { ?>
                                                <a href="<?php echo admin_url('alegra_facturacion_cr/sync/' . $item['id']); ?>" class="btn btn-default btn-icon"><i class="fa fa-refresh"></i> <?php echo _l('alegra_cr_sync_now'); ?></a>
                                             <?php }else{ ?>
                                                 <span class="label label-success"><?php echo _l('alegra_cr_sync_no_requiere'); ?></span>
                                            <?php } ?>
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
    $('.dt-table').DataTable();
});
</script>
