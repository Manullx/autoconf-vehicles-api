<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (): null => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'type' => 'https://httpstatuses.com/422',
                'title' => 'Validation failed',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'The provided data is invalid.',
                'errors' => $exception->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'type' => 'https://httpstatuses.com/401',
                'title' => 'Unauthenticated',
                'status' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Authentication is required to access this resource.',
            ], Response::HTTP_UNAUTHORIZED);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'type' => 'https://httpstatuses.com/403',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'message' => 'You are not allowed to perform this action.',
            ], Response::HTTP_FORBIDDEN);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'type' => 'https://httpstatuses.com/404',
                'title' => 'Resource not found',
                'status' => Response::HTTP_NOT_FOUND,
                'message' => 'The requested resource was not found.',
            ], Response::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $errors = [
                Response::HTTP_FORBIDDEN => ['Forbidden', 'You are not allowed to perform this action.'],
                Response::HTTP_NOT_FOUND => ['Resource not found', 'The requested resource was not found.'],
                Response::HTTP_TOO_MANY_REQUESTS => ['Too many requests', 'The request limit has been exceeded.'],
            ];

            if (! isset($errors[$status])) {
                return null;
            }

            return response()->json([
                'type' => "https://httpstatuses.com/{$status}",
                'title' => $errors[$status][0],
                'status' => $status,
                'message' => $errors[$status][1],
            ], $status, $exception->getHeaders());
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'type' => 'https://httpstatuses.com/500',
                'title' => 'Internal server error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'An unexpected error occurred.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        });
    })->create();
