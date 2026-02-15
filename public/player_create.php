<?php
declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../src/Helpers.php";

use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

$db = ConnexionMongo::db();
$teamsCol   = $db->selectCollection("teams");
$playersCol = $db->selectCollection("players");

$teams = $teamsCol->find([], ['sort' => ['leagueId' => 1, 'name' => 1]])->toArray();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teamId = trim($_POST['teamId'] ?? '');
    $team = null;

    if ($teamId !== '' && preg_match('/^[a-f0-9]{24}$/i', $teamId)) {
        $team = $teamsCol->findOne(['_id' => new ObjectId($teamId)]);
    }

    if (!$team) {
        $error = "Équipe invalide.";
    } else {
        $playerName = trim($_POST['player_name'] ?? '');
        $positions  = trim($_POST['positions'] ?? '');

        $overall  = (int)($_POST['overall'] ?? 0);
        $age      = (int)($_POST['age'] ?? 0);
        $pac      = (int)($_POST['pac'] ?? 0);
        $sho      = (int)($_POST['sho'] ?? 0);
        $pas      = (int)($_POST['pas'] ?? 0);
        $dri      = (int)($_POST['dri'] ?? 0);
        $def      = (int)($_POST['def'] ?? 0);
        $phy      = (int)($_POST['phy'] ?? 0);
        $heightCm = (int)($_POST['height_cm'] ?? 0);

        $foot    = trim($_POST['preferred_foot'] ?? '');
        $startMs = ymdToMs($_POST['contract_start'] ?? '');
        $endMs   = ymdToMs($_POST['contract_end'] ?? '');

        $value = moneyToInt($_POST['market_value'] ?? '0');

        if ($playerName === '' || $overall <= 0) {
            $error = "Nom joueur et OVR requis.";
        } elseif ($startMs === null || $endMs === null) {
            $error = "Dates invalides (YYYY-MM-DD).";
        } elseif ($endMs < $startMs) {
            $error = "Fin de contrat < début.";
        } else {
            $doc = [
                'teamId' => $team['_id'],
                'team'   => $team['name'] ?? '',
                'leagueId' => $team['leagueId'] ?? null,

                'playerName' => $playerName,
                'positions'  => $positions,

                'overall' => $overall,
                'age'     => $age,
                'pac'     => $pac,
                'sho'     => $sho,
                'pas'     => $pas,
                'dri'     => $dri,
                'def'     => $def,
                'phy'     => $phy,

                'heightCm' => $heightCm,
                'preferredFoot' => $foot,

                'contractStart' => $startMs,
                'contractEnd'   => $endMs,

                'marketValue' => $value,
                'slug' => makeSlug($playerName, $overall),

                'createdAt' => new UTCDateTime(),
                'updatedAt' => new UTCDateTime(),
            ];

            $playersCol->insertOne($doc);
            header("Location: /index.php");
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Créer un joueur</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="wrap">

  <h1>Créer un joueur</h1>

  <div class="nav">
    <a href="/index.php">← Retour</a>
    <a class="active" href="/player_create.php">+ Ajouter joueur</a>
  </div>

  <?php if ($error): ?>
    <div class="err"><?= h($error) ?></div>
  <?php endif; ?>

  <form class="card" method="post">
    <div class="row">
      <div>
        <label>Équipe</label>
        <select name="teamId" required>
          <option value="">-- choisir --</option>
          <?php foreach ($teams as $t): ?>
            <option value="<?= h((string)$t['_id']) ?>"><?= h($t['name'] ?? '') ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>Nom joueur</label>
        <input name="player_name" required>
      </div>
    </div>

    <div style="height:10px"></div>

    <div class="row">
      <div>
        <label>Postes</label>
        <input name="positions" placeholder="ex: ST RW">
      </div>

      <div class="row">
        <div>
          <label>OVR</label>
          <input name="overall" type="number" min="0" required>
        </div>
        <div>
          <label>Âge</label>
          <input name="age" type="number" min="0">
        </div>
      </div>
    </div>

    <div style="height:10px"></div>

    <div class="row3">
      <div><label>PAC</label><input name="pac" type="number" min="0"></div>
      <div><label>SHO</label><input name="sho" type="number" min="0"></div>
      <div><label>PAS</label><input name="pas" type="number" min="0"></div>
      <div><label>DRI</label><input name="dri" type="number" min="0"></div>
      <div><label>DEF</label><input name="def" type="number" min="0"></div>
      <div><label>PHY</label><input name="phy" type="number" min="0"></div>
    </div>

    <div style="height:10px"></div>

    <div class="row">
      <div>
        <label>Taille (cm)</label>
        <input name="height_cm" type="number" min="0">
      </div>
      <div>
        <label>Pied</label>
        <input name="preferred_foot" placeholder="Left/Right">
      </div>
    </div>

    <div style="height:10px"></div>

    <label>Contrat</label>
    <div class="row">
      <div><input type="date" name="contract_start" required></div>
      <div><input type="date" name="contract_end" required></div>
    </div>

    <div style="height:10px"></div>

    <label>Valeur marché</label>
    <input name="market_value" placeholder="ex: 175500000">

    <div style="height:14px"></div>

    <button type="submit">Créer</button>
    <a class="pill" href="/index.php" style="margin-left:10px;">Annuler</a>
  </form>

</div>
</body>
</html>
