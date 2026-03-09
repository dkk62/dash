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

    public static function findByEmail(string $email): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM clients WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name, string $email, string $phone, string $cycleType, ?string $password = null): int {
        $db = getDB();
        $passwordHash = $password ? password_hash($password, PASSWORD_BCRYPT) : null;
        $stmt = $db->prepare("INSERT INTO clients (name, email, phone, password_hash, cycle_type) VALUES (?,?,?,?,?)");
        $stmt->execute([$name, $email, $phone, $passwordHash, $cycleType]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, string $name, string $email, string $phone, string $cycleType, ?string $password = null): void {
        $db = getDB();
        if ($password !== null) {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE clients SET name=?, email=?, phone=?, cycle_type=?, password_hash=? WHERE id=?");
            $stmt->execute([$name, $email, $phone, $cycleType, $passwordHash, $id]);
        } else {
            $stmt = $db->prepare("UPDATE clients SET name=?, email=?, phone=?, cycle_type=? WHERE id=?");
            $stmt->execute([$name, $email, $phone, $cycleType, $id]);
        }
    }

    public static function updatePasswordOnly(int $id, string $password): void {
        $db = getDB();
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE clients SET password_hash=? WHERE id=?");
        $stmt->execute([$passwordHash, $id]);
    }

    public static function delete(int $id): void {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM clients WHERE id=?");
        $stmt->execute([$id]);
    }
}
