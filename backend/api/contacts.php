<?php
// backend/api/contacts.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../repositories/ContactRepository.php';
require_once __DIR__ . '/../repositories/GroupRepository.php';
require_once __DIR__ . '/../repositories/HistoryRepository.php';
require_once __DIR__ . '/../services/ContactService.php';

header('Content-Type: application/json');
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$userId = currentUserId();

$service = new ContactService(
    new ContactRepository(getDB()),
    new GroupRepository(getDB()),
    new HistoryRepository(getDB())
);

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                $action = $_GET['action'] ?? '';
                if ($action === 'history') {
                    jsonResponse($service->getHistory($id, $userId));
                } else {
                    jsonResponse($service->getContact($id, $userId));
                }
            } elseif (isset($_GET['search'])) {
                $favOnly = !empty($_GET['favorites']);
                jsonResponse($service->searchContacts($userId, $_GET['search'], $favOnly));
            } elseif (isset($_GET['group_id'])) {
                jsonResponse($service->filterByGroup((int)$_GET['group_id'], $userId));
            } else {
                $sort    = $_GET['sort'] ?? 'last_name';
                $order   = $_GET['order'] ?? 'ASC';
                $favOnly = !empty($_GET['favorites']);
                $page    = max(1, (int)($_GET['page'] ?? 1));
                $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 50)));
                jsonResponse($service->listContacts($userId, $sort, $order, $favOnly, $page, $perPage));
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            // Bulk operations
            if (isset($data['bulk_action'])) {
                $ids = array_map('intval', $data['ids'] ?? []);
                switch ($data['bulk_action']) {
                    case 'delete':
                        $deleted = $service->deleteMany($ids, $userId);
                        jsonResponse(['deleted' => $deleted]);
                        break;
                    case 'assign_group':
                        $service->bulkAssignGroup($ids, (int)$data['group_id'], $userId);
                        jsonResponse(['success' => true]);
                        break;
                    case 'export':
                        // Return contacts data for client-side CSV generation
                        $contacts = $service->getContactsByIds($ids, $userId);
                        jsonResponse($contacts);
                        break;
                    default:
                        jsonResponse(['error' => 'Невідома масова операція'], 400);
                }
            } else {
                jsonResponse($service->createContact($userId, $data), 201);
            }
            break;

        case 'PUT':
            if (!$id) jsonResponse(['error' => 'ID required'], 400);
            $data = json_decode(file_get_contents('php://input'), true);
            jsonResponse($service->updateContact($id, $userId, $data));
            break;

        case 'PATCH':
            if (!$id) jsonResponse(['error' => 'ID required'], 400);
            $data   = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? '';
            if ($action === 'toggle_favorite') {
                $newState = $service->toggleFavorite($id, $userId);
                jsonResponse(['favorite' => $newState]);
            } elseif ($action === 'assign_group') {
                $service->assignGroup($id, (int)$data['group_id'], $userId);
                jsonResponse(['success' => true]);
            } else {
                jsonResponse(['error' => 'Невідома дія'], 400);
            }
            break;

        case 'DELETE':
            if (!$id) jsonResponse(['error' => 'ID required'], 400);
            $action = $_GET['action'] ?? '';
            if ($action === 'ungroup') {
                $service->removeFromGroup($id, (int)$_GET['group_id'], $userId);
                jsonResponse(['success' => true]);
            } else {
                $service->deleteContact($id, $userId);
                jsonResponse(['success' => true]);
            }
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (RuntimeException $e) {
    jsonResponse(['error' => $e->getMessage()], $e->getCode() ?: 404);
} catch (InvalidArgumentException $e) {
    jsonResponse(['error' => $e->getMessage()], 400);
}
