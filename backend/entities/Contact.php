<?php
// backend/entities/Contact.php

class Contact {
    public int $id;
    public int $userId;
    public string $firstName;
    public string $lastName;
    public string $phone;
    public string $email;
    public string $note;
    public bool $favorite;
    public string $createdAt;

    public function __construct(
        int $userId,
        string $firstName,
        string $lastName,
        string $phone = '',
        string $email = '',
        string $note = '',
        bool $favorite = false,
        string $createdAt = '',
        int $id = 0
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->phone = $phone;
        $this->email = $email;
        $this->note = $note;
        $this->favorite = $favorite;
        $this->createdAt = $createdAt ?: date('Y-m-d H:i:s');
    }

    public static function fromArray(array $data): self {
        return new self(
            (int)$data['user_id'],
            $data['first_name'],
            $data['last_name'],
            $data['phone'] ?? '',
            $data['email'] ?? '',
            $data['note'] ?? '',
            (bool)($data['favorite'] ?? false),
            $data['created_at'] ?? '',
            (int)($data['id'] ?? 0)
        );
    }

    public function toArray(): array {
        return [
            'id'         => $this->id,
            'user_id'    => $this->userId,
            'first_name' => $this->firstName,
            'last_name'  => $this->lastName,
            'phone'      => $this->phone,
            'email'      => $this->email,
            'note'       => $this->note,
            'favorite'   => $this->favorite,
            'created_at' => $this->createdAt,
        ];
    }
}
