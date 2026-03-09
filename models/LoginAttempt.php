<?php

class LoginAttempt {
    private const MAX_ATTEMPTS = 3;
    private const LOCKOUT_SECONDS = 3600; // 1 hour
    private static bool $tableChecked = false;

    private static function ensureTable(): void {
        if (self::$tableChecked) {
            return;
        }

        $db = getDB();
        $db->exec(
            "CREATE TABLE IF NOT EXISTS login_attempts (
                id INT(11) NOT NULL AUTO_INCREMENT,
                identifier VARCHAR(191) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                attempt_count INT(11) NOT NULL DEFAULT 0,
                first_attempt_at DATETIME NOT NULL,
                last_attempt_at DATETIME NOT NULL,
                locked_until DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_identifier_ip (identifier, ip_address),
                KEY idx_locked_until (locked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$tableChecked = true;
    }

    private static function normalizeIdentifier(string $identifier): string {
        return strtolower(trim($identifier));
    }

    public static function getState(string $identifier, string $ipAddress): array {
        self::ensureTable();

        $identifier = self::normalizeIdentifier($identifier);
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM login_attempts WHERE identifier = ? AND ip_address = ? LIMIT 1");
        $stmt->execute([$identifier, $ipAddress]);
        $row = $stmt->fetch();

        if (!$row) {
            return [
                'is_locked' => false,
                'attempt_count' => 0,
                'remaining_seconds' => 0,
            ];
        }

        $now = time();
        $firstAttemptTs = strtotime((string) $row['first_attempt_at']) ?: $now;
        $lockedUntilTs = !empty($row['locked_until']) ? (strtotime((string) $row['locked_until']) ?: 0) : 0;

        // Expired attempt windows are reset automatically.
        if ($lockedUntilTs > 0 && $now >= $lockedUntilTs) {
            self::clear($identifier, $ipAddress);
            return [
                'is_locked' => false,
                'attempt_count' => 0,
                'remaining_seconds' => 0,
            ];
        }

        if ($lockedUntilTs > $now) {
            return [
                'is_locked' => true,
                'attempt_count' => (int) $row['attempt_count'],
                'remaining_seconds' => $lockedUntilTs - $now,
            ];
        }

        if (($now - $firstAttemptTs) >= self::LOCKOUT_SECONDS) {
            self::clear($identifier, $ipAddress);
            return [
                'is_locked' => false,
                'attempt_count' => 0,
                'remaining_seconds' => 0,
            ];
        }

        return [
            'is_locked' => false,
            'attempt_count' => (int) $row['attempt_count'],
            'remaining_seconds' => 0,
        ];
    }

    public static function recordFailure(string $identifier, string $ipAddress): array {
        self::ensureTable();

        $identifier = self::normalizeIdentifier($identifier);
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM login_attempts WHERE identifier = ? AND ip_address = ? LIMIT 1");
        $stmt->execute([$identifier, $ipAddress]);
        $row = $stmt->fetch();

        $now = new DateTimeImmutable('now');

        if (!$row) {
            $stmt = $db->prepare(
                "INSERT INTO login_attempts (identifier, ip_address, attempt_count, first_attempt_at, last_attempt_at, locked_until)
                 VALUES (?,?,?,?,?,NULL)"
            );
            $stmt->execute([
                $identifier,
                $ipAddress,
                1,
                $now->format('Y-m-d H:i:s'),
                $now->format('Y-m-d H:i:s'),
            ]);

            return [
                'is_locked' => false,
                'attempt_count' => 1,
                'remaining_seconds' => 0,
            ];
        }

        $firstAttemptAt = new DateTimeImmutable((string) $row['first_attempt_at']);
        $attemptCount = (int) $row['attempt_count'];

        if (($now->getTimestamp() - $firstAttemptAt->getTimestamp()) >= self::LOCKOUT_SECONDS) {
            $attemptCount = 0;
            $firstAttemptAt = $now;
        }

        $attemptCount++;
        $lockedUntil = null;

        if ($attemptCount >= self::MAX_ATTEMPTS) {
            $lockedUntil = $now->modify('+1 hour')->format('Y-m-d H:i:s');
        }

        $stmt = $db->prepare(
            "UPDATE login_attempts
             SET attempt_count = ?, first_attempt_at = ?, last_attempt_at = ?, locked_until = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $attemptCount,
            $firstAttemptAt->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s'),
            $lockedUntil,
            (int) $row['id'],
        ]);

        return [
            'is_locked' => $lockedUntil !== null,
            'attempt_count' => $attemptCount,
            'remaining_seconds' => $lockedUntil ? (strtotime($lockedUntil) - $now->getTimestamp()) : 0,
        ];
    }

    public static function clear(string $identifier, string $ipAddress): void {
        self::ensureTable();

        $identifier = self::normalizeIdentifier($identifier);
        $db = getDB();

        $stmt = $db->prepare("DELETE FROM login_attempts WHERE identifier = ? AND ip_address = ?");
        $stmt->execute([$identifier, $ipAddress]);
    }
}
