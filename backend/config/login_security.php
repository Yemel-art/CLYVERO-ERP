<?php

declare(strict_types=1);

return [
    /*
    | Enable only after the administrator's real email address and outbound
    | SMTP delivery have both been tested successfully.
    */
    'admin_email_otp_enabled' => (bool) env('ADMIN_LOGIN_OTP_ENABLED', false),
];
