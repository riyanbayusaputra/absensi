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

    'risk' => [
        'enforce'              => true,
        'review_at'            => 30,
        'reject_at'            => 60,
        'first_device_trusted' => true,
        'max_travel_kmh'       => 120,
        'clock_skew_ms'        => 120000,
        'allowed_tz_offsets'   => [-420, -480, -540],
        'office_ips'           => [],

        'weights' => [
            'gps_perfect_accuracy' => 25,
            'gps_no_altitude'      => 10,
            'gps_static'           => 20,
            'gps_const_accuracy'   => 10,
            'same_coords_history'  => 25,
            'impossible_travel'    => 40,
            'clock_skew'           => 25,
            'timezone_mismatch'    => 20,
            'webdriver'            => 50,
            'desktop_device'       => 15,
            'no_device_id'         => 20,
            'new_device'           => 15,
            'untrusted_device'     => 15,
            'shared_device'        => 50,
            'shared_coords'        => 30,
            'ip_outside'           => 10,
        ],
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