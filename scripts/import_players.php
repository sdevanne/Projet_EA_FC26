<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\UTCDateTime;

function hlog(string $msg): void { echo $msg . PHP_EOL; }

function slugify(string $s): string {
    $s = trim($s);
    $s = mb_strtolower($s, 'UTF-8');
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = preg_replace('/[^a-z0-9]+/i', '-', $s);
    $s = trim((string)$s, '-');
    return $s !== '' ? $s : 'n-a';
}

function parseOverall($v): int {
    $s = trim((string)$v);
    if ($s === '') return 0;
    if (preg_match('/^(\d{1,2})/', $s, $m)) return (int)$m[1];
    return (int)$s;
}

function parseInt($v): int {
    $s = trim((string)$v);
    if ($s === '') return 0;
    $s = preg_replace('/[^\d]/', '', $s);
    return (int)$s;
}

function ymdToMs(?string $ymd): ?int {
    $s = trim((string)$ymd);
    if ($s === '') return null;

    if (ctype_digit($s) && strlen($s) >= 12) return (int)$s;

    $s = substr($s, 0, 10);
    $dt = DateTime::createFromFormat('Y-m-d', $s, new DateTimeZone('UTC'));
    if (!$dt) return null;
    return $dt->getTimestamp() * 1000;
}

function detectDelimiter(string $firstLine): string {
    $cComma = substr_count($firstLine, ',');
    $cSemi  = substr_count($firstLine, ';');
    return ($cSemi > $cComma) ? ';' : ',';
}

function openCsv(string $path) {
    $h = fopen($path, 'rb');
    if (!$h) return false;

    $bom = fread($h, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($h);

    return $h;
}

$db = ConnexionMongo::db();

$leaguesCol  = $db->selectCollection('leagues');
$teamsCol    = $db->selectCollection('teams');
$playersCol  = $db->selectCollection('players');

try {
    $playersCol->createIndex(['teamId' => 1, 'slug' => 1], ['unique' => true]);
} catch (Throwable $e) {}

$base = realpath(__DIR__ . '/../data/raw/players');
if (!$base || !is_dir($base)) {
    hlog("❌ Dossier introuvable: data/raw/players");
    exit(1);
}

$leagueByCode = [];
foreach ($leaguesCol->find([], ['projection' => ['code' => 1]]) as $lg) {
    if (!empty($lg['code'])) $leagueByCode[(string)$lg['code']] = $lg['_id'];
}

$totalFiles = 0;
$totalPlayers = 0;

$leagueDirs = array_filter(glob($base . '/*'), 'is_dir');
sort($leagueDirs);

foreach ($leagueDirs as $dir) {
    $leagueCode = basename($dir);
    $leagueId = $leagueByCode[$leagueCode] ?? null;

    if (!$leagueId) {
        hlog("⚠️ Ligue introuvable pour $leagueCode, ignoré.");
        continue;
    }

    $files = glob($dir . '/*.csv');
    sort($files);

    hlog("== $leagueCode (" . count($files) . " fichiers) ==");

    foreach ($files as $file) {
        $totalFiles++;

        $handle = openCsv($file);
        if (!$handle) { hlog("❌ Impossible d'ouvrir " . basename($file)); continue; }

        $firstLine = fgets($handle);
        if ($firstLine === false) { fclose($handle); hlog("⚠️ Fichier vide: " . basename($file)); continue; }

        $delimiter = detectDelimiter($firstLine);
        fclose($handle);

        $handle = openCsv($file);
        if (!$handle) { hlog("❌ Impossible d'ouvrir " . basename($file)); continue; }

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header || count($header) < 2) { fclose($handle); hlog("❌ Header invalide " . basename($file)); continue; }

        $header = array_map(fn($x) => strtolower(trim((string)$x)), $header);

        $need = [
            'player_name','team_name','positions','overall','age',
            'pac','sho','pas','dri','def','phy',
            'height_cm','preferred_foot','contract_start','contract_end','market_value'
        ];

        $idx = [];
        foreach ($need as $col) {
            $i = array_search($col, $header, true);
            $idx[$col] = ($i === false) ? -1 : (int)$i;
        }

        if ($idx['player_name'] === -1 || $idx['team_name'] === -1) {
            fclose($handle);
            hlog("❌ Colonne manquante (player_name/team_name) dans " . basename($file));
            continue;
        }

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) === 1 && trim((string)$row[0]) === '') continue;

            if (count($row) === 1 && strpos((string)$row[0], $delimiter) !== false) {
                $row = str_getcsv((string)$row[0], $delimiter);
            }

            $playerName = trim((string)($row[$idx['player_name']] ?? ''));
            $teamName   = trim((string)($row[$idx['team_name']] ?? ''));

            if ($playerName === '' || $teamName === '') { $skipped++; continue; }

            $team = $teamsCol->findOne([
                'leagueId' => $leagueId,
                '$or' => [
                    ['name' => $teamName],
                    ['slug' => slugify($teamName)]
                ]
            ]);

            if (!$team) {
                $skipped++;
                continue;
            }

            $positions = trim((string)($row[$idx['positions']] ?? ''));

            $overall = parseOverall($row[$idx['overall']] ?? '');
            $age     = parseInt($row[$idx['age']] ?? '');

            $pac = parseInt($row[$idx['pac']] ?? '');
            $sho = parseInt($row[$idx['sho']] ?? '');
            $pas = parseInt($row[$idx['pas']] ?? '');
            $dri = parseInt($row[$idx['dri']] ?? '');
            $def = parseInt($row[$idx['def']] ?? '');
            $phy = parseInt($row[$idx['phy']] ?? '');

            $height = parseInt($row[$idx['height_cm']] ?? '');
            $foot   = trim((string)($row[$idx['preferred_foot']] ?? ''));

            $startMs = ymdToMs((string)($row[$idx['contract_start']] ?? ''));
            $endMs   = ymdToMs((string)($row[$idx['contract_end']] ?? ''));

            $value = parseInt($row[$idx['market_value']] ?? '');

            $playerSlug = slugify($playerName);

            $doc = [
                'leagueId'        => $leagueId,
                'teamId'          => $team['_id'],
                'team'            => $team['name'] ?? $teamName,

                'playerName'      => $playerName,
                'slug'            => $playerSlug,
                'positions'       => $positions,

                'overall'         => $overall,
                'age'             => $age,

                'pac'             => $pac,
                'sho'             => $sho,
                'pas'             => $pas,
                'dri'             => $dri,
                'def'             => $def,
                'phy'             => $phy,

                'heightCm'        => $height,
                'preferredFoot'   => $foot,

                'contractStart'   => $startMs,
                'contractEnd'     => $endMs,

                'marketValue'     => $value,

                'updatedAt'       => new UTCDateTime(),
            ];

            try {
                $playersCol->updateOne(
                    ['teamId' => $team['_id'], 'slug' => $playerSlug],
                    [
                        '$set' => $doc,
                        '$setOnInsert' => ['createdAt' => new UTCDateTime()],
                    ],
                    ['upsert' => true]
                );
                $imported++;
            } catch (Throwable $e) {
                $skipped++;
            }
        }

        fclose($handle);

        $totalPlayers += $imported;
        hlog("✅ " . basename($file) . " : $imported joueurs importés" . ($skipped ? " (skip $skipped)" : ""));
    }
}

hlog("✅ Import players terminé. Fichiers: $totalFiles | Total joueurs: $totalPlayers");
