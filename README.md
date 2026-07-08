# Clipku Pay - Payment Gateway SaaS Multi Merchant

Platform payment gateway self-hosted berbasis PHP Native 8.2+ dengan integrasi AldiQRIS untuk pembayaran QRIS. Mendukung multi merchant, wallet management, withdrawal, settlement, dan role-based access control.

## Fitur Utama

- **Multi Merchant** - Kelola banyak merchant dengan wallet, fee, dan settlement terpisah
- **Integrasi AldiQRIS** - Terima pembayaran QRIS otomatis via AldiQRIS API
- **Webhook Real-time** - Notifikasi pembayaran otomatis dengan validasi signature HMAC SHA-256
- **Wallet System** - Available balance, hold balance, ledger mutasi lengkap
- **Withdrawal** - Pencairan dana dengan approval workflow
- **Settlement** - Batch settlement per periode per merchant
- **Fee Engine** - Flexible fee: flat, percentage, atau hybrid per merchant
- **Role-Based Access** - 6 role: Super Admin, Admin, Finance, Support, Merchant, Staff Merchant
- **Merchant API** - REST API dengan Bearer token authentication
- **Audit Log** - Pencatatan semua aktivitas penting
- **Keamanan** - CSRF, password hashing, rate limiting, signature validation

## Tech Stack

- **Backend**: PHP Native 8.2+ (tanpa framework)
- **Frontend**: TailwindCSS CDN + Vanilla JavaScript
- **Storage**: JSON file storage (mudah migrasi ke MySQL)
- **Font**: Inter (Google Fonts)

## Struktur Direktori

```
/
├── admin/                  # Halaman admin panel
│   ├── dashboard.php
│   ├── merchants.php
│   ├── transactions.php
│   ├── withdrawals.php
│   ├── settlements.php
│   ├── webhook-logs.php
│   ├── audit-logs.php
│   └── settings.php
├── merchant/               # Halaman merchant panel
│   ├── dashboard.php
│   ├── create-payment.php
│   ├── transactions.php
│   ├── transaction-detail.php
│   ├── payment-links.php
│   ├── wallet.php
│   ├── withdraw.php
│   ├── withdraw-history.php
│   ├── api-keys.php
│   ├── webhook-settings.php
│   └── profile.php
├── api/                    # Merchant REST API
│   └── index.php
├── app/                    # Application logic
│   ├── Auth.php
│   ├── Helpers.php
│   ├── Router.php
│   ├── Controllers/
│   ├── Services/
│   └── Repositories/
├── config/                 # Configuration
│   ├── app.php
│   └── gateway.php
├── includes/               # Layout & shared views
├── assets/                 # CSS & JavaScript
├── storage/                # JSON data storage
├── index.php               # Landing page
├── login.php               # Login page
├── register.php            # Merchant registration
├── webhook.php             # Webhook endpoint
├── success.php             # Payment success page
├── logout.php              # Logout handler
└── .htaccess               # Apache config & security
```

## Instalasi

### Persyaratan

- PHP 8.2 atau lebih baru
- Ekstensi PHP: `curl`, `json`, `mbstring`, `openssl`
- Web server Apache (dengan mod_rewrite) atau Nginx
- HTTPS (recommended untuk production)

### Langkah Instalasi

1. **Upload file ke hosting**

   Upload seluruh isi folder ke root domain atau subdomain Anda.

2. **Set permission folder storage**

   ```bash
   chmod -R 755 storage/
   chmod 644 storage/*.json
   chmod 644 storage/logs.txt
   ```

3. **Inisialisasi storage (jika belum ada)**

   Buat file JSON kosong jika belum tersedia:
   ```bash
   cd storage/
   for f in users.json merchants.json transactions.json wallets.json \
            withdrawals.json settlements.json webhook_events.json \
            audit_logs.json settings.json wallet_ledger.json notifications.json; do
     [ ! -f "$f" ] && echo "[]" > "$f"
   done
   touch logs.txt
   ```

4. **Buat akun Super Admin**

   Jalankan script berikut dari root project:
   ```bash
   php -r "
   require 'app/Helpers.php';
   \$users = json_decode(file_get_contents('storage/users.json'), true) ?: [];
   \$users[] = [
       'id' => generate_uuid(),
       'merchant_id' => null,
       'name' => 'Super Admin',
       'email' => 'admin@yourdomain.com',
       'password_hash' => password_hash('YOUR_SECURE_PASSWORD', PASSWORD_DEFAULT),
       'role' => 'super_admin',
       'status' => 'active',
       'created_at' => date('Y-m-d H:i:s'),
       'updated_at' => date('Y-m-d H:i:s'),
   ];
   file_put_contents('storage/users.json', json_encode(\$users, JSON_PRETTY_PRINT), LOCK_EX);
   echo 'Super Admin created!';
   "
   ```

5. **Konfigurasi AldiQRIS API Key**

   Edit file `config/gateway.php`:
   ```php
   'aldiqris' => [
       'base_url' => 'https://aldiqris.pages.dev',
       'api_key' => 'gopay_YOUR_API_KEY_HERE',
       // ...
   ],
   ```

   Atau set environment variable:
   ```bash
   export ALDIQRIS_API_KEY="gopay_YOUR_API_KEY_HERE"
   ```

