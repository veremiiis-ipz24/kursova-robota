<?php
// backend/entities/User.php

class User {
    public int $id;
    public string $username;
    public string $passwordHash;
    public string $role;

    public function __construct(string $username, string $passwordHash, string $role = 'user', int $id = 0) {
        $this->id = $id;
        $this->username = $username;
        $this->passwordHash = $passwordHash;
        $this->role = $role;
    }

    public static function fromArray(array $data): self {
        return new self(
            $data['username'],
            $data['password_hash'],
            $data['role'] ?? 'user',
            (int)($data['id'] ?? 0)
        );
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'role' => $this->role,
        ];
    }
}
