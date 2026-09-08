<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Origens permitidas
        $allowedOrigins = [
            'https://parafa.com.br',
            'https://www.parafa.com.br',
            'https://api.parafa.com.br',   // API subdomain
            'http://localhost:3000',     // Frontend dev
            'http://127.0.0.1:3000',    // Frontend dev
            'http://172.17.0.1:3000',   // Docker frontend
        ];

        // IPs autorizados (para requisições server-to-server)
        $allowedIps = [
            '127.0.0.1',
            '::1',
            '172.17.0.1',  // Docker frontend
            '172.18.0.1',  // Docker networks
            '172.19.0.1',
            '147.182.248.223', // Servidor
        ];

        // API Keys válidas
        $validApiKeys = [
            env('INTERNAL_API_KEY', 'parafa_api_2024_secure_key'),
        ];

        // Em desenvolvimento, liberar tudo
        if (app()->environment(['local', 'dev'])) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } else {
            // Validação rigorosa em produção
            $origin = $request->header('origin');
            $clientIp = $this->getClientIp($request);
            $apiKey = $request->header('X-API-Key');

            $authorized = false;

            // 1. Verificar API Key
            if ($apiKey && in_array($apiKey, $validApiKeys)) {
                $authorized = true;
                $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
            }
            // 2. Verificar Origin
            elseif ($origin && in_array($origin, $allowedOrigins)) {
                $authorized = true;
                $response->headers->set('Access-Control-Allow-Origin', $origin);
            }
            // 3. Verificar IP (para requisições diretas)
            elseif (in_array($clientIp, $allowedIps)) {
                $authorized = true;
                $response->headers->set('Access-Control-Allow-Origin', '*');
            }

            if (!$authorized) {
                return response()->json([
                    'error' => 'Acesso não autorizado',
                    'message' => 'Esta API só aceita requisições de origens autorizadas',
                    'debug' => [
                        'origin' => $origin,
                        'ip' => $clientIp,
                        'has_api_key' => !empty($apiKey)
                    ]
                ], 403);
            }
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-API-Key');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        if ($request->isMethod('OPTIONS')) {
            return response()->json([], 200);
        }

        return $response;
    }

    /**
     * Obter IP real do cliente
     */
    private function getClientIp(Request $request): string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipHeaders as $header) {
            $ip = $request->server($header);
            if (!empty($ip) && $ip !== 'unknown') {
                $ip = explode(',', $ip)[0];
                return trim($ip);
            }
        }

        return $request->ip();
    }
}
