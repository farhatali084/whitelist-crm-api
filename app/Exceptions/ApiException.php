<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A refusal the API returns on purpose (business rule broken, conflict).
 * Rendered as {"error": {"code", "message", "details"?}} by bootstrap/app.php.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    /** 422: the request is well-formed but breaks a business rule (e.g. outside working hours). */
    public static function unprocessable(string $code, string $message, array $details = []): self
    {
        return new self(422, $code, $message, $details);
    }

    /** 409: the request conflicts with existing data (e.g. overlapping meeting). */
    public static function conflict(string $code, string $message, array $details = []): self
    {
        return new self(409, $code, $message, $details);
    }

    /** The single JSON error shape used for every error response. */
    public static function toResponse(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        $body = ['code' => $code, 'message' => $message];
        if ($details) {
            $body['details'] = $details;
        }

        return response()->json(['error' => $body], $status);
    }
}
