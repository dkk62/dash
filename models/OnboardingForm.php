<?php

class OnboardingForm {

    public static function findByClientId(int $clientId): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM client_onboarding WHERE client_id = ?");
        $stmt->execute([$clientId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['form_data'] = json_decode($row['form_data'], true) ?: [];
        return $row;
    }

    public static function save(int $clientId, array $formData, string $status = 'draft'): int {
        $db = getDB();
        $existing = self::findByClientId($clientId);
        $json = json_encode($formData, JSON_UNESCAPED_UNICODE);

        if ($existing) {
            $sql = "UPDATE client_onboarding SET form_data = ?, status = ?, submitted_at = ?, updated_at = NOW() WHERE client_id = ?";
            $submittedAt = ($status === 'submitted') ? date('Y-m-d H:i:s') : $existing['submitted_at'];
            $db->prepare($sql)->execute([$json, $status, $submittedAt, $clientId]);
            return (int) $existing['id'];
        }

        $sql = "INSERT INTO client_onboarding (client_id, form_data, status, submitted_at) VALUES (?, ?, ?, ?)";
        $submittedAt = ($status === 'submitted') ? date('Y-m-d H:i:s') : null;
        $db->prepare($sql)->execute([$clientId, $json, $status, $submittedAt]);
        return (int) $db->lastInsertId();
    }

    public static function markReviewed(int $clientId, int $reviewedBy): void {
        $db = getDB();
        $db->prepare("UPDATE client_onboarding SET status = 'reviewed', reviewed_at = NOW(), reviewed_by = ? WHERE client_id = ?")
           ->execute([$reviewedBy, $clientId]);
    }

    public static function allSubmitted(): array {
        $db = getDB();
        $stmt = $db->query(
            "SELECT co.*, c.name AS client_name, c.email AS client_email
             FROM client_onboarding co
             JOIN clients c ON c.id = co.client_id
             WHERE co.status IN ('submitted','reviewed')
             ORDER BY co.submitted_at DESC"
        );
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['form_data'] = json_decode($row['form_data'], true) ?: [];
        }
        return $rows;
    }
}
