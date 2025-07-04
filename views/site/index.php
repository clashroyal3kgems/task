<?php
// Конфигурация базы данных
use app\widgets\Alert;
use yii\helpers\Html;

/** @var string $content */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'TaskScheduler');

// Установка соединения с БД
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8'");
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}
function hex2rgb($hex) {
    $hex = str_replace("#", "", $hex);

    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex,0,1).substr($hex,0,1));
        $g = hexdec(substr($hex,1,1).substr($hex,1,1));
        $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }

    return "$r, $g, $b";
}

// Получение текущего пользователя (для примера используем user_id = 1)
$user_id = Yii::$app->user->id;
if (empty($user_id)) {
    die('Пользователь не авторизован или $user_id пустой');
}

// Обработка добавления новой задачи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority, project_id) 
                             VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $_POST['title'],
            $_POST['description'] ?? null,
            $_POST['due_date'] ? date('Y-m-d H:i:s', strtotime($_POST['due_date'])) : null,
            $_POST['priority'] ?? 'medium',
            $project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : null
        ]);

        // Перенаправление для предотвращения повторной отправки формы
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        $error = "Ошибка при добавлении задачи: " . $e->getMessage();
    }
}

// Улучшенная функция для безопасного вывода данных
function safeOutput($data, $default = '') {
    if ($data === null) {
        return $default;
    }
    return htmlspecialchars((string) $data, ENT_QUOTES, 'UTF-8');
}

// Получение данных пользователя
$userStmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);



// Получение проектов пользователя
$projectsStmt = $pdo->prepare("SELECT * FROM projects ORDER BY name");
$projectsStmt->execute();
$projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);


// Добавляем стандартные проекты, если их нет
$defaultProjects = [
    ['name' => 'Работа', 'color' => '#5e35b1', 'icon' => 'fa-briefcase'],
    ['name' => 'Обучение', 'color' => '#26a69a', 'icon' => 'fa-graduation-cap'],
    ['name' => 'Личное', 'color' => '#ff7043', 'icon' => 'fa-user']
];

foreach ($defaultProjects as $defaultProject) {
    $exists = false;
    foreach ($projects as $project) {
        if ($project['name'] === $defaultProject['name']) {
            $exists = true;
            break;
        }
    }

    if (!$exists) {
        // Добавляем стандартный проект в массив
        $projects[] = [
            'project_id' => 'default_' . strtolower(str_replace(' ', '_', $defaultProject['name'])),
            'name' => $defaultProject['name'],
            'color' => $defaultProject['color'],
            'icon' => $defaultProject['icon'],
            'user_id' => $user_id
        ];
    }
}

