<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/services/TaskService.php';
require_once __DIR__ . '/../app/repositories/TaskRepository.php';

echo "Testing Task Workflow State Machine...\n";

try {
    $db = Database::getConnection();

    $taskService = new TaskService();
    $taskRepo = new TaskRepository();

    // 1. Create Task
    $taskData = [
        'task_category' => 'PRINTING',
        'vertical_type' => 'FRONT_LIT',
        'work_description' => 'Test task for state machine',
        'location_text' => '123 Main Street',
        'priority' => 'HIGH',
        'start_date' => date('Y-m-d'),
        'expected_close_date' => date('Y-m-d', strtotime('+3 days'))
    ];
    $opsUserId = 3; // Phase 2 Test Ops
    $opsRole = 'OPS_MANAGER';

    echo "1. Creating Task...\n";
    $task = $taskService->createTask($taskData, $opsUserId, $opsRole);
    if ($task['status'] !== 'OPEN') {
        throw new Exception("Task not created in OPEN state! Found: " . $task['status']);
    }
    echo "   [OK] Task created with status OPEN. Task ID: {$task['id']}\n";

    // 2. Start Task
    echo "2. Starting Task...\n";
    $task = $taskService->markInProgress($task['id'], $opsUserId, $opsRole);
    if ($task['status'] !== 'RUNNING') {
        throw new Exception("Task not started! Expected RUNNING, Found: " . $task['status']);
    }
    echo "   [OK] Task transitioned to RUNNING.\n";

    // Since we need AFTER_WORK proof to mark work done, let's inject a fake upload directly into DB for testing
    echo "3. Injecting fake AFTER_WORK proof...\n";
    require_once __DIR__ . '/../app/repositories/UploadRepository.php';
    $uploadRepo = new UploadRepository();
    $uploadRepo->create([
        'parent_type' => 'TASK',
        'parent_id' => $task['id'],
        'upload_type' => 'IMAGE',
        'work_type' => 'FABRICATION',
        'is_discovery_mode' => 0,
        'file_path' => '/fake/path.jpg',
        'original_file_name' => 'fake.jpg',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 1024,
        'photo_label' => 'AFTER_WORK',
        'comment_text' => null,
        'gps_latitude' => null,
        'gps_longitude' => null,
        'authority_visibility' => 'HIDDEN',
        'created_by_user_id' => $opsUserId
    ]);
    echo "4. Marking Work Done...\n";
    $doneData = [
        'task_id' => $task['id'],
        'progress_percent' => 100,
        'completion_note' => 'All done!'
    ];
    $task = $taskService->markWorkDone($doneData, $opsUserId, $opsRole);
    if ($task['status'] !== 'COMPLETED') {
        throw new Exception("Task not completed! Expected COMPLETED, Found: " . $task['status']);
    }
    if (empty($task['actual_close_date'])) {
        throw new Exception("Task close date was not set!");
    }
    echo "   [OK] Task transitioned to COMPLETED. Close date: {$task['actual_close_date']}\n";

    // Verify Audit Logs
    echo "5. Verifying Audit Logs...\n";
    $stmt = $db->prepare("SELECT action_type FROM audit_logs WHERE entity_type = 'task' AND entity_id = ? ORDER BY id ASC");
    $stmt->execute([$task['id']]);
    $logs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $expectedLogs = ['TASK_CREATED', 'TASK_STARTED', 'TASK_COMPLETED'];
    if ($logs !== $expectedLogs) {
        throw new Exception("Audit logs mismatch! Expected: " . implode(',', $expectedLogs) . " Found: " . implode(',', $logs));
    }
    echo "   [OK] Audit logs verified.\n";

    echo "6. Cleaning up test data...\n";
    $db->exec("DELETE FROM audit_logs WHERE entity_type = 'task' AND entity_id = " . (int)$task['id']);
    $db->exec("DELETE FROM uploads WHERE parent_type = 'TASK' AND parent_id = " . (int)$task['id']);
    $db->exec("DELETE FROM tasks WHERE id = " . (int)$task['id']);
    echo "   [OK] Cleanup complete.\n";

    echo "ALL TESTS PASSED!\n";

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
}
