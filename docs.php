<?php
/**
 * Public API Documentation
 * Comprehensive developer docs for PayGate Pro API
 */
require_once __DIR__ . '/includes/init.php';
$appName = setting('app_name', 'PayGate Pro');
$appUrl = setting('app_url', app_url(''));
$baseApi = rtrim($appUrl, '/') . '/api/index.php';
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - <?= e($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script>
    tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif'],mono:['JetBrains Mono','monospace']}}}}
    </script>
    <style>
        .sidebar-link.active { background: rgba(59,130,246,0.1); color: #2563eb; border-left: 3px solid #2563eb; }
        pre code { white-space: pre-wrap; word-break: break-all; }
        .copy-btn:active { transform: scale(0.95); }
    </style>
</head>
<body class="font-sans bg-white text-slate-900">


<!-- Top Navigation -->
<header class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-xl border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="/" class="flex items-center gap-2">
                <div class="w-8 h-8 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                </div>
                <span class="font-bold text-slate-800"><?= e($appName) ?></span>
            </a>
            <span class="hidden sm:inline-block text-slate-300 mx-2">|</span>
            <span class="hidden sm:inline-block text-sm font-medium text-slate-500">API Docs</span>
        </div>
        <div class="flex items-center gap-3">
            <a href="/register.php" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">Daftar</a>
            <a href="/login.php" class="text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">Login</a>
        </div>
    </div>
</header>


<div class="flex pt-16">
<!-- Sidebar -->
<aside class="hidden lg:block w-64 fixed top-16 left-0 bottom-0 overflow-y-auto border-r border-slate-200 bg-slate-50/50 p-6">
    <nav class="space-y-1">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Getting Started</p>
        <a href="#introduction" class="sidebar-link block px-3 py-2 text-sm text-slate-600 hover:text-blue-600 rounded-lg transition-colors">Introduction</a>
        <a href="#authentication" class="sidebar-link block px-3 py-2 text-sm text-slate-600 hover:text-blue-600 rounded-lg transition-colors">Authentication</a>
        <a href="#base-url" class="sidebar-link block px-3 py-2 text-sm text-slate-600 hover:text-blue-600 rounded-lg transition-colors">Base URL</a>
        <a href="#errors" class="sidebar-link block px-3 py-2 text-sm text-slate-600 hover:text-blue-600 rounded-lg transition-colors">Error Handling</a>
        
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 mt-6">Endpoints</p>
        <a href="#create-transaction" class="sidebar-link block px-3 py-2 text-sm text-slate-600 hover:text-blue-600 rounded-lg transition-colors">Create Transaction</a>
        <a href="#get-transaction" class="sidebar-link block px-3 py-2 text-sm text-slate-600 hover:text-blue-600 rounded-lg transition-colors">Get Transaction</a>
        <a href="#get-wallet" class="sidebar-link block px-3 py-2 text-sm text-slate-600 hover:text-blue-600 rounded-lg transition-colors">Get Wallet</a>
        <a href="#get-withdrawals" class="sidebar-link block px-3 py-2 text-sm text-slate-600 hover:text-blue-600 rounded-lg transition-colors">Get Withdrawals</a>
        
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 mt-6">Webhook</p>
        <a href="#webhook" class="sidebar-link block px-3 py-2 text-sm text-slate-600 hover:text-blue-600 rounded-lg transition-colors">Webhook Events</a>
        <a href="#webhook-signature" class="sidebar-link block px-3 py-2 text-sm text-slate-600 hover:text-blue-600 rounded-lg transition-colors">Signature Validation</a>
        <a href="#status-codes" class="sidebar-link block px-3 py-2 text-sm text-slate-600 hover:text-blue-600 rounded-lg transition-colors">Status Codes</a>
    </nav>
</aside>


<!-- Main Content -->
<main class="flex-1 lg:ml-64 max-w-4xl mx-auto px-4 sm:px-8 py-12">

<!-- Introduction -->
<section id="introduction" class="mb-16">
    <h1 class="text-4xl font-extrabold text-slate-900 mb-4">API Documentation</h1>
    <p class="text-lg text-slate-600 leading-relaxed mb-6">Integrasi pembayaran QRIS dengan <?= e($appName) ?>. Terima pembayaran dari semua e-wallet dan mobile banking Indonesia dalam hitungan menit.</p>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100 rounded-xl p-4">
            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mb-2">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <h3 class="font-semibold text-slate-800 text-sm">Cepat</h3>
            <p class="text-xs text-slate-500 mt-1">Integrasi dalam 5 menit</p>
        </div>
        <div class="bg-gradient-to-br from-emerald-50 to-green-50 border border-emerald-100 rounded-xl p-4">
            <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center mb-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <h3 class="font-semibold text-slate-800 text-sm">Aman</h3>
            <p class="text-xs text-slate-500 mt-1">HMAC-SHA256 webhook</p>
        </div>
        <div class="bg-gradient-to-br from-amber-50 to-yellow-50 border border-amber-100 rounded-xl p-4">
            <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center mb-2">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="font-semibold text-slate-800 text-sm">QRIS</h3>
            <p class="text-xs text-slate-500 mt-1">Semua bank & e-wallet</p>
        </div>
    </div>
</section>


<!-- Authentication -->
<section id="authentication" class="mb-16">
    <h2 class="text-2xl font-bold text-slate-900 mb-4 flex items-center gap-2">
        <span class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center"><svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg></span>
        Authentication
    </h2>
    <p class="text-slate-600 mb-4">Semua request API memerlukan header <code class="bg-slate-100 px-2 py-0.5 rounded text-sm font-mono text-blue-600">Authorization</code> dengan Bearer token.</p>
    <div class="bg-slate-900 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 bg-slate-800">
            <span class="text-xs text-slate-400 font-mono">Header</span>
            <button onclick="copyCode(this)" class="copy-btn text-xs text-slate-400 hover:text-white transition-colors">Copy</button>
        </div>
        <pre class="p-4 text-sm"><code class="text-emerald-300">Authorization: Bearer <span class="text-amber-300">YOUR_API_KEY</span></code></pre>
    </div>
    <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-4">
        <p class="text-sm text-amber-800"><strong>Penting:</strong> API Key bisa ditemukan di Dashboard Merchant setelah akun diaktifkan oleh admin. Jaga kerahasiaan API Key Anda.</p>
    </div>
</section>

<!-- Base URL -->
<section id="base-url" class="mb-16">
    <h2 class="text-2xl font-bold text-slate-900 mb-4 flex items-center gap-2">
        <span class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center"><svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg></span>
        Base URL
    </h2>
    <div class="bg-slate-900 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 bg-slate-800">
            <span class="text-xs text-slate-400 font-mono">Base URL</span>
            <button onclick="copyCode(this)" class="copy-btn text-xs text-slate-400 hover:text-white transition-colors">Copy</button>
        </div>
        <pre class="p-4 text-sm"><code class="text-emerald-300"><?= e($baseApi) ?></code></pre>
    </div>
</section>


<!-- Error Handling -->
<section id="errors" class="mb-16">
    <h2 class="text-2xl font-bold text-slate-900 mb-4">Error Handling</h2>
    <p class="text-slate-600 mb-4">API menggunakan HTTP status code standar. Response error selalu dalam format JSON.</p>
    <div class="overflow-x-auto">
        <table class="w-full text-sm border border-slate-200 rounded-xl overflow-hidden">
            <thead><tr class="bg-slate-50"><th class="px-4 py-3 text-left font-semibold text-slate-700">Code</th><th class="px-4 py-3 text-left font-semibold text-slate-700">Meaning</th><th class="px-4 py-3 text-left font-semibold text-slate-700">Description</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                <tr><td class="px-4 py-3 font-mono text-emerald-600">200</td><td class="px-4 py-3">OK</td><td class="px-4 py-3 text-slate-500">Request berhasil</td></tr>
                <tr><td class="px-4 py-3 font-mono text-emerald-600">201</td><td class="px-4 py-3">Created</td><td class="px-4 py-3 text-slate-500">Resource berhasil dibuat</td></tr>
                <tr><td class="px-4 py-3 font-mono text-amber-600">400</td><td class="px-4 py-3">Bad Request</td><td class="px-4 py-3 text-slate-500">Request tidak valid (parameter salah/kurang)</td></tr>
                <tr><td class="px-4 py-3 font-mono text-red-600">401</td><td class="px-4 py-3">Unauthorized</td><td class="px-4 py-3 text-slate-500">API key tidak valid atau tidak ada</td></tr>
                <tr><td class="px-4 py-3 font-mono text-red-600">403</td><td class="px-4 py-3">Forbidden</td><td class="px-4 py-3 text-slate-500">Merchant tidak aktif</td></tr>
                <tr><td class="px-4 py-3 font-mono text-red-600">404</td><td class="px-4 py-3">Not Found</td><td class="px-4 py-3 text-slate-500">Resource tidak ditemukan</td></tr>
                <tr><td class="px-4 py-3 font-mono text-red-600">429</td><td class="px-4 py-3">Too Many Requests</td><td class="px-4 py-3 text-slate-500">Rate limit terlampaui</td></tr>
                <tr><td class="px-4 py-3 font-mono text-red-600">500</td><td class="px-4 py-3">Server Error</td><td class="px-4 py-3 text-slate-500">Kesalahan internal server</td></tr>
            </tbody>
        </table>
    </div>
    <div class="mt-4 bg-slate-900 rounded-xl overflow-hidden">
        <div class="px-4 py-2 bg-slate-800"><span class="text-xs text-slate-400 font-mono">Error Response Format</span></div>
        <pre class="p-4 text-sm"><code class="text-emerald-300">{
  "success": false,
  "error": "Pesan error detail"
}</code></pre>
    </div>
</section>


<!-- Create Transaction -->
<section id="create-transaction" class="mb-16">
    <div class="flex items-center gap-3 mb-4">
        <span class="px-3 py-1 rounded-lg text-xs font-bold font-mono bg-emerald-100 text-emerald-700">POST</span>
        <h2 class="text-2xl font-bold text-slate-900">Create Transaction</h2>
    </div>
    <p class="text-slate-600 mb-4">Buat transaksi pembayaran QRIS baru. Customer akan diarahkan ke halaman pembayaran dengan QR code.</p>
    
    <div class="bg-slate-900 rounded-xl overflow-hidden mb-6">
        <div class="flex items-center justify-between px-4 py-2 bg-slate-800">
            <span class="text-xs text-slate-400 font-mono">Endpoint</span>
            <button onclick="copyCode(this)" class="copy-btn text-xs text-slate-400 hover:text-white transition-colors">Copy</button>
        </div>
        <pre class="p-4 text-sm"><code class="text-emerald-300">POST <?= e($baseApi) ?>?action=create_transaction</code></pre>
    </div>

    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-3">Request Body (JSON)</h3>
    <div class="overflow-x-auto mb-6">
        <table class="w-full text-sm border border-slate-200 rounded-xl overflow-hidden">
            <thead><tr class="bg-slate-50"><th class="px-4 py-3 text-left font-semibold">Parameter</th><th class="px-4 py-3 text-left font-semibold">Type</th><th class="px-4 py-3 text-left font-semibold">Required</th><th class="px-4 py-3 text-left font-semibold">Description</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                <tr><td class="px-4 py-3 font-mono text-blue-600 text-xs">amount</td><td class="px-4 py-3 text-slate-500">integer</td><td class="px-4 py-3"><span class="text-red-500 font-bold text-xs">Yes</span></td><td class="px-4 py-3 text-slate-600">Jumlah pembayaran (Rupiah)</td></tr>
                <tr><td class="px-4 py-3 font-mono text-blue-600 text-xs">order_id</td><td class="px-4 py-3 text-slate-500">string</td><td class="px-4 py-3"><span class="text-slate-400 text-xs">No</span></td><td class="px-4 py-3 text-slate-600">Order ID unik (auto-generate jika kosong)</td></tr>
                <tr><td class="px-4 py-3 font-mono text-blue-600 text-xs">link_name</td><td class="px-4 py-3 text-slate-500">string</td><td class="px-4 py-3"><span class="text-slate-400 text-xs">No</span></td><td class="px-4 py-3 text-slate-600">Nama/deskripsi pembayaran</td></tr>
                <tr><td class="px-4 py-3 font-mono text-blue-600 text-xs">customer_name</td><td class="px-4 py-3 text-slate-500">string</td><td class="px-4 py-3"><span class="text-slate-400 text-xs">No</span></td><td class="px-4 py-3 text-slate-600">Nama customer</td></tr>
                <tr><td class="px-4 py-3 font-mono text-blue-600 text-xs">customer_email</td><td class="px-4 py-3 text-slate-500">string</td><td class="px-4 py-3"><span class="text-slate-400 text-xs">No</span></td><td class="px-4 py-3 text-slate-600">Email customer</td></tr>
                <tr><td class="px-4 py-3 font-mono text-blue-600 text-xs">customer_wa</td><td class="px-4 py-3 text-slate-500">string</td><td class="px-4 py-3"><span class="text-slate-400 text-xs">No</span></td><td class="px-4 py-3 text-slate-600">Nomor WhatsApp (08xxxx)</td></tr>
                <tr><td class="px-4 py-3 font-mono text-blue-600 text-xs">webhook_url</td><td class="px-4 py-3 text-slate-500">string</td><td class="px-4 py-3"><span class="text-slate-400 text-xs">No</span></td><td class="px-4 py-3 text-slate-600">URL callback notifikasi</td></tr>
                <tr><td class="px-4 py-3 font-mono text-blue-600 text-xs">redirect_url</td><td class="px-4 py-3 text-slate-500">string</td><td class="px-4 py-3"><span class="text-slate-400 text-xs">No</span></td><td class="px-4 py-3 text-slate-600">URL redirect setelah bayar</td></tr>
            </tbody>
        </table>
    </div>


    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div>
            <h4 class="text-xs font-bold text-slate-500 uppercase mb-2">Request Example</h4>
            <div class="bg-slate-900 rounded-xl overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 bg-slate-800">
                    <span class="text-xs text-slate-400 font-mono">cURL</span>
                    <button onclick="copyCode(this)" class="copy-btn text-xs text-slate-400 hover:text-white transition-colors">Copy</button>
                </div>
                <pre class="p-4 text-xs leading-relaxed"><code class="text-emerald-300">curl -X POST "<?= e($baseApi) ?>?action=create_transaction" \
  -H "Authorization: Bearer <span class="text-amber-300">YOUR_API_KEY</span>" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 50000,
    "order_id": "INV-001",
    "link_name": "Pembayaran Produk A",
    "customer_name": "John Doe"
  }'</code></pre>
            </div>
        </div>
        <div>
            <h4 class="text-xs font-bold text-slate-500 uppercase mb-2">Response (201)</h4>
            <div class="bg-slate-900 rounded-xl overflow-hidden">
                <div class="px-4 py-2 bg-slate-800"><span class="text-xs text-slate-400 font-mono">JSON</span></div>
                <pre class="p-4 text-xs leading-relaxed"><code class="text-emerald-300">{
  "success": true,
  "data": {
    "id": "a1b2c3d4-...",
    "order_id": "INV-001",
    "amount": 50000,
    "fee": 350,
    "net_amount": 49650,
    "status": "PENDING",
    "payment_url": "https://pay.../pay.php?order_id=INV-001",
    "qr_url": "https://...",
    "created_at": "2026-07-08 10:00:00"
  }
}</code></pre>
            </div>
        </div>
    </div>
</section>


<!-- Get Transaction -->
<section id="get-transaction" class="mb-16">
    <div class="flex items-center gap-3 mb-4">
        <span class="px-3 py-1 rounded-lg text-xs font-bold font-mono bg-blue-100 text-blue-700">GET</span>
        <h2 class="text-2xl font-bold text-slate-900">Get Transaction</h2>
    </div>
    <p class="text-slate-600 mb-4">Cek status dan detail transaksi berdasarkan order_id.</p>
    <div class="bg-slate-900 rounded-xl overflow-hidden mb-4">
        <div class="flex items-center justify-between px-4 py-2 bg-slate-800">
            <span class="text-xs text-slate-400 font-mono">Endpoint</span>
            <button onclick="copyCode(this)" class="copy-btn text-xs text-slate-400 hover:text-white transition-colors">Copy</button>
        </div>
        <pre class="p-4 text-sm"><code class="text-emerald-300">GET <?= e($baseApi) ?>?action=get_transaction&order_id=<span class="text-amber-300">INV-001</span></code></pre>
    </div>
    <div class="bg-slate-900 rounded-xl overflow-hidden">
        <div class="px-4 py-2 bg-slate-800"><span class="text-xs text-slate-400 font-mono">Response (200)</span></div>
        <pre class="p-4 text-xs leading-relaxed"><code class="text-emerald-300">{
  "success": true,
  "data": {
    "id": "a1b2c3d4-...",
    "order_id": "INV-001",
    "amount": 50000,
    "fee": 350,
    "net_amount": 49650,
    "status": "PAID",
    "payment_url": "https://...",
    "qr_url": "https://...",
    "paid_at": "2026-07-08 10:05:00",
    "created_at": "2026-07-08 10:00:00"
  }
}</code></pre>
    </div>
</section>

<!-- Get Wallet -->
<section id="get-wallet" class="mb-16">
    <div class="flex items-center gap-3 mb-4">
        <span class="px-3 py-1 rounded-lg text-xs font-bold font-mono bg-blue-100 text-blue-700">GET</span>
        <h2 class="text-2xl font-bold text-slate-900">Get Wallet</h2>
    </div>
    <p class="text-slate-600 mb-4">Lihat saldo wallet merchant (available, pending, hold).</p>
    <div class="bg-slate-900 rounded-xl overflow-hidden mb-4">
        <div class="px-4 py-2 bg-slate-800"><span class="text-xs text-slate-400 font-mono">Endpoint</span></div>
        <pre class="p-4 text-sm"><code class="text-emerald-300">GET <?= e($baseApi) ?>?action=wallet</code></pre>
    </div>
    <div class="bg-slate-900 rounded-xl overflow-hidden">
        <div class="px-4 py-2 bg-slate-800"><span class="text-xs text-slate-400 font-mono">Response (200)</span></div>
        <pre class="p-4 text-xs leading-relaxed"><code class="text-emerald-300">{
  "success": true,
  "data": {
    "available_balance": 500000,
    "pending_balance": 50000,
    "hold_balance": 0,
    "withdrawn_balance": 200000,
    "total_received": 750000,
    "total_fee": 5250
  }
}</code></pre>
    </div>
</section>


<!-- Get Withdrawals -->
<section id="get-withdrawals" class="mb-16">
    <div class="flex items-center gap-3 mb-4">
        <span class="px-3 py-1 rounded-lg text-xs font-bold font-mono bg-blue-100 text-blue-700">GET</span>
        <h2 class="text-2xl font-bold text-slate-900">Get Withdrawals</h2>
    </div>
    <p class="text-slate-600 mb-4">Lihat riwayat penarikan dana (withdrawal).</p>
    <div class="bg-slate-900 rounded-xl overflow-hidden mb-4">
        <div class="px-4 py-2 bg-slate-800"><span class="text-xs text-slate-400 font-mono">Endpoint</span></div>
        <pre class="p-4 text-sm"><code class="text-emerald-300">GET <?= e($baseApi) ?>?action=withdrawals</code></pre>
    </div>
    <div class="bg-slate-900 rounded-xl overflow-hidden">
        <div class="px-4 py-2 bg-slate-800"><span class="text-xs text-slate-400 font-mono">Response (200)</span></div>
        <pre class="p-4 text-xs leading-relaxed"><code class="text-emerald-300">{
  "success": true,
  "data": [
    {
      "id": "wd-uuid-...",
      "amount": 100000,
      "bank_name": "BCA",
      "account_number": "123****890",
      "status": "SUCCESS",
      "created_at": "2026-07-07 09:00:00"
    }
  ]
}</code></pre>
    </div>
</section>

<!-- Webhook -->
<section id="webhook" class="mb-16">
    <h2 class="text-2xl font-bold text-slate-900 mb-4 flex items-center gap-2">
        <span class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center"><svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg></span>
        Webhook Events
    </h2>
    <p class="text-slate-600 mb-4">Saat status pembayaran berubah, sistem akan mengirim HTTP POST ke webhook URL yang dikonfigurasi. Pastikan endpoint Anda merespon dengan HTTP 200.</p>
    
    <div class="bg-slate-900 rounded-xl overflow-hidden mb-4">
        <div class="px-4 py-2 bg-slate-800"><span class="text-xs text-slate-400 font-mono">Webhook Payload</span></div>
        <pre class="p-4 text-xs leading-relaxed"><code class="text-emerald-300">{
  "order_id": "INV-001",
  "transaction_status": "settlement",
  "status_code": "200",
  "gross_amount": "50000.00",
  "transaction_time": "2026-07-08 10:05:00"
}</code></pre>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
        <p class="text-sm text-blue-800"><strong>Headers yang dikirim:</strong></p>
        <ul class="text-sm text-blue-700 mt-2 space-y-1 list-disc list-inside">
            <li><code class="font-mono text-xs bg-blue-100 px-1 rounded">Content-Type: application/json</code></li>
            <li><code class="font-mono text-xs bg-blue-100 px-1 rounded">X-Signature: HMAC-SHA256 hash</code></li>
        </ul>
    </div>
</section>


<!-- Webhook Signature -->
<section id="webhook-signature" class="mb-16">
    <h2 class="text-2xl font-bold text-slate-900 mb-4">Signature Validation</h2>
    <p class="text-slate-600 mb-4">Setiap webhook memiliki header <code class="bg-slate-100 px-2 py-0.5 rounded text-sm font-mono">X-Signature</code>. Validasi signature untuk memastikan webhook asli dari kami.</p>
    
    <div class="bg-slate-900 rounded-xl overflow-hidden mb-4">
        <div class="flex items-center justify-between px-4 py-2 bg-slate-800">
            <span class="text-xs text-slate-400 font-mono">PHP Example</span>
            <button onclick="copyCode(this)" class="copy-btn text-xs text-slate-400 hover:text-white transition-colors">Copy</button>
        </div>
        <pre class="p-4 text-xs leading-relaxed"><code class="text-emerald-300"><span class="text-slate-500">// Ambil raw body dan signature</span>
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

<span class="text-slate-500">// Hitung signature dengan API key Anda</span>
$expected = hash_hmac('sha256', $payload, <span class="text-amber-300">$yourApiKey</span>);

<span class="text-slate-500">// Bandingkan (timing-safe)</span>
if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    die('Invalid signature');
}