6. **Set App URL**

   Edit `config/app.php` atau set environment variable:
   ```bash
   export APP_URL="https://yourdomain.com"
   ```

### Konfigurasi Nginx (alternatif Apache)

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/paygate;
    index index.php;

    # Deny access to sensitive directories
    location ~ ^/(storage|config|app|includes)/ {
        deny all;
        return 403;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Cara Penggunaan

### Login Admin

- **URL**: `https://yourdomain.com/login.php`
- **Default**: `admin@paygate.local` / `admin123`
- **PENTING**: Ubah password segera setelah instalasi!

### Register Merchant

1. Buka `https://yourdomain.com/register.php`
2. Isi form registrasi (nama bisnis, email, password)
3. Login ke merchant dashboard
4. Admin harus mengaktifkan merchant dari Admin Panel > Merchants

### Set Webhook URL

1. Login sebagai merchant
2. Buka menu **Webhook** di sidebar
3. Masukkan URL webhook yang menerima notifikasi pembayaran
4. Simpan

### Membuat Transaksi (Dashboard)

1. Login sebagai merchant
2. Klik **Buat Pembayaran**
3. Isi amount (wajib) dan informasi lainnya
4. Submit - Payment link akan dihasilkan otomatis
5. Copy payment URL dan kirim ke customer

### Membuat Transaksi (API)

```bash
curl -X POST https://yourdomain.com/api/index.php?action=create_transaction \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": "INV-2026-001",
    "amount": 150000,
    "link_name": "Pembayaran Order #001",
    "webhook_url": "https://yourdomain.com/webhook.php",
    "customer_name": "John Doe",
    "customer_wa": "08123456789",
    "customer_email": "john@email.com"
  }'
```

**Catatan**: Hanya `order_id` dan `amount` yang wajib. Jika `order_id` dikosongkan, sistem akan generate otomatis.

### Menguji Webhook

Webhook dikirim sebagai POST request dengan:
- Body: JSON payload
- Header: `X-Signature` (HMAC SHA-256 dari body menggunakan API key sebagai secret)

Contoh payload webhook dari AldiQRIS:
```json
{
  "transaction_time": "2026-07-07 10:00:00",
  "transaction_status": "settlement",
  "transaction_id": "uuid-here",
  "order_id": "INV-2026-001",
  "gross_amount": "150000.00",
  "status_code": "200"
}
```

Untuk testing manual:
```bash
# Generate signature
PAYLOAD='{"order_id":"INV-2026-001","transaction_status":"settlement","gross_amount":"150000.00"}'
SECRET="YOUR_API_KEY"
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')

# Send webhook
curl -X POST https://yourdomain.com/webhook.php \
  -H "Content-Type: application/json" \
  -H "X-Signature: $SIGNATURE" \
  -d "$PAYLOAD"
```

### Withdrawal (Penarikan Dana)

1. Merchant login dan buka menu **Tarik Dana**
2. Isi jumlah, bank, nomor rekening, dan nama pemilik
3. Submit - dana dipindah ke hold_balance
4. Admin/Finance approve dari Admin Panel > Withdrawals
5. Setelah transfer manual selesai, Admin klik "Mark Success"
6. Dana dipindah dari hold_balance ke withdrawn_balance

### Settlement

1. Admin buka menu **Settlements**
2. Pilih merchant dan periode
3. Klik "Buat Settlement" - sistem menghitung total transaksi PAID
4. Review dan approve settlement
5. Mark complete setelah transfer dilakukan

## Update & Migrasi Database

Setiap kali update kode (git pull / upload versi baru), **wajib** jalankan migrasi
database agar schema sinkron dengan kode:

```bash
php scripts/migrate.php
```

Perintah lain:

```bash
php scripts/migrate.php status     # lihat migrasi sudah/belum diterapkan
php scripts/migrate.php --pretend  # simulasi tanpa mengubah DB
```

- Migrasi bersifat **idempotent** (aman dijalankan berulang) dan dicatat di tabel
  `schema_migrations` sehingga tidak dijalankan ulang.
- Instalasi baru via `install.php` menjalankan migrasi otomatis.
- Cek status schema kapan saja di `GET /api/health.php` (field `database_schema`).

File migrasi berada di `migrations/` dan `database/migrations/` dan dijalankan
berurutan sesuai nama file.

## Merchant API Reference

### Authentication

API menggunakan **satu API key per akun** (berlaku untuk semua proyek). Ambil di
**Pengaturan › API Key**. Sertakan sebagai Bearer token:

```
Authorization: Bearer YOUR_ACCOUNT_API_KEY
```

### Memilih proyek tujuan

Karena satu key dipakai untuk banyak proyek, tentukan proyek tujuan tiap request
via header (atau query param):

```
X-Project-Id: <merchant_id>      # berdasarkan ID proyek
X-Project: <slug>                # atau berdasarkan slug proyek
```

Aturan:
- Jika akun hanya punya **1 proyek**, header ini opsional.
- Jika akun punya **>1 proyek** dan header tidak disertakan, request ditolak `400`
  untuk mencegah salah tujuan.

> **Kompatibilitas:** API key lama per-proyek (`merchants.api_key`) tetap berlaku.
> Untuk key lama, proyek ditentukan otomatis dari key tersebut (tanpa header).

### Webhook Signing Secret

Verifikasi tanda tangan webhook (`X-Signature`) tetap memakai **secret per-proyek**
(bukan API key akun). Ambil di **Project Settings › Webhook › Webhook Signing Secret**.

### Endpoints

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/index.php?action=create_transaction` | Buat transaksi baru |
| GET | `/api/index.php?action=get_transaction&order_id=XXX` | Cek status transaksi |
| GET | `/api/index.php?action=wallet` | Lihat saldo wallet |
| GET | `/api/index.php?action=withdrawals` | Lihat riwayat withdrawal |

Contoh (curl):

```bash
curl -X POST "https://pay.clipku.com/api/v1/transactions" \
  -H "Authorization: Bearer YOUR_ACCOUNT_API_KEY" \
  -H "X-Project-Id: db103f96-6b83-406b-b5e3-2720964d867e" \
  -H "Content-Type: application/json" \
  -d '{"order_id":"INV-001","amount":50000,"customer_name":"Budi"}'
```

### Response Format

```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "order_id": "INV-20260707-ABC123",
    "amount": 150000,
    "fee": 1050,
    "net_amount": 148950,
    "status": "PENDING",
    "payment_url": "https://...",
    "qr_url": "https://...",
    "created_at": "2026-07-07 10:00:00"
  }
}
```

## Roles & Permissions

| Role | Akses |
|------|-------|
| Super Admin | Semua fitur tanpa batasan |
| Admin | Kelola merchant, transaksi, webhook, laporan |
| Finance | Approval withdrawal dan settlement |
| Support | View-only: transaksi, merchant, webhook log, audit log |
| Merchant | Buat transaksi, wallet, withdraw, API key, webhook |
| Staff Merchant | Akses terbatas sesuai assignment merchant |

## Fee Engine

Mendukung 3 model fee:

- **Flat**: Potongan tetap per transaksi (contoh: Rp 1.000)
- **Percentage**: Persentase dari amount (contoh: 0.7%)
- **Hybrid**: Kombinasi percentage + flat (contoh: 0.7% + Rp 500)

Fee dikonfigurasi secara:
- **Global** (Admin > Settings)
- **Per Merchant** (Admin > Merchants > Kelola)

## Backup Storage

### Manual Backup

```bash
# Backup semua data
tar -czf backup_$(date +%Y%m%d_%H%M%S).tar.gz storage/