// Получение задач пользователя (ближайшие 10)
$tasksStmt = $pdo->prepare("
    SELECT t.*, p.name AS project_name, p.color AS project_color, p.icon AS project_icon
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.project_id
    WHERE t.user_id = ?
    ORDER BY 
        CASE 
            WHEN t.due_date IS NULL THEN 1
            ELSE 0
        END,
        t.due_date ASC,
        CASE t.priority
            WHEN 'high' THEN 0
            WHEN 'medium' THEN 1
            WHEN 'low' THEN 2
        END
    LIMIT 10
");
$tasksStmt->execute([$user_id]);
$tasks = $tasksStmt->fetchAll(PDO::FETCH_ASSOC);

// Получение подзадач для задач
$taskIds = array_column($tasks, 'task_id');
$subtasks = [];
if (!empty($taskIds)) {
    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
    $subtasksStmt = $pdo->prepare("
        SELECT * FROM subtasks 
        WHERE task_id IN ($placeholders)
        ORDER BY created_at
    ");
    $subtasksStmt->execute($taskIds);
    $subtasksData = $subtasksStmt->fetchAll(PDO::FETCH_ASSOC);

    // Группировка подзадач по task_id
    foreach ($subtasksData as $subtask) {
        $subtasks[$subtask['task_id']][] = $subtask;
    }
}

// Получение событий для календаря (текущий месяц)
$currentMonth = date('Y-m');
$eventsStmt = $pdo->prepare("
    SELECT * FROM events 
    WHERE user_id = ? 
    AND start_datetime BETWEEN ? AND LAST_DAY(?)
    ORDER BY start_datetime
");
$eventsStmt->execute([$user_id, $currentMonth . '-01', $currentMonth]);
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

// Получение статистики
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_tasks,
        SUM(is_completed) AS completed_tasks,
        SUM(due_date < NOW() AND is_completed = 0) AS overdue_tasks,
        SUM(priority = 'high') AS high_priority_tasks
    FROM tasks 
    WHERE user_id = ?
");
$statsStmt->execute([$user_id]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Получение уведомлений
$notificationsStmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 5
");
$notificationsStmt->execute([$user_id]);
$notifications = $notificationsStmt->fetchAll(PDO::FETCH_ASSOC);
$unreadNotificationsCount = count($notifications);

// Получение категорий задач
$categoriesStmt = $pdo->prepare("SELECT * FROM task_categories");
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskScheduler - Умный планировщик задач</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Все стили из предыдущего HTML-кода остаются без изменений */
        :root {
            --primary: #5e35b1;
            --primary-light: #7e57c2;
            --primary-dark: #4527a0;
            --secondary: #26a69a;
            --accent: #ff7043;
            --text: #2d3748;
            --text-light: #4a5568;
            --text-lighter: #718096;
            --bg: #f7fafc;
            --bg-panel: #ffffff;
            --border: #e2e8f0;
            --success: #48bb78;
            --warning: #ed8936;
            --danger: #f56565;
            --info: #4299e1;
            --low-priority: #a0aec0;
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: var(--text);
            background-color: var(--bg);
            line-height: 1.5;
        }

        /* Остальные стили остаются точно такими же, как в предыдущем HTML */
        :root {
            --primary: #5e35b1;
            --primary-light: #7e57c2;
            --primary-dark: #4527a0;
            --secondary: #26a69a;
            --accent: #ff7043;
            --text: #2d3748;
            --text-light: #4a5568;
            --text-lighter: #718096;
            --bg: #f7fafc;
            --bg-panel: #ffffff;
            --border: #e2e8f0;
            --success: #48bb78;
            --warning: #ed8936;
            --danger: #f56565;
            --info: #4299e1;
            --low-priority: #a0aec0;
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: var(--text);
            background-color: var(--bg);
            line-height: 1.5;
        }

        /* Основная структура */
        .app-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }

        /* Сайдбар */
        .sidebar {
            border-radius: 0.75rem;
            background-color: var(--bg-panel);
            border-right: 1px solid var(--border);
            padding: 1.5rem;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
        }

        .logo-icon {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.2s;
        }

        .nav-item:hover, .nav-item.active {
            background-color: rgba(94, 53, 177, 0.1);
            color: var(--primary);
        }

        .nav-item.active {
            font-weight: 500;
        }

        .nav-icon {
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }

        .projects-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1.5rem 0 0.5rem;
            color: var(--text-light);
            font-size: 0.875rem;
        }

        .add-project {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            font-size: 1rem;
        }

        /* Основное содержимое */
        .main-content {
            padding: 2rem;

            margin: 0 auto;
            width: 100%;
        }

        /* Хедер */
        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .search-bar {
            position: relative;
            width: 250px;
        }

        .search-input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            background-color: var(--bg-panel);
            font-size: 0.875rem;
        }

        .search-icon {
            position: absolute;
            left: -2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-lighter);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-light);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
        }

        .notification-badge {
            position: relative;
        }

        .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            font-weight: 600;
        }

        /* Статистика */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background-color: var(--bg-panel);
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .stat-title {
            font-size: 0.875rem;
            color: var(--text-lighter);
            font-weight: 500;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-change {
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .positive {
            color: rgb(70,40,161);
        }

        .negative {
            color: var(--danger);
        }

        /* Основной контент */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Календарь */
        .calendar-card {
            background-color: var(--bg-panel);
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .calendar-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .calendar-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .calendar-btn {
            background-color: var(--bg);
            border: none;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            transition: background-color 0.2s;
        }

        .calendar-btn:hover {
            background-color: var(--border);
        }

        .month-year {
            font-weight: 500;
            min-width: 120px;
            text-align: center;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
        }

        .day-header {
            text-align: center;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-lighter);
            padding: 0.5rem;
        }

        .calendar-day {
            aspect-ratio: 1;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .calendar-day:hover {
            background-color: var(--bg);
        }

        .day-number {
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .event {
            font-size: 0.7rem;
            padding: 0.15rem 0.25rem;
            border-radius: 0.25rem;
            margin-bottom: 0.15rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .event-primary {
            background-color: rgba(94, 53, 177, 0.1);
            color: var(--primary);
            border-left: 2px solid var(--primary);
        }

        .event-secondary {
            background-color: rgba(38, 166, 154, 0.1);
            color: var(--secondary);
            border-left: 2px solid var(--secondary);
        }

        .event-accent {
            background-color: rgba(255, 112, 67, 0.1);
            color: var(--accent);
            border-left: 2px solid var(--accent);
        }

        .current-day {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .current-day .day-number {
            color: white;
        }

        /* Список задач */
        .tasks-card {
            background-color: var(--bg-panel);
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .tasks-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .tasks-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .add-task-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            transition: background-color 0.2s;
        }

        .add-task-btn:hover {
            background-color: var(--primary-dark);
        }

        .task-filters {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .filter-btn {
            background-color: var(--bg);
            border: none;
            border-radius: 1rem;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn:hover, .filter-btn.active {
            background-color: var(--primary);
            color: white;
        }

        .task-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .task-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
            background-color: var(--bg);
            transition: all 0.2s;
        }

        .task-item:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .task-checkbox {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid var(--border);
            border-radius: 0.25rem;
            cursor: pointer;
            margin-top: 2px;
            transition: all 0.2s;
            position: relative;
        }

        .task-checkbox:checked {
            background-color: var(--success);
            border-color: var(--success);
        }

        .task-checkbox:checked::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            color: white;
            font-size: 0.7rem;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .task-content {
            flex: 1;
        }

        .task-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .task-title.completed {
            text-decoration: line-through;
            color: var(--text-lighter);
        }

        .task-description {
            font-size: 0.8125rem;
            color: var(--text-lighter);
            margin-bottom: 0.25rem;
        }

        .task-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.75rem;
            color: var(--text-lighter);
        }

        .task-date {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .task-priority {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .priority-high {
            background-color: var(--danger);
        }

        .priority-medium {
            background-color: var(--warning);
        }

        .priority-low {
            background-color: var(--low-priority);
        }

        .task-actions {
            display: flex;
            gap: 0.5rem;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .task-item:hover .task-actions {
            opacity: 1;
        }

        .task-btn {
            background: none;
            border: none;
            color: var(--text-lighter);
            cursor: pointer;
            font-size: 0.875rem;
            transition: color 0.2s;
        }

        .task-btn:hover {
            color: var(--primary);
        }

        /* Адаптивность */
        @media (max-width: 992px) {
            .app-container {
                grid-template-columns: 80px 1fr;
            }

            .sidebar-header span,
            .nav-item span,
            .projects-header span {
                display: none;
            }

            .logo-icon, .nav-icon {
                font-size: 1.25rem;
            }

            .nav-item {
                justify-content: center;
                padding: 0.75rem;
            }

            .add-project {
                margin-left: auto;
                margin-right: auto;
            }
        }

        @media (max-width: 768px) {
            .main-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .user-actions {
                width: 100%;
                justify-content: space-between;
            }

            .search-bar {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .app-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                height: auto;
                top: auto;
                padding: 0.5rem;
                z-index: 100;
                border-right: none;
                border-top: 1px solid var(--border);
            }

            .sidebar-header, .projects-header {
                display: none;
            }

            .nav-menu {
                flex-direction: row;
                justify-content: space-around;
            }

            .nav-item {
                flex-direction: column;
                font-size: 0.75rem;
                gap: 0.25rem;
                padding: 0.5rem;
            }

            .nav-icon {
                font-size: 1.1rem;
            }

            .main-content {
                padding-bottom: 80px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="flash-message">
        <?= safeOutput($_SESSION['flash_message']) ?>
        <?php unset($_SESSION['flash_message']); ?>
    </div>

    <style>
        .flash-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: var(--success);
            color: white;
            padding: 12px 24px;
            border-radius: 4px;
            box-shadow: var(--shadow-md);
            z-index: 1000;
            animation: fadeInOut 3s ease-in-out;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(-20px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-20px); }
        }
        .all-tasks-btn {
            color: var(--text-light);
            margin-bottom: 10px;
        }

        .all-tasks-btn:hover,
        .all-tasks-btn.active {
            background-color: rgba(94, 53, 177, 0.1);
            color: var(--primary) !important;
            font-weight: 500;
        }
    </style>
<?php endif; ?>
<div class="app-container">
    <!-- Сайдбар -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="#" class="logo">
                <i class="fas fa-calendar-check logo-icon"></i>
                <span>TaskScheduler</span>
            </a>
        </div>

        <nav class="nav-menu">
            <a href="#" class="nav-item active">
                <i class="fas fa-home nav-icon"></i>
                <span>Главная</span>
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-calendar-days nav-icon"></i>
                <span>Календарь</span>
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-tasks nav-icon"></i>
                <span>Задачи</span>
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-bell nav-icon"></i>
                <span>Напоминания</span>
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-chart-pie nav-icon"></i>
                <span>Аналитика</span>
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-cog nav-icon"></i>
                <span>Настройки</span>
            </a>
        </nav>


        <div class="projects-header">
            <span>ПРОЕКТЫ</span>
            <button class="add-project">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        <a href="#" class="nav-item all-tasks-btn" data-project-id="all">
            <i class="fas fa-tasks nav-icon"></i>
            <span>Все задачи</span>
        </a>
        <div id="tasks-container">
        <nav class="nav-menu">
            <?php foreach ($projects as $project): ?>
                <a href="#" class="nav-item"
                   style="color: <?= safeOutput($project['color']) ?>"
                   data-project-id="<?= safeOutput($project['project_id']) ?>">
                    <i class="fas <?= safeOutput($project['icon']) ?> nav-icon"></i>
                    <span><?= safeOutput($project['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
</div>


    </aside>
    <?php
    $user = Yii::$app->user->identity;?>
    <!-- Основное содержимое -->
    <main class="main-content">
        <!-- Хедер -->
        <header class="main-header">
            <h1 class="page-title">Добро пожаловать, <?= safeOutput($user['first_name'] ?? 'Пользователь') ?>!</h1>
            <div class="user-actions">
                <div class="search-bar">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="Поиск задач...">
                </div>

                <div class="notification-badge">
                    <i class="fas fa-bell" style="font-size: 1.25rem; color: var(--text-light);"></i>
                    <?php if ($unreadNotificationsCount > 0): ?>
                        <span class="badge"><?= $unreadNotificationsCount ?></span>
                    <?php endif; ?>
                </div>

                <div class="user-profile" id="userProfileDropdown">
                    <div class="avatar" style="background-color: <?= safeOutput($user['avatar_color']) ?>">
                        <?= mb_substr($user['first_name'] ?? 'П', 0, 1) ?>
                    </div>
                    <i class="fas fa-chevron-down" style="color: var(--text-light);"></i>

                    <!-- Выпадающее меню -->
                    <div class="dropdown-menu" id="dropdownMenu">
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-user"></i> Профиль
                        </a>
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-cog"></i> Настройки
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="/TaskScheduler/web/index.php?r=site%2Flogout" data-method="post" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Выход
                        </a>

                    </div>
                </div>
            </div>
        </header>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Всего задач</span>
                    <div class="stat-icon" style="background-color: rgba(94, 53, 177, 0.1); color: var(--primary);">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
                <div class="stat-value"><?= safeOutput($stats['total_tasks']) ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>Общее количество ваших задач</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Выполнено</span>
                    <div class="stat-icon" style="background-color: rgba(72, 187, 120, 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-value"><?= safeOutput($stats['completed_tasks']) ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>Количество выполненных вами задач</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Просрочено</span>
                    <div class="stat-icon" style="background-color: rgba(245, 101, 101, 0.1); color: var(--danger);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="stat-value"><?= safeOutput($stats['overdue_tasks']) ?></div>
                <div class="stat-change negative">
                    <i class="fas fa-arrow-down"></i>
                    <span>Количество просроченных вами задач</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Высокий приоритет</span>
                    <div class="stat-icon" style="background-color: rgba(237, 137, 54, 0.1); color: var(--warning);">
                        <i class="fas fa-flag"></i>
                    </div>
                </div>
                <div class="stat-value"><?= safeOutput($stats['high_priority_tasks']) ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>Количество особых задач</span>
                </div>
            </div>
        </div>

        <!-- Основной контент -->
        <div class="content-grid">
            <!-- Календарь -->
            <section class="calendar-card">
                <div class="calendar-header">
                    <h2 class="calendar-title"><?= date('F Y') ?></h2>
                    <div class="calendar-nav">
                        <button class="calendar-btn">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="calendar-btn">
                            Сегодня
                        </button>
                        <button class="calendar-btn">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <span class="month-year"><?= date('F Y') ?></span>
                    </div>
                </div>

                <div class="calendar-grid">
                    <div class="day-header">Пн</div>
                    <div class="day-header">Вт</div>
                    <div class="day-header">Ср</div>
                    <div class="day-header">Чт</div>
                    <div class="day-header">Пт</div>
                    <div class="day-header">Сб</div>
                    <div class="day-header">Вс</div>

                    <?php
                    // Генерация календаря
                    $firstDayOfMonth = date('N', strtotime(date('Y-m-01')));
                    $daysInMonth = date('t');
                    $currentDay = date('j');

                    // Пустые ячейки для первого дня месяца
                    for ($i = 1; $i < $firstDayOfMonth; $i++) {
                        echo '<div class="calendar-day"></div>';
                    }

                    // Ячейки с днями месяца
                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $date = date('Y-m') . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                        $isCurrentDay = ($day == $currentDay);

                        echo '<div class="calendar-day' . ($isCurrentDay ? ' current-day' : '') . '">';
                        echo '<div class="day-number">' . $day . '</div>';

                        // Вывод событий для этого дня
                        foreach ($events as $event) {
                            $eventDay = date('j', strtotime($event['start_datetime']));
                            if ($eventDay == $day) {
                                $eventClass = '';
                                if ($event['event_type'] == 'meeting') $eventClass = 'event-primary';
                                elseif ($event['event_type'] == 'deadline') $eventClass = 'event-secondary';
                                elseif ($event['event_type'] == 'birthday') $eventClass = 'event-accent';

                                echo '<div class="event ' . $eventClass . '">' . safeOutput($event['title']) . '</div>';
                            }
                        }

                        echo '</div>';
                    }
                    ?>
                </div>
            </section>

            <!-- Список задач -->
            <section class="tasks-card">
                <div class="tasks-header">
                    <h2 class="tasks-title">Ближайшие задачи</h2>
                    <button class="add-task-btn">
                        <i class="fas fa-plus"></i>
                        <span>Добавить</span>
                    </button>
                </div>

                <div class="task-filters">
                    <button class="filter-btn active">Все</button>
                    <button class="filter-btn">Сегодня</button>
                    <button class="filter-btn">Высокий</button>
                    <button class="filter-btn">Завершённые</button>
                </div>

                <div class="task-list">
                    <?php foreach ($tasks as $task): ?>
                        <div class="task-item" data-task-id="<?= safeOutput($task['task_id']) ?>">
                            <input type="checkbox" class="task-checkbox" <?= $task['is_completed'] ? 'checked' : '' ?>>
                            <div class="task-content">
                                <div class="task-title <?= $task['is_completed'] ? 'completed' : '' ?>">
                                    <?= safeOutput($task['title']) ?>
                                    <span class="task-priority priority-<?= safeOutput($task['priority']) ?>"></span>
                                </div>
                                <div class="task-description"><?= safeOutput($task['description']) ?></div>
                                <div class="task-meta">
                                    <?php if ($task['due_date']): ?>
                                        <span class="task-date">
                                        <i class="far fa-calendar-alt"></i>
                                        <?= date('d M', strtotime($task['due_date'])) ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($task['project_name']): ?>
                                        <span>Проект: <?= safeOutput($task['project_name']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($subtasks[$task['task_id']])): ?>
                                    <div class="subtasks" style="margin-top: 8px;">
                                        <?php foreach ($subtasks[$task['task_id']] as $subtask): ?>
                                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                                <input type="checkbox" <?= $subtask['is_completed'] ? 'checked' : '' ?>
                                                       style="width: 14px; height: 14px;">
                                                <span style="font-size: 0.8rem; <?= $subtask['is_completed'] ? 'text-decoration: line-through; color: var(--text-lighter);' : '' ?>">
                                            <?= safeOutput($subtask['title']) ?>
                                        </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="task-actions">
                                <button class="task-btn">
                                    <i class="far fa-edit"></i>
                                </button>
                                <button class="task-btn">
                                    <i class="far fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>
</div>

<!-- Скрипты для взаимодействия -->
<script>
    // Функция для применения темы
    function applyTheme(theme) {
        // Удаляем все классы тем
        document.body.classList.remove('dark-theme', 'russian-theme');

        // Добавляем класс выбранной темы (кроме светлой)
        if (theme !== 'light') {
            document.body.classList.add(theme + '-theme');
        }

        // Сохраняем выбор в localStorage
        localStorage.setItem('selectedTheme', theme);

        // Обновляем активный вариант в настройках
        updateActiveThemeOption(theme);
    }

    // Функция для обновления активной опции темы
    function updateActiveThemeOption(theme) {
        document.querySelectorAll('.theme-option').forEach(option => {
            option.classList.remove('active');
            option.querySelector('.fa-check').style.display = 'none';

            if (option.dataset.theme === theme) {
                option.classList.add('active');
                option.querySelector('.fa-check').style.display = 'inline-block';
            }
        });
    }

    // Обработчик кликов по выбору темы
    document.addEventListener('click', function(e) {
        if (e.target.closest('.theme-option')) {
            const option = e.target.closest('.theme-option');
            const theme = option.dataset.theme;
            applyTheme(theme);
        }
    });

    // При загрузке страницы применяем сохраненную тему
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('selectedTheme') || 'light';
        applyTheme(savedTheme);
    });

    // Обработка открытия/закрытия модального окна настроек
    document.querySelector('.nav-item:nth-child(6)').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('settingsModal').style.display = 'flex';
    });

    document.querySelector('.close-settings-modal').addEventListener('click', function() {
        document.getElementById('settingsModal').style.display = 'none';
    });

    document.getElementById('settingsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
</script>
<!-- Модальное окно настроек -->
<div id="settingsModal" class="settings-modal">
    <div class="settings-modal-content">
        <div class="settings-modal-header">
            <h3 class="settings-modal-title"><i class="fas fa-cog"></i> Настройки темы</h3>
            <button class="close-settings-modal">&times;</button>
        </div>

        <div class="theme-options">
            <div class="theme-option" data-theme="light">
                <div class="theme-preview" style="background: linear-gradient(135deg, #f7fafc 0%, #ffffff 50%, #e2e8f0 100%);"></div>
                <div class="theme-info">
                    <div class="theme-name">Светлая тема</div>
                    <div class="theme-description">Стандартная светлая цветовая схема</div>
                </div>
                <i class="fas fa-check" style="color: var(--primary); display: none;"></i>
            </div>

            <div class="theme-option" data-theme="dark">
                <div class="theme-preview" style="background: linear-gradient(135deg, #1a202c 0%, #2d3748 50%, #4a5568 100%);"></div>
                <div class="theme-info">
                    <div class="theme-name">Темная тема</div>
                    <div class="theme-description">Комфортная тема для работы ночью</div>
                </div>
                <i class="fas fa-check" style="color: var(--primary); display: none;"></i>
            </div>

            <div class="theme-option" data-theme="russian">
                <div class="theme-preview" style="background: linear-gradient(
            to bottom,
            #ffffff 0%, #ffffff 33%,
            #0039a6 33%, #0039a6 66%,
            #d52b1e 66%, #d52b1e 100%
        );;"></div>
                <div class="theme-info">
                    <div class="theme-name">Российская тема</div>
                    <div class="theme-description">Тема в цветах российского флага</div>
                </div>
                <i class="fas fa-check" style="color: var(--primary); display: none;"></i>
            </div>
        </div>
    </div>
</div>

<!-- Стили для модального окна -->
<style>
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0,0,0,0.5);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        padding: 20px;
        background-color: white;
        border-radius: 8px;
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 24px;
        border-bottom: 1px solid var(--border);
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.25rem;
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-light);
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-row {
        display: flex;
        gap: 16px;
    }

    .form-row .form-group {
        flex: 1;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text);
    }

    input[type="text"],
    textarea,
    select,
    input[type="datetime-local"] {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.875rem;
    }

    textarea {
        min-height: 80px;
        resize: vertical;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 16px 24px;
        border-top: 1px solid var(--border);
    }

    .btn-primary {
        background-color: var(--primary);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
    }

    .btn-secondary {
        background-color: var(--bg);
        color: var(--text);
        border: 1px solid var(--border);
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        margin: 0 24px 16px;
    }

    .btn-cancel {
        background: none;
        border: none;
        color: var(--text-light);
        cursor: pointer;
    }

    .subtask-input {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }

    .subtask-input input {
        flex: 1;
    }

    .remove-subtask {
        background: none;
        border: none;
        color: var(--danger);
        cursor: pointer;
        font-size: 1.2rem;
    }
</style>

<!-- Скрипты для работы формы -->

<!-- Добавьте этот код перед закрывающим тегом </body> -->
<div id="calendarModal" class="calendar-modal">
    <div class="calendar-modal-content">
        <div class="calendar-modal-header">
            <h3 class="calendar-modal-title">Календарь</h3>
            <button class="close-calendar-modal">&times;</button>
        </div>

        <div class="calendar-nav-buttons">
            <button id="prevMonth" class="calendar-nav-btn">
                <i class="fas fa-chevron-left"></i> Предыдущий месяц
            </button>
            <h3 id="currentMonthYear"><?= date('F Y') ?></h3>
            <button id="nextMonth" class="calendar-nav-btn">
                Следующий месяц <i class="fas fa-chevron-right"></i>
            </button>
        </div>

        <div class="modal-calendar-grid">
            <div class="modal-day-header">Пн</div>
            <div class="modal-day-header">Вт</div>
            <div class="modal-day-header">Ср</div>
            <div class="modal-day-header">Чт</div>
            <div class="modal-day-header">Пт</div>
            <div class="modal-day-header">Сб</div>
            <div class="modal-day-header">Вс</div>

            <?php
            // Генерация календаря для модального окна
            $firstDayOfMonth = date('N', strtotime(date('Y-m-01')));
            $daysInMonth = date('t');
            $currentDay = date('j');

            // Пустые ячейки для первого дня месяца
            for ($i = 1; $i < $firstDayOfMonth; $i++) {
                echo '<div class="modal-calendar-day"></div>';
            }

            // Ячейки с днями месяца
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = date('Y-m') . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                $isCurrentDay = ($day == $currentDay);

                echo '<div class="modal-calendar-day' . ($isCurrentDay ? ' modal-current-day' : '') . '">';
                echo '<div class="modal-day-number">' . $day . '</div>';

                // Вывод событий для этого дня
                foreach ($events as $event) {
                    $eventDay = date('j', strtotime($event['start_datetime']));
                    if ($eventDay == $day) {
                        $eventClass = '';
                        if ($event['event_type'] == 'meeting') $eventClass = 'event-primary';
                        elseif ($event['event_type'] == 'deadline') $eventClass = 'event-secondary';
                        elseif ($event['event_type'] == 'birthday') $eventClass = 'event-accent';

                        echo '<div class="modal-event ' . $eventClass . '">' . safeOutput($event['title']) . '</div>';
                    }
                }

                echo '</div>';
            }
            ?>
        </div>
    </div>
</div>

<script>
    // Обработка клика по кнопке календаря в сайдбаре
    document.querySelector('.nav-item:nth-child(2)').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('calendarModal').style.display = 'flex';
    });

    // Закрытие модального окна календаря
    document.querySelector('.close-calendar-modal').addEventListener('click', function() {
        document.getElementById('calendarModal').style.display = 'none';
    });

    // Закрытие по клику вне формы
    document.getElementById('calendarModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });

    // Навигация по месяцам (упрощенная версия)
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();
    const monthNames = ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"];

    function updateCalendarTitle() {
        document.getElementById('currentMonthYear').textContent = monthNames[currentMonth] + ' ' + currentYear;
    }

    document.getElementById('prevMonth').addEventListener('click', function() {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        updateCalendarTitle();
        // Здесь можно добавить AJAX-загрузку данных для нового месяца
    });

    document.getElementById('nextMonth').addEventListener('click', function() {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        updateCalendarTitle();
        // Здесь можно добавить AJAX-загрузку данных для нового месяца
    });
</script>
<style>
    /* Стили для модального окна календаря */
    .calendar-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0,0,0,0.5);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .calendar-modal-content {
        background-color: white;
        border-radius: 8px;
        width: 90%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        padding: 20px;
    }

    .calendar-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .calendar-modal-title {
        font-size: 1.5rem;
        font-weight: 600;
    }

    .close-calendar-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-light);
    }

    /* Улучшенный календарь для модального окна */
    .modal-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
    }

    .modal-day-header {
        text-align: center;
        font-weight: 600;
        padding: 10px;
        background-color: var(--primary-light);
        color: white;
        border-radius: 4px;
    }

    .modal-calendar-day {
        aspect-ratio: 1;
        border: 1px solid var(--border);
        border-radius: 4px;
        padding: 8px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        flex-direction: column;
    }

    .modal-calendar-day:hover {
        background-color: var(--bg);
    }

    .modal-day-number {
        font-weight: 500;
        margin-bottom: 4px;
    }

    .modal-event {
        font-size: 0.7rem;
        padding: 2px 4px;
        border-radius: 2px;
        margin-bottom: 2px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .modal-current-day {
        background-color: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    .modal-current-day .modal-day-number {
        color: white;
    }

    /* Навигация календаря */
    .calendar-nav-buttons {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
    }

    .calendar-nav-btn {
        background-color: var(--primary);
        color: white;
        border: none;
        border-radius: 4px;
        padding: 8px 16px;
        cursor: pointer;
    }
</style>
<!-- Добавьте этот код в секцию стилей -->
<style>
    /* Стили для модального окна задач */
    .tasks-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0,0,0,0.5);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .tasks-modal-content {
        background-color: white;
        border-radius: 8px;
        width: 90%;
        max-width: 900px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        padding: 25px;
    }

    .tasks-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border);
    }

    .tasks-modal-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--primary);
    }

    .close-tasks-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-light);
    }

    /* Стили для списка задач */
    .tasks-list-container {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .task-filter-buttons {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .task-filter-btn {
        padding: 8px 16px;
        border-radius: 20px;
        border: 1px solid var(--border);
        background-color: white;
        cursor: pointer;
        font-size: 0.875rem;
        transition: all 0.2s;
    }

    .task-filter-btn:hover, .task-filter-btn.active {
        background-color: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .task-item-modal {
        background-color: var(--bg-panel);
        border-radius: 8px;
        padding: 15px;
        box-shadow: var(--shadow-sm);
        transition: all 0.2s;
        border-left: 4px solid var(--border);
    }

    .task-item-modal.completed {
        opacity: 0.8;
        border-left-color: var(--success);
    }

    .task-item-modal.high-priority {
        border-left-color: var(--danger);
    }

    .task-item-modal.medium-priority {
        border-left-color: var(--warning);
    }

    .task-item-modal.low-priority {
        border-left-color: var(--low-priority);
    }

    .task-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .task-title-modal {
        font-weight: 600;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .task-title-modal.completed {
        text-decoration: line-through;
        color: var(--text-lighter);
    }

    .task-checkbox-modal {
        width: 18px;
        height: 18px;
        accent-color: var(--success);
    }

    .task-date-modal {
        font-size: 0.8rem;
        color: var(--text-lighter);
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .task-description-modal {
        margin: 10px 0;
        color: var(--text-light);
        font-size: 0.9rem;
    }

    .task-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.8rem;
        color: var(--text-lighter);
    }

    .task-project-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        background-color: rgba(94, 53, 177, 0.1);
        color: var(--primary);
    }

    .task-actions-modal {
        display: flex;
        gap: 10px;
    }

    .task-action-btn {
        background: none;
        border: none;
        color: var(--text-lighter);
        cursor: pointer;
        font-size: 0.9rem;
        transition: color 0.2s;
    }

    .task-action-btn:hover {
        color: var(--primary);
    }

    .no-tasks-message {
        text-align: center;
        padding: 30px;
        color: var(--text-lighter);
    }
    .nav-menu a.active {
        background-color: rgba(94, 53, 177, 0.1);
        color: var(--primary) !important;
        font-weight: 500;
    }
</style>

<!-- Добавьте этот код перед закрывающим тегом </body> -->
<div id="tasksModal" class="tasks-modal">
    <div class="tasks-modal-content">
        <div class="tasks-modal-header">
            <h3 class="tasks-modal-title"><i class="fas fa-tasks"></i> Все задачи</h3>
            <button class="close-tasks-modal">&times;</button>
        </div>

        <div class="task-filter-buttons">
            <button class="task-filter-btn active" data-filter="all">Все задачи</button>
            <button class="task-filter-btn" data-filter="today">Сегодня</button>
            <button class="task-filter-btn" data-filter="completed">Выполненные</button>
            <button class="task-filter-btn" data-filter="active">Активные</button>
            <button class="task-filter-btn" data-filter="high">Высокий приоритет</button>
        </div>

        <div class="tasks-list-container" id="tasksListContainer">
            <?php foreach ($tasks as $task): ?>
                <div class="task-item-modal <?= $task['is_completed'] ? 'completed' : '' ?> <?= 'priority-' . $task['priority'] ?>">
                    <div class="task-header">
                        <div class="task-title-modal <?= $task['is_completed'] ? 'completed' : '' ?>">
                            <input type="checkbox" class="task-checkbox-modal" <?= $task['is_completed'] ? 'checked' : '' ?> data-task-id="<?= $task['task_id'] ?>">
                            <?= safeOutput($task['title']) ?>
                        </div>
                        <div class="task-date-modal">
                            <i class="far fa-calendar-alt"></i>
                            <?= $task['due_date'] ? date('d.m.Y', strtotime($task['due_date'])) : 'Без срока' ?>
                        </div>
                    </div>

                    <?php if (!empty($task['description'])): ?>
                        <div class="task-description-modal">
                            <?= safeOutput($task['description']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="task-footer">
                        <?php if ($task['project_name']): ?>
                            <span class="task-project-badge" style="background-color: rgba(<?= hex2rgb($task['project_color'] ?? '#5e35b1') ?>, 0.1); color: <?= safeOutput($task['project_color'] ?? '#5e35b1') ?>;">
                                <i class="fas <?= safeOutput($task['project_icon'] ?? 'fa-folder') ?>"></i>
                                <?= safeOutput($task['project_name']) ?>
                            </span>
                        <?php else: ?>
                            <span>Без проекта</span>
                        <?php endif; ?>

                        <div class="task-actions-modal">
                            <button class="task-action-btn" title="Редактировать"><i class="far fa-edit"></i></button>
                            <button class="task-action-btn" title="Удалить"><i class="far fa-trash-alt"></i></button>
                        </div>
                    </div>

                    <?php if (!empty($subtasks[$task['task_id']])): ?>
                        <div class="subtasks-container" style="margin-top: 10px; padding-left: 20px;">
                            <?php foreach ($subtasks[$task['task_id']] as $subtask): ?>
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                    <input type="checkbox" <?= $subtask['is_completed'] ? 'checked' : '' ?>
                                           style="width: 16px; height: 16px;">
                                    <span style="font-size: 0.85rem; <?= $subtask['is_completed'] ? 'text-decoration: line-through; color: var(--text-lighter);' : '' ?>">
                                        <?= safeOutput($subtask['title']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if (empty($tasks)): ?>
                <div class="no-tasks-message">
                    <i class="far fa-check-circle" style="font-size: 3rem; margin-bottom: 15px; color: var(--success);"></i>
                    <h3>Нет задач</h3>
                    <p>Все задачи выполнены или еще не созданы</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Функция для конвертации HEX в RGB (для прозрачности)
    function hex2rgb(hex) {
        hex = hex.replace('#', '');
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        return `${r}, ${g}, ${b}`;
    }

    // Обработка клика по кнопке задач в сайдбаре
    document.querySelector('.nav-item:nth-child(3)').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('tasksModal').style.display = 'flex';
    });


    // Обработчик для кнопки закрытия модального окна задач
    document.querySelector('.close-tasks-modal').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('tasksModal').style.display = 'none';
    });

    // Обработчик для кнопки закрытия модального окна календаря
    document.querySelector('.close-calendar-modal').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('calendarModal').style.display = 'none';
    });

    // Альтернативный вариант - делегирование событий
    document.addEventListener('click', function(e) {
        // Закрытие по кнопке
        if (e.target.classList.contains('close-modal')) {
            e.preventDefault();
            document.getElementById('taskModal').style.display = 'none';
        }

        // Закрытие по клику вне окна
        if (e.target === document.getElementById('taskModal')) {
            document.getElementById('taskModal').style.display = 'none';
        }
    });
    // Закрытие по клику вне формы
    document.getElementById('tasksModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });

    // Полноценная реализация фильтрации задач
    document.querySelectorAll('.task-filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelector('.task-filter-btn.active').classList.remove('active');
            this.classList.add('active');
            const filter = this.dataset.filter;

            const allTasks = document.querySelectorAll('.task-item-modal');
            const today = new Date();
            const todayStr = `${today.getDate()}.${today.getMonth()+1}.${today.getFullYear()}`;

            allTasks.forEach(task => {
                task.style.display = 'block';
                const taskDate = task.querySelector('.task-date-modal').textContent;
                const isCompleted = task.classList.contains('completed');
                const priorityClass = Array.from(task.classList).find(cls =>
                    cls.startsWith('priority-'));
                const priority = priorityClass ? priorityClass.split('-')[1] : 'medium';

                if (filter === 'completed') {
                    if (!isCompleted) task.style.display = 'none';
                }
                else if (filter === 'active') {
                    if (isCompleted) task.style.display = 'none';
                }
                else if (filter === 'high') {
                    if (priority !== 'high') task.style.display = 'none';
                }
                else if (filter === 'today') {
                    if (taskDate !== todayStr && taskDate !== 'Без срока') {
                        task.style.display = 'none';
                    }
                }
            });

            // Показываем сообщение, если нет задач после фильтрации
            const visibleTasks = Array.from(allTasks).filter(task =>
                task.style.display !== 'none' &&
                !task.classList.contains('no-tasks-message')
            );

            const noTasksMessage = document.querySelector('.no-tasks-message');

            if (visibleTasks.length === 0) {
                if (!noTasksMessage) {
                    const container = document.getElementById('tasksListContainer');
                    container.innerHTML += `
                    <div class="no-tasks-message">
                        <i class="far fa-check-circle" style="font-size: 3rem; margin-bottom: 15px; color: var(--success);"></i>
                        <h3>Нет задач по выбранному фильтру</h3>
                        <p>Попробуйте изменить параметры фильтрации</p>
                    </div>
                `;
                }
            } else if (noTasksMessage) {
                noTasksMessage.remove();
            }
        });
    });


    // Обработка чекбоксов задач
    document.querySelectorAll('.task-checkbox-modal').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const taskId = this.dataset.taskId;
            const taskItem = this.closest('.task-item-modal');
            const taskTitle = taskItem.querySelector('.task-title-modal');

            if (this.checked) {
                taskItem.classList.add('completed');
                taskTitle.classList.add('completed');
                // Здесь можно отправить AJAX-запрос на сервер для обновления статуса
                console.log('Задача выполнена:', taskId);
            } else {
                taskItem.classList.remove('completed');
                taskTitle.classList.remove('completed');
                console.log('Задача не выполнена:', taskId);
            }
        });
    });
