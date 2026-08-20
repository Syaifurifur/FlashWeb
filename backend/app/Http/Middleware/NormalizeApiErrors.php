<?php

namespace App\Http\Middleware;

use App\Support\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class NormalizeApiErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() < 400 || ! $response instanceof JsonResponse) {
            return $response;
        }

        $data = (array) $response->getData(true);
        if (isset($data['error'])) return $response;

        $status = $response->getStatusCode();
        $message = $data['message'] ?? 'Permintaan tidak dapat diproses.';
        $payload = ApiErrorResponse::payload(
            $request,
            $message,
            $status,
            errors: (array) ($data['errors'] ?? []),
            extra: collect($data)->except(['message', 'errors', 'error'])->all(),
        );
        $response->setData($payload);

        return $response;
    }
}
