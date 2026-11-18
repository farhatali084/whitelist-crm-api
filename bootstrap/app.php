<?php

use App\Exceptions\ApiException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '', // paths exactly as in the assignment: /companies, /meetings, ...
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Business-rule refusals thrown by our own code (422 / 409).
        $exceptions->render(fn (ApiException $e) => ApiException::toResponse($e->status, $e->errorCode, $e->getMessage(), $e->details));

        $exceptions->render(fn (ValidationException $e) => ApiException::toResponse(
            422, 'validation_failed', 'The request data is invalid.', $e->errors()
        ));

        // Unknown id in the URL, e.g. PATCH /meetings/999.
        $exceptions->render(function (NotFoundHttpException $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof ModelNotFoundException) {
                $model = Str::snake(class_basename($previous->getModel()), ' ');
                $ids = implode(', ', $previous->getIds());

                return ApiException::toResponse(404, 'not_found', ucfirst($model)." {$ids} not found.");
            }

            return ApiException::toResponse(404, 'not_found', 'Endpoint not found.');
        });

        $exceptions->render(fn (MethodNotAllowedHttpException $e) => ApiException::toResponse(
            405, 'method_not_allowed', $e->getMessage() ?: 'Method not allowed.'
        ));

        // SQLite gave up waiting for the write lock (very high concurrency): ask to retry.
        $exceptions->render(function (QueryException $e) {
            if (str_contains($e->getMessage(), 'database is locked')) {
                return ApiException::toResponse(409, 'busy_retry', 'Another booking is being saved right now. Please retry.');
            }

            return null; // fall through to the generic handler below
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof HttpExceptionInterface) {
                return ApiException::toResponse($e->getStatusCode(), 'http_error', $e->getMessage() ?: 'HTTP error.');
            }
            if (config('app.debug')) {
                return null; // show the full stack trace while developing
            }

            return ApiException::toResponse(500, 'internal_error', 'Unexpected server error.');
        });
    })->create();
