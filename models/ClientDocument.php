<?php

class ClientDocument {

    public static function baseDocPath(int $clientId): string {
        return UPLOAD_PATH . '/clients/' . $clientId . '/documents';
    }

    public static function forClient(int $clientId): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM client_documents WHERE client_id = ? ORDER BY uploaded_at DESC");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public static function forClientDetailed(int $clientId): array {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT cd.id, cd.original_filename, cd.file_path, cd.uploaded_at, cd.uploaded_by_type,
                    COALESCE(u.name, c.name) AS uploaded_by_name
             FROM client_documents cd
             LEFT JOIN users u ON u.id = cd.uploaded_by AND cd.uploaded_by_type = 'user'
             LEFT JOIN clients c ON c.id = cd.uploaded_by AND cd.uploaded_by_type = 'client'
             WHERE cd.client_id = ?
             ORDER BY cd.uploaded_at DESC"
        );
        $stmt->execute([$clientId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            unset($row['file_path']);
        }
        return $rows;
    }

    public static function hasFiles(int $clientId): bool {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM client_documents WHERE client_id = ?");
        $stmt->execute([$clientId]);
        return $stmt->fetchColumn() > 0;
    }

    public static function bulkHasFiles(array $clientIds): array {
        if (empty($clientIds)) return [];
        $db = getDB();
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $stmt = $db->prepare("SELECT DISTINCT client_id FROM client_documents WHERE client_id IN ($placeholders)");
        $stmt->execute($clientIds);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int)$row['client_id']] = true;
        }
        return $result;
    }

    public static function create(int $clientId, string $filePath, string $originalName, int $uploadedBy, string $uploadedByType = 'user'): int {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO client_documents (client_id, file_path, original_filename, uploaded_by, uploaded_by_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$clientId, $filePath, $originalName, $uploadedBy, $uploadedByType]);
        return (int)$db->lastInsertId();
    }

    public static function findById(int $id): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM client_documents WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByIds(array $ids): array {
        if (empty($ids)) return [];
        $db = getDB();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT * FROM client_documents WHERE id IN ($placeholders)");
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll();
    }

    public static function docPath(int $clientId, ?string $dateFolder = null): string {
        $dateFolder = $dateFolder ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFolder)) {
            $dateFolder = date('Y-m-d');
        }

        return self::baseDocPath($clientId) . '/' . $dateFolder;
    }

    public static function forClientGroupedByDate(int $clientId): array {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT DATE(uploaded_at) AS upload_date, COUNT(*) AS file_count
             FROM client_documents
             WHERE client_id = ?
             GROUP BY DATE(uploaded_at)
             ORDER BY upload_date DESC"
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public static function forClientOnDate(int $clientId, string $date): array {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT cd.id, cd.original_filename, cd.uploaded_at, cd.downloaded_at, cd.uploaded_by_type,
                    COALESCE(u.name, c.name) AS uploaded_by_name
             FROM client_documents cd
             LEFT JOIN users u ON u.id = cd.uploaded_by AND cd.uploaded_by_type = 'user'
             LEFT JOIN clients c ON c.id = cd.uploaded_by AND cd.uploaded_by_type = 'client'
             WHERE cd.client_id = ? AND DATE(cd.uploaded_at) = ?
             ORDER BY cd.uploaded_at ASC"
        );
        $stmt->execute([$clientId, $date]);
        return $stmt->fetchAll();
    }

    public static function bulkCountsForClients(array $clientIds): array {
        if (empty($clientIds)) return [];
        $db = getDB();
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $stmt = $db->prepare(
            "SELECT client_id,
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN downloaded_at IS NULL THEN 1 ELSE 0 END) AS not_downloaded_count
             FROM client_documents
             WHERE client_id IN ($placeholders)
             GROUP BY client_id"
        );
        $stmt->execute($clientIds);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['client_id']] = [
                'total'          => (int) $row['total_count'],
                'not_downloaded' => (int) $row['not_downloaded_count'],
            ];
        }
        return $result;
    }

    public static function markDownloaded(array $ids): void {
        if (empty($ids)) return;
        $db = getDB();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "UPDATE client_documents SET downloaded_at = NOW() WHERE id IN ($placeholders) AND downloaded_at IS NULL"
        );
        $stmt->execute(array_values($ids));
    }
}
