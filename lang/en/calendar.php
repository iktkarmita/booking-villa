<?php

return [
    'nav' => ['seasonal_prices' => 'Seasonal prices', 'calendar' => 'Calendar'],
    'models' => ['seasonal_price' => 'Seasonal price'],
    'fields' => [
        'room' => 'Room',
        'label' => 'Season name',
        'start_date' => 'From',
        'end_date' => 'Until (inclusive)',
        'price_per_night' => 'Price per night',
        'min_nights' => 'Min. nights',
        'min_nights_help' => 'Leave empty to use the room\'s minimum nights.',
        'note' => 'Note',
    ],
    'page' => [
        'title' => 'Availability calendar',
        'room' => 'Room',
        'no_rooms' => 'No active rooms yet. Add a room first.',
        'prev' => 'Previous month',
        'next' => 'Next month',
        'free' => 'Available',
        'booked' => 'Booked',
        'blocked' => 'Blocked',
        'weekdays' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    ],
    'actions' => [
        'block' => 'Block dates',
        'unblock' => 'Unblock dates',
        'blocked_done' => ':count night(s) blocked.',
        'unblocked_done' => ':count night(s) reopened.',
        'conflict' => 'There is a booking in that date range, so it cannot be blocked.',
    ],
];
