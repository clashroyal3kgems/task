<?php
// Конфигурация базы данных
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'TaskScheduler');

header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка подключения к базе данных']);
    exit;
}

// Получаем данные из запроса
$taskId = $_POST['task_id'] ?? null;
$isCompleted = $_POST['is_completed'] ?? 0;

if (!$taskId) {
    echo json_encode(['success' => false, 'error' => 'Не указан ID задачи']);
    exit;
}

try {
    // Обновляем статус задачи в базе данных
    $stmt = $pdo->prepare("UPDATE tasks SET is_completed = ? WHERE task_id = ?");
    $stmt->execute([$isCompleted, $taskId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка при обновлении задачи: ' . $e->getMessage()]);
}