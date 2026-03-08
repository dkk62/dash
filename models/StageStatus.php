<?php

class StageStatus {
    public static function byPeriod(int $periodId): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM stage_status WHERE period_id = ? ORDER BY stage_name");
        $stmt->execute([$periodId]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['stage_name']] = $row;
        }
        return $result;
    }

    public static function find(int $periodId, string $stageName): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM stage_status WHERE period_id = ? AND stage_name = ?");
        $stmt->execute([$periodId, $stageName]);
        return $stmt->fetch() ?: null;
    }

    public static function setGreen(int $periodId, string $stageName): void {
        $db = getDB();
        $stmt = $db->prepare("UPDATE stage_status SET status='green', last_upload_at=NOW() WHERE period_id=? AND stage_name=?");
        $stmt->execute([$periodId, $stageName]);
    }

    public static function setOrange(int $periodId, string $stageName): void {
        $db = getDB();
        $stmt = $db->prepare("UPDATE stage_status SET status='orange', last_download_at=NOW() WHERE period_id=? AND stage_name=?");
        $stmt->execute([$periodId, $stageName]);
    }

    public static function resetToGrey(int $periodId, string $stageName): void {
        $db = getDB();
        $stmt = $db->prepare("UPDATE stage_status SET status='grey', last_upload_at=NULL, last_download_at=NULL WHERE period_id=? AND stage_name=?");
        $stmt->execute([$periodId, $stageName]);
    }

    public static function allOrange(int $periodId): bool {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM stage_status WHERE period_id = ? AND status != 'orange'");
        $stmt->execute([$periodId]);
        return $stmt->fetchColumn() == 0;
    }
}
