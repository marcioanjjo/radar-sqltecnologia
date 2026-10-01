<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use Google\Client as GoogleClient;
use Google\Service\Oauth2 as GoogleOauth2;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    private function getGoogleClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? '');
        $client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
        $client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI'] ?? '');
        $client->addScope('email');
        $client->addScope('profile');
        return $client;
    }

    public function login(Request $request, Response $response): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!empty($_SESSION['admin_user'])) {
            return $response->withHeader('Location', '/admin/dashboard')->withStatus(302);
        }

        $client = $this->getGoogleClient();
        $authUrl = $client->createAuthUrl();

        $html = "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <title>Login - Radar Notícias Admin</title>
            <style>
                body { font-family: system-ui, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f3f4f6; margin: 0; }
                .card { background: white; padding: 2.5rem; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%; }
                .btn { display: inline-block; background-color: #2563eb; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; margin-top: 1.5rem; }
                .btn:hover { background-color: #1d4ed8; }
            </style>
        </head>
        <body>
            <div class='card'>
                <h2>Radar Notícias</h2>
                <p>Acesso exclusivo à redação editorial.</p>
                <a href='" . htmlspecialchars($authUrl) . "' class='btn'>Entrar com Google</a>
            </div>
        </body>
        </html>";

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function callback(Request $request, Response $response): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $params = $request->getQueryParams();
        $code = $params['code'] ?? null;

        if (!$code) {
            $response->getBody()->write('Código de autorização não recebido.');
            return $response->withStatus(400);
        }

        $client = $this->getGoogleClient();

        try {
            $token = $client->fetchAccessTokenWithAuthCode($code);
            $client->setAccessToken($token);

            $oauth2 = new GoogleOauth2($client);
            $userInfo = $oauth2->userinfo->get();

            $email = strtolower(trim($userInfo->email));
            $adminEmail = strtolower(trim($_ENV['ADMIN_EMAIL'] ?? ''));

            // Validação estrita de Whitelist
            if (empty($adminEmail) || $email !== $adminEmail) {
                $response->getBody()->write("Acesso negado. O e-mail {$email} não possui autorização administrativa.");
                return $response->withStatus(403);
            }

            // Regista ou atualiza utilizador no MySQL
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nome, email, google_id, avatar_url, ultimo_login)
                VALUES (:nome, :email, :google_id, :avatar, NOW())
                ON DUPLICATE KEY UPDATE 
                    nome = VALUES(nome),
                    google_id = VALUES(google_id),
                    avatar_url = VALUES(avatar_url),
                    ultimo_login = NOW()
            ");

            $stmt->execute([
                ':nome'      => $userInfo->name ?? 'Administrador',
                ':email'     => $email,
                ':google_id' => $userInfo->id,
                ':avatar'    => $userInfo->picture ?? null
            ]);

            // Regista a sessão do utilizador
            $_SESSION['admin_user'] = [
                'nome'   => $userInfo->name,
                'email'  => $email,
                'avatar' => $userInfo->picture
            ];

            return $response->withHeader('Location', '/admin/dashboard')->withStatus(302);
        } catch (\Throwable $e) {
            $response->getBody()->write('Erro ao autenticar com a Google: ' . $e->getMessage());
            return $response->withStatus(500);
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();

        return $response->withHeader('Location', '/admin/login')->withStatus(302);
    }
}
