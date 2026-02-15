<?php
declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\ObjectId;

$db = ConnexionMongo::db();
$reportsCol = $db->selectCollection("scout_reports");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Méthode non autorisée");
}

$id = trim($_POST['id'] ?? '');
if ($id === '' || !preg_match('/^[a-f0-9]{24}$/i', $id)) {
    http_response_code(400);
    exit("ID invalide");
}

$reportsCol->deleteOne(['_id' => new ObjectId($id)]);

header("Location: /scout_reports.php");
exit;
