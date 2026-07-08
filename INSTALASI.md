# Panduan Instalasi Clipku Pay di cPanel Hosting

Panduan ini menjelaskan langkah-langkah instalasi aplikasi Clipku Pay di shared hosting yang menggunakan cPanel. Membutuhkan PHP 8.2+ dan MySQL/MariaDB. Tidak membutuhkan akses SSH atau Composer.

---

## Persyaratan Sistem

| Komponen | Minimum | Keterangan |
|----------|---------|------------|
| PHP | 8.2 atau lebih baru | **Wajib**. Cek di cPanel > Select PHP Version |
| MySQL/MariaDB | 5.7+ / 10.3+ | **Wajib**. Buat database di cPanel > MySQL Databases |
| Web Server | Apache + mod_rewrite | Sudah termasuk di semua cPanel hosting |
| Ekstensi PHP | curl, json, mbstring, openssl, pdo, pdo_mysql | Pastikan aktif di Select PHP Version |
| Composer | **Tidak perlu** | Tidak ada dependency external |
| SSH | **Tidak perlu** | Semua bisa dilakukan via File Manager & phpMyAdmin |
| Disk Space | Minimal 50 MB | Untuk file aplikasi |

---

## Langkah 1: Download Source Code

Download source code dari repository GitHub:

```
https://github.com/Aldi1963/Payment-gateway
```

Pilih salah satu cara:
- Klik tombol hijau **Code** > **Download ZIP**
- Atau clone via git jika memiliki akses SSH

---

## Langkah 2: Cek dan Set PHP Version

1. Login ke **cPanel**
2. Cari dan buka **Select PHP Version** (atau "MultiPHP Manager")
3. Pilih domain/subdomain Anda
4. Set PHP version ke **8.2** atau lebih baru (8.3, 8.4 juga supported)
5. Klik tab **Extensions**, pastikan yang berikut aktif (centang):
   - `curl`
   - `json` (biasanya built-in, tidak perlu dicentang)
   - `mbstring`
   - `openssl`
   - `pdo`
   - `pdo_mysql`
6. Klik **Save**

> **Catatan:** Jika hosting Anda hanya menyediakan PHP 8.1 atau lebih rendah, aplikasi ini tidak akan berjalan. Hubungi provider hosting untuk upgrade.

---

## Langkah 3: Buat Database MySQL

1. Buka **cPanel > MySQL Databases**
2. Di bagian **Create New Database**, masukkan nama database (misal: `paygate`)
3. Klik **Create Database**
4. Di bagian **Add New User**, buat user baru:
   - Username: (misal: `paygate_user`)
   - Password: (buat password yang kuat)
5. Klik **Create User**
6. Di bagian **Add User To Database**:
   - Pilih user yang baru dibuat
   - Pilih database yang baru dibuat
   - Klik **Add**
7. Di halaman privileges, centang **ALL PRIVILEGES**
8. Klik **Make Changes**

> **Catat** nama database, username, dan password — akan dibutuhkan di langkah installer.
> Di cPanel, nama lengkap biasanya: `cpanelusername_namadb` dan `cpanelusername_namauser`.

---

## Langkah 4: Upload File ke Hosting

1. Buka **cPanel > File Manager**
2. Masuk ke folder tujuan instalasi:
   - Untuk domain utama: `public_html/`
   - Untuk subdomain (misal `pay.domain.com`): `public_html/pay/` atau sesuai konfigurasi subdomain
   - Untuk addon domain: folder yang sudah ditentukan saat membuat addon domain
3. **Upload** file ZIP yang sudah didownload
4. Klik kanan pada file ZIP > **Extract**
5. Pastikan semua file berada langsung di folder tujuan (bukan di subfolder `Payment-gateway-main/`)

**Struktur yang BENAR setelah upload:**

