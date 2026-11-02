<?php

return [
    // TOTP is mandatory for Super Admin, HR Admin and Payroll Officer roles.
    'require_two_factor' => env('ROSTER_REQUIRE_2FA', true),

    // Bank names offered when an employee changes their account (free text is still accepted).
    'banks' => [
        'id' => ['BCA', 'Mandiri', 'BNI', 'BRI', 'CIMB Niaga', 'Permata', 'BSI', 'Danamon', 'OCBC', 'Jago', 'SeaBank'],
        'gb' => ['Barclays', 'HSBC UK', 'Lloyds', 'NatWest', 'Santander UK', 'Monzo', 'Starling', 'Nationwide'],
        'default' => [],
    ],
];
