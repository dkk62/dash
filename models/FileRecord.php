<?php

class FileRecord {
    public static function forStage(int $periodId, string $stage, ?int $accountId = null): array {
        $db = getDB();
        if ($stage === 'stage1' && $accountId !== null) {
            $stmt = $db->prepare("SELECT * FROM files WHERE period_id=? AND stage_name=? AND account_id=?");
            $stmt->execute([$periodId, $stage, $accountId]);
        } else {
            $stmt = $db->prepare("SELECT * FROM files WHERE period_id=? AND stage_name=? AND account_id IS NULL");
            $stmt->execute([$periodId, $stage]);
        }
        return $stmt->fetchAll();
    }

    public static function deleteForStage(int $periodId, string $stage, ?int $accountId = null): void {
        $db = getDB();
        if ($stage === 'stage1' && $accountId !== null) {
            $stmt = $db->prepare("DELETE FROM files WHERE period_id=? AND stage_name=? AND account_id=?");
            $stmt->execute([$periodId, $stage, $accountId]);
        } else {
            $stmt = $db->prepare("DELETE FROM files WHERE period_id=? AND stage_name=?");
            $stmt->execute([$periodId, $stage]);
        }
    }

    public static function create(int $periodId, string $stage, ?int $accountId, string $filePath, string $originalName, int $uploadedBy): int {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO files (period_id, stage_name, account_id, file_path, original_filename, uploaded_by) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$periodId, $stage, $accountId, $filePath, $originalName, $uploadedBy]);
        return (int) $db->lastInsertId();
    }

    public static function hasFile(int $periodId, string $stage, ?int $accountId = null): bool {
        $db = getDB();
        if ($stage === 'stage1' && $accountId !== null) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM files WHERE period_id=? AND stage_name=? AND account_id=?");
            $stmt->execute([$periodId, $stage, $accountId]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM files WHERE period_id=? AND stage_name=?");
            $stmt->execute([$periodId, $stage]);
        }
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Load file presence data for multiple periods in one query.
     * Returns [periodId => ['stage1' => [accountId => true], 'stage2' => true, ...]]
     */
    public static function bulkHasFiles(array $periodIds): array {
        if (empty($periodIds)) return [];
        $db = getDB();
        $placeholders = implode(',', array_fill(0, count($periodIds), '?'));
        $stmt = $db->prepare("SELECT period_id, stage_name, account_id FROM files WHERE period_id IN ($placeholders)");
        $stmt->execute($periodIds);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $pid   = (int)$row['period_id'];
            $stage = $row['stage_name'];
            $aid   = $row['account_id'];
            if ($stage === 'stage1') {
                if (!isset($result[$pid]['stage1']) || !is_array($result[$pid]['stage1'])) {
                    $result[$pid]['stage1'] = [];
                }
                $result[$pid]['stage1'][(int)$aid] = true;
            } else {
                $result[$pid][$stage] = true;
            }
        }
        return $result;
    }

    public static function forStageDetailed(int $periodId, string $stage, ?int $accountId = null): array {
        $db = getDB();
        if ($stage === 'stage1' && $accountId !== null) {
            $stmt = $db->prepare(
                "SELECT f.id AS file_id, f.original_filename, f.file_path, f.uploaded_at, u.name AS uploaded_by_name
                 FROM files f
                 JOIN users u ON u.id = f.uploaded_by
                 WHERE f.period_id=? AND f.stage_name=? AND f.account_id=?
                 ORDER BY f.uploaded_at DESC"
            );
            $stmt->execute([$periodId, $stage, $accountId]);
        } else {
            $stmt = $db->prepare(
                "SELECT f.id AS file_id, f.original_filename, f.file_path, f.uploaded_at, u.name AS uploaded_by_name
                 FROM files f
                 JOIN users u ON u.id = f.uploaded_by
                 WHERE f.period_id=? AND f.stage_name=? AND f.account_id IS NULL
                 ORDER BY f.uploaded_at DESC"
            );
            $stmt->execute([$periodId, $stage]);
        }
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            unset($row['file_path']);
        }
        return $rows;
    }

    public static function findById(int $id): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function getFirst(int $periodId, string $stage, ?int $accountId = null): ?array {
        $db = getDB();
        if ($stage === 'stage1' && $accountId !== null) {
            $stmt = $db->prepare("SELECT * FROM files WHERE period_id=? AND stage_name=? AND account_id=? ORDER BY uploaded_at DESC LIMIT 1");
            $stmt->execute([$periodId, $stage, $accountId]);
        } else {
            $stmt = $db->prepare("SELECT * FROM files WHERE period_id=? AND stage_name=? ORDER BY uploaded_at DESC LIMIT 1");
            $stmt->execute([$periodId, $stage]);
        }
        return $stmt->fetch() ?: null;
    }
}