```
public_html/          (atau folder subdomain Anda)
├── .htaccess         ← HARUS ada di root
├── index.php
├── login.php
├── register.php
├── install.php
├── webhook.php
├── pay.php
├── cron.php
├── export.php
├── verify.php
├── success.php
├── logout.php
├── admin/
│   ├── dashboard.php
│   ├── settings.php
│   └── ... (file admin lainnya)
├── merchant/
│   ├── dashboard.php
│   └── ... (file merchant lainnya)
├── api/
│   └── index.php
├── app/
│   ├── Auth.php
│   ├── Helpers.php
│   ├── Controllers/
│   ├── Services/
│   └── Repositories/
├── config/
│   ├── app.php
│   └── gateway.php
├── includes/
│   ├── init.php
│   └── ... (layout files)
├── assets/
│   ├── app.js
│   └── style.css
└── storage/
    ├── users.json
    ├── merchants.json
    ├── transactions.json
    └── ... (file JSON lainnya)
```

> **PENTING:** Jika setelah extract file berada di dalam subfolder (misalnya `public_html/Payment-gateway-main/`), pindahkan semua isi folder tersebut ke `public_html/` langsung. File `.htaccess` dan `index.php` harus berada di root folder domain.

---

## Langkah 4: Set Permission Folder Storage

Folder `storage/` harus bisa ditulis oleh PHP.

### Via File Manager:
1. Klik kanan folder `storage/`
2. Pilih **Change Permissions**
3. Set ke `755` (atau `775` jika 755 tidak bekerja)
4. Centang **Recurse into subdirectories**
5. Klik **Change Permissions**

### Via Terminal (jika tersedia):
```bash
chmod -R 755 storage/
```

> Jika setelah instalasi muncul error "Permission denied" saat menyimpan data, ubah permission ke `775` atau `777` (kurang aman, tapi beberapa hosting memerlukan ini).

---

## Langkah 5: Buka Halaman Installer

**PENTING:** Sebelum membuka installer, Anda perlu mengizinkan akses ke `install.php` terlebih dahulu.

### 5a. Edit .htaccess (sementara)

1. Buka **File Manager**
2. Klik kanan file `.htaccess` > **Edit**
3. Cari baris berikut:
   ```
   RewriteRule ^install\.php$ - [F,L]
   ```
4. Tambahkan tanda `#` di depannya untuk menonaktifkan (comment out):
   ```
   # RewriteRule ^install\.php$ - [F,L]
   ```
5. Klik **Save Changes**

### 5b. Buka Installer di Browser

Buka URL berikut di browser:

```
https://domainanda.com/install.php
```

Anda akan melihat halaman instalasi dengan:
- **System Check** — menampilkan status PHP version, storage writable, dan extensions
- **Form pembuatan Super Admin**

### 5c. Isi Form Admin

| Field | Isi dengan |
|-------|-----------|
| Nama Admin | Nama Anda (contoh: `Admin Clipku`) |
| Email Admin | Email valid (contoh: `admin@domainanda.com`) |
| Password Admin | Minimal 8 karakter, harus mengandung huruf DAN angka |

Klik **Install & Buat Admin**

Jika berhasil, akan muncul pesan "Instalasi Berhasil!" dengan link ke halaman login.

---

## Langkah 6: Amankan Setelah Instalasi

### 6a. Aktifkan kembali proteksi install.php

1. Buka **File Manager**
2. Edit `.htaccess`
3. Hapus tanda `#` yang tadi ditambahkan:
   ```
   RewriteRule ^install\.php$ - [F,L]
   ```
4. **Save Changes**

### 6b. Hapus file install.php (SANGAT DIREKOMENDASIKAN)

1. Di File Manager, klik kanan `install.php`
2. Pilih **Delete**
3. Konfirmasi hapus

> Jika `install.php` tidak dihapus dan proteksi `.htaccess` dimatikan, siapapun bisa mengakses halaman installer dan membuat admin baru.

---

## Langkah 7: Login dan Konfigurasi Awal

