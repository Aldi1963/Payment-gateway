<?php
/**
 * Invoice Service
 * Generate, send, and manage invoices with PDF generation
 */

require_once base_path('app/Database.php');
require_once base_path('app/Services/AuditLogService.php');

class InvoiceService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Create invoice from transaction or standalone
     */
    public function create(array $data): array
    {
        $id = generate_uuid();
        $invoiceNumber = $this->generateNumber();

        $invoice = [
            'id' => $id,
            'merchant_id' => $data['merchant_id'],
            'transaction_id' => $data['transaction_id'] ?? null,
            'invoice_number' => $invoiceNumber,
            'customer_name' => sanitize($data['customer_name'] ?? ''),
            'customer_email' => sanitize($data['customer_email'] ?? ''),
            'customer_phone' => sanitize($data['customer_phone'] ?? ''),
            'customer_address' => sanitize($data['customer_address'] ?? ''),
            'items' => json_encode($data['items'] ?? []),
            'subtotal' => (int)($data['subtotal'] ?? 0),
            'tax' => (int)($data['tax'] ?? 0),
            'discount' => (int)($data['discount'] ?? 0),

            'total' => (int)($data['total'] ?? $data['subtotal'] ?? 0),
            'currency' => 'IDR',
            'status' => 'draft',
            'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime('+7 days')),
            'notes' => sanitize($data['notes'] ?? ''),
            'payment_url' => null,
            'paid_at' => null,
            'sent_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $cols = implode(',', array_map(fn($k) => "`{$k}`", array_keys($invoice)));
        $vals = implode(',', array_map(fn($k) => ":{$k}", array_keys($invoice)));
        $stmt = $this->db->prepare("INSERT INTO `invoices` ({$cols}) VALUES ({$vals})");
        $stmt->execute($invoice);

        return ['success' => true, 'invoice' => $invoice, 'message' => 'Invoice berhasil dibuat.'];
    }

    /**
     * Generate invoice number INV-YYYYMM-XXXX
     */
    private function generateNumber(): string
    {
        $prefix = 'INV-' . date('Ym') . '-';
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM `invoices` WHERE `invoice_number` LIKE :prefix");
        $stmt->execute(['prefix' => $prefix . '%']);
        $count = (int)$stmt->fetchColumn() + 1;
        return $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate PDF invoice (HTML-based, no external lib needed)
     */
    public function generatePdf(string $invoiceId): string
    {
        $invoice = $this->find($invoiceId);
        if (!$invoice) return '';

        require_once base_path('app/Repositories/MerchantRepository.php');
        $merchantRepo = new MerchantRepository();
        $merchant = $merchantRepo->find($invoice['merchant_id']);

        $items = is_string($invoice['items']) ? json_decode($invoice['items'], true) : ($invoice['items'] ?? []);
        $appName = setting('app_name', 'PayGate Pro');

        // Generate HTML invoice
        $html = $this->buildInvoiceHtml($invoice, $merchant, $items, $appName);
        return $html;
    }


    /**
     * Build HTML invoice template
     */
    private function buildInvoiceHtml(array $inv, ?array $merchant, array $items, string $appName): string
    {
        $merchantName = $merchant['business_name'] ?? $appName;
        $html = "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Invoice {$inv['invoice_number']}</title>";
        $html .= "<style>body{font-family:Inter,Arial,sans-serif;margin:0;padding:20px;color:#1e293b}";
        $html .= ".inv-header{display:flex;justify-content:space-between;border-bottom:2px solid #2563eb;padding-bottom:20px;margin-bottom:20px}";
        $html .= ".inv-title{font-size:28px;font-weight:800;color:#2563eb}";
        $html .= "table{width:100%;border-collapse:collapse;margin:20px 0}th,td{padding:10px;text-align:left;border-bottom:1px solid #e2e8f0}";
        $html .= "th{background:#f8fafc;font-weight:600;font-size:12px;text-transform:uppercase;color:#64748b}";
        $html .= ".total-row{font-weight:700;font-size:16px;border-top:2px solid #1e293b}";
        $html .= ".badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600}";
        $html .= ".paid{background:#d1fae5;color:#065f46}.unpaid{background:#fef3c7;color:#92400e}";
        $html .= "@media print{body{padding:0}}</style></head><body>";
        $html .= "<div class='inv-header'><div><div class='inv-title'>{$merchantName}</div>";
        $html .= "<p style='margin:5px 0;color:#64748b;font-size:13px'>" . e($merchant['address'] ?? '') . " " . e($merchant['city'] ?? '') . "</p>";
        $html .= "<p style='margin:0;color:#64748b;font-size:13px'>" . e($merchant['email'] ?? '') . "</p></div>";
        $html .= "<div style='text-align:right'><h2 style='margin:0;color:#1e293b'>INVOICE</h2>";
        $html .= "<p style='font-size:18px;font-weight:700;color:#2563eb;margin:5px 0'>{$inv['invoice_number']}</p>";
        $html .= "<p style='font-size:12px;color:#64748b'>Tanggal: " . format_date($inv['created_at'], 'd M Y') . "</p>";
        $html .= "<p style='font-size:12px;color:#64748b'>Jatuh Tempo: " . format_date($inv['due_date'], 'd M Y') . "</p>";
        $status = $inv['status'] === 'paid' ? "<span class='badge paid'>LUNAS</span>" : "<span class='badge unpaid'>BELUM BAYAR</span>";
        $html .= "<p style='margin-top:8px'>{$status}</p></div></div>";

        // Bill To
        $html .= "<div style='margin-bottom:20px'><p style='font-size:11px;color:#64748b;text-transform:uppercase;font-weight:600'>Tagihan Kepada:</p>";
        $html .= "<p style='font-weight:600;font-size:15px;margin:4px 0'>" . e($inv['customer_name']) . "</p>";
        $html .= "<p style='color:#64748b;font-size:13px;margin:2px 0'>" . e($inv['customer_email']) . "</p>";
        $html .= "<p style='color:#64748b;font-size:13px;margin:2px 0'>" . e($inv['customer_address']) . "</p></div>";

        // Items table
        $html .= "<table><thead><tr><th>Item</th><th>Qty</th><th>Harga</th><th style='text-align:right'>Jumlah</th></tr></thead><tbody>";
        foreach ($items as $item) {
            $qty = (int)($item['qty'] ?? 1);
            $price = (int)($item['price'] ?? 0);
            $lineTotal = $qty * $price;
            $html .= "<tr><td>" . e($item['name'] ?? '-') . "</td><td>{$qty}</td><td>" . format_currency($price) . "</td><td style='text-align:right'>" . format_currency($lineTotal) . "</td></tr>";
        }
        $html .= "</tbody></table>";

        // Totals
        $html .= "<div style='text-align:right;margin-top:10px'>";
        $html .= "<p>Subtotal: <strong>" . format_currency($inv['subtotal']) . "</strong></p>";
        if ($inv['tax'] > 0) $html .= "<p>Pajak: " . format_currency($inv['tax']) . "</p>";
        if ($inv['discount'] > 0) $html .= "<p>Diskon: -" . format_currency($inv['discount']) . "</p>";
        $html .= "<p style='font-size:20px;font-weight:800;color:#2563eb;margin-top:10px'>Total: " . format_currency($inv['total']) . "</p></div>";

        if (!empty($inv['notes'])) {
            $html .= "<div style='margin-top:30px;padding:15px;background:#f8fafc;border-radius:8px'><p style='font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase'>Catatan:</p><p style='font-size:13px;color:#475569'>" . e($inv['notes']) . "</p></div>";
        }

        if (!empty($inv['payment_url'])) {
            $html .= "<div style='text-align:center;margin-top:30px'><a href='" . e($inv['payment_url']) . "' style='display:inline-block;background:#2563eb;color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:600'>Bayar Sekarang</a></div>";
        }

        $html .= "<div style='text-align:center;margin-top:40px;padding-top:20px;border-top:1px solid #e2e8f0'>";
        $html .= "<p style='font-size:11px;color:#94a3b8'>{$appName} - Payment Gateway</p></div>";
        $html .= "</body></html>";
        return $html;
    }

    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM `invoices` WHERE `id` = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByMerchant(string $merchantId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM `invoices` WHERE `merchant_id` = :mid ORDER BY `created_at` DESC");
        $stmt->execute(['mid' => $merchantId]);
        return $stmt->fetchAll() ?: [];
    }

    public function markPaid(string $id): bool
    {
        return $this->db->prepare("UPDATE `invoices` SET `status`='paid', `paid_at`=:now, `updated_at`=:now2 WHERE `id`=:id")
            ->execute(['now' => now(), 'now2' => now(), 'id' => $id]);
    }

    public function markSent(string $id): bool
    {
        return $this->db->prepare("UPDATE `invoices` SET `status`='sent', `sent_at`=:now, `updated_at`=:now2 WHERE `id`=:id")
            ->execute(['now' => now(), 'now2' => now(), 'id' => $id]);
    }

    public function delete(string $id): bool
    {
        return $this->db->prepare("DELETE FROM `invoices` WHERE `id`=:id AND `status`='draft'")->execute(['id' => $id]);
    }
}
