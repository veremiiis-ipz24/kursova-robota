<?php
// backend/api/groups.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../repositories/GroupRepository.php';
require_once __DIR__ . '/../repositories/ContactRepository.php';
require_once __DIR__ . '/../services/GroupService.php';
require_once __DIR__ . '/../services/ImportExportService.php';
require_once __DIR__ . '/../repositories/HistoryRepository.php';
require_once __DIR__ . '/../services/ContactService.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

header('Content-Type: application/json');
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$userId = currentUserId();
$action = $_GET['action'] ?? '';

$groupService = new GroupService(new GroupRepository(getDB()));

// Group CSV export
if ($method === 'GET' && $id && $action === 'export') {
    $contactRepo  = new ContactRepository(getDB());
    $groupRepo    = new GroupRepository(getDB());
    $historyRepo  = new HistoryRepository(getDB());
    $contactService = new ContactService($contactRepo, $groupRepo, $historyRepo);
    $ieService    = new ImportExportService($contactService, $contactRepo, $groupRepo);

    $csv = $ieService->exportGroupCSV($id, $userId);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="group_export.csv"');
    header('Content-Length: ' . strlen($csv));
    if (ob_get_level()) ob_end_clean();
    echo $csv;
    exit;
}

try {
    switch ($method) {
        case 'GET':
            jsonResponse($groupService->listGroups($userId));
            break;
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            jsonResponse($groupService->createGroup($userId, $data['name'] ?? ''), 201);
            break;
        case 'PUT':
            if (!$id) jsonResponse(['error' => 'ID required'], 400);
            $data = json_decode(file_get_contents('php://input'), true);
            jsonResponse($groupService->updateGroup($id, $userId, $data['name'] ?? ''));
            break;
        case 'DELETE':
            if (!$id) jsonResponse(['error' => 'ID required'], 400);
            $groupService->deleteGroup($id, $userId);
            jsonResponse(['success' => true]);
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (RuntimeException $e) {
    jsonResponse(['error' => $e->getMessage()], $e->getCode() ?: 404);
} catch (InvalidArgumentException $e) {
    jsonResponse(['error' => $e->getMessage()], 400);
}
