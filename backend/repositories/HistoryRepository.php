<?php
// backend/repositories/HistoryRepository.php

class HistoryRepository {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function log(int $contactId, int $userId, string $action): void {
        $stmt = $this->db->prepare(
            "INSERT INTO contact_history (contact_id, user_id, action, created_at)
             VALUES (:contact_id, :user_id, :action, NOW())"
        );
        $stmt->execute([
            ':contact_id' => $contactId,
            ':user_id'    => $userId,
            ':action'     => $action,
        ]);
    }

    public function findByContact(int $contactId): array {
        $stmt = $this->db->prepare(
            "SELECT h.id, h.contact_id, h.user_id, h.action, h.created_at,
                    u.username
             FROM contact_history h
             JOIN users u ON u.id = h.user_id
             WHERE h.contact_id = :contact_id
             ORDER BY h.created_at DESC
             LIMIT 100"
        );
        $stmt->execute([':contact_id' => $contactId]);
        return $stmt->fetchAll();
    }
}
