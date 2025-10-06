<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura <?php echo format_invoice_number($invoice->id); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            font-size: 12px; 
            line-height: 1.4;
            padding: 20px;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #333;
        }
        .company-info {
            flex: 1;
        }
        .logo {
            max-width: <?php echo $settings['logo_width'] ?? '120'; ?>px;
            height: auto;
            margin-bottom: 10px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .company-details {
            font-size: 11px;
            color: #666;
        }
        .invoice-info {
            text-align: right;
            flex: 1;
        }
        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        .invoice-number {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .invoice-date {
            font-size: 11px;
            color: #666;
        }
        .client-section {
            background: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .client-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .client-details {
            font-size: 12px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background: #333;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .items-table tr:hover {
            background: #f9f9f9;
        }
        .items-table .text-right {
            text-align: right;
        }
        .totals-section {
            margin-left: auto;
            width: 300px;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .totals-table .total-row {
            font-weight: bold;
            font-size: 14px;
            background: #f5f5f5;
        }
        .totals-table .total-row td {
            padding: 12px;
            border-top: 2px solid #333;
        }
        .alegra-section {
            margin-top: 20px;
            padding: 15px;
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            border-radius: 5px;
        }
        .alegra-title {
            font-weight: bold;
            color: #1976D2;
            margin-bottom: 8px;
        }
        .footer-conditions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
            line-height: 1.6;
        }
        .footer-legal {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #999;
        }
        .footer-message {
            text-align: center;
            margin-top: 30px;
            font-style: italic;
            color: #666;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            @page { margin: 1cm; }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <?php if ($settings['print_logo'] && !empty($settings['company_logo_path'])): ?>
                    <img src="<?php echo base_url($settings['company_logo_path']); ?>" alt="Logo" class="logo">
                <?php endif; ?>
                <div class="company-name"><?php echo get_option('companyname'); ?></div>
                <div class="company-details">
                    <?php if (get_option('company_address')): ?>
                        <?php echo nl2br(get_option('company_address')); ?><br>
                    <?php endif; ?>
                    <?php if (get_option('company_phonenumber')): ?>
                        Tel: <?php echo get_option('company_phonenumber'); ?><br>
                    <?php endif; ?>
                    <?php if (get_option('company_email')): ?>
                        Email: <?php echo get_option('company_email'); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="invoice-info">
                <div class="invoice-title">FACTURA</div>
                <div class="invoice-number"><?php echo format_invoice_number($invoice->id); ?></div>
                <div class="invoice-date">Fecha: <?php echo _d($invoice->date); ?></div>
                <?php if ($invoice->duedate): ?>
                    <div class="invoice-date">Vencimiento: <?php echo _d($invoice->duedate); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Client Information -->
        <div class="client-section">
            <div class="client-title">FACTURAR A:</div>
            <div class="client-details">
                <strong><?php echo $invoice->client->company; ?></strong><br>
                <?php if ($invoice->client->vat): ?>
                    ID Fiscal: <?php echo $invoice->client->vat; ?><br>
                <?php endif; ?>
                <?php if ($invoice->client->address): ?>
                    <?php echo nl2br($invoice->client->address); ?><br>
                <?php endif; ?>
                <?php if ($invoice->client->phonenumber): ?>
                    Tel: <?php echo $invoice->client->phonenumber; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Precio Unit.</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoice->items as $item): ?>
                    <tr>
                        <td>
                            <strong><?php echo $item['description']; ?></strong>
                            <?php if (!empty($item['long_description'])): ?>
                                <br><small style="color: #666;"><?php echo nl2br($item['long_description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?php echo $item['qty']; ?></td>
                        <td class="text-right"><?php echo app_format_money($item['rate'], $invoice->currency_name); ?></td>
                        <td class="text-right"><?php echo app_format_money($item['qty'] * $item['rate'], $invoice->currency_name); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td>Subtotal:</td>
                    <td class="text-right"><?php echo app_format_money($invoice->subtotal, $invoice->currency_name); ?></td>
                </tr>
                <?php if ($invoice->discount_total > 0): ?>
                    <tr>
                        <td>Descuento:</td>
                        <td class="text-right">-<?php echo app_format_money($invoice->discount_total, $invoice->currency_name); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($invoice->total_tax > 0): ?>
                    <tr>
                        <td>IVA:</td>
                        <td class="text-right"><?php echo app_format_money($invoice->total_tax, $invoice->currency_name); ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td>TOTAL:</td>
                    <td class="text-right"><?php echo app_format_money($invoice->total, $invoice->currency_name); ?></td>
                </tr>
            </table>
        </div>

        <!-- Alegra Information -->
        <?php if ($alegra_map && !empty($alegra_map['alegra_invoice_id'])): ?>
            <div class="alegra-section">
                <div class="alegra-title">Información de Factura Electrónica</div>
                <div>
                    <strong>ID Alegra:</strong> <?php echo $alegra_map['alegra_invoice_id']; ?><br>
                    <?php if (!empty($alegra_map['alegra_invoice_number'])): ?>
                        <strong>Número:</strong> <?php echo $alegra_map['alegra_invoice_number']; ?><br>
                    <?php endif; ?>
                    <strong>Estado:</strong> 
                    <span style="color: <?php echo $alegra_map['status'] == 'completed' ? '#4CAF50' : '#FF9800'; ?>;">
                        <?php echo ucfirst($alegra_map['status']); ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer Conditions -->
        <?php if ($settings['show_footer_conditions'] && !empty($settings['footer_conditions'])): ?>
            <div class="footer-conditions">
                <strong>Condiciones de Venta:</strong><br>
                <?php echo nl2br(htmlspecialchars($settings['footer_conditions'])); ?>
            </div>
        <?php endif; ?>

        <!-- Footer Message -->
        <?php if (!empty($settings['print_footer_message'])): ?>
            <div class="footer-message">
                <?php echo htmlspecialchars($settings['print_footer_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Legal Text -->
        <?php if (!empty($settings['footer_legal_text'])): ?>
            <div class="footer-legal">
                <?php echo nl2br(htmlspecialchars($settings['footer_legal_text'])); ?>
            </div>
        <?php endif; ?>
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