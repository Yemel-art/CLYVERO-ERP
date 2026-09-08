<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base controller for every API endpoint.
 *
 * Provides the standard envelope helpers so each controller method
 * shapes its response identically (per API Blueprint §5).
 */
abstract class ApiController extends Controller
{
    use AuthorizesRequests;
    /**
     * @param  array<string, mixed>|\Illuminate\Contracts\Support\Arrayable|null  $data
     * @param  array<string, mixed>  $meta
     */
    protected function ok(
        mixed $data = null,
        string $message = 'OK.',
        int $status = Response::HTTP_OK,
        array $meta = [],
    ): JsonResponse {
        $payload = ['success' => true, 'message' => __($message)];
        if ($data !== null) {
            $payload['data'] = $data;
        }
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, mixed>|\Illuminate\Contracts\Support\Arrayable|null  $data
     */
    protected function created(mixed $data, string $message = 'Resource created successfully.'): JsonResponse
    {
        return $this->ok($data, $message, Response::HTTP_CREATED);
    }

    protected function noContent(string $message = 'OK.'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => __($message)], Response::HTTP_NO_CONTENT);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    protected function error(
        string $message,
        int $status = Response::HTTP_BAD_REQUEST,
        array $errors = [],
        ?string $errorCode = null,
    ): JsonResponse {
        $payload = ['success' => false, 'message' => __($message)];
        if ($errorCode !== null) {
            $payload['error_code'] = $errorCode;
        }
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