</script>
<div class="task-item-modal priority-high">...</div>
<div class="task-item-modal priority-medium">...</div>
<div class="task-item-modal priority-low">...</div>
<!-- Добавьте этот код в секцию стилей -->
<style>
    /* Стили для модального окна аналитики */
    .analytics-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0,0,0,0.5);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .analytics-modal-content {
        background-color: white;
        border-radius: 8px;
        width: 90%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        padding: 25px;
    }

    .analytics-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border);
    }

    .analytics-modal-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--primary);
    }

    .close-analytics-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-light);
    }

    /* Стили для статистики */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card-modal {
        background-color: var(--bg-panel);
        border-radius: 8px;
        padding: 20px;
        box-shadow: var(--shadow-sm);
        text-align: center;
    }

    .stat-value-modal {
        font-size: 2.5rem;
        font-weight: 700;
        margin: 10px 0;
    }

    .stat-title-modal {
        font-size: 1rem;
        color: var(--text-light);
    }

    .stat-icon-modal {
        font-size: 1.5rem;
        margin-bottom: 10px;
    }

    /* Стили для графиков */
    .charts-container {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;
    }

    .chart-card {
        background-color: var(--bg-panel);
        border-radius: 8px;
        padding: 20px;
        box-shadow: var(--shadow-sm);
    }

    .chart-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 15px;
        color: var(--primary);
    }

    .chart-placeholder {
        height: 250px;
        background-color: var(--bg);
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-light);
    }

    /* Стили для списка просроченных задач */
    .overdue-tasks {
        margin-top: 30px;
    }

    .overdue-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 15px;
        color: var(--danger);
    }

    .overdue-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .overdue-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 15px;
        background-color: var(--bg);
        border-radius: 6px;
        border-left: 4px solid var(--danger);
    }

    .overdue-task-title {
        font-weight: 500;
    }

    .overdue-task-date {
        font-size: 0.8rem;
        color: var(--text-light);
    }
