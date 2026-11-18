<?php

/*
| Business rules for meetings. All times in the API are UTC; working hours are
| checked in this timezone.
*/

return [
    'timezone' => env('CRM_TIMEZONE', 'Asia/Almaty'),
    'workday_start' => env('CRM_WORKDAY_START', '09:00'),
    'workday_end' => env('CRM_WORKDAY_END', '18:00'),
    'meeting_minutes' => (int) env('CRM_MEETING_MINUTES', 60),
    // ISO-8601 day numbers: 1 = Monday ... 7 = Sunday
    'workdays' => [1, 2, 3, 4, 5],
];
