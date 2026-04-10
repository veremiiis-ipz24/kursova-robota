<?php
// backend/api/importexport.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../repositories/ContactRepository.php';
require_once __DIR__ . '/../repositories/GroupRepository.php';
require_once __DIR__ . '/../repositories/HistoryRepository.php';
require_once __DIR__ . '/../services/ContactService.php';
require_once __DIR__ . '/../services/ImportExportService.php';

requireAuth();
$userId = currentUserId();
$action = $_GET['action'] ?? '';

$contactRepo    = new ContactRepository(getDB());
$groupRepo      = new GroupRepository(getDB());
$historyRepo    = new HistoryRepository(getDB());
$contactService = new ContactService($contactRepo, $groupRepo, $historyRepo);
$service        = new ImportExportService($contactService, $contactRepo, $groupRepo);

try {
    if ($action === 'export') {
        $csv = $service->exportCSV($userId);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="contacts_export.csv"');
        header('Content-Length: ' . strlen($csv));
        if (ob_get_level()) ob_end_clean();
        echo $csv;
        exit;

    } elseif ($action === 'export_selected' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $ids  = array_map('intval', $data['ids'] ?? []);
        $csv  = $service->exportSelected($ids, $userId);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="contacts_selected.csv"');
        header('Content-Length: ' . strlen($csv));
        if (ob_get_level()) ob_end_clean();
        echo $csv;
        exit;

    } elseif ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $csvContent = isset($_FILES['csv_file'])
            ? file_get_contents($_FILES['csv_file']['tmp_name'])
            : file_get_contents('php://input');
        if (!mb_check_encoding($csvContent, 'UTF-8')) {
            $csvContent = mb_convert_encoding($csvContent, 'UTF-8', 'auto');
        }
        jsonResponse($service->importCSV($userId, $csvContent));

    } else {
        header('Content-Type: application/json');
        jsonResponse(['error' => 'Невірна дія'], 400);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    jsonResponse(['error' => $e->getMessage()], 400);
}
