<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Projet\Fc25\ConnexionMongo;

$db = ConnexionMongo::db();

// DROP collections
$collections = ['leagues', 'teams', 'players', 'scout_reports', 'transfers'];

foreach ($collections as $c) {
    try {
        $db->dropCollection($c);
        echo "✅ Collection supprimée : {$c}\n";
    } catch (Throwable $e) {
        echo "⚠️ Impossible de supprimer {$c} : " . $e->getMessage() . "\n";
    }
}
echo "✅ Reset terminé.\n";
