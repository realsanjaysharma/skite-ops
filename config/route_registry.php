<?php

return [
    'auth/login' => [
        'controller' => 'AuthController',
        'method' => 'login',
        'public' => true,
        'csrf_exempt' => true,
    ],
    'auth/logout' => [
        'controller' => 'AuthController',
        'method' => 'logout',
        'allow_during_force_reset' => true,
        'csrf_exempt' => true,
    ],
    'auth/session' => [
        'controller' => 'AuthController',
        'method' => 'session',
        'allow_during_force_reset' => true,
        'csrf_exempt' => true,
    ],
    'auth/reset-password' => [
        'controller' => 'AuthController',
        'method' => 'resetPassword',
        'allow_during_force_reset' => true,
    ],
    'user/create' => [
        'controller' => 'UserController',
        'method' => 'createUser',
        'module_key' => 'governance.user_management',
        'capability' => 'manage',
    ],
    'user/update' => [
        'controller' => 'UserController',
        'method' => 'updateUser',
        'module_key' => 'governance.user_management',
        'capability' => 'manage',
    ],
    'user/get' => [
        'controller' => 'UserController',
        'method' => 'getUserById',
        'module_key' => 'governance.user_management',
        'capability' => 'read',
    ],
    'user/list' => [
        'controller' => 'UserController',
        'method' => 'getAllUsers',
        'module_key' => 'governance.user_management',
        'capability' => 'read',
    ],
    'user/delete' => [
        'controller' => 'UserController',
        'method' => 'softDeleteUser',
        'module_key' => 'governance.user_management',
        'capability' => 'manage',
    ],
    'user/activate' => [
        'controller' => 'UserController',
        'method' => 'activateUser',
        'module_key' => 'governance.user_management',
        'capability' => 'manage',
    ],
    'user/deactivate' => [
        'controller' => 'UserController',
        'method' => 'deactivateUser',
        'module_key' => 'governance.user_management',
        'capability' => 'manage',
    ],
    'user/restore' => [
        'controller' => 'UserController',
        'method' => 'restoreUser',
        'module_key' => 'governance.user_management',
        'capability' => 'manage',
    ],
    'role/list' => [
        'controller' => 'RoleController',
        'method' => 'getAllRoles',
        'module_key' => 'governance.access_mappings',
        'capability' => 'read',
    ],
    'role/get' => [
        'controller' => 'RoleController',
        'method' => 'getRoleById',
        'module_key' => 'governance.access_mappings',
        'capability' => 'read',
    ],
    'role/create' => [
        'controller' => 'RoleController',
        'method' => 'createRole',
        'module_key' => 'governance.access_mappings',
        'capability' => 'manage',
    ],
    'role/update' => [
        'controller' => 'RoleController',
        'method' => 'updateRole',
        'module_key' => 'governance.access_mappings',
        'capability' => 'manage',
    ],

    // =========================================
    // GREEN BELT MASTER
    // =========================================

    'belt/list' => [
        'controller' => 'BeltController',
        'method' => 'listBelts',
        'module_key' => 'green_belt.master',
        'capability' => 'read',
    ],
    'belt/get' => [
        'controller' => 'BeltController',
        'method' => 'getBelt',
        'module_key' => 'green_belt.detail',
        'capability' => 'read',
    ],
    'belt/create' => [
        'controller' => 'BeltController',
        'method' => 'createBelt',
        'module_key' => 'green_belt.master',
        'capability' => 'manage',
    ],
    'belt/update' => [
        'controller' => 'BeltController',
        'method' => 'updateBelt',
        'module_key' => 'green_belt.master',
        'capability' => 'manage',
    ],

    // =========================================
    // SUPERVISOR ASSIGNMENTS
    // =========================================

    'supervisorassignment/list' => [
        'controller' => 'BeltAssignmentController',
        'method' => 'listSupervisorAssignments',
        'module_key' => 'green_belt.master',
        'capability' => 'read',
    ],
    'supervisorassignment/create' => [
        'controller' => 'BeltAssignmentController',
        'method' => 'createSupervisorAssignment',
        'module_key' => 'green_belt.master',
        'capability' => 'manage',
    ],
    'supervisorassignment/close' => [
        'controller' => 'BeltAssignmentController',
        'method' => 'closeSupervisorAssignment',
        'module_key' => 'green_belt.master',
        'capability' => 'manage',
    ],

    // =========================================
    // AUTHORITY ASSIGNMENTS
    // =========================================

    'authorityassignment/list' => [
        'controller' => 'BeltAssignmentController',
        'method' => 'listAuthorityAssignments',
        'module_key' => 'green_belt.master',
        'capability' => 'read',
    ],
    'authorityassignment/create' => [
        'controller' => 'BeltAssignmentController',
        'method' => 'createAuthorityAssignment',
        'module_key' => 'green_belt.master',
        'capability' => 'manage',
    ],
    'authorityassignment/close' => [
        'controller' => 'BeltAssignmentController',
        'method' => 'closeAuthorityAssignment',
        'module_key' => 'green_belt.master',
        'capability' => 'manage',
    ],

    // =========================================
    // OUTSOURCED ASSIGNMENTS
    // =========================================

    'outsourcedassignment/list' => [
        'controller' => 'BeltAssignmentController',
        'method' => 'listOutsourcedAssignments',
        'module_key' => 'green_belt.master',
        'capability' => 'read',
    ],
    'outsourcedassignment/create' => [
        'controller' => 'BeltAssignmentController',
        'method' => 'createOutsourcedAssignment',
        'module_key' => 'green_belt.master',
        'capability' => 'manage',
    ],
    'outsourcedassignment/close' => [
        'controller' => 'BeltAssignmentController',
        'method' => 'closeOutsourcedAssignment',
        'module_key' => 'green_belt.master',
        'capability' => 'manage',
    ],

    // =========================================
    // MAINTENANCE CYCLES
    // =========================================

    'cycle/list' => [
        'controller' => 'MaintenanceCycleController',
        'method' => 'listCycles',
        'module_key' => 'green_belt.maintenance_cycles',
        'capability' => 'read',
    ],
    'cycle/start' => [
        'controller' => 'MaintenanceCycleController',
        'method' => 'startCycle',
        'module_key' => 'green_belt.maintenance_cycles',
        'capability' => 'manage',
    ],
    'cycle/close' => [
        'controller' => 'MaintenanceCycleController',
        'method' => 'closeCycle',
        'module_key' => 'green_belt.maintenance_cycles',
        'capability' => 'manage',
    ],

    // =========================================
    // ISSUES
    // =========================================

    'issue/list' => [
        'controller' => 'IssueController',
        'method' => 'listIssues',
    ],
    'issue/get' => [
        'controller' => 'IssueController',
        'method' => 'getIssue',
    ],
    'issue/create' => [
        'controller' => 'IssueController',
        'method' => 'createIssue',
    ],
    'issue/in-progress' => [
        'controller' => 'IssueController',
        'method' => 'markInProgress',
    ],
    'issue/close' => [
        'controller' => 'IssueController',
        'method' => 'closeIssue',
    ],
    'issue/link-task' => [
        'controller' => 'IssueController',
        'method' => 'linkTask',
    ],

    // =========================================
    // REQUEST INTAKE
    // =========================================

    'request/list' => [
        'controller' => 'RequestController',
        'method' => 'listRequests',
    ],
    'request/get' => [
        'controller' => 'RequestController',
        'method' => 'getRequest',
    ],
    'request/create' => [
        'controller' => 'RequestController',
        'method' => 'createRequest',
    ],
    'request/approve' => [
        'controller' => 'RequestController',
        'method' => 'approveRequest',
    ],
    'request/reject' => [
        'controller' => 'RequestController',
        'method' => 'rejectRequest',
    ],

    // =========================================
    // TASKS
    // =========================================

    'task/create' => [
        'controller' => 'TaskController',
        'method' => 'createTask',
    ],
    'task/list' => [
        'controller' => 'TaskController',
        'method' => 'listTasks',
    ],
    'task/get' => [
        'controller' => 'TaskController',
        'method' => 'getTask',
    ],
    'task/update' => [
        'controller' => 'TaskController',
        'method' => 'updateTask',
    ],
    'task/archive' => [
        'controller' => 'TaskController',
        'method' => 'archiveTask',
    ],
    'task/progress' => [
        'controller' => 'TaskProgressController',
        'method' => 'updateProgress',
    ],
    'task/work-done' => [
        'controller' => 'TaskController',
        'method' => 'markWorkDone',
    ],

    // =========================================
    // TASK WORKER ASSIGNMENTS
    // =========================================

    'taskworker/assign' => [
        'controller' => 'TaskWorkerController',
        'method' => 'assignWorkers',
    ],
    'taskworker/release' => [
        'controller' => 'TaskWorkerController',
        'method' => 'releaseWorker',
    ],

    // =========================================
    // TASK PROGRESS (COMMERCIAL VIEWS)
    // =========================================

    'taskprogress/list' => [
        'controller' => 'TaskProgressController',
        'method' => 'listTaskProgress',
    ],
    'taskprogress/get' => [
        'controller' => 'TaskProgressController',
        'method' => 'getTaskProgress',
    ],

    // =========================================
    // FABRICATION WORKERS
    // =========================================

    'worker/list' => [
        'controller' => 'WorkerController',
        'method' => 'listWorkers',
    ],
    'worker/get' => [
        'controller' => 'WorkerController',
        'method' => 'getWorker',
    ],
    'worker/create' => [
        'controller' => 'WorkerController',
        'method' => 'createWorker',
    ],
    'worker/update' => [
        'controller' => 'WorkerController',
        'method' => 'updateWorker',
    ],
    'worker/availability' => [
        'controller' => 'WorkerController',
        'method' => 'getAvailability',
    ],

    // =========================================
    // WORKER DAILY ENTRIES
    // =========================================

    'workday/list' => [
        'controller' => 'WorkerEntryController',
        'method' => 'listEntries',
    ],
    'workday/mark' => [
        'controller' => 'WorkerEntryController',
        'method' => 'markEntry',
    ],

    // =========================================
    // LABOUR ENTRIES
    // =========================================

    'labour/list' => [
        'controller' => 'LabourController',
        'method' => 'listLabourEntries',
    ],
    'labour/mark' => [
        'controller' => 'LabourController',
        'method' => 'markLabourCounts',
    ],

    // =========================================
    // SUPERVISOR ATTENDANCE
    // =========================================

    'attendance/list' => [
        'controller' => 'AttendanceController',
        'method' => 'listAttendanceRecords',
    ],
    'attendance/mark' => [
        'controller' => 'AttendanceController',
        'method' => 'markAttendance',
    ],

    // =========================================
    // WATERING LOGS
    // =========================================

    'watering/list' => [
        'controller' => 'WateringController',
        'method' => 'listWateringRecords',
    ],
    'watering/mark' => [
        'controller' => 'WateringController',
        'method' => 'markWatering',
    ],

    // =========================================
    // UPLOADS
    // =========================================

    'upload/create' => [
        'controller' => 'UploadController',
        'method' => 'createUpload',
        // Shared endpoint: module access is governed by UploadController surface resolution
    ],

    'upload/my-list' => [
        'controller' => 'UploadController',
        'method' => 'myList',
        // Shared endpoint: any upload-capable field role may list own uploads
    ],

    'upload/delete' => [
        'controller' => 'UploadController',
        'method' => 'deleteUpload',
        // Shared endpoint: upload creator self-delete within time window
    ],

    // =========================================
    // REPORTS
    // =========================================

    'report/worker-activity' => [
        'controller' => 'ReportController',
        'method' => 'getWorkerActivity',
    ],
];
