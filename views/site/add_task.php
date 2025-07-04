<?php
session_start();
require_once 'config.php'; // Подключение к БД

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Получаем данные из формы
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $due_date = $_POST['due_date'] ?? null;
        $priority = $_POST['priority'] ?? 'medium';
        $project_id = $_POST['project_id'] ?? null;
        $user_id = 1; // Замените на реальный ID пользователя

        // Вставляем задачу в БД
        $stmt = $pdo->prepare("
            INSERT INTO tasks (user_id, project_id, title, description, due_date, priority, is_completed, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$user_id, $project_id, $title, $description, $due_date, $priority]);
        $task_id = $pdo->lastInsertId();

        // Обработка подзадач (если они есть)
        if (!empty($_POST['subtasks'])) {
            foreach ($_POST['subtasks'] as $subtaskTitle) {
                if (!empty(trim($subtaskTitle))) {
                    $stmt = $pdo->prepare("
                        INSERT INTO subtasks (task_id, title, is_completed, created_at)
                        VALUES (?, ?, 0, NOW())
                    ");
                    $stmt->execute([$task_id, trim($subtaskTitle)]);
                }
            }
        }

        $_SESSION['flash_message'] = 'Задача успешно добавлена!';
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = 'Ошибка при добавлении задачи: ' . $e->getMessage();
    }

    header('Location: index.php');
    exit();
}