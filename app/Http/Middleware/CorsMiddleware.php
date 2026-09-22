<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
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
            '172.23.0.1',  // Docker network cnpj-internal (host)
            '172.23.0.3',  // Docker network cnpj-internal (frontend)
            '192.168.1.30', // Host LAN IP
            '147.182.248.223', // Servidor
        ];

        // API Keys válidas
        $validApiKeys = [
            env('INTERNAL_API_KEY', 'parafa_api_2024_secure_key'),
        ];

        // Validação rigorosa em produção
        $origin = $request->header('origin');
        $clientIp = $this->getClientIp($request);
        $apiKey = $request->header('X-API-Key');

        // Os endereços da rede Docker mudam a cada deploy.
        $remoteIp = $request->server('REMOTE_ADDR', '');
        $internalPeer = $remoteIp && IpUtils::checkIp($remoteIp, [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]);

        if (app()->environment(['local', 'dev', 'development']) || $internalPeer) {
            $allowedOrigin = '*';
        } else {

            $authorized = false;

            // 1. Verificar API Key
            if ($apiKey && in_array($apiKey, $validApiKeys)) {
                $authorized = true;
                $allowedOrigin = $origin ?: '*';
            }
            // 2. Verificar Origin
            elseif ($origin && in_array($origin, $allowedOrigins)) {
                $authorized = true;
                $allowedOrigin = $origin;
            }
            // 3. Verificar IP (para requisições diretas)
            elseif (in_array($clientIp, $allowedIps)) {
                $authorized = true;
                $allowedOrigin = '*';
            }

            if (! $authorized) {
                return response()->json([
                    'error' => 'Acesso não autorizado',
                    'message' => 'Esta API só aceita requisições de origens autorizadas',
                    'debug' => [
                        'origin' => $origin,
                        'ip' => $clientIp,
                        'has_api_key' => ! empty($apiKey),
                    ],
                ], 403);
            }
        }

        // Não consulte o banco para uma requisição que será recusada.
        $response = $request->isMethod('OPTIONS') ? response()->json([], 200) : $next($request);

        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-API-Key');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }

    /**
     * Obter IP real do cliente
     */
    private function getClientIp(Request $request): string
    {
        return $request->ip();
    }
}
