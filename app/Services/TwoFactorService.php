<?php
/**
 * Two-Factor Authentication Service
 * TOTP (Time-based One-Time Password) + Backup Codes
 * 
 * Uses HMAC-SHA1 based TOTP (RFC 6238) compatible with Google Authenticator
 */

require_once base_path('app/Database.php');

class TwoFactorService
{
    private PDO $db;
    private int $codeLength = 6;
    private int $timeStep = 30; // seconds

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Generate a new secret for user
     */
    public function generateSecret(): string
    {
        // 20 bytes = 160 bits, base32 encoded
        $bytes = random_bytes(20);
        return $this->base32Encode($bytes);
    }

    /**
     * Generate backup codes (8 single-use codes)
     */
    public function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))); // 8-char hex codes
        }
        return $codes;
    }

    /**
     * Enable 2FA for user
     */
    public function enable(string $userId, string $secret, string $verifyCode): array
    {
        // Verify the code first to ensure user has set up their authenticator
        if (!$this->verifyCode($secret, $verifyCode)) {
            return ['success' => false, 'message' => 'Kode OTP tidak valid. Pastikan waktu perangkat Anda akurat.'];
        }

        $backupCodes = $this->generateBackupCodes();

        $this->db->prepare("UPDATE `users` SET `two_factor_secret`=:secret, `two_factor_backup_codes`=:codes, `two_factor_enabled`=1, `updated_at`=:now WHERE `id`=:id")
            ->execute([
                'secret' => $secret,
                'codes' => json_encode($backupCodes),
                'now' => now(),
                'id' => $userId,
            ]);

        return ['success' => true, 'message' => '2FA berhasil diaktifkan.', 'backup_codes' => $backupCodes];
    }


    /**
     * Disable 2FA for user
     */
    public function disable(string $userId, string $password): array
    {
        require_once base_path('app/Repositories/UserRepository.php');
        $userRepo = new UserRepository();
        $user = $userRepo->find($userId);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Password tidak valid.'];
        }

        $this->db->prepare("UPDATE `users` SET `two_factor_secret`=NULL, `two_factor_backup_codes`=NULL, `two_factor_enabled`=0, `updated_at`=:now WHERE `id`=:id")
            ->execute(['now' => now(), 'id' => $userId]);

        return ['success' => true, 'message' => '2FA berhasil dinonaktifkan.'];
    }

    /**
     * Verify OTP code during login
     */
    public function verify(string $userId, string $code): bool
    {
        $stmt = $this->db->prepare("SELECT `two_factor_secret`, `two_factor_backup_codes` FROM `users` WHERE `id`=:id AND `two_factor_enabled`=1");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        if (!$user) return false;

        // Check TOTP code (allow 1 step tolerance for clock drift)
        if ($this->verifyCode($user['two_factor_secret'], $code)) {
            return true;
        }

        // Check backup codes
        $backupCodes = json_decode($user['two_factor_backup_codes'] ?? '[]', true);
        $codeIndex = array_search(strtoupper($code), $backupCodes);
        if ($codeIndex !== false) {
            // Remove used backup code
            unset($backupCodes[$codeIndex]);
            $this->db->prepare("UPDATE `users` SET `two_factor_backup_codes`=:codes WHERE `id`=:id")
                ->execute(['codes' => json_encode(array_values($backupCodes)), 'id' => $userId]);
            return true;
        }

        return false;
    }

    /**
     * Check if user has 2FA enabled
     */
    public function isEnabled(string $userId): bool
    {
        $stmt = $this->db->prepare("SELECT `two_factor_enabled` FROM `users` WHERE `id`=:id");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        return $row && (int)($row['two_factor_enabled'] ?? 0) === 1;
    }

    /**
     * Generate provisioning URI for QR code (Google Authenticator format)
     */
    public function getProvisioningUri(string $secret, string $email): string
    {
        $issuer = setting('app_name', 'Clipku Pay');
        $encodedIssuer = rawurlencode($issuer);
        $encodedEmail = rawurlencode($email);
        return "otpauth://totp/{$encodedIssuer}:{$encodedEmail}?secret={$secret}&issuer={$encodedIssuer}&digits={$this->codeLength}&period={$this->timeStep}";
    }

    /**
     * Verify TOTP code against secret (with +-1 step tolerance)
     */
    private function verifyCode(string $secret, string $code): bool
    {
        $timeSlice = (int)floor(time() / $this->timeStep);

        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = $this->generateTotp($secret, $timeSlice + $i);
            if (hash_equals($calculatedCode, str_pad($code, $this->codeLength, '0', STR_PAD_LEFT))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate TOTP code for a time slice
     */
    private function generateTotp(string $secret, int $timeSlice): string
    {
        $secretBytes = $this->base32Decode($secret);
        $timeBytes = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $timeBytes, $secretBytes, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % pow(10, $this->codeLength);
        return str_pad((string)$code, $this->codeLength, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) { $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT); }
        $result = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $result .= $alphabet[bindec($chunk)];
        }
        return $result;
    }

    private function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split(strtoupper($data)) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos !== false) $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) $result .= chr(bindec($byte));
        }
        return $result;
    }
}
