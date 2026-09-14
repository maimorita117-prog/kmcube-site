<?php
return [
    'timezone' => 'Asia/Tokyo',
    'shop_name' => 'KMCUBE Yakushima',
    'shop_email' => 'm-morita@nn-cube.com',
    'mail_from' => 'no-reply@k-mcube.com',
    'admin_password' => 'CHANGE_ME_NOW',
    'base_url' => 'https://k-mcube.com',
    'cars' => [
        'kei' => ['label' => '軽自動車', 'model' => 'N-BOXクラス', 'price' => 6600, 'hourly_price' => 1100, 'inventory' => 2],
        'compact' => ['label' => 'コンパクト', 'model' => 'AQUAクラス', 'price' => 7700, 'hourly_price' => 1300, 'inventory' => 2],
        'van' => ['label' => 'ミニバン', 'model' => '7人乗りクラス', 'price' => 9900, 'hourly_price' => 1700, 'inventory' => 1],
    ],
    'insurance_per_day' => 1100,
    'child_seat_per_day' => 550,
    'service_prices' => [
        'stay' => 6600,
        'hike' => 8800,
        'activity' => 6600,
        'boat' => 8800,
    ],
];
