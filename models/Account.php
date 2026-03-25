<?php

class Account {
    public static function byClient(int $clientId): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM accounts WHERE client_id = ? AND is_active = 1 ORDER BY account_name");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $clientId, string $name, string $bankFeedMode = 'manual'): int {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO accounts (client_id, account_name, bank_feed_mode) VALUES (?,?,?)");
        $stmt->execute([$clientId, $name, $bankFeedMode]);
        $accountId = (int) $db->lastInsertId();

        // Ensure this new account appears in stage1_status for existing periods.
        $status = $bankFeedMode === 'automatic' ? 'orange' : 'grey';
        $periodStmt = $db->prepare("SELECT id FROM periods WHERE client_id = ?");
        $periodStmt->execute([$clientId]);
        $periodIds = $periodStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($periodIds)) {
            $insertS1 = $db->prepare("INSERT INTO stage1_status (period_id, account_id, status) VALUES (?,?,?)");
            foreach ($periodIds as $periodId) {
                $insertS1->execute([(int) $periodId, $accountId, $status]);
            }
        }

        return $accountId;
    }

    public static function update(int $id, string $name, bool $isActive, string $bankFeedMode = 'manual'): void {
        $db = getDB();
        $stmt = $db->prepare("UPDATE accounts SET account_name=?, is_active=?, bank_feed_mode=? WHERE id=?");
        $stmt->execute([$name, $isActive ? 1 : 0, $bankFeedMode, $id]);

        if ($bankFeedMode === 'automatic') {
            // Automatic bank feed should unblock stage1 and mark it orange.
            $updateS1 = $db->prepare("UPDATE stage1_status SET status='orange', last_download_at=NOW() WHERE account_id=?");
            $updateS1->execute([$id]);
        }
    }

    public static function delete(int $id): void {
        // Remove uploaded stage1 files for this account across all periods
        $account = self::find($id);
        if ($account) {
            $clientId = (int) $account['client_id'];
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM periods WHERE client_id = ?");
            $stmt->execute([$clientId]);
            $periodIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($periodIds as $periodId) {
                $dir = UPLOAD_PATH . '/clients/' . $clientId . '/' . $periodId . '/stage1/' . $id;
                deleteDirectory($dir);
            }
            $delStmt = $db->prepare("DELETE FROM accounts WHERE id=?");
            $delStmt->execute([$id]);
        } else {
            $db = getDB();
            $stmt = $db->prepare("DELETE FROM accounts WHERE id=?");
            $stmt->execute([$id]);
        }
    }
}
