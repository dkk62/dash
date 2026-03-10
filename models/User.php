<?php

class User {
    public static function findByEmail(string $email): ?array {
        $db = getDB();
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            // If the users table is missing, allow auth flow to continue and fail gracefully.
            if ($e->getCode() === '42S02') {
                error_log('User lookup failed: users table is missing.');
                return null;
            }
            throw $e;
        }
    }

    public static function findById(int $id): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name, string $email, string $password, string $role): int {
        $db = getDB();
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)");
        $stmt->execute([$name, $email, $hash, $role]);
        return (int) $db->lastInsertId();
    }

    public static function all(): array {
        $db = getDB();
        return $db->query("SELECT id, name, email, role, created_at FROM users ORDER BY name")->fetchAll();
    }

    public static function byRole(string $role): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE role = ? ORDER BY name");
        $stmt->execute([$role]);
        return $stmt->fetchAll();
    }
}
