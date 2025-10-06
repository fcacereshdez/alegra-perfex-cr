<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket <?php echo format_invoice_number($invoice->id); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Courier New', monospace; 
            font-size: 11px; 
            line-height: 1.3;
            padding: 10px;
            max-width: 350px;
            margin: 0 auto;
        }
        .ticket-container {
            background: white;
            padding: 10px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px dashed #333;
        }
        .logo {
            max-width: <?php echo min(($settings['logo_width'] ?? 120), 200); ?>px;
            height: auto;
            margin-bottom: 8px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .company-details {
            font-size: 10px;
            color: #666;
        }
        .section {
            margin: 10px 0;
            padding: 8px 0;
        }
        .divider {
            border-top: 1px dashed #999;
            margin: 10px 0;
        }
        .divider-double {
            border-top: 2px solid #333;
            margin: 10px 0;
        }
        .invoice-info {
            margin-bottom: 10px;
        }
        .invoice-info div {
            margin-bottom: 3px;
        }
        .items {
            margin: 10px 0;
        }
        .item {
            margin-bottom: 8px;
        }
        .item-name {
            font-weight: bold;
        }
        .item-details {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
        }
        .totals {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #333;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }
        .total-row.grand-total {
            font-size: 13px;
            font-weight: bold;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 2px solid #333;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 2px dashed #333;
            font-size: 10px;
        }
        .footer-message {
            margin: 10px 0;
            font-style: italic;
        }
        .footer-conditions {
            font-size: 9px;
            text-align: left;
            margin: 10px 0;
            line-height: 1.4;
        }
        .footer-legal {
            font-size: 8px;
            color: #666;
            margin-top: 10px;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            @page { 
                size: 80mm auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="ticket-container">
        <!-- Header -->
        <div class="header">
            <?php if ($settings['print_logo'] && !empty($settings['company_logo_path'])): ?>
                <img src="<?php echo base_url($settings['company_logo_path']); ?>" alt="Logo" class="logo">
            <?php endif; ?>
            <div class="company-name"><?php echo get_option('companyname'); ?></div>
            <div class="company-details">
                <?php if (get_option('company_phonenumber')): ?>
                    Tel: <?php echo get_option('company_phonenumber'); ?><br>
                <?php endif; ?>
                <?php if (get_option('company_email')): ?>
                    <?php echo get_option('company_email'); ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Invoice Info -->
        <div class="invoice-info">
            <div class="bold">TICKET DE VENTA</div>
            <div>No: <?php echo format_invoice_number($invoice->id); ?></div>
            <div>Fecha: <?php echo _d($invoice->date); ?></div>
            <div>Hora: <?php echo date('H:i:s'); ?></div>
            <?php if ($invoice->client): ?>
                <div class="divider"></div>
                <div>Cliente: <?php echo $invoice->client->company; ?></div>
            <?php endif; ?>
        </div>

        <div class="divider-double"></div>

        <!-- Items -->
        <div class="items">
            <?php foreach ($invoice->items as $item): ?>
                <div class="item">
                    <div class="item-name"><?php echo $item['description']; ?></div>
                    <div class="item-details">
                        <span><?php echo $item['qty']; ?> x <?php echo app_format_money($item['rate'], $invoice->currency_name); ?></span>
                        <span class="bold"><?php echo app_format_money($item['qty'] * $item['rate'], $invoice->currency_name); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Totals -->
        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span><?php echo app_format_money($invoice->subtotal, $invoice->currency_name); ?></span>
            </div>
            
            <?php if ($invoice->discount_total > 0): ?>
                <div class="total-row">
                    <span>Descuento:</span>
                    <span>-<?php echo app_format_money($invoice->discount_total, $invoice->currency_name); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($invoice->total_tax > 0): ?>
                <div class="total-row">
                    <span>IVA:</span>
                    <span><?php echo app_format_money($invoice->total_tax, $invoice->currency_name); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span><?php echo app_format_money($invoice->total, $invoice->currency_name); ?></span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <?php if (!empty($settings['print_footer_message'])): ?>
                <div class="footer-message">
                    <?php echo htmlspecialchars($settings['print_footer_message']); ?>
                </div>
            <?php endif; ?>

            <?php if ($settings['show_footer_conditions_ticket'] && !empty($settings['footer_conditions'])): ?>
                <div class="divider"></div>
                <div class="footer-conditions">
                    <strong>Condiciones:</strong><br>
                    <?php 
                    // Mostrar solo las primeras 150 caracteres en ticket
                    $conditions = $settings['footer_conditions'];
                    if (strlen($conditions) > 150) {
                        echo htmlspecialchars(substr($conditions, 0, 147) . '...');
                    } else {
                        echo htmlspecialchars($conditions);
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($settings['footer_legal_text'])): ?>
                <div class="divider"></div>
                <div class="footer-legal">
                    <?php echo nl2br(htmlspecialchars($settings['footer_legal_text'])); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-imprimir al cargar
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>