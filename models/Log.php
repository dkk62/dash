<?php

class LogModel {
    /**
     * Returns the most recent 'reminder_sent' timestamp for each supplied client ID.
     * Result is keyed by client_id => 'created_at' string (or null if never sent).
     */
    public static function lastReminderByClients(array $clientIds): array {
        if (empty($clientIds)) {
            return [];
        }
        $db = getDB();
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $stmt = $db->prepare(
            "SELECT client_id, MAX(created_at) AS last_sent
             FROM logs
             WHERE action = 'reminder_sent' AND client_id IN ($placeholders)
             GROUP BY client_id"
        );
        $stmt->execute(array_values($clientIds));
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['client_id']] = $row['last_sent'];
        }
        return $result;
    }

    public static function recent(int $limit = 100, ?int $periodId = null, ?string $actionFilter = null): array {
        $db = getDB();
        $sql = "SELECT l.*, u.name AS user_name, p.period_label, c.name AS client_name
                FROM logs l
                JOIN users u ON u.id = l.user_id
                LEFT JOIN periods p ON p.id = l.period_id
                LEFT JOIN clients c ON c.id = p.client_id
                WHERE 1=1";
        $params = [];

        if ($periodId) {
            $sql .= " AND l.period_id = ?";
            $params[] = $periodId;
        }
        if ($actionFilter) {
            $sql .= " AND l.action = ?";
            $params[] = $actionFilter;
        }

        $sql .= " ORDER BY l.created_at DESC LIMIT " . (int) $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