</style>

<!-- Добавьте этот код перед закрывающим тегом </body> -->
<div id="analyticsModal" class="analytics-modal">
    <div class="analytics-modal-content">
        <div class="analytics-modal-header">
            <h3 class="analytics-modal-title"><i class="fas fa-chart-pie"></i> Аналитика задач</h3>
            <button class="close-analytics-modal">&times;</button>
        </div>

        <div class="stats-container">
            <div class="stat-card-modal">
                <div class="stat-icon-modal" style="color: var(--primary);">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-value-modal"><?= safeOutput($stats['total_tasks']) ?></div>
                <div class="stat-title-modal">Всего задач</div>
            </div>

            <div class="stat-card-modal">
                <div class="stat-icon-modal" style="color: var(--success);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value-modal"><?= safeOutput($stats['completed_tasks']) ?></div>
                <div class="stat-title-modal">Выполнено</div>
            </div>

            <div class="stat-card-modal">
                <div class="stat-icon-modal" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value-modal"><?= safeOutput($stats['overdue_tasks']) ?></div>
                <div class="stat-title-modal">Просрочено</div>
            </div>

            <div class="stat-card-modal">
                <div class="stat-icon-modal" style="color: var(--warning);">
                    <i class="fas fa-flag"></i>
                </div>
                <div class="stat-value-modal"><?= safeOutput($stats['high_priority_tasks']) ?></div>
                <div class="stat-title-modal">Высокий приоритет</div>
            </div>
        </div>

        <div class="charts-container">
            <div class="chart-card">
                <h4 class="chart-title">Состояние задач</h4>
                <div class="chart-placeholder">
                    <p>Нажмите для отображения диаграммы выполнения задач</p>

                </div>
            </div>

        </div>

        <?php if ($stats['overdue_tasks'] > 0): ?>
            <div class="overdue-tasks">
                <h4 class="overdue-title"><i class="fas fa-exclamation-circle"></i> Просроченные задачи</h4>
                <div class="overdue-list">
                    <?php
                    // Получаем просроченные задачи
                    $overdueStmt = $pdo->prepare("
                        SELECT t.title, t.due_date, p.name AS project_name
                        FROM tasks t
                        LEFT JOIN projects p ON t.project_id = p.project_id
                        WHERE t.user_id = ? 
                        AND t.due_date < NOW() 
                        AND t.is_completed = 0
                        ORDER BY t.due_date ASC
                        LIMIT 5
                    ");
                    $overdueStmt->execute([$user_id]);
                    $overdueTasks = $overdueStmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($overdueTasks as $task): ?>
                        <div class="overdue-item">
                            <div>
                                <div class="overdue-task-title"><?= safeOutput($task['title']) ?></div>
                                <div class="overdue-task-date">
                                    Просрочено с <?= date('d.m.Y', strtotime($task['due_date'])) ?>
                                    <?php if ($task['project_name']): ?>
                                        • Проект: <?= safeOutput($task['project_name']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button class="task-action-btn" title="Перейти к задаче">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Обработка клика по кнопке аналитики в сайдбаре
    document.querySelector('.nav-item:nth-child(5)').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('analyticsModal').style.display = 'flex';
    });

    // Закрытие модального окна аналитики
    document.querySelector('.close-analytics-modal').addEventListener('click', function() {
        document.getElementById('analyticsModal').style.display = 'none';
    });

    // Закрытие по клику вне формы
    document.getElementById('analyticsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });

    // Инициализация графиков (пример для Chart.js)
    document.getElementById('analyticsModal').addEventListener('click', function() {
        // Здесь можно добавить код для инициализации графиков
        // Например, с использованием библиотеки Chart.js
        console.log('Можно инициализировать графики здесь');
    });
    // Пример инициализации графиков
    function initCharts() {
        // График выполнения задач
        const statusCtx = document.createElement('canvas');
        document.querySelector('.chart-placeholder').replaceWith(statusCtx);

        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Выполнено', 'Активные', 'Просрочено'],
                datasets: [{
                    data: [
                        <?= $stats['completed_tasks'] ?>,
                        <?= $stats['total_tasks'] - $stats['completed_tasks'] - $stats['overdue_tasks'] ?>,
                        <?= $stats['overdue_tasks'] ?>
                    ],
                    backgroundColor: [
                        'rgba(72, 187, 120, 0.8)',
                        'rgba(66, 153, 225, 0.8)',
                        'rgba(245, 101, 101, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // График приоритетов
        const priorityCtx = document.createElement('canvas');
        document.querySelectorAll('.chart-placeholder')[1].replaceWith(priorityCtx);

        new Chart(priorityCtx, {
            type: 'pie',
            data: {
                labels: ['Высокий', 'Средний', 'Низкий'],
                datasets: [{
                    data: [
                        <?= $stats['high_priority_tasks'] ?>,

                        <?= $lowPriorityCount ?? 0 ?>
                    ],
                    backgroundColor: [
                        'rgba(245, 101, 101, 0.8)',
                        'rgba(237, 137, 54, 0.8)',
                        'rgba(160, 174, 192, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Вызываем инициализацию графиков при открытии модального окна
    document.getElementById('analyticsModal').addEventListener('click', function() {
        initCharts();
    });
</script>
<script>
    // Обработка кликов по проектам в сайдбаре
    document.querySelectorAll('.nav-menu a[data-project-id]').forEach(projectLink => {
        projectLink.addEventListener('click', function(e) {
            e.preventDefault();
            const projectId = this.dataset.projectId;

            // Скрываем все задачи
            document.querySelectorAll('.task-item').forEach(task => {
                task.style.display = 'none';
            });

            // Показываем только задачи выбранного проекта
            document.querySelectorAll(`.task-item[data-project-id="${projectId}"]`).forEach(task => {
                task.style.display = 'flex';
            });

            // Обновляем активный элемент в меню
            document.querySelectorAll('.nav-menu a').forEach(link => {
                link.classList.remove('active');
            });
            this.classList.add('active');

            // Обновляем заголовок
            const projectName = this.querySelector('span').textContent;
            document.querySelector('.tasks-title').textContent = `Задачи: ${projectName}`;
        });
    });

    // Обработка клика по "Все задачи" (главная страница)
    document.querySelector('.nav-item.active').addEventListener('click', function(e) {
        e.preventDefault();

        // Показываем все задачи
        document.querySelectorAll('.task-item').forEach(task => {
            task.style.display = 'flex';
        });

        // Обновляем заголовок
        document.querySelector('.tasks-title').textContent = 'Ближайшие задачи';

        // Обновляем активный элемент в меню
        document.querySelectorAll('.nav-menu a').forEach(link => {
            link.classList.remove('active');
        });
        document.querySelector('.nav-item.active').classList.add('active');
    });
    // Обработка клика по "Все задачи"
    document.querySelector('.all-tasks-btn').addEventListener('click', function(e) {
        e.preventDefault();

        // Показываем все задачи
        document.querySelectorAll('.task-item').forEach(task => {
            task.style.display = 'flex';
        });

        // Обновляем активный элемент в меню
        document.querySelectorAll('.nav-menu a, .all-tasks-btn').forEach(link => {
            link.classList.remove('active');
        });
        this.classList.add('active');

        // Обновляем заголовок
        document.querySelector('.tasks-title').textContent = 'Все задачи';
    });
</script>
<style>
    /* Стили для модального окна настроек */
    .settings-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0,0,0,0.5);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .settings-modal-content {
        background-color: var(--bg-panel);
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow-lg);
        padding: 25px;
    }

    .settings-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border);
    }

    .settings-modal-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--primary);
    }

    .close-settings-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-light);
    }

    .theme-options {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-bottom: 20px;
    }

    .theme-option {
        display: flex;
        align-items: center;
        padding: 15px;
        border-radius: 8px;
        background-color: var(--bg);
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid transparent;
    }

    .theme-option:hover {
        background-color: rgba(94, 53, 177, 0.1);
    }

    .theme-option.active {
        border-color: var(--primary);
    }

    .theme-preview {
        width: 40px;
        height: 40px;
        border-radius: 6px;
        margin-right: 15px;
    }

    .theme-info {
        flex: 1;
    }

    .theme-name {
        font-weight: 600;
        margin-bottom: 5px;
    }

    .theme-description {
        font-size: 0.875rem;
        color: var(--text-light);
    }

    /* Темная тема */
    body.dark-theme {
        --primary: #7e57c2;
        --primary-light: #9575cd;
        --primary-dark: #673ab7;
        --secondary: #26a69a;
        --accent: #ff7043;
        --text: #e2e8f0;
        --text-light: #a0aec0;
        --text-lighter: #718096;
        --bg: #1a202c;
        --bg-panel: #2d3748;
        --border: #4a5568;
        --success: #48bb78;
        --warning: #ed8936;
        --danger: #f56565;
        --info: #4299e1;
        --low-priority: #718096;
    }

    /* Российская тема */
    /* Российская тема с фиксированным фоновым изображением */
    body.russian-theme {
        --primary: #d52b1e; /* Красный */
        --primary-light: #f44336;
        --primary-dark: #b71c1c;
        --secondary: #0057b7; /* Синий */
        --accent: #ffd700; /* Золотой */
        --text: #212121;
        --text-light: #424242;
        --text-lighter: #616161;
        --bg: #f5f5f5;
        --bg-panel: rgba(255, 255, 255, 0.9); /* Полупрозрачный белый для лучшей читаемости */
        --border: #e0e0e0;
        --success: #388e3c;
        --warning: #ffa000;
        --danger: #d32f2f;
        --info: #1976d2;
        --low-priority: #9e9e9e;

        /* Ваше фоновое изображение */
        background-image: url('/TaskScheduler/web/img/1111.jpg');
        background-size: cover;
        background-attachment: fixed;
        background-position: center;
    }

    /* Для лучшей читаемости контента */
    body.russian-theme .main-content,
    body.russian-theme .sidebar,
    body.russian-theme .stat-card,
    body.russian-theme .calendar-card,
    body.russian-theme .tasks-card {

        box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
    }
</style>
<script>
    // Обработка клика по кнопке настроек в сайдбаре
    document.querySelector('.nav-menu a:nth-child(6)').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('settingsModal').style.display = 'flex';
    });

    // Закрытие модального окна настроек
    document.querySelector('.close-settings-modal').addEventListener('click', function() {
        document.getElementById('settingsModal').style.display = 'none';
    });

    // Закрытие по клику вне формы
    document.getElementById('settingsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });

    // Обработка выбора темы
    document.querySelectorAll('.theme-option').forEach(option => {
        option.addEventListener('click', function() {
            // Удаляем активный класс у всех вариантов
            document.querySelectorAll('.theme-option').forEach(opt => {
                opt.classList.remove('active');
                opt.querySelector('.fa-check').style.display = 'none';
            });

            // Добавляем активный класс к выбранному варианту
            this.classList.add('active');
            this.querySelector('.fa-check').style.display = 'block';

            // Получаем выбранную тему
            const theme = this.dataset.theme;

            // Удаляем все классы тем
            document.body.classList.remove('dark-theme', 'russian-theme');

            // Добавляем класс выбранной темы (кроме светлой)
            if (theme !== 'light') {
                document.body.classList.add(`${theme}-theme`);
            }

            // Сохраняем выбор в localStorage
            localStorage.setItem('selectedTheme', theme);
        });
    });

    // При загрузке страницы проверяем сохраненную тему
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('selectedTheme') || 'light';

        // Применяем сохраненную тему
        if (savedTheme !== 'light') {
            document.body.classList.add(`${savedTheme}-theme`);
        }

        // Активируем соответствующий вариант в настройках
        const activeOption = document.querySelector(`.theme-option[data-theme="${savedTheme}"]`);
        if (activeOption) {
            document.querySelectorAll('.theme-option').forEach(opt => {
                opt.classList.remove('active');
                opt.querySelector('.fa-check').style.display = 'none';
            });

            activeOption.classList.add('active');
            activeOption.querySelector('.fa-check').style.display = 'block';
        }
    });