1. Buka `https://domainanda.com/login.php`
2. Login dengan email dan password yang dibuat di langkah 5
3. Anda akan masuk ke **Admin Dashboard**

### Konfigurasi yang perlu dilakukan:

#### a. Set App URL (wajib)
1. Buka **Settings** (menu sidebar)
2. Tab **Umum**
3. Isi field **App URL** dengan URL lengkap domain Anda:
   ```
   https://domainanda.com
   ```
   (tanpa trailing slash `/`)
4. Klik **Simpan**

#### b. Konfigurasi Gateway API AldiQRIS (wajib)
1. Buka **Settings** > Tab **Gateway API**
2. Isi:
   - **Base URL:** `https://aldiqris.pages.dev`
   - **API Key:** API key dari AldiQRIS Anda (format: `gopay_xxxxx`)
   - **Endpoint:** `/api/trx` (default, jangan ubah)
   - **Timeout:** `30` (detik)
   - **SSL Verification:** centang (aktifkan)
3. Klik **Simpan**

#### c. Atur Fee Default (opsional)
1. Buka **Settings** > Tab **Fee & Transaksi**
2. Atur default fee untuk merchant baru
3. Atau buka **Fee Engine** di sidebar untuk membuat fee rules yang lebih kompleks

---

## Langkah 8: Setup Cron Job (Opsional tapi Direkomendasikan)

Cron job diperlukan untuk:
- Otomatis expire transaksi PENDING yang lewat batas waktu
- Proses antrian webhook retry
- Bersihkan file rate limit yang expired

### Setup di cPanel:

1. Buka **cPanel > Cron Jobs**
2. Di bagian **Add New Cron Job**:
   - **Common Settings:** Once Per Minute (`* * * * *`)
   - **Command:**
     ```
     /usr/local/bin/php /home/USERNAME/public_html/cron.php all >> /home/USERNAME/public_html/storage/cron.log 2>&1
     ```
3. Klik **Add New Cron Job**

> **PENTING:** Ganti `USERNAME` dengan username cPanel Anda. Ganti `/public_html/` dengan path yang sesuai jika menginstall di subdomain.

### Cara mengetahui path yang benar:

1. Buka **File Manager**
2. Navigasi ke folder instalasi
3. Lihat di bagian atas, path ditampilkan (contoh: `/home/abc123/public_html/`)

### Cara mengetahui path PHP:

Buat file `phppath.php` dengan isi:
```php
<?php echo PHP_BINARY; ?>
```
Upload dan buka di browser. Outputnya adalah path PHP yang benar (biasanya `/usr/local/bin/php` atau `/opt/cpanel/ea-php82/root/usr/bin/php`).

Hapus file `phppath.php` setelah selesai.

---

## Langkah 9: Setup SSL (HTTPS)

### Menggunakan Let's Encrypt (Gratis):
1. Buka **cPanel > SSL/TLS Status** atau **Let's Encrypt**
2. Pilih domain Anda
3. Klik **Issue** atau **Install**
4. Tunggu hingga sertifikat terpasang

### Setelah SSL aktif:
1. Edit `.htaccess`
2. Cari baris:
   ```
   # Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
   ```
3. Hapus tanda `#` untuk mengaktifkan HSTS:
   ```
   Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
   ```
4. Klik **Save**

### Force HTTPS (opsional):
Tambahkan di bagian paling atas `.htaccess` (sebelum `RewriteEngine On`):
```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## Langkah 10: Verifikasi Instalasi

Buka URL berikut dan pastikan berfungsi:

| URL | Yang Seharusnya Muncul |
|-----|----------------------|
| `https://domainanda.com/` | Landing page Clipku Pay |
| `https://domainanda.com/login.php` | Form login |
| `https://domainanda.com/register.php` | Form registrasi merchant |
| `https://domainanda.com/admin/dashboard.php` | Dashboard admin (setelah login) |
| `https://domainanda.com/webhook.php` | Response JSON (jika POST) atau 405 |
| `https://domainanda.com/storage/` | **403 Forbidden** (artinya proteksi bekerja) |
| `https://domainanda.com/config/` | **403 Forbidden** (artinya proteksi bekerja) |
| `https://domainanda.com/app/` | **403 Forbidden** (artinya proteksi bekerja) |

