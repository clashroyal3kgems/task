<?php
require_once 'db_config.php'; // Файл с настройками БД

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['task_id']) || !isset($data['is_completed'])) {
        throw new Exception('Недостаточно данных');
    }

    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $stmt = $pdo->prepare("UPDATE tasks SET is_completed = ? WHERE task_id = ?");
    $stmt->execute([$data['is_completed'], $data['task_id']]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}