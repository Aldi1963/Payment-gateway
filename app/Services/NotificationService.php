<?php
/**
 * Notification Service
 * Handles sending notifications (placeholder for future implementation)
 */

class NotificationService
{
    /**
     * Send notification to merchant
     */
    public function notifyMerchant(string $merchantId, string $type, string $message, array $data = []): bool
    {
        // Log the notification attempt
        app_log("Notification [{$type}] to merchant {$merchantId}: {$message}", 'INFO');
        
        // Store in-app notification
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
     * Store notification for in-app display
     */
    private function storeNotification(string $recipientId, string $type, string $message, array $data = []): void
    {
        $notifications = $this->loadNotifications();
        
        $notifications[] = [
            'id' => generate_uuid(),
            'recipient_id' => $recipientId,
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'read' => false,
            'created_at' => now(),
        ];

        // Keep only last 100 notifications per recipient
        $grouped = [];
        foreach ($notifications as $n) {
            $grouped[$n['recipient_id']][] = $n;
        }
        
        $result = [];
        foreach ($grouped as $recipientNotifs) {
            $result = array_merge($result, array_slice($recipientNotifs, -100));
        }

        $this->saveNotifications($result);
    }

    /**
     * Get unread notifications for a user/merchant
     */
    public function getUnread(string $recipientId): array
    {
        $notifications = $this->loadNotifications();
        return array_values(array_filter($notifications, fn($n) => 
            $n['recipient_id'] === $recipientId && !$n['read']
        ));
    }

    /**
     * Mark notification as read
     */
    public function markRead(string $notificationId): void
    {
        $notifications = $this->loadNotifications();
        foreach ($notifications as &$n) {
            if ($n['id'] === $notificationId) {
                $n['read'] = true;
                break;
            }
        }
        $this->saveNotifications($notifications);
    }

    private function loadNotifications(): array
    {
        $file = storage_path('notifications.json');
        if (!file_exists($file)) return [];
        $content = file_get_contents($file);
        return json_decode($content, true) ?: [];
    }

    private function saveNotifications(array $data): void
    {
        $file = storage_path('notifications.json');
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
