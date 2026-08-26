<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class InvalidCredentialsException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'The email or password you entered is incorrect.',
            httpStatus: Response::HTTP_UNAUTHORIZED,
            errorCode: 'invalid_credentials',
        );
    }
}
