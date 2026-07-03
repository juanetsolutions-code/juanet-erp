<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\ExceptionLogServiceInterface;
use Throwable;

class ExceptionLoggingMiddleware
{
    protected ExceptionLogServiceInterface $exceptionLogService;

    public function __construct(ExceptionLogServiceInterface $exceptionLogService)
    {
        $this->exceptionLogService = $exceptionLogService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            // Log the exception through the Enterprise Exception Log Service
            $this->exceptionLogService->log($e);
            throw $e;
        }
    }
}
