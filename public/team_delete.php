<?php
declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\ObjectId;

$db = ConnexionMongo::db();
$teamsCol   = $db->selectCollection("teams");
$playersCol = $db->selectCollection("players");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Méthode non autorisée");
}

$id = trim((string)($_POST['id'] ?? ''));
if ($id === '' || !preg_match('/^[a-f0-9]{24}$/i', $id)) {
    http_response_code(400);
    exit("ID invalide");
}

$teamId = new ObjectId($id);

// SAFE: empêcher suppression si des joueurs existent
$countPlayers = $playersCol->countDocuments(['teamId' => $teamId]);
if ($countPlayers > 0) {
    header("Location: /index.php?page=teams&err=" . urlencode("Impossible: l'équipe contient encore des joueurs."));
    exit;
}

$teamsCol->deleteOne(['_id' => $teamId]);

header("Location: /index.php?page=teams");
exit;
