<?php
// backend/api/auth.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../services/UserService.php';

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$userService = new UserService(new UserRepository(getDB()));

try {
    if ($method === 'POST' && $action === 'login') {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = $userService->authenticate($data['username'] ?? '', $data['password'] ?? '');
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            jsonResponse(['success' => true, 'user' => $user]);
        } else {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
    } elseif ($method === 'POST' && $action === 'logout') {
        session_destroy();
        jsonResponse(['success' => true]);
    } elseif ($method === 'GET' && $action === 'me') {
        if (!empty($_SESSION['user_id'])) {
            jsonResponse(['user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role'],
            ]]);
        } else {
            jsonResponse(['user' => null]);
        }
    } else {
        jsonResponse(['error' => 'Not found'], 404);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], $e->getCode() ?: 400);
}
