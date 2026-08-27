<?php

return [
    'name' => 'shipping',
    'domains' => [
        'easypost' => [
            'enabled' => true,
        ],
    ],
    'easypost' => [
        'api_key' => env('EASYPOST_API_KEY'),
        'webhook_secret' => env('EASYPOST_WEBHOOK_SECRET'),
        'defaults' => [
            'from_address' => [
                'company' => 'EasyPost',
                'street1' => '118 2nd Street',
                'street2' => '4th Floor',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94105',
                'country' => 'US',
                'phone' => '415-456-7890',
            ],
            'to_address' => [
                'name' => 'Dr. Steve Brule',
                'street1' => '179 N Harbor Dr',
                'city' => 'Redondo Beach',
                'state' => 'CA',
                'zip' => '90277',
                'country' => 'US',
                'phone' => '310-808-5243',
            ],
            'parcel' => [
                'length' => 4,
                'width' => 4,
                'height' => 4,
                'weight' => 1.92,
            ],
            'units' => [
                'dimension' => 'in',
                'weight' => 'oz',
            ],
        ],
    ],
];
