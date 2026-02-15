<?php
declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function slugify(string $text): string {
    $text = trim(mb_strtolower($text, 'UTF-8'));
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim((string)$text, '-');
    return $text === '' ? 'n-a' : $text;
}

$db = ConnexionMongo::db();
$leaguesCol = $db->selectCollection("leagues");
$teamsCol   = $db->selectCollection("teams");

$leagues = $leaguesCol->find([], ['sort' => ['code' => 1]])->toArray();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leagueIdStr = trim((string)($_POST['leagueId'] ?? ''));
    $league = null;

    if ($leagueIdStr !== '' && preg_match('/^[a-f0-9]{24}$/i', $leagueIdStr)) {
        $league = $leaguesCol->findOne(['_id' => new ObjectId($leagueIdStr)]);
    }

    $name = trim((string)($_POST['name'] ?? ''));

    $rating = (int)($_POST['rating'] ?? 0);
    $att = (int)($_POST['att'] ?? 0);
    $mid = (int)($_POST['mid'] ?? 0);
    $def = (int)($_POST['def'] ?? 0);
    $budget = (int)($_POST['budget'] ?? 0);
    $avgAge = (float)($_POST['avgAge'] ?? 0);
    $youthDev = (int)($_POST['youthDev'] ?? 0);

    if (!$league) {
        $error = "Ligue invalide.";
    } elseif ($name === '') {
        $error = "Nom d'équipe requis.";
    } else {
        $slug = slugify($name);

        try {
            $teamsCol->insertOne([
                'name' => $name,
                'slug' => $slug,
                'leagueId' => $league['_id'],

                'rating' => $rating,
                'att' => $att,
                'mid' => $mid,
                'def' => $def,
                'budget' => $budget,
                'avgAge' => $avgAge,
                'youthDev' => $youthDev,

                'createdAt' => new UTCDateTime(),
            ]);

            header("Location: /index.php?page=teams");
            exit;
        } catch (Throwable $e) {
            $error = "Impossible de créer l'équipe (doublon dans la ligue ?)";
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Créer une équipe</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="wrap">

  <h1>Créer une équipe</h1>

  <div class="nav">
    <a href="/index.php?page=teams">← Retour</a>
    <a class="active" href="/team_create.php">+ Ajouter team</a>
  </div>

  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <form class="card" method="post">
    <div class="row">
      <div>
        <label>Ligue</label>
        <select name="leagueId" required>
          <option value="">-- choisir --</option>
          <?php foreach ($leagues as $lg): ?>
            <?php $val = (string)$lg['_id']; $label = (($lg['code'] ?? '') . ' - ' . ($lg['name'] ?? '')); ?>
            <option value="<?= h($val) ?>" <?= ((string)($_POST['leagueId'] ?? '')) === $val ? 'selected' : '' ?>>
              <?= h($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>Nom équipe</label>
        <input name="name" value="<?= h((string)($_POST['name'] ?? '')) ?>" required>
      </div>
    </div>

    <div style="height:10px"></div>

    <div class="row3">
      <div><label>OVR</label><input name="rating" type="number" min="0" value="<?= h((string)($_POST['rating'] ?? '0')) ?>"></div>
      <div><label>ATT</label><input name="att" type="number" min="0" value="<?= h((string)($_POST['att'] ?? '0')) ?>"></div>
      <div><label>MID</label><input name="mid" type="number" min="0" value="<?= h((string)($_POST['mid'] ?? '0')) ?>"></div>
      <div><label>DEF</label><input name="def" type="number" min="0" value="<?= h((string)($_POST['def'] ?? '0')) ?>"></div>
      <div><label>Budget</label><input name="budget" type="number" min="0" value="<?= h((string)($_POST['budget'] ?? '0')) ?>"></div>
      <div><label>Âge moyen</label><input name="avgAge" type="number" step="0.1" min="0" value="<?= h((string)($_POST['avgAge'] ?? '0')) ?>"></div>
    </div>

    <div style="height:10px"></div>

    <div class="row">
      <div>
        <label>Youth Dev</label>
        <input name="youthDev" type="number" min="0" value="<?= h((string)($_POST['youthDev'] ?? '0')) ?>">
      </div>
      <div>
        <label>Info</label>
        <input value="Champs optionnels (sauf ligue + nom)" disabled style="opacity:.55">
      </div>
    </div>

    <div style="height:14px"></div>

    <button type="submit">Créer</button>
    <a class="pill" href="/index.php?page=teams" style="margin-left:10px;">Annuler</a>
  </form>

</div>
</body>
</html>
