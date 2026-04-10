<?php
// backend/repositories/GroupRepository.php

require_once __DIR__ . '/../entities/Group.php';

class GroupRepository {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function save(Group $group): Group {
        if ($group->id === 0) {
            $stmt = $this->db->prepare(
                "INSERT INTO groups_table (user_id, name) VALUES (:user_id, :name)"
            );
            $stmt->execute([':user_id' => $group->userId, ':name' => $group->name]);
            $group->id = (int)$this->db->lastInsertId();
        } else {
            $stmt = $this->db->prepare(
                "UPDATE groups_table SET name=:name WHERE id=:id AND user_id=:user_id"
            );
            $stmt->execute([':name' => $group->name, ':id' => $group->id, ':user_id' => $group->userId]);
        }
        return $group;
    }

    public function delete(int $id, int $userId): bool {
        $stmt = $this->db->prepare("DELETE FROM groups_table WHERE id=:id AND user_id=:user_id");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function findById(int $id, int $userId): ?Group {
        $stmt = $this->db->prepare("SELECT * FROM groups_table WHERE id=:id AND user_id=:user_id");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ? Group::fromArray($row) : null;
    }

    public function findAll(int $userId): array {
        $stmt = $this->db->prepare("SELECT * FROM groups_table WHERE user_id=:user_id ORDER BY name ASC");
        $stmt->execute([':user_id' => $userId]);
        return array_map([Group::class, 'fromArray'], $stmt->fetchAll());
    }

    public function addContact(int $groupId, int $contactId): void {
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO contact_groups (contact_id, group_id) VALUES (:contact_id, :group_id)"
        );
        $stmt->execute([':contact_id' => $contactId, ':group_id' => $groupId]);
    }

    public function removeContact(int $groupId, int $contactId): void {
        $stmt = $this->db->prepare(
            "DELETE FROM contact_groups WHERE contact_id=:contact_id AND group_id=:group_id"
        );
        $stmt->execute([':contact_id' => $contactId, ':group_id' => $groupId]);
    }

    public function getGroupsForContact(int $contactId): array {
        $stmt = $this->db->prepare(
            "SELECT g.* FROM groups_table g
             JOIN contact_groups cg ON g.id = cg.group_id
             WHERE cg.contact_id=:contact_id ORDER BY g.name ASC"
        );
        $stmt->execute([':contact_id' => $contactId]);
        return array_map([Group::class, 'fromArray'], $stmt->fetchAll());
    }
}
