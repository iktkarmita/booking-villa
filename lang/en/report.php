<?php

return [
    'nav' => 'Reports',
    'title' => 'Reports',
    'export' => 'Export CSV',
    'from' => 'From',
    'to' => 'To',
    'period' => 'Period :from to :to (:days days). Revenue counts payments received within this period.',
    'presets' => ['month' => 'This month', 'last_month' => 'Last month', 'last_30' => 'Last 30 days', 'year' => 'This year'],
    'cards' => [
        'revenue' => 'Payments received',
        'refunded' => 'Refunds paid',
        'net' => 'Net revenue',
        'bookings' => 'Bookings created',
        'occupancy' => 'Occupancy',
        'nights' => 'Nights sold / capacity',
    ],
    'by_status' => 'Bookings by status',
    'by_villa' => 'By villa',
    'cols' => ['villa' => 'Villa', 'bookings' => 'Bookings', 'nights' => 'Nights', 'occupancy' => 'Occupancy', 'revenue' => 'Revenue'],
    'empty' => 'No data for this period.',
];
