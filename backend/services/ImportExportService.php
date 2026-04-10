<?php
// backend/services/ImportExportService.php

require_once __DIR__ . '/../repositories/ContactRepository.php';
require_once __DIR__ . '/../repositories/GroupRepository.php';
require_once __DIR__ . '/../services/ContactService.php';

class ImportExportService {
    private ContactService    $contactService;
    private ContactRepository $contactRepo;
    private GroupRepository   $groupRepo;

    public function __construct(
        ContactService    $contactService,
        ContactRepository $contactRepo,
        GroupRepository   $groupRepo
    ) {
        $this->contactService = $contactService;
        $this->contactRepo    = $contactRepo;
        $this->groupRepo      = $groupRepo;
    }

    /** Export ALL contacts for a user */
    public function exportCSV(int $userId): string {
        $result   = $this->contactRepo->findAll($userId, 'last_name', 'ASC', false, 1, 100000);
        $contacts = $result['contacts'];
        return $this->buildCSV($contacts, $userId);
    }

    /** Export contacts belonging to a specific group */
    public function exportGroupCSV(int $groupId, int $userId): string {
        // Verify group ownership
        $group = $this->groupRepo->findById($groupId, $userId);
        if (!$group) throw new RuntimeException('Групу не знайдено', 404);
        $contacts = $this->contactRepo->findByGroup($groupId, $userId);
        return $this->buildCSV($contacts, $userId, $group->name);
    }

    /** Export a specific selection of contacts by ID */
    public function exportSelected(array $ids, int $userId): string {
        $contacts = $this->contactRepo->findManyByIds($ids, $userId);
        return $this->buildCSV($contacts, $userId);
    }

    private function buildCSV(array $contacts, int $userId, string $groupName = ''): string {
        $rows = [['id', 'first_name', 'last_name', 'phone', 'email', 'groups', 'note', 'created_at']];
        foreach ($contacts as $c) {
            $groups = array_map(
                fn($g) => $g->name,
                $this->groupRepo->getGroupsForContact($c->id)
            );
            $phone = ($c->phone !== '') ? "\t" . $c->phone : '';
            $rows[] = [
                $c->id,
                $c->firstName,
                $c->lastName,
                $phone,
                $c->email,
                implode('; ', $groups),
                (string)$c->note,
                $c->createdAt,
            ];
        }

        $fp = fopen('php://temp', 'r+');
        fwrite($fp, "\xEF\xBB\xBF"); // UTF-8 BOM
        foreach ($rows as $row) fputcsv($fp, $row);
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }

    public function importCSV(int $userId, string $csvContent): array {
        $csvContent = ltrim($csvContent, "\xEF\xBB\xBF");
        $lines = explode("\n", trim($csvContent));
        if (count($lines) < 2) throw new InvalidArgumentException('CSV повинен мати заголовок та хоча б один рядок');

        $headers  = str_getcsv(array_shift($lines));
        $required = ['first_name', 'last_name'];
        foreach ($required as $req) {
            if (!in_array($req, $headers)) {
                throw new InvalidArgumentException("Відсутній обов'язковий стовпець: $req");
            }
        }

        $imported = 0;
        $errors   = [];
        foreach ($lines as $i => $line) {
            if (empty(trim($line))) continue;
            $values = str_getcsv($line);
            $row    = array_combine($headers, array_pad($values, count($headers), ''));
            if (isset($row['phone'])) $row['phone'] = ltrim($row['phone'], "\t");
            try {
                $this->contactService->createContact($userId, $row);
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Рядок " . ($i + 2) . ": " . $e->getMessage();
            }
        }
        return ['imported' => $imported, 'errors' => $errors];
    }
}
