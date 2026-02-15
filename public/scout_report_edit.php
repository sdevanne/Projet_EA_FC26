<?php
declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\ObjectId;

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$db = ConnexionMongo::db();
$reportsCol = $db->selectCollection("scout_reports");
$playersCol = $db->selectCollection("players");

$id = trim($_GET['id'] ?? '');
if ($id === '' || !preg_match('/^[a-f0-9]{24}$/i', $id)) {
    http_response_code(400);
    exit("ID invalide");
}

$report = $reportsCol->findOne(['_id' => new ObjectId($id)]);
if (!$report) {
    http_response_code(404);
    exit("Rapport introuvable");
}

$player = null;
if (!empty($report['playerId'])) {
    $player = $playersCol->findOne(['_id' => $report['playerId']], ['projection' => ['playerName' => 1, 'team' => 1, 'teamId' => 1]]);
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 0);
    $notes  = trim((string)($_POST['notes'] ?? ''));

    $strengths = $_POST['strengths'] ?? [];
    $weaknesses = $_POST['weaknesses'] ?? [];

    if (!is_array($strengths)) $strengths = [];
    if (!is_array($weaknesses)) $weaknesses = [];

    $strengths = array_values(array_filter(array_map('trim', $strengths)));
    $weaknesses = array_values(array_filter(array_map('trim', $weaknesses)));

    if ($rating < 1 || $rating > 10) {
        $error = "La note doit être entre 1 et 10.";
    } else {
        $reportsCol->updateOne(
            ['_id' => $report['_id']],
            ['$set' => [
                'rating' => $rating,
                'strengths' => $strengths,
                'weaknesses' => $weaknesses,
                'notes' => $notes,
            ]]
        );

        // retour logique: si teamId connu -> team, sinon -> liste rapports
        $teamId = (string)($player['teamId'] ?? '');
        if ($teamId !== '') {
            header("Location: /index.php?page=team&id=" . urlencode($teamId));
            exit;
        }
        header("Location: /scout_reports.php");
        exit;
    }
}

$playerName = (string)($player['playerName'] ?? 'Joueur');
$teamName = (string)($player['team'] ?? '');

$strengths = (array)($report['strengths'] ?? []);
$weaknesses = (array)($report['weaknesses'] ?? []);
$strengths = array_pad($strengths, 2, '');
$weaknesses = array_pad($weaknesses, 2, '');
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Modifier rapport scout</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="wrap">

  <h1>Modifier rapport scout</h1>

  <div class="nav">
    <a href="/scout_reports.php">← Liste rapports</a>
    <a class="active" href="#">Modifier</a>
  </div>

  <div class="card" style="margin-bottom:12px;">
    <p class="muted" style="margin:0;">
      Joueur : <strong><?= h($playerName) ?></strong>
      <?php if ($teamName): ?> — Équipe : <strong><?= h($teamName) ?></strong><?php endif; ?>
    </p>
  </div>

  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <form class="card" method="post">
    <div class="row">
      <div>
        <label>Note (/10)</label>
        <input name="rating" type="number" min="1" max="10" value="<?= h((string)($report['rating'] ?? 7)) ?>" required>
      </div>
      <div>
        <label>Commentaire</label>
        <input value="(optionnel)" disabled style="opacity:.55">
      </div>
    </div>

    <div style="height:10px"></div>

    <div class="row">
      <div>
        <h3 style="margin:0 0 8px;">Forces</h3>
        <input name="strengths[]" value="<?= h((string)$strengths[0]) ?>" placeholder="ex: Vitesse">
        <input name="strengths[]" value="<?= h((string)$strengths[1]) ?>" placeholder="ex: Passes">
      </div>

      <div>
        <h3 style="margin:0 0 8px;">Faiblesses</h3>
        <input name="weaknesses[]" value="<?= h((string)$weaknesses[0]) ?>" placeholder="ex: Défense">
        <input name="weaknesses[]" value="<?= h((string)$weaknesses[1]) ?>" placeholder="ex: Physique">
      </div>
    </div>

    <div style="height:10px"></div>

    <label>Commentaire (optionnel)</label>
    <textarea name="notes" rows="5"><?= h((string)($report['notes'] ?? '')) ?></textarea>

    <div style="height:14px"></div>

    <button type="submit">Enregistrer</button>
    <a class="pill" href="/scout_reports.php" style="margin-left:10px;">Annuler</a>
  </form>

</div>
</body>
</html>