---

## Troubleshooting

### Error 500 (Internal Server Error)

**Penyebab umum:**
- `.htaccess` tidak kompatibel dengan konfigurasi hosting
- PHP version tidak sesuai

**Solusi:**
1. Cek **Error Logs** di cPanel
2. Pastikan PHP version 8.2+
3. Jika masih error, coba hapus baris `php_value` di `.htaccess` bagian bawah (beberapa hosting tidak mengizinkan `php_value` di `.htaccess`)

### Halaman Blank (Putih)

**Penyebab:** Error PHP tapi display_errors dimatikan.

**Solusi:**
1. Buka cPanel > **Select PHP Version** > **Options**
2. Set `display_errors` ke `On` sementara
3. Refresh halaman untuk melihat error
4. Perbaiki masalahnya
5. Kembalikan `display_errors` ke `Off`

### Error "Permission denied" saat simpan data

**Solusi:**
```
Folder storage/ → Permission 775 atau 777
File *.json di dalam storage/ → Permission 664 atau 666
```

### Login redirect loop / Session error

**Penyebab:** Session save path tidak writable.

**Solusi:**
1. Buka cPanel > **Select PHP Version** > **Options**
2. Pastikan `session.save_path` menunjuk ke folder yang writable
3. Atau tambahkan di bagian atas `.htaccess`:
   ```
   php_value session.save_path "/home/USERNAME/tmp"
   ```
   (buat folder `tmp` jika belum ada, permission 700)

### .htaccess tidak bekerja (URL rewrite error)

**Solusi:**
1. Pastikan mod_rewrite aktif (cPanel biasanya sudah aktif)
2. Buat file `.htaccess` test:
   ```
   RewriteEngine On
   RewriteRule ^test$ index.php [L]
   ```
3. Jika tetap error, hubungi hosting provider untuk memastikan AllowOverride All aktif

### Webhook tidak masuk

**Checklist:**
1. Pastikan webhook URL bisa diakses dari internet
2. Pastikan merchant sudah set webhook URL di dashboard
3. Cek **Admin > Webhook Logs** untuk melihat incoming webhooks
4. Pastikan AldiQRIS mengirim webhook ke URL yang benar
5. Test webhook menggunakan tool di **Merchant > Test Webhook**

### Cron job tidak berjalan

**Checklist:**
1. Pastikan path PHP benar (cek dengan `which php` atau buat file phppath.php)
2. Pastikan path file `cron.php` benar (full absolute path)
3. Cek file `storage/cron.log` untuk output
4. Coba jalankan manual via Terminal cPanel:
   ```bash
   php /home/USERNAME/public_html/cron.php all
   ```

---

## Update Aplikasi & Migrasi Database (WAJIB setelah update)

Setiap kali menarik kode baru (git pull / upload versi baru), **wajib** menjalankan
migrasi database agar struktur tabel sinkron dengan kode. Jika tidak, halaman
tertentu bisa error 500 karena kode memakai tabel/kolom yang belum ada.

### Cara menjalankan migrasi

**Via SSH / Terminal cPanel** (direkomendasikan):

```bash
cd /home/USERNAME/public_html
php scripts/migrate.php
```

Output contoh:
```
Applied 1 migration(s):
  - 2026_07_multi_project.sql (12 run, 0 skipped)
Already applied: 2 migration(s).
```

### Perintah yang tersedia

