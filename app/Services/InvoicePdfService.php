<?php
/**
 * Invoice PDF Service
 * Generates PDF invoices using pure PHP (no external library required)
 * 
 * Features:
 * - Generate PDF invoice from transaction/invoice data
 * - Custom templates per merchant
 * - Multi-format output (PDF, HTML)
 * - Auto-numbering with prefix
 * - Support for line items, tax, and discounts
 * 
 * Note: Uses HTML-to-PDF approach. For production with heavy usage,
 * integrate wkhtmltopdf or dompdf via composer.
 */

require_once base_path('app/Database.php');

class InvoicePdfService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Create an invoice from a transaction
     */
    public function createFromTransaction(string $transactionId, array $options = []): array
    {
        $stmt = $this->db->prepare("SELECT t.*, m.business_name, m.email as merchant_email, m.phone as merchant_phone, m.address as merchant_address 
            FROM transactions t LEFT JOIN merchants m ON m.id = t.merchant_id WHERE t.id = :id");
        $stmt->execute(['id' => $transactionId]);
        $tx = $stmt->fetch();

        if (!$tx) {
            return ['success' => false, 'message' => 'Transaction not found'];
        }

        $invoiceNumber = $this->generateInvoiceNumber($tx['merchant_id']);
        
        $invoice = [
            'id' => generate_uuid(),
            'merchant_id' => $tx['merchant_id'],
            'transaction_id' => $transactionId,
            'invoice_number' => $invoiceNumber,
            'customer_name' => $tx['customer_name'] ?: 'Customer',
            'customer_email' => $tx['customer_email'],
            'customer_phone' => $tx['customer_wa'],
            'customer_address' => $options['customer_address'] ?? null,
            'items' => json_encode([
                [
                    'description' => $tx['link_name'] ?: "Order {$tx['order_id']}",
                    'quantity' => 1,
                    'unit_price' => (int)$tx['amount'],
                    'total' => (int)$tx['amount'],
                ]
            ]),
            'subtotal' => (int)$tx['amount'],
            'tax_rate' => (float)($options['tax_rate'] ?? 0),
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => (int)$tx['amount'],
            'currency' => $tx['currency'] ?? 'IDR',
            'status' => $tx['status'] === 'PAID' ? 'paid' : 'sent',
            'due_date' => $options['due_date'] ?? date('Y-m-d', strtotime('+7 days')),
            'paid_at' => $tx['paid_at'],
            'notes' => $options['notes'] ?? null,
            'footer' => $options['footer'] ?? null,
            'template' => $options['template'] ?? 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Calculate tax
        if ($invoice['tax_rate'] > 0) {
            $invoice['tax_amount'] = (int)round($invoice['subtotal'] * $invoice['tax_rate'] / 100);
            $invoice['total_amount'] = $invoice['subtotal'] + $invoice['tax_amount'] - $invoice['discount_amount'];
        }

        // Save to database
        $stmt = $this->db->prepare(
            "INSERT INTO `invoices` (`id`, `merchant_id`, `transaction_id`, `invoice_number`, `customer_name`, `customer_email`, `customer_phone`, `customer_address`, `items`, `subtotal`, `tax_rate`, `tax_amount`, `discount_amount`, `total_amount`, `currency`, `status`, `due_date`, `paid_at`, `notes`, `footer`, `template`, `created_at`, `updated_at`)
             VALUES (:id, :merchant_id, :transaction_id, :invoice_number, :customer_name, :customer_email, :customer_phone, :customer_address, :items, :subtotal, :tax_rate, :tax_amount, :discount_amount, :total_amount, :currency, :status, :due_date, :paid_at, :notes, :footer, :template, :created_at, :updated_at)"
        );
        $stmt->execute([
            'id' => $invoice['id'],
            'merchant_id' => $invoice['merchant_id'],
            'transaction_id' => $invoice['transaction_id'],
            'invoice_number' => $invoice['invoice_number'],
            'customer_name' => $invoice['customer_name'],
            'customer_email' => $invoice['customer_email'],
            'customer_phone' => $invoice['customer_phone'],
            'customer_address' => $invoice['customer_address'],
            'items' => $invoice['items'],
            'subtotal' => $invoice['subtotal'],
            'tax_rate' => $invoice['tax_rate'],
            'tax_amount' => $invoice['tax_amount'],
            'discount_amount' => $invoice['discount_amount'],
            'total_amount' => $invoice['total_amount'],
            'currency' => $invoice['currency'],
            'status' => $invoice['status'],
            'due_date' => $invoice['due_date'],
            'paid_at' => $invoice['paid_at'],
            'notes' => $invoice['notes'],
            'footer' => $invoice['footer'],
            'template' => $invoice['template'],
            'created_at' => $invoice['created_at'],
            'updated_at' => $invoice['updated_at'],
        ]);

        return ['success' => true, 'invoice' => $invoice];
    }

    /**
     * Create a custom invoice (not tied to a transaction)
     */
    public function createCustom(string $merchantId, array $data): array
    {
        $invoiceNumber = $data['invoice_number'] ?? $this->generateInvoiceNumber($merchantId);
        $items = $data['items'] ?? [];
        
        $subtotal = 0;
        foreach ($items as &$item) {
            $item['total'] = (int)($item['quantity'] ?? 1) * (int)($item['unit_price'] ?? 0);
            $subtotal += $item['total'];
        }

        $taxRate = (float)($data['tax_rate'] ?? 0);
        $taxAmount = (int)round($subtotal * $taxRate / 100);
        $discountAmount = (int)($data['discount_amount'] ?? 0);
        $totalAmount = $subtotal + $taxAmount - $discountAmount;

        $invoice = [
            'id' => generate_uuid(),
            'merchant_id' => $merchantId,
            'transaction_id' => null,
            'invoice_number' => $invoiceNumber,
            'customer_name' => $data['customer_name'] ?? 'Customer',
            'customer_email' => $data['customer_email'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_address' => $data['customer_address'] ?? null,
            'items' => json_encode($items),
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'currency' => $data['currency'] ?? 'IDR',
            'status' => 'draft',
            'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime('+7 days')),
            'paid_at' => null,
            'notes' => $data['notes'] ?? null,
            'footer' => $data['footer'] ?? null,
            'template' => $data['template'] ?? 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $stmt = $this->db->prepare(
            "INSERT INTO `invoices` (`id`, `merchant_id`, `transaction_id`, `invoice_number`, `customer_name`, `customer_email`, `customer_phone`, `customer_address`, `items`, `subtotal`, `tax_rate`, `tax_amount`, `discount_amount`, `total_amount`, `currency`, `status`, `due_date`, `paid_at`, `notes`, `footer`, `template`, `created_at`, `updated_at`)
             VALUES (:id, :merchant_id, :transaction_id, :invoice_number, :customer_name, :customer_email, :customer_phone, :customer_address, :items, :subtotal, :tax_rate, :tax_amount, :discount_amount, :total_amount, :currency, :status, :due_date, :paid_at, :notes, :footer, :template, :created_at, :updated_at)"
        );
        $stmt->execute([
            'id' => $invoice['id'],
            'merchant_id' => $invoice['merchant_id'],
            'transaction_id' => $invoice['transaction_id'],
            'invoice_number' => $invoice['invoice_number'],
            'customer_name' => $invoice['customer_name'],
            'customer_email' => $invoice['customer_email'],
            'customer_phone' => $invoice['customer_phone'],
            'customer_address' => $invoice['customer_address'],
            'items' => $invoice['items'],
            'subtotal' => $invoice['subtotal'],
            'tax_rate' => $invoice['tax_rate'],
            'tax_amount' => $invoice['tax_amount'],
            'discount_amount' => $invoice['discount_amount'],
            'total_amount' => $invoice['total_amount'],
            'currency' => $invoice['currency'],
            'status' => $invoice['status'],
            'due_date' => $invoice['due_date'],
            'paid_at' => $invoice['paid_at'],
            'notes' => $invoice['notes'],
            'footer' => $invoice['footer'],
            'template' => $invoice['template'],
            'created_at' => $invoice['created_at'],
            'updated_at' => $invoice['updated_at'],
        ]);

        return ['success' => true, 'invoice' => $invoice];
    }

    /**
     * Generate invoice as HTML (for PDF conversion or browser display)
     */
    public function generateHtml(string $invoiceId): string
    {
        $stmt = $this->db->prepare(
            "SELECT i.*, m.business_name, m.email as merchant_email, m.phone as merchant_phone, m.address as merchant_address
             FROM invoices i LEFT JOIN merchants m ON m.id = i.merchant_id WHERE i.id = :id"
        );
        $stmt->execute(['id' => $invoiceId]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            return '<h1>Invoice not found</h1>';
        }

        $items = json_decode($invoice['items'], true) ?: [];
        $appName = setting('app_name', 'PayGate Pro');

        return $this->renderTemplate($invoice, $items, $appName);
    }

    /**
     * Generate PDF binary data
     * Uses HTML rendering + browser print (simple approach)
     * For production: integrate wkhtmltopdf or dompdf
     */
    public function generatePdf(string $invoiceId): ?string
    {
        $html = $this->generateHtml($invoiceId);
        
        // Store HTML as file for PDF conversion
        $pdfDir = storage_path('invoices');
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }
        
        $htmlPath = $pdfDir . "/{$invoiceId}.html";
        file_put_contents($htmlPath, $html);
        
        // Try wkhtmltopdf if available
        $pdfPath = $pdfDir . "/{$invoiceId}.pdf";
        $wkhtmltopdf = '/usr/local/bin/wkhtmltopdf';
        
        if (file_exists($wkhtmltopdf) || is_executable('wkhtmltopdf')) {
            $cmd = escapeshellarg($wkhtmltopdf ?: 'wkhtmltopdf') . 
                   ' --quiet --page-size A4 --margin-top 10mm --margin-bottom 10mm ' .
                   escapeshellarg($htmlPath) . ' ' . escapeshellarg($pdfPath);
            exec($cmd, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($pdfPath)) {
                // Update invoice record with pdf_path
                $this->db->prepare("UPDATE invoices SET pdf_path = :path, updated_at = NOW() WHERE id = :id")
                    ->execute(['path' => $pdfPath, 'id' => $invoiceId]);
                return $pdfPath;
            }
        }
        
        // Fallback: return HTML path (can be printed to PDF from browser)
        return $htmlPath;
    }

    /**
     * Get invoice by ID
     */
    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM invoices WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $invoice = $stmt->fetch();
        if ($invoice && isset($invoice['items'])) {
            $invoice['items_parsed'] = json_decode($invoice['items'], true) ?: [];
        }
        return $invoice ?: null;
    }

    /**
     * Get invoices by merchant
     */
    public function getByMerchant(string $merchantId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("SELECT * FROM invoices WHERE merchant_id = :mid ORDER BY created_at DESC LIMIT :lim");
        $stmt->bindValue(':mid', $merchantId);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Mark invoice as sent
     */
    public function markSent(string $invoiceId): bool
    {
        $stmt = $this->db->prepare("UPDATE invoices SET status = 'sent', sent_at = NOW(), updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $invoiceId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Mark invoice as paid
     */
    public function markPaid(string $invoiceId): bool
    {
        $stmt = $this->db->prepare("UPDATE invoices SET status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $invoiceId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Generate invoice number with auto-increment per merchant
     */
    private function generateInvoiceNumber(string $merchantId): string
    {
        $prefix = setting('invoice_prefix', 'INV');
        $year = date('Y');
        $month = date('m');

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) + 1 as next_num FROM invoices WHERE merchant_id = :mid AND YEAR(created_at) = :year"
        );
        $stmt->execute(['mid' => $merchantId, 'year' => $year]);
        $nextNum = (int)$stmt->fetchColumn();

        return "{$prefix}/{$year}{$month}/" . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Render invoice HTML template
     */
    private function renderTemplate(array $invoice, array $items, string $appName): string
    {
        $currency = $invoice['currency'] ?? 'IDR';
        $formatAmount = function(int $amount) use ($currency) {
            if ($currency === 'IDR') return 'Rp ' . number_format($amount, 0, ',', '.');
            return $currency . ' ' . number_format($amount / 100, 2, '.', ',');
        };

        $statusLabel = match($invoice['status']) {
            'paid' => '<span style="background:#10b981;color:#fff;padding:4px 12px;border-radius:4px;font-weight:bold;">PAID</span>',
            'sent' => '<span style="background:#f59e0b;color:#fff;padding:4px 12px;border-radius:4px;font-weight:bold;">UNPAID</span>',
            'overdue' => '<span style="background:#ef4444;color:#fff;padding:4px 12px;border-radius:4px;font-weight:bold;">OVERDUE</span>',
            default => '<span style="background:#6b7280;color:#fff;padding:4px 12px;border-radius:4px;font-weight:bold;">DRAFT</span>',
        };

        $itemsHtml = '';
        foreach ($items as $i => $item) {
            $itemsHtml .= '<tr>
                <td style="padding:10px;border-bottom:1px solid #e5e7eb;">' . ($i + 1) . '</td>
                <td style="padding:10px;border-bottom:1px solid #e5e7eb;">' . e($item['description'] ?? '') . '</td>
                <td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:center;">' . (int)($item['quantity'] ?? 1) . '</td>
                <td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;">' . $formatAmount((int)($item['unit_price'] ?? 0)) . '</td>
                <td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;">' . $formatAmount((int)($item['total'] ?? 0)) . '</td>
            </tr>';
        }

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice ' . e($invoice['invoice_number']) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif; font-size: 14px; color: #1f2937; line-height: 1.5; }
        .container { max-width: 800px; margin: 0 auto; padding: 40px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .invoice-title { font-size: 32px; font-weight: bold; color: #111827; }
        table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 8px 10px; }
        @media print { body { padding: 0; } .container { padding: 20px; } }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <div class="invoice-title">INVOICE</div>
            <div style="color:#6b7280;margin-top:4px;">' . e($invoice['invoice_number']) . '</div>
        </div>
        <div style="text-align:right;">
            ' . $statusLabel . '
        </div>
    </div>

    <div style="display:flex;justify-content:space-between;margin-bottom:30px;">
        <div>
            <strong style="color:#6b7280;font-size:12px;text-transform:uppercase;">From</strong><br>
            <strong>' . e($invoice['business_name'] ?? $appName) . '</strong><br>
            ' . e($invoice['merchant_email'] ?? '') . '<br>
            ' . e($invoice['merchant_phone'] ?? '') . '<br>
            ' . e($invoice['merchant_address'] ?? '') . '
        </div>
        <div style="text-align:right;">
            <strong style="color:#6b7280;font-size:12px;text-transform:uppercase;">Bill To</strong><br>
            <strong>' . e($invoice['customer_name']) . '</strong><br>
            ' . e($invoice['customer_email'] ?? '') . '<br>
            ' . e($invoice['customer_phone'] ?? '') . '<br>
            ' . e($invoice['customer_address'] ?? '') . '
        </div>
    </div>

    <div style="display:flex;justify-content:space-between;margin-bottom:30px;background:#f9fafb;padding:15px;border-radius:8px;">
        <div><strong>Invoice Date:</strong> ' . format_date($invoice['created_at'], 'd M Y') . '</div>
        <div><strong>Due Date:</strong> ' . ($invoice['due_date'] ? format_date($invoice['due_date'], 'd M Y') : '-') . '</div>
        ' . ($invoice['paid_at'] ? '<div><strong>Paid:</strong> ' . format_date($invoice['paid_at'], 'd M Y H:i') . '</div>' : '') . '
    </div>

    <table style="margin-bottom:30px;">
        <thead>
            <tr style="background:#f3f4f6;">
                <th style="padding:10px;text-align:left;width:40px;">#</th>
                <th style="padding:10px;text-align:left;">Description</th>
                <th style="padding:10px;text-align:center;width:60px;">Qty</th>
                <th style="padding:10px;text-align:right;width:120px;">Price</th>
                <th style="padding:10px;text-align:right;width:120px;">Total</th>
            </tr>
        </thead>
        <tbody>' . $itemsHtml . '</tbody>
    </table>

    <div style="display:flex;justify-content:flex-end;">
        <table style="width:300px;" class="totals">
            <tr>
                <td><strong>Subtotal</strong></td>
                <td style="text-align:right;">' . $formatAmount((int)$invoice['subtotal']) . '</td>
            </tr>
            ' . ($invoice['tax_rate'] > 0 ? '<tr><td>Tax (' . $invoice['tax_rate'] . '%)</td><td style="text-align:right;">' . $formatAmount((int)$invoice['tax_amount']) . '</td></tr>' : '') . '
            ' . ($invoice['discount_amount'] > 0 ? '<tr><td>Discount</td><td style="text-align:right;color:#ef4444;">-' . $formatAmount((int)$invoice['discount_amount']) . '</td></tr>' : '') . '
            <tr style="border-top:2px solid #111827;">
                <td style="padding-top:12px;"><strong style="font-size:16px;">Total</strong></td>
                <td style="text-align:right;padding-top:12px;"><strong style="font-size:16px;">' . $formatAmount((int)$invoice['total_amount']) . '</strong></td>
            </tr>
        </table>
    </div>

    ' . (!empty($invoice['notes']) ? '<div style="margin-top:40px;padding:15px;background:#f9fafb;border-radius:8px;"><strong>Notes:</strong><br>' . nl2br(e($invoice['notes'])) . '</div>' : '') . '
    ' . (!empty($invoice['footer']) ? '<div style="margin-top:20px;text-align:center;color:#6b7280;font-size:12px;">' . e($invoice['footer']) . '</div>' : '') . '

    <div style="margin-top:40px;text-align:center;color:#9ca3af;font-size:11px;">
        Generated by ' . e($appName) . ' &mdash; ' . date('d M Y H:i') . '
    </div>
</div>
</body>
</html>';
    }
}
