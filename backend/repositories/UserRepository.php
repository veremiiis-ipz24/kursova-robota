<?php
// backend/repositories/UserRepository.php

require_once __DIR__ . '/../entities/User.php';

class UserRepository {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function save(User $user): User {
        if ($user->id === 0) {
            $stmt = $this->db->prepare(
                "INSERT INTO users (username, password_hash, role) VALUES (:username, :password_hash, :role)"
            );
            $stmt->execute([
                ':username' => $user->username,
                ':password_hash' => $user->passwordHash,
                ':role' => $user->role,
            ]);
            $user->id = (int)$this->db->lastInsertId();
        } else {
            $stmt = $this->db->prepare(
                "UPDATE users SET password_hash=:password_hash WHERE id=:id"
            );
            $stmt->execute([':password_hash' => $user->passwordHash, ':id' => $user->id]);
        }
        return $user;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id=:id AND role != 'admin'");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function findByUsername(string $username): ?User {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username=:username");
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();
        return $row ? User::fromArray($row) : null;
    }

    public function findAll(): array {
        $stmt = $this->db->query("SELECT * FROM users ORDER BY username ASC");
        return array_map([User::class, 'fromArray'], $stmt->fetchAll());
    }
}
