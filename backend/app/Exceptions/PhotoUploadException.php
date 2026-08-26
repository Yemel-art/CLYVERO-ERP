<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class PhotoUploadException extends DomainException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            message: 'Photo upload failed: ' . $reason,
            httpStatus: Response::HTTP_UNPROCESSABLE_ENTITY,
            errorCode: 'photo_upload_failed',
        );
    }
}
