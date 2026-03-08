<?php

class Stage1Status {
    public static function byPeriod(int $periodId): array {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT s.*, a.account_name 
             FROM stage1_status s 
             JOIN accounts a ON a.id = s.account_id 
             WHERE s.period_id = ? 
             ORDER BY a.account_name"
        );
        $stmt->execute([$periodId]);
        return $stmt->fetchAll();
    }

    public static function find(int $periodId, int $accountId): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM stage1_status WHERE period_id = ? AND account_id = ?");
        $stmt->execute([$periodId, $accountId]);
        return $stmt->fetch() ?: null;
    }

    public static function setGreen(int $periodId, int $accountId): void {
        $db = getDB();
        $stmt = $db->prepare("UPDATE stage1_status SET status='green', last_upload_at=NOW() WHERE period_id=? AND account_id=?");
        $stmt->execute([$periodId, $accountId]);
    }

    public static function setOrange(int $periodId, int $accountId): void {
        $db = getDB();
        $stmt = $db->prepare("UPDATE stage1_status SET status='orange', last_download_at=NOW() WHERE period_id=? AND account_id=?");
        $stmt->execute([$periodId, $accountId]);
    }

    public static function allGrey(int $periodId): bool {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM stage1_status WHERE period_id = ? AND status != 'grey'");
        $stmt->execute([$periodId]);
        return $stmt->fetchColumn() == 0;
    }

    public static function allOrange(int $periodId): bool {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM stage1_status WHERE period_id = ? AND status != 'orange'");
        $stmt->execute([$periodId]);
        return $stmt->fetchColumn() == 0;
    }

    public static function anyGreyOrOrange(int $periodId): bool {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM stage1_status WHERE period_id = ? AND status IN ('grey','orange')");
        $stmt->execute([$periodId]);
        return $stmt->fetchColumn() > 0;
    }

    public static function pendingAccounts(int $periodId): array {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT s.*, a.account_name 
             FROM stage1_status s 
             JOIN accounts a ON a.id = s.account_id 
             WHERE s.period_id = ? AND s.status IN ('grey','orange')
             ORDER BY a.account_name"
        );
        $stmt->execute([$periodId]);
        return $stmt->fetchAll();
    }
}
