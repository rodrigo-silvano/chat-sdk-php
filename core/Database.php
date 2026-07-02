<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $configPath = __DIR__ . '/../config/database.php';
            
            if (file_exists($configPath)) {
                $config = require $configPath;
            } else {
                $config = [
                    'host' => getenv('DB_HOST') ?: 'localhost',
                    'dbname' => getenv('DB_NAME') ?: 'chat_sdk',
                    'username' => getenv('DB_USER') ?: 'chat_sdk',
                    'password' => getenv('DB_PASS') ?: 'chat_sdk_dev',
                ];
            }

            try {
                $dsn = sprintf(
                    "mysql:host=%s;dbname=%s;charset=utf8mb4",
                    $config['host'],
                    $config['dbname']
                );
                
                self::$instance = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
                exit;
            }
        }

        return self::$instance;
    }
}
