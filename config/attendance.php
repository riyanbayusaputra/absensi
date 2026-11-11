<?php

return [
    'face' => [
        'script_url'          => '/js/face-api.js',
        'model_url'           => '/models',
        'min_samples'         => 1,
        'max_samples'         => 3,
        'enroll_max_distance' => 0.6,
        'match_threshold'     => 0.5,
        'auto_approve_first'  => false,
        'allow_gallery'       => true,
    ],

    'rules' => [
        'checkin_open_before_min' => 120,
      'max_accuracy_m' => (int) env('ATTENDANCE_MAX_ACCURACY', 90000000),   // 0 = tidak dibatasi, hanya radius
        'min_gap_minutes'         => 1,
        'max_failed_face'         => 100,
        'lock_minutes'            => 2,
    ],

    

    'leave' => [
        'approval_levels'      => ['atasan', 'hrd'],
        'work_days'            => [1, 2, 3, 4, 5, 6],
        'prorata_new_employee' => true,
    ],
    'report' => [
    'company_name' => env('COMPANY_NAME', env('APP_NAME', 'Perusahaan')),
],
];