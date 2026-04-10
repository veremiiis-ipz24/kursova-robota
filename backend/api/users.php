<?php
// backend/api/users.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../services/UserService.php';

header('Content-Type: application/json');
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$service = new UserService(new UserRepository(getDB()));

try {
    switch ($method) {
        case 'GET':
            jsonResponse($service->listUsers());
            break;
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            jsonResponse($service->createUser($data['username'] ?? '', $data['password'] ?? '', $data['role'] ?? 'user'), 201);
            break;
        case 'DELETE':
            if (!$id) jsonResponse(['error' => 'ID required'], 400);
            $service->deleteUser($id);
            jsonResponse(['success' => true]);
            break;
        case 'PATCH':
            if (!$id) jsonResponse(['error' => 'ID required'], 400);
            $data = json_decode(file_get_contents('php://input'), true);
            $service->changePassword($id, $data['password'] ?? '');
            jsonResponse(['success' => true]);
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], $e->getCode() ?: 400);
}
