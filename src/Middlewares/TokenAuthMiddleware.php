<?php

declare(strict_types=1);

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class TokenAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        $expectedToken = $_ENV['API_WEBHOOK_TOKEN'] ?? '';

        // Valida se o cabeçalho possui o formato "Bearer <token>"
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches) || empty($expectedToken)) {
            return $this->unauthorizedResponse('Token de autorização não fornecido ou inválido.');
        }

        $receivedToken = $matches[1];

        // Comparação segura contra ataques de timing
        if (!hash_equals($expectedToken, $receivedToken)) {
            return $this->unauthorizedResponse('Token de autorização incorreto.');
        }

        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $payload = json_encode([
            'status' => 'erro',
            'codigo' => 401,
            'mensagem' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
}
