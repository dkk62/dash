<?php

class NotificationQueue {
    public static function enqueue(
        string $targetRole,
        string $stage,
        string $clientName,
        string $periodLabel,
        ?string $accountName,
        string $uploadedBy,
        array $fileNames
    ): void {
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO notification_queue
                (target_role, stage, client_name, period_label, account_name, uploaded_by, file_names)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $targetRole,
            $stage,
            $clientName,
            $periodLabel,
            $accountName,
            $uploadedBy,
            json_encode($fileNames),
        ]);
    }

    /** Return all unsent rows grouped by target_role. */
    public static function fetchUnsent(): array {
        $db = getDB();
        $stmt = $db->query(
            "SELECT * FROM notification_queue WHERE sent_at IS NULL ORDER BY target_role, queued_at"
        );
        return $stmt->fetchAll();
    }

    /** Mark a list of IDs as sent. */
    public static function markSent(array $ids): void {
        if (empty($ids)) {
            return;
        }
        $db = getDB();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare(
            "UPDATE notification_queue SET sent_at = NOW() WHERE id IN ($placeholders)"
        )->execute($ids);
    }
}