</script>

<!-- Модальное окно для создания задачи -->
<div id="taskModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Добавить задачу</h3>
            <button class="close-modal">&times;</button>
        </div>
        <form id="taskForm" method="POST">
            <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->csrfToken ?>">
            <div class="form-group">
                <label>Название</label>
                <input type="text" name="title" required>
            </div>

            <div class="form-group">
                <label>Описание</label>
                <textarea name="description"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Срок</label>
                    <input type="datetime-local" name="due_date">
                </div>

                <div class="form-group">
                    <label>Приоритет</label>
                    <select name="priority">
                        <option value="low">Низкий</option>
                        <option value="medium" selected>Средний</option>
                        <option value="high">Высокий</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Проект</label>
                <select name="project_id">
                    <option value="">Без проекта</option>
                    <?php foreach ($projects as $project): ?>
                        <?php if (!str_starts_with($project['project_id'], 'default_')): ?>
                            <option value="<?= safeOutput($project['project_id']) ?>">
                                <?= safeOutput($project['name']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel close-modal">Отмена</button>
                <button type="submit" class="btn-primary">Добавить</button>
            </div>
        </form>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.task-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const taskId = this.closest('.task-item').dataset.taskId;
                const isCompleted = this.checked ? 1 : 0;

                fetch('update_task.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        task_id: taskId,
                        is_completed: isCompleted
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            this.checked = !isCompleted;
                            alert('Ошибка обновления задачи');
                        } else {
                            const taskTitle = this.closest('.task-item').querySelector('.task-title');
                            if (isCompleted) {
                                taskTitle.classList.add('completed');
                            } else {
                                taskTitle.classList.remove('completed');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.checked = !isCompleted;
                    });
            });
        });
    });
