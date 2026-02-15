<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\UTCDateTime;

function slugify(string $text): string {
    $text = trim(mb_strtolower($text, 'UTF-8'));
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim((string)$text, '-');
    return $text === '' ? 'n-a' : $text;
}

$db = ConnexionMongo::db();

// unique: (leagueId + slug)
$db->teams->createIndex(['leagueId' => 1, 'slug' => 1], ['unique' => true]);

$dir = __DIR__ . '/../data/raw/teams';
$files = glob($dir . '/*.csv');

if (!$files) {
    echo "❌ Aucun fichier CSV trouvé dans $dir\n";
    exit(1);
}

$now = new UTCDateTime((new DateTimeImmutable())->getTimestamp() * 1000);
$totalInserted = 0;

foreach ($files as $file) {
    $code = strtoupper(pathinfo($file, PATHINFO_FILENAME)); // ex: ANG1
    $league = $db->leagues->findOne(['code' => $code]);

    if (!$league) {
        echo "⚠️ Ligue introuvable pour $code (fichier: " . basename($file) . "), ignoré.\n";
        continue;
    }

    $handle = fopen($file, 'rb');
    if (!$handle) {
        echo "❌ Impossible d'ouvrir " . basename($file) . "\n";
        continue;
    }

    // BOM
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    $header = fgetcsv($handle, 0, ',');
    if (!$header) {
        echo "⚠️ Fichier vide: " . basename($file) . "\n";
        fclose($handle);
        continue;
    }

    $header = array_map(fn($x) => strtolower(trim((string)$x)), $header);
    $map = array_flip($header);

    $required = ['team_name','ovr','att','mid','def','budget','avg_age','youth_dev'];
    foreach ($required as $col) {
        if (!isset($map[$col])) {
            echo "❌ Colonne manquante '$col' dans " . basename($file) . "\n";
            fclose($handle);
            continue 2;
        }
    }

    $countFile = 0;

    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        if (count($row) < count($header)) continue;

        $name = trim((string)$row[$map['team_name']]);
        if ($name === '') continue;

        $slug = slugify($name);

        $doc = [
            'name' => $name,
            'slug' => $slug,
            'leagueId' => $league->_id,
            'rating' => (int)$row[$map['ovr']],
            'att' => (int)$row[$map['att']],
            'mid' => (int)$row[$map['mid']],
            'def' => (int)$row[$map['def']],
            'budget' => (int)round((float)$row[$map['budget']]),
            'avgAge' => (float)$row[$map['avg_age']],
            'youthDev' => (int)$row[$map['youth_dev']],
            'createdAt' => $now,
        ];

        $db->teams->updateOne(
            ['leagueId' => $league->_id, 'slug' => $slug],
            ['$set' => $doc],
            ['upsert' => true]
        );

        $countFile++;
        $totalInserted++;
    }

    fclose($handle);
    echo "✅ $code : $countFile clubs importés (" . basename($file) . ")\n";
}

echo "✅ Import teams terminé. Total: $totalInserted\n";
