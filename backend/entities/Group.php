<?php
// backend/entities/Group.php

class Group {
    public int $id;
    public int $userId;
    public string $name;

    public function __construct(int $userId, string $name, int $id = 0) {
        $this->id = $id;
        $this->userId = $userId;
        $this->name = $name;
    }

    public static function fromArray(array $data): self {
        return new self(
            (int)$data['user_id'],
            $data['name'],
            (int)($data['id'] ?? 0)
        );
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'name' => $this->name,
        ];
    }
}
