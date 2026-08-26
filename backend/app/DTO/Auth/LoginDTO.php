<?php

declare(strict_types=1);

namespace App\DTO\Auth;

/**
 * Login intent — passed from Controller (via Form Request) into AuthService.
 */
final readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public string $ipAddress,
        public ?string $schoolSlug = null,
        public ?string $userAgent = null,
        public bool $rememberMe = false,
    ) {
    }
}
