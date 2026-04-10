<?php
// backend/services/GroupService.php

require_once __DIR__ . '/../repositories/GroupRepository.php';
require_once __DIR__ . '/../entities/Group.php';

class GroupService {
    private GroupRepository $groupRepo;

    public function __construct(GroupRepository $groupRepo) {
        $this->groupRepo = $groupRepo;
    }

    public function createGroup(int $userId, string $name): array {
        if (empty(trim($name))) throw new InvalidArgumentException('Group name is required');
        $group = new Group($userId, trim($name));
        return $this->groupRepo->save($group)->toArray();
    }

    public function deleteGroup(int $id, int $userId): bool {
        if (!$this->groupRepo->findById($id, $userId)) {
            throw new RuntimeException('Group not found', 404);
        }
        return $this->groupRepo->delete($id, $userId);
    }

    public function listGroups(int $userId): array {
        return array_map(fn($g) => $g->toArray(), $this->groupRepo->findAll($userId));
    }

    public function addContact(int $groupId, int $contactId, int $userId): void {
        if (!$this->groupRepo->findById($groupId, $userId)) {
            throw new RuntimeException('Group not found', 404);
        }
        $this->groupRepo->addContact($groupId, $contactId);
    }

    public function removeContact(int $groupId, int $contactId, int $userId): void {
        if (!$this->groupRepo->findById($groupId, $userId)) {
            throw new RuntimeException('Group not found', 404);
        }
        $this->groupRepo->removeContact($groupId, $contactId);
    }

    public function updateGroup(int $id, int $userId, string $name): array {
        $group = $this->groupRepo->findById($id, $userId);
        if (!$group) throw new RuntimeException('Group not found', 404);
        if (empty(trim($name))) throw new InvalidArgumentException('Group name is required');
        $group->name = trim($name);
        return $this->groupRepo->save($group)->toArray();
    }
}
