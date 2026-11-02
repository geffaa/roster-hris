<?php

return [
    // TOTP is mandatory for Super Admin, HR Admin and Payroll Officer roles.
    'require_two_factor' => env('ROSTER_REQUIRE_2FA', true),
];
