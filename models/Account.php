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

    public static function create(int $clientId, string $name): int {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO accounts (client_id, account_name) VALUES (?,?)");
        $stmt->execute([$clientId, $name]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, string $name, bool $isActive): void {
        $db = getDB();
        $stmt = $db->prepare("UPDATE accounts SET account_name=?, is_active=? WHERE id=?");
        $stmt->execute([$name, $isActive ? 1 : 0, $id]);
    }

    public static function delete(int $id): void {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM accounts WHERE id=?");
        $stmt->execute([$id]);
    }
}
