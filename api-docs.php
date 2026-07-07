<?php
/**
 * Interactive API Documentation Page
 * Auto-generated endpoint reference with Try It feature
 */
require_once __DIR__ . '/includes/init.php';
$appName = setting('app_name', 'PayGate Pro');
$appUrl = setting('app_url', app_url(''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - <?= e($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif'],mono:['JetBrains Mono','monospace']}}}}</script>
</head>
<body class="font-sans bg-slate-50 text-slate-900">
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="mb-10">
        <h1 class="text-3xl font-bold text-slate-800"><?= e($appName) ?> API</h1>
        <p class="text-slate-500 mt-2">REST API untuk integrasi pembayaran. Base URL: <code class="bg-slate-200 px-2 py-0.5 rounded text-sm font-mono"><?= e(rtrim($appUrl, '/')) ?>/api/index.php</code></p>
        <p class="text-sm text-slate-400 mt-1">Authentication: <code class="text-blue-600">Authorization: Bearer YOUR_API_KEY</code></p>
    </div>


    <?php
    $endpoints = [
        ['method'=>'POST','path'=>'?action=create_transaction','title'=>'Create Transaction','desc'=>'Buat transaksi pembayaran baru','params'=>[
            ['name'=>'order_id','type'=>'string','required'=>false,'desc'=>'Order ID unik (auto-generate jika kosong)'],
            ['name'=>'amount','type'=>'integer','required'=>true,'desc'=>'Jumlah pembayaran dalam Rupiah'],
            ['name'=>'link_name','type'=>'string','required'=>false,'desc'=>'Nama/label pembayaran'],
            ['name'=>'webhook_url','type'=>'string','required'=>false,'desc'=>'URL untuk menerima notifikasi'],
            ['name'=>'redirect_url','type'=>'string','required'=>false,'desc'=>'URL redirect setelah bayar'],
            ['name'=>'customer_name','type'=>'string','required'=>false,'desc'=>'Nama customer'],
            ['name'=>'customer_wa','type'=>'string','required'=>false,'desc'=>'Nomor WhatsApp customer'],
            ['name'=>'customer_email','type'=>'string','required'=>false,'desc'=>'Email customer'],
        ],'response'=>'{"success":true,"data":{"id":"uuid","order_id":"INV-...","amount":50000,"fee":350,"net_amount":49650,"status":"PENDING","payment_url":"https://...","qr_url":"https://...","created_at":"2026-07-07 10:00:00"}}'],
        ['method'=>'GET','path'=>'?action=get_transaction&order_id=INV-XXX','title'=>'Get Transaction','desc'=>'Cek status transaksi berdasarkan order_id','params'=>[
            ['name'=>'order_id','type'=>'string','required'=>true,'desc'=>'Order ID yang ingin dicek'],
        ],'response'=>'{"success":true,"data":{"id":"uuid","order_id":"INV-...","amount":50000,"status":"PAID","paid_at":"2026-07-07 10:05:00"}}'],
        ['method'=>'GET','path'=>'?action=wallet','title'=>'Get Wallet','desc'=>'Lihat saldo wallet merchant','params'=>[],'response'=>'{"success":true,"data":{"available_balance":500000,"hold_balance":0,"withdrawn_balance":200000,"total_received":700000,"total_fee":4900}}'],
        ['method'=>'GET','path'=>'?action=withdrawals','title'=>'Get Withdrawals','desc'=>'Lihat riwayat withdrawal','params'=>[],'response'=>'{"success":true,"data":[{"id":"uuid","amount":100000,"bank_name":"BCA","status":"SUCCESS","created_at":"..."}]}'],
    ];
    foreach ($endpoints as $ep): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-6 mb-6">
        <div class="flex items-center gap-3 mb-3">
            <span class="px-3 py-1 rounded text-xs font-bold font-mono <?= $ep['method']==='POST' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' ?>"><?= $ep['method'] ?></span>
            <code class="text-sm font-mono text-slate-700"><?= e($ep['path']) ?></code>
        </div>
        <h3 class="text-lg font-semibold text-slate-800"><?= e($ep['title']) ?></h3>
        <p class="text-sm text-slate-500 mt-1"><?= e($ep['desc']) ?></p>

        <?php if (!empty($ep['params'])): ?>
        <div class="mt-4">
            <h4 class="text-xs font-semibold text-slate-500 uppercase mb-2">Parameters</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm"><thead><tr class="bg-slate-50"><th class="px-3 py-2 text-left font-medium text-slate-600">Name</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-left">Required</th><th class="px-3 py-2 text-left">Description</th></tr></thead><tbody>
                <?php foreach ($ep['params'] as $param): ?>
                <tr class="border-t border-slate-100"><td class="px-3 py-2 font-mono text-xs text-blue-600"><?= $param['name'] ?></td><td class="px-3 py-2 text-slate-500 text-xs"><?= $param['type'] ?></td><td class="px-3 py-2"><?= $param['required'] ? '<span class="text-red-500 text-xs font-bold">Yes</span>' : '<span class="text-slate-400 text-xs">No</span>' ?></td><td class="px-3 py-2 text-slate-600 text-xs"><?= $param['desc'] ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
        </div>
        <?php endif; ?>

        <div class="mt-4">
            <h4 class="text-xs font-semibold text-slate-500 uppercase mb-2">Response Example</h4>
            <pre class="bg-slate-900 text-emerald-300 rounded-lg p-4 text-xs font-mono overflow-x-auto"><?= e($ep['response']) ?></pre>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Webhook Section -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 mb-6">
        <h2 class="text-xl font-bold text-slate-800 mb-4">Webhook Notifications</h2>
        <p class="text-sm text-slate-500 mb-4">Sistem akan mengirim POST request ke webhook URL Anda saat status pembayaran berubah.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h4 class="text-xs font-semibold text-slate-500 uppercase mb-2">Headers</h4>
                <pre class="bg-slate-100 rounded-lg p-3 text-xs font-mono">Content-Type: application/json
X-Signature: HMAC-SHA256(body, your_api_key)</pre>
            </div>
            <div>
                <h4 class="text-xs font-semibold text-slate-500 uppercase mb-2">Payload</h4>
                <pre class="bg-slate-100 rounded-lg p-3 text-xs font-mono">{"event":"payment.status_changed","order_id":"INV-...","status":"PAID","amount":50000,"paid_at":"..."}</pre>
            </div>
        </div>
    </div>

    <div class="text-center text-sm text-slate-400 mt-10">
        <p><?= e($appName) ?> v1.0 — Payment Gateway API</p>
    </div>
</div>
</body>
</html>
