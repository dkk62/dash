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
