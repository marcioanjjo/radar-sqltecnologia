<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    //Construtor Privado impodi instanciação externa direta
    private function __construtct() {}

    //Impedi a clonagem da instacia
    private function __clone() {}

    //Impedi a desserialização
    public function __wakeup()
    {
        throw new RuntimeException("Não é possível desserializar a instância do banco de dados.");
    }

    public static function getConnection(): PDO
    {

        if (self::$instance === null) {
            $host           = $_ENV['DB_HOST'] ?? 'db';
            $port           = $_ENV['DB_PORT'] ?? '3306';
            $dataName       = $_ENV['DB_DATABASE'] ?? 'radar_db';
            $user           = $_ENV['DB_USERNAME'] ?? 'radar_user';
            $password       = $_ENV['DB_PASSWORD'] ?? 'radar_password_secret';

            $dns = "mysql:host={$host};port={$port};dbname={$dataName};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES  => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dns, $user, $password, $options);
            } catch (PDOException $e) {
                throw new RuntimeException("Erro ao conectar ao banco de dados: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}
