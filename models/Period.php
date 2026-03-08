<?php

class Period {
    public static function byClient(int $clientId): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM periods WHERE client_id = ? ORDER BY created_at DESC");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM periods WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $clientId, string $label): int {
        $db = getDB();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO periods (client_id, period_label) VALUES (?,?)");
            $stmt->execute([$clientId, $label]);
            $periodId = (int) $db->lastInsertId();

            // Auto-create stage1_status for each active account
            $accounts = Account::byClient($clientId);
            $ins = $db->prepare("INSERT INTO stage1_status (period_id, account_id) VALUES (?,?)");
            foreach ($accounts as $acc) {
                $ins->execute([$periodId, $acc['id']]);
            }

            // Auto-create stage_status for stages 2-4
            $ins2 = $db->prepare("INSERT INTO stage_status (period_id, stage_name) VALUES (?,?)");
            foreach (['stage2', 'stage3', 'stage4'] as $stage) {
                $ins2->execute([$periodId, $stage]);
            }

            $db->commit();
            return $periodId;
        } catch (\Exception $ex) {
            $db->rollBack();
            throw $ex;
        }
    }

    public static function lock(int $id): void {
        $db = getDB();
        $stmt = $db->prepare("UPDATE periods SET is_locked = 1 WHERE id = ?");
        $stmt->execute([$id]);
    }

    public static function delete(int $id): void {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM periods WHERE id = ?");
        $stmt->execute([$id]);
    }

    public static function allWithClient(): array {
        $db = getDB();
        return $db->query("SELECT p.*, c.name AS client_name, c.email AS client_email, c.id AS client_id 
                           FROM periods p 
                           JOIN clients c ON c.id = p.client_id 
                           ORDER BY c.name, p.created_at DESC")->fetchAll();
    }
}
