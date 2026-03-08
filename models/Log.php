<?php

class LogModel {
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
