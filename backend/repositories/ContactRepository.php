<?php
// backend/repositories/ContactRepository.php

require_once __DIR__ . '/../entities/Contact.php';

class ContactRepository {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function save(Contact $contact): Contact {
        if ($contact->id === 0) {
            $stmt = $this->db->prepare(
                "INSERT INTO contacts (user_id, first_name, last_name, phone, email, note, favorite, created_at)
                 VALUES (:user_id, :first_name, :last_name, :phone, :email, :note, :favorite, :created_at)"
            );
            $stmt->execute([
                ':user_id'    => $contact->userId,
                ':first_name' => $contact->firstName,
                ':last_name'  => $contact->lastName,
                ':phone'      => $contact->phone,
                ':email'      => $contact->email,
                ':note'       => $contact->note,
                ':favorite'   => (int)$contact->favorite,
                ':created_at' => $contact->createdAt,
            ]);
            $contact->id = (int)$this->db->lastInsertId();
        } else {
            $stmt = $this->db->prepare(
                "UPDATE contacts SET first_name=:first_name, last_name=:last_name,
                 phone=:phone, email=:email, note=:note, favorite=:favorite
                 WHERE id=:id AND user_id=:user_id"
            );
            $stmt->execute([
                ':first_name' => $contact->firstName,
                ':last_name'  => $contact->lastName,
                ':phone'      => $contact->phone,
                ':email'      => $contact->email,
                ':note'       => $contact->note,
                ':favorite'   => (int)$contact->favorite,
                ':id'         => $contact->id,
                ':user_id'    => $contact->userId,
            ]);
        }
        return $contact;
    }

    public function toggleFavorite(int $id, int $userId): bool {
        $stmt = $this->db->prepare(
            "UPDATE contacts SET favorite = NOT favorite WHERE id=:id AND user_id=:user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        // Return the new state
        $c = $this->findById($id, $userId);
        return $c ? $c->favorite : false;
    }

    public function delete(int $id, int $userId): bool {
        $stmt = $this->db->prepare("DELETE FROM contacts WHERE id=:id AND user_id=:user_id");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteMany(array $ids, int $userId): int {
        if (empty($ids)) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "DELETE FROM contacts WHERE id IN ($placeholders) AND user_id = ?"
        );
        $stmt->execute([...$ids, $userId]);
        return $stmt->rowCount();
    }

    public function findById(int $id, int $userId): ?Contact {
        $stmt = $this->db->prepare("SELECT * FROM contacts WHERE id=:id AND user_id=:user_id");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ? Contact::fromArray($row) : null;
    }

    /**
     * Returns paginated contacts with total count.
     * @return array{contacts: Contact[], total: int}
     */
    public function findAll(
        int $userId,
        string $sortBy = 'last_name',
        string $order = 'ASC',
        bool $favoritesOnly = false,
        int $page = 1,
        int $perPage = 50
    ): array {
        $allowed = ['first_name', 'last_name', 'email', 'phone', 'created_at', 'favorite'];
        $sortBy = in_array($sortBy, $allowed) ? $sortBy : 'last_name';
        $order  = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $where  = 'WHERE user_id=:user_id';
        if ($favoritesOnly) $where .= ' AND favorite = 1';

        // Count total for pagination
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM contacts $where");
        $countStmt->execute([':user_id' => $userId]);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare(
            "SELECT * FROM contacts $where ORDER BY $sortBy $order LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'contacts' => array_map([Contact::class, 'fromArray'], $stmt->fetchAll()),
            'total'    => $total,
        ];
    }

    public function search(int $userId, string $query, bool $favoritesOnly = false): array {
        $q = '%' . $query . '%';
        $where = "user_id=:user_id AND (first_name LIKE :q OR last_name LIKE :q OR phone LIKE :q OR email LIKE :q)";
        if ($favoritesOnly) $where .= ' AND favorite = 1';
        $stmt = $this->db->prepare(
            "SELECT * FROM contacts WHERE $where ORDER BY last_name ASC"
        );
        $stmt->execute([':user_id' => $userId, ':q' => $q]);
        return array_map([Contact::class, 'fromArray'], $stmt->fetchAll());
    }

    public function findByGroup(int $groupId, int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT c.* FROM contacts c
             JOIN contact_groups cg ON c.id = cg.contact_id
             WHERE cg.group_id=:group_id AND c.user_id=:user_id
             ORDER BY c.last_name ASC"
        );
        $stmt->execute([':group_id' => $groupId, ':user_id' => $userId]);
        return array_map([Contact::class, 'fromArray'], $stmt->fetchAll());
    }

    public function findManyByIds(array $ids, int $userId): array {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT * FROM contacts WHERE id IN ($placeholders) AND user_id = ?"
        );
        $stmt->execute([...$ids, $userId]);
        return array_map([Contact::class, 'fromArray'], $stmt->fetchAll());
    }
}