| Perintah | Fungsi |
|----------|--------|
| `php scripts/migrate.php` | Jalankan semua migrasi yang belum diterapkan |
| `php scripts/migrate.php status` | Lihat daftar migrasi (sudah/belum diterapkan) |
| `php scripts/migrate.php --pretend` | Simulasi (lihat yang akan dijalankan, tanpa mengubah DB) |

### Catatan penting

- Migration **aman dijalankan berulang** (idempotent). Menjalankan dua kali tidak
  akan menyebabkan error "Duplicate column" — statement yang objeknya sudah ada
  otomatis dilewati.
- Migrasi yang sudah dijalankan dicatat di tabel `schema_migrations`, sehingga
  tidak akan dijalankan ulang.
- Saat instalasi baru via `install.php`, migrasi otomatis dijalankan — Anda tidak
  perlu menjalankannya manual pada instalasi pertama.
- Jika hosting **tidak punya akses SSH**, jalankan migrasi lewat installer ulang
  atau minta provider menjalankan `php scripts/migrate.php`. Anda juga bisa
  mengecek status schema di endpoint `GET /api/health.php` (lihat bagian
  `database_schema`).

### Cek kesehatan schema

Buka `https://domainanda.com/api/health.php`. Bagian `database_schema` menunjukkan
apakah ada migrasi yang tertunda:

```json
"database_schema": {
    "status": "unhealthy",
    "pending_migrations": ["2026_07_multi_project.sql"],
    "message": "Schema drift detected. Run: php scripts/migrate.php"
}
```

Jika `status` = `healthy`, schema sudah sinkron.

---

## Instalasi di Subdomain

Jika ingin menginstall di subdomain (misalnya `pay.domainanda.com`):

1. Buat subdomain di **cPanel > Subdomains**
2. Catat **Document Root** yang ditampilkan (misal: `/home/user/public_html/pay`)
3. Upload semua file ke folder tersebut
4. Ikuti langkah yang sama seperti di atas
5. Gunakan URL subdomain untuk semua akses (https://pay.domainanda.com)

---

## Setelah Instalasi Berhasil

Langkah selanjutnya:

1. **Register Merchant** — Buka `/register.php` untuk mendaftarkan merchant pertama
2. **Aktivasi Merchant** — Login admin, buka **Merchants**, klik Detail, ubah status ke Active
3. **Set Webhook** — Merchant login, buka **Webhook & URL**, ajukan webhook URL
4. **Buat Transaksi** — Merchant buka **Buat Pembayaran**, isi form, dapatkan payment link
5. **Test Pembayaran** — Buka payment link yang dihasilkan, bayar via QRIS

---

## Keamanan Production Checklist

Sebelum go-live, pastikan:

- [ ] File `install.php` sudah dihapus
- [ ] SSL/HTTPS sudah aktif
- [ ] Password admin sudah kuat (huruf + angka + simbol)
- [ ] App URL sudah di-set benar di Settings
- [ ] AldiQRIS API key sudah dikonfigurasi
- [ ] Folder `storage/`, `config/`, `app/`, `includes/` return 403 saat diakses via browser
- [ ] Cron job sudah disetup
- [ ] HSTS header sudah diaktifkan (uncomment di .htaccess)
- [ ] Backup rutin sudah dijadwalkan

---

## Backup & Restore

### Backup manual:
1. Download seluruh folder `storage/` via File Manager
2. Simpan di tempat aman

### Backup otomatis (via Cron):
```
0 2 * * * tar -czf /home/USERNAME/backups/paygate_$(date +\%Y\%m\%d).tar.gz /home/USERNAME/public_html/storage/
```

### Restore:
Upload kembali file-file JSON dari backup ke folder `storage/`.

---

## Kontak & Support

Jika mengalami masalah yang tidak tercakup di panduan ini, periksa:
- Error log di cPanel
- File `storage/logs.txt` untuk log aplikasi
- Webhook logs di Admin Dashboard

---

*Dokumen ini dibuat untuk Clipku Pay v1.0 — Payment Gateway SaaS Multi Merchant*
