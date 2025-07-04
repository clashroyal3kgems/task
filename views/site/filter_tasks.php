<?php
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=TaskScheduler", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получаем параметры из GET-запроса
    $projectId = $_GET['project_id'] ?? null;
    $user_id = 1; // Замените на реальный ID пользователя из сессии

    $sql = "SELECT t.*, p.name AS project_name, p.color AS project_color, p.icon AS project_icon 
            FROM tasks t 
            LEFT JOIN projects p ON t.project_id = p.project_id 
            WHERE t.user_id = ?";

    $params = [$user_id];

    if ($projectId && $projectId !== 'all') {
        $sql .= " AND t.project_id = ?";
        $params[] = $projectId;
    }

    $sql .= " ORDER BY 
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
            LIMIT 10";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получаем подзадачи
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

        foreach ($subtasksData as $subtask) {
            $subtasks[$subtask['task_id']][] = $subtask;
        }
    }

    // Генерируем HTML для задач
    ob_start();
    foreach ($tasks as $task): ?>
        <div class="task-item" data-task-id="<?= htmlspecialchars($task['task_id']) ?>">
            <input type="checkbox" class="task-checkbox" <?= $task['is_completed'] ? 'checked' : '' ?>>
            <div class="task-content">
                <div class="task-title <?= $task['is_completed'] ? 'completed' : '' ?>">
                    <?= htmlspecialchars($task['title']) ?>
                    <span class="task-priority priority-<?= htmlspecialchars($task['priority']) ?>"></span>
                </div>
                <?php if (!empty($task['description'])): ?>
                    <div class="task-description"><?= htmlspecialchars($task['description']) ?></div>
                <?php endif; ?>
                <div class="task-meta">
                    <?php if ($task['due_date']): ?>
                        <span class="task-date">
                            <i class="far fa-calendar-alt"></i>
                            <?= date('d M', strtotime($task['due_date'])) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($task['project_name']): ?>
                        <span>Проект: <?= htmlspecialchars($task['project_name']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($subtasks[$task['task_id']])): ?>
                    <div class="subtasks" style="margin-top: 8px;">
                        <?php foreach ($subtasks[$task['task_id']] as $subtask): ?>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                <input type="checkbox" <?= $subtask['is_completed'] ? 'checked' : '' ?>
                                       style="width: 14px; height: 14px;">
                                <span style="font-size: 0.8rem; <?= $subtask['is_completed'] ? 'text-decoration: line-through; color: var(--text-lighter);' : '' ?>">
                                    <?= htmlspecialchars($subtask['title']) ?>
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
    <?php endforeach;
    $html = ob_get_clean();

    echo json_encode(['html' => $html]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}