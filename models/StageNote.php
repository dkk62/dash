<?php

class StageNote {
    /**
     * Append a chat message to the note for a stage entry.
     * Notes are stored as a JSON array: [{"by":"Name","at":"datetime","msg":"text"}, ...]
     * Legacy plain-text notes are migrated into the JSON format automatically.
     */
    public static function append(int $periodId, string $stage, int $accountId, string $message, int $userId): array {
        $db = getDB();
        $userName = '';
        $uStmt = $db->prepare("SELECT name FROM users WHERE id=?");
        $uStmt->execute([$userId]);
        $row = $uStmt->fetch();
        if ($row) $userName = $row['name'];

        // Load existing note
        $stmt = $db->prepare(
            "SELECT note FROM stage_notes WHERE period_id=? AND stage_name=? AND account_id=?"
        );
        $stmt->execute([$periodId, $stage, $accountId]);
        $existing = $stmt->fetchColumn();

        $entries = self::parseEntries($existing ?: '');

        $newEntry = [
            'by'  => $userName,
            'at'  => date('Y-m-d H:i'),
            'msg' => $message,
        ];
        $entries[] = $newEntry;

        $json = json_encode($entries, JSON_UNESCAPED_UNICODE);

        $save = $db->prepare(
            "INSERT INTO stage_notes (period_id, stage_name, account_id, note, updated_by, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE note=VALUES(note), updated_by=VALUES(updated_by), updated_at=NOW()"
        );
        $save->execute([$periodId, $stage, $accountId, $json, $userId]);

        return $newEntry;
    }

    /**
     * Parse stored note value into entries array.
     * Handles both JSON array format and legacy plain-text.
     */
    public static function parseEntries(string $raw): array {
        $raw = trim($raw);
        if ($raw === '') return [];

        $decoded = json_decode($raw, true);
        if (is_array($decoded) && (empty($decoded) || isset($decoded[0]['msg']))) {
            return $decoded;
        }

        // Legacy plain-text note — wrap as a single entry
        return [['by' => 'System', 'at' => '', 'msg' => $raw]];
    }

    /**
     * Check whether a note has any entries (for icon highlight).
     */
    public static function hasEntries(string $raw): bool {
        return !empty(self::parseEntries($raw));
    }

    /**
     * Get the latest message text for display (e.g. digest emails).
     */
    public static function latestMessage(string $raw): string {
        $entries = self::parseEntries($raw);
        if (empty($entries)) return '';
        $last = end($entries);
        return $last['msg'] ?? '';
    }

    /**
     * Bulk load notes for multiple periods.
     * Returns [periodId => [stageKey => noteText]]
     * stageKey = stage_name . '_' . account_id
     */
    public static function bulkByPeriods(array $periodIds): array {
        if (empty($periodIds)) return [];
        $db = getDB();
        $placeholders = implode(',', array_fill(0, count($periodIds), '?'));
        $stmt = $db->prepare(
            "SELECT period_id, stage_name, account_id, note
             FROM stage_notes
             WHERE period_id IN ($placeholders) AND note != ''"
        );
        $stmt->execute($periodIds);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['stage_name'] . '_' . (int)$row['account_id'];
            $result[(int)$row['period_id']][$key] = $row['note'];
        }
        return $result;
    }

    /**
     * Fetch all non-empty notes with client/period/account context for the digest.
     * If $sinceDate is provided (Y-m-d), only notes updated on or after that date are returned.
     */
    public static function allNonEmpty(?string $sinceDate = null): array {
        $db = getDB();
        $sql = "SELECT sn.period_id, sn.stage_name, sn.account_id, sn.note,
                       p.period_label, p.client_id, c.name AS client_name,
                       a.account_name
                FROM stage_notes sn
                JOIN periods p ON p.id = sn.period_id
                JOIN clients c ON c.id = p.client_id
                LEFT JOIN accounts a ON a.id = sn.account_id AND sn.account_id > 0
                WHERE sn.note != ''";
        $params = [];
        if ($sinceDate !== null) {
            $sql .= " AND DATE(sn.updated_at) = ?";
            $params[] = $sinceDate;
        }
        $sql .= " ORDER BY c.name, p.period_label, sn.stage_name, sn.account_id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