</script>
<script>
    // Открытие модального окна
    document.querySelector('.add-task-btn').addEventListener('click', function() {
        document.getElementById('taskModal').style.display = 'flex';
    });

    // Закрытие модального окна
    document.addEventListener('click', function(e) {
        // Закрытие по кнопке
        if (e.target.classList.contains('close-modal')) {
            e.preventDefault();
            document.getElementById('taskModal').style.display = 'none';
        }

        // Закрытие по клику вне окна
        if (e.target === document.getElementById('taskModal')) {
            document.getElementById('taskModal').style.display = 'none';
        }
    });
</script>
<style>
    /* Стили для выпадающего меню */
    .user-profile {
        position: relative;
        cursor: pointer;
    }

    .dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background-color: var(--bg-panel);
        border-radius: 6px;
        box-shadow: var(--shadow-md);
        padding: 8px 0;
        width: 150px;
        z-index: 100;
        display: none;
    }

    .dropdown-menu.show {
        display: block;
    }

    .dropdown-item {
        padding: 8px 16px;
        color: var(--text);
        text-decoration: none;
        display: block;
        transition: background-color 0.2s;
    }

    .dropdown-item:hover {
        background-color: var(--bg);
    }

    .dropdown-divider {
        height: 1px;
        background-color: var(--border);
        margin: 4px 0;
    }
