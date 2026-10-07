<?php

declare(strict_types=1);

use App\Config\Database;
use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use App\Controllers\WebhookController;
use App\Middlewares\TokenAuthMiddleware;
use App\Controllers\AuthController;
use App\Middlewares\AdminAuthMiddleware;
use Slim\Routing\RouteCollectorProxy;
use App\Controllers\NoticiaAdminController;
use App\Controllers\PortalController;
use App\Controllers\AfiliadoController;

require_once __DIR__ . '/../vendor/autoload.php';

//carrega a variável de ambiente (.env)
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

//Instancia a aplicação Slim
$app = AppFactory::create();

//Middlewares Essenciais
$app->addBodyParsingMiddleware(); // Permite receber payloads JSON no corpo da requisição (Webhook)
$app->addRoutingMiddleware(); // Permite o roteamento das requisições

//Habilita exibição detalhada de erros apenas em desenvolvimento
$debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
$app->addErrorMiddleware($debug, true, true);

// Rotas Públicas do Portal de Notícias
$app->get('/', [PortalController::class, 'index']);
$app->get('/noticia/{slug}', [PortalController::class, 'artigo']);
$app->get('/termos', [PortalController::class, 'termos']);
$app->get('/privacidade', [PortalController::class, 'privacidade']);
// Rota Pública de Redirecionamento e Rastreamento de Afiliados
$app->get('/ir/{id:[0-9]+}', [AfiliadoController::class, 'redirecionar']);


//Rota de Verificação do Banco de Dados (Health Check)
$app->get('/health-db', function (Request $request, Response $response): Response {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT 1'); // Executa uma consulta simples para verificar a conexão
        $ok = (bool) $stmt->fetchColumn();

        $dados = [
            'database' => 'Conectado',
            'health'   => $ok ? 'ok' : 'falha'

        ];
        $statusCode = 200;
    } catch (\Throwable $e) {
        $dados = [
            'database' => 'erro',
            'mensagem' => $e->getMessage()
        ];
        $statusCode = 500;
    }

    $response->getBody()->write(json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
});

// Rota do Webhook protegida pelo TokenAuthMiddleware
$app->post('/api/v1/noticias/webhook', [WebhookController::class, 'receberNoticia'])
    ->add(new TokenAuthMiddleware());


// Rotas de Autenticação
$app->get('/admin/login', [AuthController::class, 'login']);
$app->get('/admin/auth/google/callback', [AuthController::class, 'callback']);
$app->get('/admin/logout', [AuthController::class, 'logout']);

// Grupo Protegido do Painel Administrativo
$app->group('/admin', function (RouteCollectorProxy $group) {

    $group->get('/dashboard', function (Request $request, Response $response): Response {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $user = $_SESSION['admin_user'];

        $html = "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head><meta charset='UTF-8'><title>Dashboard - Radar Notícias</title></head>
        <body style='font-family: sans-serif; padding: 2rem;'>
            <h1>Painel Administrativo</h1>
            <p>Sessão iniciada como: <strong>" . htmlspecialchars($user['nome']) . "</strong> (" . htmlspecialchars($user['email']) . ")</p>
            <p><a href='/admin/noticias'>Ir para Redação de Notícias</a></p>
            <p><a href='/admin/afiliados'>Gerir Links de Afiliados</a></p>
            <p><a href='/admin/logout'>Terminar Sessão</a></p>
        </body>
        </html>";

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    });

    // Gestão Editorial de Notícias
    $group->get('/noticias', [NoticiaAdminController::class, 'index']);
    $group->get('/noticias/nova', [NoticiaAdminController::class, 'criar']);
    $group->post('/noticias/guardar', [NoticiaAdminController::class, 'guardar']);
    $group->get('/noticias/{id:[0-9]+}/editar', [NoticiaAdminController::class, 'editar']);
    $group->post('/noticias/{id:[0-9]+}/atualizar', [NoticiaAdminController::class, 'atualizar']);

    // Gestão de Links de Afiliados
    $group->get('/afiliados', [AfiliadoController::class, 'listar']);
    $group->get('/afiliados/novo', [AfiliadoController::class, 'criar']);
    $group->post('/afiliados/guardar', [AfiliadoController::class, 'guardar']);
})->add(new AdminAuthMiddleware());

//Executa a aplicação
$app->run();
