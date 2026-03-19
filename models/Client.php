<?php

class Client {
    public static function all(): array {
        $db = getDB();
        return $db->query("
            SELECT c.*,
                   u0.name AS processor0_name,
                   u1.name AS processor1_name
            FROM clients c
            LEFT JOIN users u0 ON u0.id = c.processor0_id
            LEFT JOIN users u1 ON u1.id = c.processor1_id
            ORDER BY c.name
        ")->fetchAll();
    }

    public static function find(int $id): ?array {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT c.*,
                   u0.name AS processor0_name,
                   u1.name AS processor1_name
            FROM clients c
            LEFT JOIN users u0 ON u0.id = c.processor0_id
            LEFT JOIN users u1 ON u1.id = c.processor1_id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM clients WHERE email = ?");
        $stmt->execute([trim(strtolower($email))]);
        return $stmt->fetch() ?: null;
    }

    public static function findAllByEmail(string $email): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM clients WHERE email = ? ORDER BY id");
        $stmt->execute([trim(strtolower($email))]);
        return $stmt->fetchAll();
    }

    public static function emailExists(string $email, ?int $excludeId = null): bool {
        $db = getDB();
        $normalizedEmail = trim(strtolower($email));

        if ($excludeId !== null) {
            $stmt = $db->prepare("SELECT id FROM clients WHERE email = ? AND id <> ? LIMIT 1");
            $stmt->execute([$normalizedEmail, $excludeId]);
        } else {
            $stmt = $db->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
            $stmt->execute([$normalizedEmail]);
        }

        return (bool) $stmt->fetch();
    }

    public static function create(string $name, string $email, string $phone, string $cycleType, ?string $password = null, ?int $processor0Id = null, ?int $processor1Id = null): int {
        $db = getDB();
        $email = trim(strtolower($email));
        $passwordHash = $password ? password_hash($password, PASSWORD_BCRYPT) : null;
        try {
            $stmt = $db->prepare("INSERT INTO clients (name, email, phone, password_hash, cycle_type, processor0_id, processor1_id) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$name, $email, $phone, $passwordHash, $cycleType, $processor0Id, $processor1Id]);
            return (int) $db->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new RuntimeException('A client with this email already exists.', 0, $e);
            }
            throw $e;
        }
    }

    public static function update(int $id, string $name, string $email, string $phone, string $cycleType, ?string $password = null, ?int $processor0Id = null, ?int $processor1Id = null): void {
        $db = getDB();
        $email = trim(strtolower($email));
        try {
            if ($password !== null) {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE clients SET name=?, email=?, phone=?, cycle_type=?, password_hash=?, processor0_id=?, processor1_id=? WHERE id=?");
                $stmt->execute([$name, $email, $phone, $cycleType, $passwordHash, $processor0Id, $processor1Id, $id]);
            } else {
                $stmt = $db->prepare("UPDATE clients SET name=?, email=?, phone=?, cycle_type=?, processor0_id=?, processor1_id=? WHERE id=?");
                $stmt->execute([$name, $email, $phone, $cycleType, $processor0Id, $processor1Id, $id]);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new RuntimeException('A client with this email already exists.', 0, $e);
            }
            throw $e;
        }
    }

    public static function getClientIdsForUser(int $userId): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM clients WHERE processor0_id = ? OR processor1_id = ?");
        $stmt->execute([$userId, $userId]);
        return array_column($stmt->fetchAll(), 'id');
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
