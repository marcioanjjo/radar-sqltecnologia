<?php

declare(strict_types=1);

use App\Config\Database;
use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

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

// Rota inicial de boas vindas
$app->get('/', function (Request $request, Response $response): Response {
    $response->getBody()->write(json_encode([
        'projeto' => $_ENV['APP_NAME'] ?? 'Radar Notícias',
        'status'  => 'online',
        'horario' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    return $response->withHeader('Content-Type', 'application/json');
});

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

//Executa a aplicação
$app->run();
