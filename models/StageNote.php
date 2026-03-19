<?php

class StageNote {
    /**
     * Upsert a note for a stage entry.
     * For stage1: account_id = actual account id.
     * For stage2/3/4: account_id = 0.
     */
    public static function save(int $periodId, string $stage, int $accountId, string $note, int $updatedBy): void {
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO stage_notes (period_id, stage_name, account_id, note, updated_by, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE note=VALUES(note), updated_by=VALUES(updated_by), updated_at=NOW()"
        );
        $stmt->execute([$periodId, $stage, $accountId, $note, $updatedBy]);
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
     */
    public static function allNonEmpty(): array {
        $db = getDB();
        $stmt = $db->query(
            "SELECT sn.period_id, sn.stage_name, sn.account_id, sn.note,
                    p.period_label, p.client_id, c.name AS client_name,
                    a.account_name
             FROM stage_notes sn
             JOIN periods p ON p.id = sn.period_id
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN accounts a ON a.id = sn.account_id AND sn.account_id > 0
             WHERE sn.note != ''
             ORDER BY c.name, p.period_label, sn.stage_name, sn.account_id"
        );
        return $stmt->fetchAll();
    }
}
