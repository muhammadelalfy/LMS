<?php

/** What a trial (temporary) teacher account may do. */
return [
    'max_students' => (int) env('TRIAL_MAX_STUDENTS', 10),
    'max_groups' => (int) env('TRIAL_MAX_GROUPS', 2),
    'max_devices' => (int) env('TRIAL_MAX_DEVICES', 1),
    // Writes under these paths are not available to a trial account.
    'blocked_writes' => ['exams', 'exam-departments', 'payments', 'notifications/send'],
];
