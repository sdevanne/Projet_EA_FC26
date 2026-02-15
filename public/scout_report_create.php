<?php
declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$db = ConnexionMongo::db();
$playersCol = $db->selectCollection("players");
$teamsCol   = $db->selectCollection("teams");
$reportsCol = $db->selectCollection("scout_reports");

$playerId = trim($_GET['playerId'] ?? '');
if ($playerId === '' || !preg_match('/^[a-f0-9]{24}$/i', $playerId)) {
    http_response_code(400);
    exit("playerId invalide");
}

$player = $playersCol->findOne(['_id' => new ObjectId($playerId)]);
if (!$player) {
    http_response_code(404);
    exit("Joueur introuvable");
}

$team = null;
if (!empty($player['teamId'])) {
    $team = $teamsCol->findOne(['_id' => $player['teamId']]);
}

$ovr = (int)($player['overall'] ?? 0);
$pac = (int)($player['pac'] ?? 0);
$sho = (int)($player['sho'] ?? 0);
$pas = (int)($player['pas'] ?? 0);
$dri = (int)($player['dri'] ?? 0);
$def = (int)($player['def'] ?? 0);
$phy = (int)($player['phy'] ?? 0);

function pickOne(array $arr): string { return $arr[array_rand($arr)]; }

$strengthPool = [];
$weakPool = [];

if ($pac >= 85) $strengthPool[] = "Vitesse";
if ($dri >= 85) $strengthPool[] = "Dribbles";
if ($pas >= 85) $strengthPool[] = "Passes";
if ($sho >= 85) $strengthPool[] = "Finition";
if ($def >= 85) $strengthPool[] = "Défense";
if ($phy >= 85) $strengthPool[] = "Physique";
if ($ovr >= 88) $strengthPool[] = "Impact global";

if ($pac > 0 && $pac <= 65) $weakPool[] = "Vitesse";
if ($dri > 0 && $dri <= 65) $weakPool[] = "Dribbles";
if ($pas > 0 && $pas <= 65) $weakPool[] = "Passes";
if ($sho > 0 && $sho <= 65) $weakPool[] = "Finition";
if ($def > 0 && $def <= 65) $weakPool[] = "Défense";
if ($phy > 0 && $phy <= 65) $weakPool[] = "Physique";

if (!$strengthPool) $strengthPool = ["Polyvalence"];
if (!$weakPool) $weakPool = ["À travailler"];

$defaultStrengths = [pickOne($strengthPool)];
$defaultWeaknesses = [pickOne($weakPool)];

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
        $reportsCol->insertOne([
            'playerId' => $player['_id'],
            'rating' => $rating,
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'notes' => $notes,
            'createdAt' => new UTCDateTime(),
        ]);

        $teamId = (string)($player['teamId'] ?? '');
        if ($teamId !== '') {
            header("Location: /index.php?page=team&id=" . urlencode($teamId));
            exit;
        }
        header("Location: /index.php?page=players");
        exit;
    }
}

$playerName = $player['playerName'] ?? ($player['name'] ?? 'Joueur');
$teamName   = $team['name'] ?? ($player['team'] ?? '');
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Créer un rapport scout</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="wrap">

  <h1>Rapport scout</h1>

  <div style="display:flex; gap:10px; flex-wrap:wrap; margin:10px 0 14px;">
    <a class="pill" href="/index.php">← Retour</a>
    <a class="pill btn-soft" href="/scout_reports.php">Voir tous les rapports</a>
  </div>

  <p class="muted">
    Joueur : <strong><?= h($playerName) ?></strong>
    <?php if ($teamName): ?> — Équipe : <strong><?= h($teamName) ?></strong><?php endif; ?>
  </p>

  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <div class="panel">
    <form method="post" class="form-grid">

      <div class="field">
        <label>Note (/10)</label>
        <input name="rating" type="number" min="1" max="10" value="<?= h((string)($_POST['rating'] ?? 7)) ?>" required>
      </div>

      <div class="field">
        <label>—</label>
        <div class="muted" style="padding:12px 0;">Remplis forces/faiblesses + commentaire.</div>
      </div>

      <div class="field">
        <label>Forces (1)</label>
        <?php
          $s = $_POST['strengths'] ?? $defaultStrengths;
          if (!is_array($s)) $s = $defaultStrengths;
          $s = array_pad($s, 2, '');
        ?>
        <input name="strengths[]" value="<?= h($s[0]) ?>" placeholder="ex: Vitesse">
      </div>

      <div class="field">
        <label>Forces (2)</label>
        <input name="strengths[]" value="<?= h($s[1]) ?>" placeholder="ex: Passes">
      </div>

      <div class="field">
        <label>Faiblesses (1)</label>
        <?php
          $w = $_POST['weaknesses'] ?? $defaultWeaknesses;
          if (!is_array($w)) $w = $defaultWeaknesses;
          $w = array_pad($w, 2, '');
        ?>
        <input name="weaknesses[]" value="<?= h($w[0]) ?>" placeholder="ex: Défense">
      </div>

      <div class="field">
        <label>Faiblesses (2)</label>
        <input name="weaknesses[]" value="<?= h($w[1]) ?>" placeholder="ex: Physique">
      </div>

      <div class="field full">
        <label>Commentaire</label>
        <textarea name="notes" rows="5" placeholder="Résumé du profil, potentiel, points à travailler..."><?= h((string)($_POST['notes'] ?? '')) ?></textarea>
      </div>

      <div class="form-actions full">
        <button class="btn" type="submit">Enregistrer</button>
      </div>

    </form>
  </div>

</div>
</body>
</html>
