<?php

namespace Projet\Fc25;

use MongoDB\Client;
use MongoDB\Database;

class ConnexionMongo
{
    private static ?Database $db = null;

    public static function db(): Database
    {
        if (self::$db === null) {
            $config = require __DIR__ . "/../config/config.php";

            // ✅ Overrides pour les tests (ne casse pas le site)
            $mongoUri = $_ENV['MONGO_URI'] ?? getenv('MONGO_URI') ?: ($config["mongo_uri"] ?? "mongodb://127.0.0.1:27017");
            $dbName   = $_ENV['DB_NAME']   ?? getenv('DB_NAME')   ?: ($config["db_name"] ?? "Projet_EA_FC26");

            $client = new Client($mongoUri);
            self::$db = $client->selectDatabase($dbName);
        }
        return self::$db;
    }

    // utile en tests si on veut réinitialiser
    public static function reset(): void
    {
        self::$db = null;
    }
}
