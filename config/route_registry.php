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
];
