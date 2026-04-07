<?php

class ClientDocument {

    public static function forClient(int $clientId): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM client_documents WHERE client_id = ? ORDER BY uploaded_at DESC");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public static function forClientDetailed(int $clientId): array {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT cd.id, cd.original_filename, cd.file_path, cd.uploaded_at, u.name AS uploaded_by_name
             FROM client_documents cd
             JOIN users u ON u.id = cd.uploaded_by
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

    public static function create(int $clientId, string $filePath, string $originalName, int $uploadedBy): int {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO client_documents (client_id, file_path, original_filename, uploaded_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$clientId, $filePath, $originalName, $uploadedBy]);
        return (int)$db->lastInsertId();
    }

    public static function findByIds(array $ids): array {
        if (empty($ids)) return [];
        $db = getDB();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT * FROM client_documents WHERE id IN ($placeholders)");
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll();
    }

    public static function docPath(int $clientId): string {
        return UPLOAD_PATH . '/clients/' . $clientId . '/documents';
    }
}