<span class="text-slate-500">// Proses webhook...</span>
$data = json_decode($payload, true);
$orderId = $data['order_id'];
$status = $data['transaction_status'];</code></pre>
    </div>

    <div class="bg-slate-900 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 bg-slate-800">
            <span class="text-xs text-slate-400 font-mono">Node.js Example</span>
            <button onclick="copyCode(this)" class="copy-btn text-xs text-slate-400 hover:text-white transition-colors">Copy</button>
        </div>
        <pre class="p-4 text-xs leading-relaxed"><code class="text-emerald-300">const crypto = require('crypto');

app.post('/webhook', (req, res) => {
  const payload = JSON.stringify(req.body);
  const signature = req.headers['x-signature'];
  
  const expected = crypto
    .createHmac('sha256', <span class="text-amber-300">YOUR_API_KEY</span>)
    .update(payload)
    .digest('hex');
  
  if (signature !== expected) {
    return res.status(403).send('Invalid');
  }
  
  <span class="text-slate-500">// Process webhook...</span>
  res.status(200).send('OK');
});</code></pre>
    </div>
</section>


<!-- Status Codes -->
<section id="status-codes" class="mb-16">
    <h2 class="text-2xl font-bold text-slate-900 mb-4">Transaction Status</h2>
    <p class="text-slate-600 mb-4">Status yang mungkin diterima di webhook atau saat cek transaksi:</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-100 rounded-xl p-4">
            <span class="w-3 h-3 bg-emerald-500 rounded-full"></span>
            <div><p class="font-semibold text-slate-800 text-sm">PAID</p><p class="text-xs text-slate-500">Pembayaran berhasil</p></div>
        </div>
        <div class="flex items-center gap-3 bg-amber-50 border border-amber-100 rounded-xl p-4">
            <span class="w-3 h-3 bg-amber-500 rounded-full"></span>
            <div><p class="font-semibold text-slate-800 text-sm">PENDING</p><p class="text-xs text-slate-500">Menunggu pembayaran</p></div>
        </div>
        <div class="flex items-center gap-3 bg-red-50 border border-red-100 rounded-xl p-4">
            <span class="w-3 h-3 bg-red-500 rounded-full"></span>
            <div><p class="font-semibold text-slate-800 text-sm">FAILED</p><p class="text-xs text-slate-500">Pembayaran gagal</p></div>
        </div>
        <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-xl p-4">
            <span class="w-3 h-3 bg-slate-400 rounded-full"></span>
            <div><p class="font-semibold text-slate-800 text-sm">EXPIRED</p><p class="text-xs text-slate-500">Waktu habis</p></div>
        </div>
    </div>
    
    <h3 class="text-lg font-semibold text-slate-800 mt-8 mb-3">Status Mapping dari Webhook</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm border border-slate-200 rounded-xl overflow-hidden">
            <thead><tr class="bg-slate-50"><th class="px-4 py-3 text-left font-semibold">Webhook Value</th><th class="px-4 py-3 text-left font-semibold">Internal Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                <tr><td class="px-4 py-3 font-mono text-xs">paid, success, settlement, completed</td><td class="px-4 py-3"><span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs font-bold">PAID</span></td></tr>
                <tr><td class="px-4 py-3 font-mono text-xs">pending, unpaid, waiting</td><td class="px-4 py-3"><span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-xs font-bold">PENDING</span></td></tr>
                <tr><td class="px-4 py-3 font-mono text-xs">failed, cancel, canceled, cancelled, error</td><td class="px-4 py-3"><span class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs font-bold">FAILED</span></td></tr>
                <tr><td class="px-4 py-3 font-mono text-xs">expired, expire</td><td class="px-4 py-3"><span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-xs font-bold">EXPIRED</span></td></tr>
            </tbody>
        </table>
    </div>
</section>


<!-- Footer -->
<div class="border-t border-slate-200 pt-8 mt-16 text-center">
    <p class="text-sm text-slate-400"><?= e($appName) ?> API v1.0 &mdash; Payment Gateway Multi Merchant</p>
    <p class="text-xs text-slate-300 mt-1">&copy; <?= date('Y') ?> All rights reserved.</p>
</div>

</main>
</div>

<!-- Copy to clipboard script -->
<script>
function copyCode(btn) {
    const pre = btn.closest('.bg-slate-900').querySelector('pre code');
    const text = pre.textContent;
    navigator.clipboard.writeText(text).then(() => {
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 2000);
    });
}

// Active sidebar link
document.addEventListener('scroll', () => {
    const sections = document.querySelectorAll('section[id]');
    const links = document.querySelectorAll('.sidebar-link');
    let current = '';
    sections.forEach(s => {
        if (window.scrollY >= s.offsetTop - 100) current = s.id;
    });
    links.forEach(l => {
        l.classList.toggle('active', l.getAttribute('href') === '#' + current);
    });
});
</script>
</body>
</html>
