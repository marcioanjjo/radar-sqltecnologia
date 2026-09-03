<?php

declare(strict_types=1);

use App\Config\Database;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

try {
    $pdo = Database::getConnection();
    echo "<h2 style='color: green;'>✔ Conexão PDO (Singleton) estabelecida com sucesso!</h2>";

    //teste de consulta simples
    $stmt = $pdo->query("SELECT DATABASE() AS banco_atual, VERSION() AS versao_mysql");
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<h2 style='color: blue;'>Informações do Banco de Dados:</h2>";
    echo "<p><strong>Banco Atual:</strong> " . htmlspecialchars($resultado['banco_atual']) . "</p>";
    echo "<p><strong>Versão MySQL:</strong> " . htmlspecialchars($resultado['versao_mysql']) . "</p>";
} catch (\Throwable $e) {
    echo "<h2 style='color: red;'>❌Falha na conexão.</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
