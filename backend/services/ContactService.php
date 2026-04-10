<?php
// backend/services/ContactService.php

require_once __DIR__ . '/../repositories/ContactRepository.php';
require_once __DIR__ . '/../repositories/GroupRepository.php';
require_once __DIR__ . '/../repositories/HistoryRepository.php';
require_once __DIR__ . '/../entities/Contact.php';

class ContactFactory {
    public static function create(int $userId, array $data): Contact {
        return new Contact(
            $userId,
            trim($data['first_name'] ?? ''),
            trim($data['last_name'] ?? ''),
            trim($data['phone'] ?? ''),
            trim($data['email'] ?? ''),
            trim($data['note'] ?? ''),
            (bool)($data['favorite'] ?? false)
        );
    }
}

class ContactService {
    private ContactRepository $contactRepo;
    private GroupRepository   $groupRepo;
    private HistoryRepository $historyRepo;

    public function __construct(
        ContactRepository $contactRepo,
        GroupRepository   $groupRepo,
        HistoryRepository $historyRepo
    ) {
        $this->contactRepo = $contactRepo;
        $this->groupRepo   = $groupRepo;
        $this->historyRepo = $historyRepo;
    }

    public function createContact(int $userId, array $data): array {
        $this->validateContact($data);
        $contact = ContactFactory::create($userId, $data);
        $saved = $this->contactRepo->save($contact);
        $this->historyRepo->log($saved->id, $userId, 'Контакт створено');
        return $saved->toArray();
    }

    public function updateContact(int $id, int $userId, array $data): array {
        $contact = $this->contactRepo->findById($id, $userId);
        if (!$contact) throw new RuntimeException('Контакт не знайдено', 404);
        $this->validateContact($data);

        // Build change description for history
        $changes = [];
        $map = [
            'first_name' => ["Ім'я",    $contact->firstName],
            'last_name'  => ['Прізвище', $contact->lastName],
            'phone'      => ['Телефон',  $contact->phone],
            'email'      => ['Email',    $contact->email],
            'note'       => ['Нотатка',  $contact->note],
        ];
        foreach ($map as $field => [$label, $old]) {
            $new = trim($data[$field] ?? '');
            if ($new !== $old) $changes[] = "змінено «$label»";
        }

        $contact->firstName = trim($data['first_name']);
        $contact->lastName  = trim($data['last_name']);
        $contact->phone     = trim($data['phone'] ?? '');
        $contact->email     = trim($data['email'] ?? '');
        $contact->note      = trim($data['note'] ?? '');

        $saved = $this->contactRepo->save($contact);
        if ($changes) {
            $this->historyRepo->log($id, $userId, 'Контакт відредаговано: ' . implode(', ', $changes));
        }
        return $saved->toArray();
    }

    public function toggleFavorite(int $id, int $userId): bool {
        $contact = $this->contactRepo->findById($id, $userId);
        if (!$contact) throw new RuntimeException('Контакт не знайдено', 404);
        $newState = $this->contactRepo->toggleFavorite($id, $userId);
        $action = $newState ? 'Додано до улюблених' : 'Видалено з улюблених';
        $this->historyRepo->log($id, $userId, $action);
        return $newState;
    }

    public function deleteContact(int $id, int $userId): bool {
        if (!$this->contactRepo->findById($id, $userId)) {
            throw new RuntimeException('Контакт не знайдено', 404);
        }
        return $this->contactRepo->delete($id, $userId);
    }

    public function deleteMany(array $ids, int $userId): int {
        return $this->contactRepo->deleteMany($ids, $userId);
    }

    public function searchContacts(int $userId, string $query, bool $favoritesOnly = false): array {
        $contacts = $this->contactRepo->search($userId, $query, $favoritesOnly);
        return array_map(function ($c) {
            $arr = $c->toArray();
            $arr['groups'] = array_map(fn($g) => $g->toArray(), $this->groupRepo->getGroupsForContact($c->id));
            return $arr;
        }, $contacts);
    }

    public function listContacts(
        int $userId,
        string $sortBy = 'last_name',
        string $order = 'ASC',
        bool $favoritesOnly = false,
        int $page = 1,
        int $perPage = 50
    ): array {
        $result = $this->contactRepo->findAll($userId, $sortBy, $order, $favoritesOnly, $page, $perPage);
        $contacts = array_map(function ($c) {
            $arr = $c->toArray();
            $arr['groups'] = array_map(fn($g) => $g->toArray(), $this->groupRepo->getGroupsForContact($c->id));
            return $arr;
        }, $result['contacts']);

        return [
            'contacts'   => $contacts,
            'total'      => $result['total'],
            'page'       => $page,
            'per_page'   => $perPage,
            'total_pages' => (int)ceil($result['total'] / $perPage),
        ];
    }

    public function getContact(int $id, int $userId): array {
        $contact = $this->contactRepo->findById($id, $userId);
        if (!$contact) throw new RuntimeException('Контакт не знайдено', 404);
        $arr = $contact->toArray();
        $arr['groups']  = array_map(fn($g) => $g->toArray(), $this->groupRepo->getGroupsForContact($id));
        $arr['history'] = $this->historyRepo->findByContact($id);
        return $arr;
    }

    public function getHistory(int $contactId, int $userId): array {
        if (!$this->contactRepo->findById($contactId, $userId)) {
            throw new RuntimeException('Контакт не знайдено', 404);
        }
        return $this->historyRepo->findByContact($contactId);
    }

    public function assignGroup(int $contactId, int $groupId, int $userId): void {
        if (!$this->contactRepo->findById($contactId, $userId)) {
            throw new RuntimeException('Контакт не знайдено', 404);
        }
        $group = $this->groupRepo->findById($groupId, $userId);
        if (!$group) throw new RuntimeException('Групу не знайдено', 404);
        $this->groupRepo->addContact($groupId, $contactId);
        $this->historyRepo->log($contactId, $userId, "Додано до групи «{$group->name}»");
    }

    public function removeFromGroup(int $contactId, int $groupId, int $userId): void {
        $group = $this->groupRepo->findById($groupId, $userId);
        $this->groupRepo->removeContact($groupId, $contactId);
        if ($group) {
            $this->historyRepo->log($contactId, $userId, "Видалено з групи «{$group->name}»");
        }
    }

    public function bulkAssignGroup(array $contactIds, int $groupId, int $userId): void {
        $group = $this->groupRepo->findById($groupId, $userId);
        if (!$group) throw new RuntimeException('Групу не знайдено', 404);
        foreach ($contactIds as $cid) {
            $this->groupRepo->addContact($groupId, (int)$cid);
            $this->historyRepo->log((int)$cid, $userId, "Додано до групи «{$group->name}» (масова операція)");
        }
    }

    public function filterByGroup(int $groupId, int $userId): array {
        return array_map(fn($c) => $c->toArray(), $this->contactRepo->findByGroup($groupId, $userId));
    }

    public function getContactsByIds(array $ids, int $userId): array {
        return array_map(fn($c) => $c->toArray(), $this->contactRepo->findManyByIds($ids, $userId));
    }

    private function validateContact(array $data): void {
        if (empty(trim($data['first_name'] ?? ''))) {
            throw new InvalidArgumentException("Ім'я є обов'язковим");
        }
        if (empty(trim($data['last_name'] ?? ''))) {
            throw new InvalidArgumentException("Прізвище є обов'язковим");
        }
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Невірна адреса електронної пошти');
        }
    }
}
