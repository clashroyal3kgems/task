<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'TaskScheduler');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8'");
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Получаем id проекта из GET-запроса
$project_id = $_GET['project_id'] ?? null;
$user_id = 1; // Тут лучше подставить текущего пользователя, как у тебя в основном файле

// Если проект "все задачи"
if ($project_id === 'all' || empty($project_id)) {
    $stmt = $pdo->prepare("
        SELECT t.*, p.name AS project_name, p.color AS project_color, p.icon AS project_icon
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.project_id
        WHERE t.user_id = ?
        ORDER BY
            CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END,
            t.due_date ASC,
            CASE t.priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 WHEN 'low' THEN 2 END
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
} else if (str_starts_with($project_id, 'default_')) {
    // Если виртуальный проект (например "Личное"), фильтруй задачи без проекта
    if ($project_id === 'default_личное') {
        $stmt = $pdo->prepare("
            SELECT t.*, NULL AS project_name, NULL AS project_color, NULL AS project_icon
            FROM tasks t
            WHERE t.user_id = ? AND (t.project_id IS NULL OR t.project_id = '')
            ORDER BY
                CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END,
                t.due_date ASC,
                CASE t.priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 WHEN 'low' THEN 2 END
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
    } else {
        // Для других default проектов можно прописать аналогично, если нужно
        echo "Задачи для этого проекта пока не поддерживаются.";
        exit;
    }
} else {
    // Фильтр по реальному project_id
    $stmt = $pdo->prepare("
        SELECT t.*, p.name AS project_name, p.color AS project_color, p.icon AS project_icon
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.project_id
        WHERE t.user_id = ? AND t.project_id = ?
        ORDER BY
            CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END,
            t.due_date ASC,
            CASE t.priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 WHEN 'low' THEN 2 END
        LIMIT 10
    ");
    $stmt->execute([$user_id, $project_id]);
}

// Получаем задачи
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Функция для безопасного вывода, как у тебя в основном коде
function safeOutput($data, $default = '') {
    if ($data === null) {
        return $default;
    }
    return htmlspecialchars((string) $data, ENT_QUOTES, 'UTF-8');
}

// Генерируем HTML для задач (пример)
foreach ($tasks as $task) {
    echo '<div class="task-item">';
    echo '<h4>' . safeOutput($task['title']) . '</h4>';
    echo '<div class="task-project" style="color:' . safeOutput($task['project_color'] ?? '#666') . '">';
    echo safeOutput($task['project_name'] ?? 'Без проекта');
    echo '</div>';
    echo '</div>';
}
