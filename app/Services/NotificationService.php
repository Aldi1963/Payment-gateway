<?php
/**
 * Notification Service
 * Handles in-app notifications stored in MySQL
 */

require_once base_path('app/Database.php');

class NotificationService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Send notification to merchant
     */
    public function notifyMerchant(string $merchantId, string $type, string $message, array $data = []): bool
    {
        app_log("Notification [{$type}] to merchant {$merchantId}: {$message}", 'INFO');
        $this->storeNotification($merchantId, $type, $message, $data);
        return true;
    }

    /**
     * Send notification to admin
     */
    public function notifyAdmin(string $type, string $message, array $data = []): bool
    {
        app_log("Admin Notification [{$type}]: {$message}", 'INFO');
        $this->storeNotification('admin', $type, $message, $data);
        return true;
    }

    /**
     * Store notification
     */
    private function storeNotification(string $recipientId, string $type, string $message, array $data = []): void
    {
        $stmt = $this->db->prepare("INSERT INTO `notifications` (`id`,`recipient_id`,`type`,`message`,`data`,`read`,`created_at`) VALUES (:id,:recipient_id,:type,:message,:data,:read,:created_at)");
        $stmt->execute([
            'id' => generate_uuid(),
            'recipient_id' => $recipientId,
            'type' => $type,
            'message' => $message,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'read' => 0,
            'created_at' => now(),
        ]);
    }

    /**
     * Get unread notifications for a user/merchant
     */
    public function getUnread(string $recipientId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM `notifications` WHERE `recipient_id` = :rid AND `read` = 0 ORDER BY `created_at` DESC LIMIT 50");
        $stmt->execute(['rid' => $recipientId]);
        $rows = $stmt->fetchAll() ?: [];
        return array_map(function($r) {
            if (isset($r['data']) && is_string($r['data'])) {
                $r['data'] = json_decode($r['data'], true) ?: [];
            }
            return $r;
        }, $rows);
    }

    /**
     * Mark notification as read
     */
    public function markRead(string $notificationId): void
    {
        $stmt = $this->db->prepare("UPDATE `notifications` SET `read` = 1 WHERE `id` = :id");
        $stmt->execute(['id' => $notificationId]);
    }

    /**
     * Mark all as read for a recipient
     */
    public function markAllRead(string $recipientId): void
    {
        $stmt = $this->db->prepare("UPDATE `notifications` SET `read` = 1 WHERE `recipient_id` = :rid AND `read` = 0");
        $stmt->execute(['rid' => $recipientId]);
    }
}
