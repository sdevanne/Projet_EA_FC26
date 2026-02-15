<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\UTCDateTime;

$db = ConnexionMongo::db();

// Index unique sur code
$db->leagues->createIndex(['code' => 1], ['unique' => true]);

$now = new UTCDateTime((new DateTimeImmutable())->getTimestamp() * 1000);

$leagues = [
    ['code' => 'ANG1', 'name' => 'Premier League', 'country' => 'Angleterre', 'level' => 1],
    ['code' => 'ESP1', 'name' => 'LaLiga',        'country' => 'Espagne',    'level' => 1],
    ['code' => 'FRA1', 'name' => 'Ligue 1',       'country' => 'France',     'level' => 1],
    ['code' => 'ITA1', 'name' => 'Serie A',       'country' => 'Italie',     'level' => 1],
    ['code' => 'ALL1', 'name' => 'Bundesliga',    'country' => 'Allemagne',  'level' => 1],
];

foreach ($leagues as $league) {
    $db->leagues->updateOne(
        ['code' => $league['code']],
        ['$setOnInsert' => $league + ['createdAt' => $now]],
        ['upsert' => true]
    );
    echo "✅ Ligue ajoutée : {$league['code']}\n";
}
echo "✅ Seed leagues terminé.\n";
