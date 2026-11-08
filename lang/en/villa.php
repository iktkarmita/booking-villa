<?php

return [
    'nav' => ['villas' => 'Villas', 'rooms' => 'Rooms'],
    'models' => ['villa' => 'Villa', 'room' => 'Room'],
    'fields' => [
        'name' => 'Name',
        'name_id' => 'Name (Indonesian)',
        'name_en' => 'Name (English)',
        'description_id' => 'Description (Indonesian)',
        'description_en' => 'Description (English)',
        'address' => 'Address',
        'check_in_time' => 'Check-in time',
        'check_out_time' => 'Check-out time',
        'is_active' => 'Active',
        'amenities' => 'Amenities',
        'images' => 'Photos',
        'image' => 'Photo',
        'villa' => 'Villa',
        'bedrooms' => 'Bedrooms',
        'bathrooms' => 'Bathrooms',
        'max_guests' => 'Max guests',
        'base_price' => 'Price per night',
        'min_nights' => 'Min. nights',
        'rooms_count' => 'Rooms',
    ],
    'sections' => ['info' => 'Villa information', 'photos' => 'Villa photos', 'amenities' => 'Amenities'],
    'quota' => [
        'exceeded' => 'Your plan\'s active room quota (:limit rooms) is full. Deactivate another room or upgrade your plan.',
        'helper' => 'Remaining quota: :remaining of :limit active rooms.',
    ],
];
