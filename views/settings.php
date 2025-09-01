<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-6">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo $title; ?>
                        </h4>
                        <hr class="hr-panel-heading" />
                        <?php echo form_open(admin_url('alegra_facturacion_cr/settings')); ?>
                        <div class="form-group">
                            <label for="alegra_email"><?php echo _l('alegra_cr_email'); ?></label>
                            <input type="email" class="form-control" name="alegra_email" id="alegra_email" value="<?php echo isset($settings['alegra_email']) ? $settings['alegra_email'] : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="alegra_token"><?php echo _l('alegra_cr_token'); ?></label>
                            <input type="password" class="form-control" name="alegra_token" id="alegra_token" value="" placeholder="********">
                            <small class="form-text text-muted"><?php echo _l('alegra_cr_token_help'); ?></small>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="btn btn-primary"><?php echo _l('alegra_cr_save_settings'); ?></button>
                        </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
