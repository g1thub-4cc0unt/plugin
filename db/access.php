<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/analytics:view' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'student' => CAP_PROHIBIT,
            'guest' => CAP_PROHIBIT,
        ],
    ],
];