</style>
<script>
    // Обработка клика по профилю пользователя
    document.getElementById('userProfileDropdown').addEventListener('click', function(e) {
        e.stopPropagation();
        document.getElementById('dropdownMenu').classList.toggle('show');
    });

    // Закрытие меню при клике вне его
    document.addEventListener('click', function(e) {
        const dropdownMenu = document.getElementById('dropdownMenu');
        if (dropdownMenu.classList.contains('show') && !e.target.closest('#userProfileDropdown')) {
            dropdownMenu.classList.remove('show');
        }
    });
</script>
<script>
    // Обработка изменения состояния чекбокса задачи
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.task-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const taskId = this.closest('.task-item').dataset.projectId; // Получаем ID задачи
                const isCompleted = this.checked ? 1 : 0;

                // Отправляем AJAX-запрос на сервер
                updateTaskStatus(taskId, isCompleted);
            });
        });
    });

    // Функция для обновления статуса задачи через AJAX
    function updateTaskStatus(taskId, isCompleted) {
        const formData = new FormData();
        formData.append('task_id', taskId);
        formData.append('is_completed', isCompleted);

        fetch('update_task_status.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Статус задачи успешно обновлен');

                    // Обновляем внешний вид задачи
                    const taskItem = document.querySelector(`.task-item[data-project-id="${taskId}"]`);
                    if (taskItem) {
                        const taskTitle = taskItem.querySelector('.task-title');
                        if (isCompleted) {
                            taskTitle.classList.add('completed');
                        } else {
                            taskTitle.classList.remove('completed');
                        }
                    }
                } else {
                    console.error('Ошибка при обновлении статуса задачи:', data.error);
                    // Возвращаем чекбокс в предыдущее состояние
                    const checkbox = document.querySelector(`.task-item[data-project-id="${taskId}"] .task-checkbox`);
                    if (checkbox) {
                        checkbox.checked = !isCompleted;
                    }
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                // Возвращаем чекбокс в предыдущее состояние
                const checkbox = document.querySelector(`.task-item[data-project-id="${taskId}"] .task-checkbox`);
                if (checkbox) {
                    checkbox.checked = !isCompleted;
                }
            });
    }
</script>

</body>
</html>