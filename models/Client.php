<?php

class Client {
    public static function all(): array {
        $db = getDB();
        return $db->query("SELECT * FROM clients ORDER BY name")->fetchAll();
    }

    public static function find(int $id): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name, string $email, string $phone, string $cycleType): int {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO clients (name, email, phone, cycle_type) VALUES (?,?,?,?)");
        $stmt->execute([$name, $email, $phone, $cycleType]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, string $name, string $email, string $phone, string $cycleType): void {
        $db = getDB();
        $stmt = $db->prepare("UPDATE clients SET name=?, email=?, phone=?, cycle_type=? WHERE id=?");
        $stmt->execute([$name, $email, $phone, $cycleType, $id]);
    }

    public static function delete(int $id): void {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM clients WHERE id=?");
        $stmt->execute([$id]);
    }
}
