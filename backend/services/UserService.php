<?php
// backend/services/UserService.php

require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../entities/User.php';

class UserService {
    private UserRepository $userRepo;

    public function __construct(UserRepository $userRepo) {
        $this->userRepo = $userRepo;
    }

    public function createUser(string $username, string $password, string $role = 'user'): array {
        if (empty(trim($username))) throw new InvalidArgumentException('Username is required');
        if (strlen($password) < 6) throw new InvalidArgumentException('Password must be at least 6 characters');
        if ($this->userRepo->findByUsername($username)) {
            throw new RuntimeException('Username already exists');
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $user = new User(trim($username), $hash, $role);
        return $this->userRepo->save($user)->toArray();
    }

    public function deleteUser(int $id): bool {
        return $this->userRepo->delete($id);
    }

    public function changePassword(int $id, string $newPassword): void {
        $stmt = null;
        if (strlen($newPassword) < 6) throw new InvalidArgumentException('Password too short');
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $user = new User('', $hash, 'user', $id);
        $user->id = $id;
        $user->passwordHash = $hash;
        $this->userRepo->save($user);
    }

    public function authenticate(string $username, string $password): ?array {
        $user = $this->userRepo->findByUsername($username);
        if ($user && password_verify($password, $user->passwordHash)) {
            return $user->toArray();
        }
        return null;
    }

    public function listUsers(): array {
        return array_map(fn($u) => $u->toArray(), $this->userRepo->findAll());
    }
}
