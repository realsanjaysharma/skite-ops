<?php

/**
 * route_registry.php
 *
 * RBAC contract: every protected route MUST declare module_key + capability.
 * Middleware enforces module access before any controller runs.
 * Controllers must NOT make role-level decisions — only record-scope decisions.
 *
 * Capabilities:
 *   read   → VIEW group and above
 *   upload → UPLOAD group and above
 *   approve → APPROVE group and above
 *   manage → MANAGE group only
 */

return [

    // ==========================================
    // AUTH (public / force-reset-safe)
    // ==========================================

    'auth/login' => [
        'controller'  => 'AuthController',
        'method'      => 'login',
        'public'      => true,
        'csrf_exempt' => true,
    ],
    'auth/logout' => [
        'controller'              => 'AuthController',
        'method'                  => 'logout',
        'allow_during_force_reset' => true,
        'csrf_exempt'             => true,
    ],
    'auth/session' => [
        'controller'              => 'AuthController',
        'method'                  => 'session',
        'allow_during_force_reset' => true,
        'csrf_exempt'             => true,
    ],
    'auth/reset-password' => [
        'controller'              => 'AuthController',
        'method'                  => 'resetPassword',
        'allow_during_force_reset' => true,
        // CSRF is NOT exempt — password change must be CSRF-protected
    ],

    // ==========================================
    // USER MANAGEMENT
    // ==========================================

    'user/list' => [
        'controller' => 'UserController',
        'method'     => 'getAllUsers',
        'module_key' => 'governance.user_management',
        'capability' => 'read',
    ],
    'user/get' => [
        'controller' => 'UserController',
        'method'     => 'getUserById',
        'module_key' => 'governance.user_management',
        'capability' => 'read',
    ],
    'user/create' => [
        'controller' => 'UserController',
        'method'     => 'createUser',
        'module_key' => 'governance.user_management',
        'capability' => 'manage',
    ],
    'user/update' => [
        'controller' => 'UserController',
        'method'     => 'updateUser',
        'module_key' => 'governance.user_management',
        'capability' => 'manage',
    ],
    'user/delete' => [
        'controller' => 'UserController',
        'method'     => 'softDeleteUser',
        'module_key' => 'governance.user_management',
        'capability' => 'manage',
    ],
    'user/activate' => [
        'controller' => 'UserController',
        'method'     => 'activateUser',
        'module_key' => 'governance.user_management',
        'capability' => 'manage',
    ],
    'user/deactivate' => [
        'controller' => 'UserController',
        'method'     => 'deactivateUser',
        'module_key' => 'governance.user_management',
        'capability' => 'manage',
    ],
    'user/restore' => [
        'controller' => 'UserController',
        'method'     => 'restoreUser',
        'module_key' => 'governance.user_management',
        'capability' => 'manage',
    ],

    // ==========================================
    // ROLE MANAGEMENT
    // ==========================================

    'role/list' => [
        'controller' => 'RoleController',
        'method'     => 'getAllRoles',
        'module_key' => 'governance.access_mappings',
        'capability' => 'read',
    ],
    'role/get' => [
        'controller' => 'RoleController',
        'method'     => 'getRoleById',
        'module_key' => 'governance.access_mappings',
        'capability' => 'read',
    ],
    'role/create' => [
        'controller' => 'RoleController',
        'method'     => 'createRole',
        'module_key' => 'governance.access_mappings',
        'capability' => 'manage',
    ],
    'role/update' => [
        'controller' => 'RoleController',
        'method'     => 'updateRole',
        'module_key' => 'governance.access_mappings',
        'capability' => 'manage',
    ],

    // ==========================================
    // GREEN BELT MASTER
    // ==========================================

    'belt/list' => [
        'controller' => 'BeltController',
        'method'     => 'listBelts',
        'module_key' => 'green_belt.master',
        'capability' => 'read',
    ],
    'belt/get' => [
        'controller' => 'BeltController',
        'method'     => 'getBelt',
        'module_key' => 'green_belt.detail',
        'capability' => 'read',
    ],
    'belt/create' => [
        'controller' => 'BeltController',
        'method'     => 'createBelt',
        'module_key' => 'green_belt.master',
        'capability' => 'manage',
    ],
    'belt/update' => [
        'controller' => 'BeltController',
        'method'     => 'updateBelt',
        'module_key' => 'green_belt.master',
        'capability' => 'manage',
    ],

    // ==========================================
    // BELT ASSIGNMENTS
    // ==========================================

    'supervisorassignment/list' => [
        'controller' => 'BeltAssignmentController',
        'method'     => 'listSupervisorAssignments',
        'module_key' => 'governance.access_mappings',
        'capability' => 'read',
    ],
    'supervisorassignment/create' => [
        'controller' => 'BeltAssignmentController',
        'method'     => 'createSupervisorAssignment',
        'module_key' => 'governance.access_mappings',
        'capability' => 'manage',
    ],
    'supervisorassignment/close' => [
        'controller' => 'BeltAssignmentController',
        'method'     => 'closeSupervisorAssignment',
        'module_key' => 'governance.access_mappings',
        'capability' => 'manage',
    ],
    'authorityassignment/list' => [
        'controller' => 'BeltAssignmentController',
        'method'     => 'listAuthorityAssignments',
        'module_key' => 'governance.access_mappings',
        'capability' => 'read',
    ],
    'authorityassignment/create' => [
        'controller' => 'BeltAssignmentController',
        'method'     => 'createAuthorityAssignment',
        'module_key' => 'governance.access_mappings',
        'capability' => 'manage',
    ],
    'authorityassignment/close' => [
        'controller' => 'BeltAssignmentController',
        'method'     => 'closeAuthorityAssignment',
        'module_key' => 'governance.access_mappings',
        'capability' => 'manage',
    ],
    'outsourcedassignment/list' => [
        'controller' => 'BeltAssignmentController',
        'method'     => 'listOutsourcedAssignments',
        'module_key' => 'governance.access_mappings',
        'capability' => 'read',
    ],
    'outsourcedassignment/create' => [
        'controller' => 'BeltAssignmentController',
        'method'     => 'createOutsourcedAssignment',
        'module_key' => 'governance.access_mappings',
        'capability' => 'manage',
    ],
    'outsourcedassignment/close' => [
        'controller' => 'BeltAssignmentController',
        'method'     => 'closeOutsourcedAssignment',
        'module_key' => 'governance.access_mappings',
        'capability' => 'manage',
    ],

    // ==========================================
    // MAINTENANCE CYCLES
    // ==========================================

    'cycle/list' => [
        'controller' => 'MaintenanceCycleController',
        'method'     => 'listCycles',
        'module_key' => 'green_belt.maintenance_cycles',
        'capability' => 'read',
    ],
    'cycle/start' => [
        'controller' => 'MaintenanceCycleController',
        'method'     => 'startCycle',
        'module_key' => 'green_belt.maintenance_cycles',
        'capability' => 'manage',
    ],
    'cycle/close' => [
        'controller' => 'MaintenanceCycleController',
        'method'     => 'closeCycle',
        'module_key' => 'green_belt.maintenance_cycles',
        'capability' => 'manage',
    ],

    // ==========================================
    // WATERING
    // ==========================================

    'watering/list' => [
        'controller' => 'WateringController',
        'method'     => 'listWateringRecords',
        'module_key' => 'green_belt.watering_oversight',
        'capability' => 'read',
    ],
    'watering/mark' => [
        'controller' => 'WateringController',
        'method'     => 'markWatering',
        'module_key' => 'green_belt.watering_oversight',
        // upload capability: supervisors (UPLOAD group) + Ops (MANAGE group) both satisfy this
        'capability' => 'upload',
    ],

    // ==========================================
    // SUPERVISOR ATTENDANCE
    // ==========================================

    'attendance/list' => [
        'controller' => 'AttendanceController',
        'method'     => 'listAttendanceRecords',
        'module_key' => 'green_belt.supervisor_attendance',
        'capability' => 'read',
    ],
    'attendance/mark' => [
        'controller' => 'AttendanceController',
        'method'     => 'markAttendance',
        'module_key' => 'green_belt.supervisor_attendance',
        // HEAD_SUPERVISOR (MANAGE group) and OPS_MANAGER both satisfy 'upload' capability.
        // Service layer enforces who can mark whose attendance and override rules.
        'capability' => 'upload',
    ],

    // ==========================================
    // LABOUR ENTRIES
    // ==========================================

    'labour/list' => [
        'controller' => 'LabourController',
        'method'     => 'listLabourEntries',
        'module_key' => 'green_belt.labour_entries',
        'capability' => 'read',
    ],
    'labour/mark' => [
        'controller' => 'LabourController',
        'method'     => 'markLabourCounts',
        'module_key' => 'green_belt.labour_entries',
        // HEAD_SUPERVISOR (MANAGE group) satisfies 'upload'. Service enforces
        // same-day/maintained-belt rules and Ops override path separately.
        'capability' => 'upload',
    ],

    // ==========================================
    // ISSUE MANAGEMENT
    // ==========================================

    'issue/list' => [
        'controller' => 'IssueController',
        'method'     => 'listIssues',
        'module_key' => 'green_belt.issue_management',
        'capability' => 'read',
    ],
    'issue/get' => [
        'controller' => 'IssueController',
        'method'     => 'getIssue',
        'module_key' => 'green_belt.issue_management',
        'capability' => 'read',
    ],
    'issue/create' => [
        'controller' => 'IssueController',
        'method'     => 'createIssue',
        'module_key' => 'green_belt.issue_management',
        'capability' => 'manage',
    ],
    'issue/in-progress' => [
        'controller' => 'IssueController',
        'method'     => 'markInProgress',
        'module_key' => 'green_belt.issue_management',
        // HEAD_SUPERVISOR has MANAGE on this module per seed
        'capability' => 'manage',
    ],
    'issue/close' => [
        'controller' => 'IssueController',
        'method'     => 'closeIssue',
        'module_key' => 'green_belt.issue_management',
        'capability' => 'manage',
    ],
    'issue/link-task' => [
        'controller' => 'IssueController',
        'method'     => 'linkTask',
        'module_key' => 'green_belt.issue_management',
        'capability' => 'manage',
    ],

    // ==========================================
    // TASK REQUESTS
    // ==========================================

    'request/list' => [
        'controller' => 'RequestController',
        'method'     => 'listRequests',
        'module_key' => 'task.request_intake',
        'capability' => 'read',
    ],
    'request/get' => [
        'controller' => 'RequestController',
        'method'     => 'getRequest',
        'module_key' => 'task.request_intake',
        'capability' => 'read',
    ],
    'request/create' => [
        'controller' => 'RequestController',
        'method'     => 'createRequest',
        'module_key' => 'task.request_intake',
        // UPLOAD group can raise requests; service still enforces allowed roles
        'capability' => 'upload',
    ],
    'request/approve' => [
        'controller' => 'RequestController',
        'method'     => 'approveRequest',
        'module_key' => 'task.management',
        'capability' => 'manage',
    ],
    'request/reject' => [
        'controller' => 'RequestController',
        'method'     => 'rejectRequest',
        'module_key' => 'task.management',
        'capability' => 'manage',
    ],

    // ==========================================
    // TASKS
    // ==========================================

    'task/list' => [
        'controller' => 'TaskController',
        'method'     => 'listTasks',
        'module_key' => 'task.management',
        'capability' => 'read',
    ],
    'task/get' => [
        'controller' => 'TaskController',
        'method'     => 'getTask',
        'module_key' => 'task.detail',
        'capability' => 'read',
    ],
    'task/create' => [
        'controller' => 'TaskController',
        'method'     => 'createTask',
        'module_key' => 'task.management',
        'capability' => 'manage',
    ],
    'task/update' => [
        'controller' => 'TaskController',
        'method'     => 'updateTask',
        'module_key' => 'task.management',
        'capability' => 'manage',
    ],
    'task/archive' => [
        'controller' => 'TaskController',
        'method'     => 'archiveTask',
        'module_key' => 'task.management',
        'capability' => 'manage',
    ],
    'task/start' => [
        'controller' => 'TaskController',
        'method'     => 'markInProgress',
        'module_key' => 'task.my_tasks', // FABRICATION_LEAD has task.my_tasks; TaskService enforces assigned-lead check
        'capability' => 'upload',
    ],
    'task/progress' => [
        'controller' => 'TaskProgressController',
        'method'     => 'updateProgress',
        'module_key' => 'task.detail',
        // FABRICATION_LEAD has UPLOAD group → satisfied by 'upload' capability
        'capability' => 'upload',
    ],
    'task/work-done' => [
        'controller' => 'TaskController',
        'method'     => 'markWorkDone',
        'module_key' => 'task.detail',
        'capability' => 'upload',
    ],

    // Fabrication Lead landing — scoped to task.my_tasks module
    'task/my' => [
        'controller' => 'TaskController',
        'method'     => 'myTasks',
        'module_key' => 'task.my_tasks',
        'capability' => 'read',
    ],

    // ==========================================
    // TASK PROGRESS (read-only commercial views)
    // ==========================================

    'taskprogress/list' => [
        'controller' => 'TaskProgressController',
        'method'     => 'listTaskProgress',
        'module_key' => 'task.progress_read',
        'capability' => 'read',
    ],
    'taskprogress/get' => [
        'controller' => 'TaskProgressController',
        'method'     => 'getTaskProgress',
        'module_key' => 'task.progress_read',
        'capability' => 'read',
    ],

    // ==========================================
    // TASK WORKER ASSIGNMENTS
    // ==========================================

    'taskworker/assign' => [
        'controller' => 'TaskWorkerController',
        'method'     => 'assignWorkers',
        'module_key' => 'task.worker_allocation',
        'capability' => 'upload',
    ],
    'taskworker/release' => [
        'controller' => 'TaskWorkerController',
        'method'     => 'releaseWorker',
        'module_key' => 'task.worker_allocation',
        'capability' => 'upload',
    ],

    // ==========================================
    // FABRICATION WORKERS
    // ==========================================

    'worker/list' => [
        'controller' => 'WorkerController',
        'method'     => 'listWorkers',
        'module_key' => 'task.worker_allocation',
        'capability' => 'read',
    ],
    'worker/get' => [
        'controller' => 'WorkerController',
        'method'     => 'getWorker',
        'module_key' => 'task.worker_allocation',
        'capability' => 'read',
    ],
    'worker/create' => [
        'controller' => 'WorkerController',
        'method'     => 'createWorker',
        'module_key' => 'task.worker_allocation',
        'capability' => 'manage',
    ],
    'worker/update' => [
        'controller' => 'WorkerController',
        'method'     => 'updateWorker',
        'module_key' => 'task.worker_allocation',
        'capability' => 'manage',
    ],
    'worker/availability' => [
        'controller' => 'WorkerController',
        'method'     => 'getAvailability',
        'module_key' => 'task.worker_allocation',
        'capability' => 'read',
    ],

    // ==========================================
    // WORKER DAILY ENTRIES
    // ==========================================

    'workday/list' => [
        'controller' => 'WorkerEntryController',
        'method'     => 'listEntries',
        'module_key' => 'task.worker_allocation',
        'capability' => 'read',
    ],
    'workday/mark' => [
        'controller' => 'WorkerEntryController',
        'method'     => 'markEntry',
        'module_key' => 'task.worker_allocation',
        'capability' => 'upload',
    ],

    // ==========================================
    // UPLOADS — field roles (UPLOAD group)
    // ==========================================

    'upload/create' => [
        'controller' => 'UploadController',
        'method'     => 'createUpload',
        // Delegate to controller to allow OUTSOURCED, MONITORING, and FABRICATION roles
        'module_key' => null,
        'capability' => 'upload',
    ],
    'upload/my-list' => [
        'controller' => 'UploadController',
        'method'     => 'myList',
        'module_key' => null,
        'capability' => 'read',
    ],
    'upload/delete' => [
        'controller' => 'UploadController',
        'method'     => 'deleteUpload',
        'module_key' => null,
        'capability' => 'upload',
    ],

    // Upload serve: authenticated only, record-scope enforced in controller
    'upload/serve' => [
        'controller' => 'UploadController',
        'method'     => 'serve',
        'module_key' => 'green_belt.detail',
        'capability' => 'read',
    ],

    // Upload list + review: Ops governance
    'upload/list' => [
        'controller' => 'UploadController',
        'method'     => 'list',
        'module_key' => 'green_belt.upload_review',
        'capability' => 'read',
    ],
    'upload/review' => [
        'controller' => 'UploadController',
        'method'     => 'review',
        'module_key' => 'green_belt.upload_review',
        'capability' => 'approve',
    ],

    // Cleanup (Ops only)
    'upload/cleanup-list' => [
        'controller' => 'UploadController',
        'method'     => 'cleanupList',
        'module_key' => 'governance.rejected_upload_cleanup',
        'capability' => 'read',
    ],
    'upload/purge' => [
        'controller' => 'UploadController',
        'method'     => 'purge',
        'module_key' => 'governance.rejected_upload_cleanup',
        'capability' => 'manage',
    ],

    // Field-role upload landing routes
    'upload/supervisor' => [
        'controller' => 'UploadController',
        'method'     => 'supervisorLanding',
        'module_key' => 'green_belt.supervisor_upload',
        'capability' => 'read',
    ],
    'upload/outsourced' => [
        'controller' => 'UploadController',
        'method'     => 'outsourcedLanding',
        'module_key' => 'green_belt.outsourced_upload',
        'capability' => 'read',
    ],

    // ==========================================
    // SITE MASTER
    // ==========================================

    'site/list' => [
        'controller' => 'SiteController',
        'method'     => 'listSites',
        'module_key' => 'advertisement.site_master',
        'capability' => 'read',
    ],
    'site/get' => [
        'controller' => 'SiteController',
        'method'     => 'getSite',
        'module_key' => 'advertisement.site_master',
        'capability' => 'read',
    ],
    'site/create' => [
        'controller' => 'SiteController',
        'method'     => 'createSite',
        'module_key' => 'advertisement.site_master',
        'capability' => 'manage',
    ],
    'site/update' => [
        'controller' => 'SiteController',
        'method'     => 'updateSite',
        'module_key' => 'advertisement.site_master',
        'capability' => 'manage',
    ],

    // ==========================================
    // MONITORING PLAN
    // ==========================================

    'monitoringplan/list' => [
        'controller' => 'MonitoringPlanController',
        'method'     => 'listPlan',
        'module_key' => 'monitoring.plan',
        'capability' => 'read',
    ],
    'monitoringplan/save' => [
        'controller' => 'MonitoringPlanController',
        'method'     => 'savePlan',
        'module_key' => 'monitoring.plan',
        'capability' => 'manage',
    ],
    'monitoringplan/copy-next-month' => [
        'controller' => 'MonitoringPlanController',
        'method'     => 'copyNextMonth',
        'module_key' => 'monitoring.plan',
        'capability' => 'manage',
    ],
    'monitoringplan/bulk-copy' => [
        'controller' => 'MonitoringPlanController',
        'method'     => 'bulkCopy',
        'module_key' => 'monitoring.plan',
        'capability' => 'manage',
    ],

    // ==========================================
    // MONITORING UPLOAD (MONITORING_TEAM landing)
    // ==========================================

    'monitoring/upload' => [
        'controller' => 'MonitoringUploadController',
        'method'     => 'index',
        'module_key' => 'monitoring.upload',
        'capability' => 'read',
    ],

    // ==========================================
    // MONITORING HISTORY
    // ==========================================

    'monitoring/history' => [
        'controller' => 'MonitoringHistoryController',
        'method'     => 'getHistory',
        'module_key' => 'monitoring.history',
        'capability' => 'read',
    ],

    // ==========================================
    // CAMPAIGN MANAGEMENT
    // ==========================================

    'campaign/list' => [
        'controller' => 'CampaignController',
        'method'     => 'listCampaigns',
        'module_key' => 'advertisement.campaign_management',
        'capability' => 'read',
    ],
    'campaign/get' => [
        'controller' => 'CampaignController',
        'method'     => 'getCampaign',
        'module_key' => 'advertisement.campaign_management',
        'capability' => 'read',
    ],
    'campaign/create' => [
        'controller' => 'CampaignController',
        'method'     => 'createCampaign',
        'module_key' => 'advertisement.campaign_management',
        'capability' => 'manage',
    ],
    'campaign/update' => [
        'controller' => 'CampaignController',
        'method'     => 'updateCampaign',
        'module_key' => 'advertisement.campaign_management',
        'capability' => 'manage',
    ],
    'campaign/end' => [
        'controller' => 'CampaignController',
        'method'     => 'endCampaign',
        'module_key' => 'advertisement.campaign_management',
        'capability' => 'manage',
    ],
    'campaign/confirm-free-media' => [
        'controller' => 'CampaignController',
        'method'     => 'confirmFreeMedia',
        'module_key' => 'media.free_media_inventory',
        'capability' => 'manage',
    ],

    // ==========================================
    // FREE MEDIA
    // ==========================================

    'freemedia/list' => [
        'controller' => 'FreeMediaController',
        'method'     => 'listFreeMedia',
        'module_key' => 'media.free_media_inventory',
        'capability' => 'read',
    ],
    'freemedia/confirm' => [
        'controller' => 'FreeMediaController',
        'method'     => 'confirmRecord',
        'module_key' => 'media.free_media_inventory',
        'capability' => 'manage',
    ],
    'freemedia/expire' => [
        'controller' => 'FreeMediaController',
        'method'     => 'expireRecord',
        'module_key' => 'media.free_media_inventory',
        'capability' => 'manage',
    ],
    'freemedia/consume' => [
        'controller' => 'FreeMediaController',
        'method'     => 'consumeRecord',
        'module_key' => 'media.free_media_inventory',
        'capability' => 'manage',
    ],

    // ==========================================
    // AUTHORITY VIEW
    // ==========================================

    'authority/view' => [
        'controller' => 'AuthorityViewController',
        'method'     => 'view',
        'module_key' => 'green_belt.authority_view',
        'capability' => 'read',
    ],
    'authority/summary' => [
        'controller' => 'AuthorityViewController',
        'method'     => 'summary',
        'module_key' => 'green_belt.authority_view',
        'capability' => 'read',
    ],
    'authority/share-helper' => [
        'controller' => 'AuthorityViewController',
        'method'     => 'shareHelper',
        'module_key' => 'green_belt.authority_view',
        'capability' => 'read',
    ],

    // ==========================================
    // REPORTS (read-only — Ops + Management)
    // ==========================================

    'report/belt-health' => [
        'controller' => 'ReportController',
        'method'     => 'getBeltHealth',
        'module_key' => 'reports.monthly',
        'capability' => 'read',
    ],
    'report/supervisor-activity' => [
        'controller' => 'ReportController',
        'method'     => 'getSupervisorActivity',
        'module_key' => 'reports.monthly',
        'capability' => 'read',
    ],
    'report/worker-activity' => [
        'controller' => 'ReportController',
        'method'     => 'getWorkerActivity',
        'module_key' => 'reports.monthly',
        'capability' => 'read',
    ],
    'report/advertisement-operations' => [
        'controller' => 'ReportController',
        'method'     => 'getAdvertisementOperations',
        'module_key' => 'reports.monthly',
        'capability' => 'read',
    ],

    // ==========================================
    // SYSTEM SETTINGS
    // ==========================================

    'settings/list' => [
        'controller' => 'SystemSettingsController',
        'method'     => 'list',
        'module_key' => 'settings.system',
        'capability' => 'read',
    ],
    'settings/update' => [
        'controller' => 'SystemSettingsController',
        'method'     => 'update',
        'module_key' => 'settings.system',
        'capability' => 'manage',
    ],

    // ==========================================
    // DASHBOARDS
    // ==========================================

    'dashboard/master' => [
        'controller' => 'DashboardController',
        'method'     => 'master',
        'module_key' => 'dashboard.master_ops',
        'capability' => 'read',
    ],
    'dashboard/green-belt' => [
        'controller' => 'DashboardController',
        'method'     => 'greenBelt',
        'module_key' => 'dashboard.green_belt',
        'capability' => 'read',
    ],
    'dashboard/advertisement' => [
        'controller' => 'DashboardController',
        'method'     => 'advertisement',
        'module_key' => 'dashboard.advertisement',
        'capability' => 'read',
    ],
    'dashboard/monitoring' => [
        'controller' => 'DashboardController',
        'method'     => 'monitoring',
        'module_key' => 'dashboard.monitoring',
        'capability' => 'read',
    ],
    'dashboard/management' => [
        'controller' => 'DashboardController',
        'method'     => 'management',
        'module_key' => 'dashboard.management',
        'capability' => 'read',
    ],

    // ==========================================
    // OVERSIGHT (Head Supervisor landing)
    // ==========================================

    'oversight/watering' => [
        'controller' => 'OversightController',
        'method'     => 'watering',
        'module_key' => 'green_belt.watering_oversight',
        'capability' => 'read',
    ],

    // ==========================================
    // AUDIT LOGS
    // ==========================================

    'audit/list' => [
        'controller' => 'AuditController',
        'method'     => 'list',
        'module_key' => 'governance.audit_logs',
        'capability' => 'read',
    ],

    // ==========================================
    // ALERT PANEL (OPS_MANAGER)
    // ==========================================

    'alert/list' => [
        'controller' => 'DashboardController',
        'method'     => 'alertPanel',
        'module_key' => 'governance.alert_panel',
        'capability' => 'read',
    ],

    // ==========================================
    // WORKER DAILY ENTRY (FABRICATION_LEAD standalone)
    // ==========================================

    'workday/my-list' => [
        'controller' => 'WorkerEntryController',
        'method'     => 'listEntries',
        'module_key' => 'task.worker_daily_entry',
        'capability' => 'read',
    ],
    'workday/my-mark' => [
        'controller' => 'WorkerEntryController',
        'method'     => 'markEntry',
        'module_key' => 'task.worker_daily_entry',
        'capability' => 'upload',
    ],

    // ==========================================
    // CLIENT MEDIA LIBRARY (SALES_TEAM, CLIENT_SERVICING)
    // ==========================================

    'media/client-library' => [
        'controller' => 'SiteController',
        'method'     => 'clientMediaLibrary',
        'module_key' => 'commercial.client_media_library',
        'capability' => 'read',
    ],

    // ==========================================
    // MEDIA PLANNING INVENTORY (MEDIA_PLANNING)
    // ==========================================

    'media/planning-view' => [
        'controller' => 'FreeMediaController',
        'method'     => 'planningView',
        'module_key' => 'commercial.media_planning_inventory',
        'capability' => 'read',
    ],

];

