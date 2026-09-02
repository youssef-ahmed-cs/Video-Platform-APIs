<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    use AuthorizesRequests;

    protected function successResponse(
        array $payload = [],
        string $message = 'Request completed successfully.',
        int $status = 200
    ): JsonResponse {
        return response()->json(array_merge([
            'success' => true,
            'message' => $message,
        ], $payload), $status);
    }

    protected function errorResponse(string $message, int $status, array $payload = []): JsonResponse
    {
        return response()->json(array_merge([
            'success' => false,
            'message' => $message,
        ], $payload), $status);
    }
}