# Restore
tar -xzf backup_20260707_100000.tar.gz
```

### Automated Backup (Cron)

```bash
# Tambahkan ke crontab
0 2 * * * cd /var/www/paygate && tar -czf /backups/paygate_$(date +\%Y\%m\%d).tar.gz storage/
```

## Keamanan Production

### Checklist Keamanan

- [ ] Ubah password Super Admin default
- [ ] Set `APP_ENV=production` (disable debug)
- [ ] Gunakan HTTPS
- [ ] Set file permission: `storage/` = 755, files = 644
- [ ] Pastikan `.htaccess` aktif (Apache) atau konfigurasi Nginx deny access
- [ ] Jangan expose folder `storage/`, `config/`, `app/`, `includes/`
- [ ] Regenerate API key merchant sebelum live
- [ ] Set `session.cookie_secure = 1` di php.ini
- [ ] Aktifkan `ssl_verify` di `config/gateway.php` untuk production
- [ ] Monitor `storage/logs.txt` secara berkala
- [ ] Backup storage secara rutin

### Environment Variables (Recommended)

```bash
export APP_URL="https://yourdomain.com"
export APP_ENV="production"
export APP_DEBUG="false"
export ALDIQRIS_API_KEY="gopay_your_key_here"
export ALDIQRIS_BASE_URL="https://aldiqris.pages.dev"
```

## Migrasi ke MySQL

Struktur repository dirancang agar mudah migrasi:

1. Buat tabel MySQL sesuai field di masing-masing JSON
2. Ganti implementasi `BaseRepository` untuk menggunakan PDO
3. Setiap repository sudah memiliki method standar (`find`, `create`, `update`, `delete`, `findAll`)
4. Tidak perlu mengubah Services atau Controllers

## Troubleshooting

| Problem | Solusi |
|---------|--------|
| Permission denied storage | `chmod -R 755 storage/ && chmod 644 storage/*.json` |
| Session not working | Pastikan `session.save_path` writable |
| Webhook tidak masuk | Cek URL, pastikan POST, cek Webhook Logs di admin |
| API key tidak valid | Regenerate dari menu API Keys |
| CSRF token expired | Refresh halaman dan submit ulang |
| JSON corrupt | Sistem otomatis restore dari .backup file |

## License

Private - Internal Use Only

---

**Developed with PHP 8.2+ | TailwindCSS | AldiQRIS Integration**
