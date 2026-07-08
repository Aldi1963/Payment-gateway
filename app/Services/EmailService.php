<?php
/**
 * Email Service
 * Sends emails via SMTP (PHP mail() fallback)
 * 
 * Supports:
 * - Registration verification
 * - Payment notifications
 * - Withdrawal notifications
 * - Password reset
 */

class EmailService
{
    private string $fromEmail;
    private string $fromName;
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = setting('notif_email_enabled', '0') === '1';
        $this->fromEmail = setting('notif_email_from', 'noreply@paygate.local');
        $this->fromName = setting('app_name', 'Clipku Pay');
    }

    /**
     * Send verification email
     */
    public function sendVerification(string $to, string $name, string $token): bool
    {
        $appUrl = setting('app_url', app_url(''));
        $verifyUrl = rtrim($appUrl, '/') . '/verify.php?token=' . urlencode($token);
        
        $subject = "Verifikasi Email - {$this->fromName}";
        $body = $this->buildHtml("
            <h2>Halo {$name}!</h2>
            <p>Terima kasih telah mendaftar di {$this->fromName}.</p>
            <p>Klik tombol di bawah untuk memverifikasi email Anda:</p>
            <p style='text-align:center;margin:30px 0'>
                <a href='{$verifyUrl}' style='background:#2563eb;color:#fff;padding:12px 30px;text-decoration:none;border-radius:8px;font-weight:600'>
                    Verifikasi Email
                </a>
            </p>
            <p style='font-size:12px;color:#64748b'>Atau copy link ini: {$verifyUrl}</p>
            <p style='font-size:12px;color:#94a3b8'>Link berlaku 24 jam.</p>
        ");

        return $this->send($to, $subject, $body);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordReset(string $to, string $name, string $token): bool
    {
        $appUrl = setting('app_url', app_url(''));
        $resetUrl = rtrim($appUrl, '/') . '/reset-password.php?token=' . urlencode($token) . '&email=' . urlencode($to);
        
        $subject = "Reset Password - {$this->fromName}";
        $body = $this->buildHtml("
            <h2>Reset Password</h2>
            <p>Halo " . e($name) . ",</p>
            <p>Anda menerima email ini karena ada permintaan reset password untuk akun Anda.</p>
            <p style='text-align:center;margin:30px 0'>
                <a href='{$resetUrl}' style='background:#2563eb;color:#fff;padding:12px 30px;text-decoration:none;border-radius:8px;font-weight:600'>
                    Reset Password
                </a>
            </p>
            <p style='font-size:12px;color:#64748b'>Atau copy link ini: {$resetUrl}</p>
            <p style='font-size:12px;color:#94a3b8'>Link berlaku 1 jam. Abaikan email ini jika Anda tidak meminta reset password.</p>
        ");

        return $this->send($to, $subject, $body);
    }

    /**
     * Send payment notification to merchant
     */
    public function sendPaymentNotification(string $to, array $transaction): bool
    {
        if (!$this->enabled) return false;
        
        $subject = "Pembayaran Berhasil - {$transaction['order_id']}";
        $body = $this->buildHtml("
            <h2>Pembayaran Diterima!</h2>
            <table style='width:100%;border-collapse:collapse;margin:20px 0'>
                <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#64748b'>Order ID</td><td style='padding:8px;border-bottom:1px solid #e2e8f0;font-weight:600'>{$transaction['order_id']}</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#64748b'>Amount</td><td style='padding:8px;border-bottom:1px solid #e2e8f0;font-weight:600;color:#059669'>" . format_currency($transaction['amount']) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#64748b'>Fee</td><td style='padding:8px;border-bottom:1px solid #e2e8f0'>" . format_currency($transaction['fee'] ?? 0) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#64748b'>Net</td><td style='padding:8px;border-bottom:1px solid #e2e8f0;font-weight:600'>" . format_currency($transaction['net_amount'] ?? 0) . "</td></tr>
                <tr><td style='padding:8px;color:#64748b'>Customer</td><td style='padding:8px'>" . e($transaction['customer_name'] ?? '-') . "</td></tr>
            </table>
        ");

        return $this->send($to, $subject, $body);
    }

    /**
     * Send withdrawal status notification
     */
    public function sendWithdrawalNotification(string $to, array $withdrawal, string $status): bool
    {
        if (!$this->enabled) return false;
        
        $statusLabel = match($status) {
            'APPROVED' => 'Disetujui',
            'SUCCESS' => 'Berhasil Ditransfer',
            'REJECTED' => 'Ditolak',
            default => $status,
        };
        
        $subject = "Withdrawal {$statusLabel} - " . format_currency($withdrawal['amount']);
        $body = $this->buildHtml("
            <h2>Status Withdrawal: {$statusLabel}</h2>
            <p>Withdrawal Anda sebesar <strong>" . format_currency($withdrawal['amount']) . "</strong> telah {$statusLabel}.</p>
            <table style='width:100%;border-collapse:collapse;margin:20px 0'>
                <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#64748b'>Bank</td><td style='padding:8px;border-bottom:1px solid #e2e8f0'>{$withdrawal['bank_name']}</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#64748b'>Rekening</td><td style='padding:8px;border-bottom:1px solid #e2e8f0'>{$withdrawal['account_number']}</td></tr>
                <tr><td style='padding:8px;color:#64748b'>Atas Nama</td><td style='padding:8px'>{$withdrawal['account_name']}</td></tr>
            </table>
            " . (!empty($withdrawal['admin_note']) ? "<p style='color:#dc2626'>Catatan: {$withdrawal['admin_note']}</p>" : "") . "
        ");

        return $this->send($to, $subject, $body);
    }

    /**
     * Send generic email
     * Uses SMTP if configured, falls back to PHP mail()
     */
    public function send(string $to, string $subject, string $htmlBody): bool
    {
        $smtpHost = setting('smtp_host', '');
        $smtpPort = (int)setting('smtp_port', 587);
        $smtpUser = setting('smtp_username', '');
        $smtpPass = setting('smtp_password', '');
        $smtpEncryption = setting('smtp_encryption', 'tls'); // tls, ssl, or empty

        // Use SMTP if configured
        if (!empty($smtpHost) && !empty($smtpUser)) {
            $result = $this->sendViaSMTP($to, $subject, $htmlBody, $smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpEncryption);
        } else {
            // Fallback to PHP mail()
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                "From: {$this->fromName} <{$this->fromEmail}>",
                "Reply-To: {$this->fromEmail}",
                'X-Mailer: ClipkuPay/1.0',
            ];
            $result = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
        }

        if ($result) {
            app_log("Email sent to {$to}: {$subject}", 'INFO');
        } else {
            app_log("Email FAILED to {$to}: {$subject}", 'ERROR');
        }

        return $result;
    }

    /**
     * Send email via SMTP directly (no external library needed)
     */
    private function sendViaSMTP(string $to, string $subject, string $body, string $host, int $port, string $user, string $pass, string $encryption): bool
    {
        try {
            $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
            $socket = @stream_socket_client("{$prefix}{$host}:{$port}", $errno, $errstr, 10);
            if (!$socket) {
                app_log("SMTP connection failed: {$errstr} ({$errno})", 'ERROR');
                return false;
            }

            $this->smtpRead($socket);
            $this->smtpSend($socket, "EHLO " . gethostname());

            // STARTTLS for TLS encryption
            if ($encryption === 'tls') {
                $this->smtpSend($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
                $this->smtpSend($socket, "EHLO " . gethostname());
            }

            // Authentication
            $this->smtpSend($socket, "AUTH LOGIN");
            $this->smtpSend($socket, base64_encode($user));
            $this->smtpSend($socket, base64_encode($pass));

            // Envelope
            $this->smtpSend($socket, "MAIL FROM:<{$this->fromEmail}>");
            $this->smtpSend($socket, "RCPT TO:<{$to}>");
            $this->smtpSend($socket, "DATA");

            // Headers + Body
            $message = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $message .= "To: {$to}\r\n";
            $message .= "Subject: {$subject}\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "X-Mailer: ClipkuPay/1.0\r\n";
            $message .= "\r\n";
            $message .= $body;
            $message .= "\r\n.";
            
            $this->smtpSend($socket, $message);
            $this->smtpSend($socket, "QUIT");
            fclose($socket);

            return true;
        } catch (\Throwable $e) {
            app_log("SMTP error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    private function smtpSend($socket, string $command): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->smtpRead($socket);
    }

    private function smtpRead($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $response;
    }

    /**
     * Build HTML email template
     */
    private function buildHtml(string $content): string
    {
        $appName = e($this->fromName);
        return "<!DOCTYPE html><html><head><meta charset='utf-8'></head><body style='font-family:Inter,Arial,sans-serif;margin:0;padding:0;background:#f1f5f9'>
        <div style='max-width:600px;margin:0 auto;padding:20px'>
            <div style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1)'>
                <div style='background:linear-gradient(135deg,#2563eb,#1d4ed8);padding:20px 30px;text-align:center'>
                    <h1 style='color:#fff;margin:0;font-size:20px'>{$appName}</h1>
                </div>
                <div style='padding:30px'>{$content}</div>
                <div style='padding:15px 30px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0'>
                    <p style='margin:0;font-size:11px;color:#94a3b8'>&copy; " . date('Y') . " {$appName}. All rights reserved.</p>
                </div>
            </div>
        </div></body></html>";
    }
}
