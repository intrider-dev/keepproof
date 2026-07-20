<?php

use App\Modules\Accounts\Exceptions\SharedHouseholdOwnershipBlocksDeletion;
use App\Modules\Accounts\Http\Middleware\RequireRecentPasswordConfirmation;
use App\Modules\Shared\Http\Middleware\AssignCorrelationId;
use App\Modules\Shared\Http\ProblemDetails;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/health/live',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignCorrelationId::class);
        $middleware->statefulApi();
        $middleware->throttleApi('api');
        $middleware->redirectGuestsTo(null);
        $middleware->alias(['password.recent' => RequireRecentPasswordConfirmation::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(static function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ProblemDetails::response(
                $request,
                422,
                'One or more fields failed validation.',
                ['errors' => $exception->errors()],
            );
        });

        $exceptions->render(static function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ProblemDetails::response($request, 401, 'Authentication is required for this request.');
        });

        $exceptions->render(static function (SharedHouseholdOwnershipBlocksDeletion $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ProblemDetails::response($request, 409, $exception->getMessage(), [
                'code' => 'shared_household_ownership',
            ]);
        });

        $exceptions->render(static function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $detail = match ($status) {
                403 => 'You are not authorized to perform this action.',
                404 => 'The requested resource was not found.',
                405 => 'The HTTP method is not supported for this endpoint.',
                419 => 'The CSRF token is missing or expired.',
                429 => 'Too many requests. Please try again later.',
                default => 'The request could not be completed.',
            };

            $response = ProblemDetails::response($request, $status, $detail);
            $response->headers->add($exception->getHeaders());

            return $response;
        });

        $exceptions->render(static function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ProblemDetails::response($request, 500, 'An unexpected error occurred.');
        });
    })->create();
