<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base for all domain (business-rule) exceptions.
 *
 * The global exception handler catches DomainException and emits the
 * standard error envelope: {success:false, message, errors?}.
 */
abstract class DomainException extends Exception
{
    public function __construct(
        string $message = '',
        protected int $httpStatus = Response::HTTP_BAD_REQUEST,
        protected string $errorCode = 'domain_error',
        /** @var array<string, mixed> */
        protected array $context = [],
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }

    public function render(): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => __($this->getMessage()),
            'error_code' => $this->errorCode,
        ];
        if ($this->context !== []) {
            $payload['errors'] = $this->context;
        }

        return response()->json($payload, $this->httpStatus);
    }
}
