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

$id = trim($_GET['id'] ?? '');
if ($id === '' || !preg_match('/^[a-f0-9]{24}$/i', $id)) {
    http_response_code(400);
    exit("ID invalide");
}

$player = $playersCol->findOne(['_id' => new ObjectId($id)]);
if (!$player) {
    http_response_code(404);
    exit("Joueur introuvable");
}

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
            $playersCol->updateOne(
                ['_id' => $player['_id']],
                ['$set' => [
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
                    'updatedAt' => new UTCDateTime(),
                ]]
            );

            header("Location: /index.php?page=players");
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Modifier joueur</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="wrap">

  <h1>Modifier joueur</h1>

  <div style="display:flex; gap:10px; flex-wrap:wrap; margin:10px 0 14px;">
    <a class="pill" href="/index.php?page=players">← Retour</a>
  </div>

  <?php if ($error): ?>
    <div class="err"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="panel">
    <form method="post" class="form-grid">

      <div class="field full">
        <label>Équipe</label>
        <select name="teamId" required>
          <option value="">-- choisir --</option>
          <?php foreach ($teams as $t): ?>
            <?php $selected = ((string)($player['teamId'] ?? '')) === ((string)$t['_id']); ?>
            <option value="<?= h((string)$t['_id']) ?>" <?= $selected ? 'selected' : '' ?>>
              <?= h($t['name'] ?? '') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field full">
        <label>Nom joueur</label>
        <input name="player_name" value="<?= h($player['playerName'] ?? '') ?>" required>
      </div>

      <div class="field full">
        <label>Postes</label>
        <input name="positions" value="<?= h($player['positions'] ?? '') ?>" placeholder="ex: ST, LW">
      </div>

      <div class="field">
        <label>OVR</label>
        <input name="overall" type="number" min="0" value="<?= h((int)($player['overall'] ?? 0)) ?>" required>
      </div>

      <div class="field">
        <label>Âge</label>
        <input name="age" type="number" min="0" value="<?= h((int)($player['age'] ?? 0)) ?>">
      </div>

      <div class="field"><label>PAC</label><input name="pac" type="number" min="0" value="<?= h((int)($player['pac'] ?? 0)) ?>"></div>
      <div class="field"><label>SHO</label><input name="sho" type="number" min="0" value="<?= h((int)($player['sho'] ?? 0)) ?>"></div>
      <div class="field"><label>PAS</label><input name="pas" type="number" min="0" value="<?= h((int)($player['pas'] ?? 0)) ?>"></div>
      <div class="field"><label>DRI</label><input name="dri" type="number" min="0" value="<?= h((int)($player['dri'] ?? 0)) ?>"></div>
      <div class="field"><label>DEF</label><input name="def" type="number" min="0" value="<?= h((int)($player['def'] ?? 0)) ?>"></div>
      <div class="field"><label>PHY</label><input name="phy" type="number" min="0" value="<?= h((int)($player['phy'] ?? 0)) ?>"></div>

      <div class="field">
        <label>Taille (cm)</label>
        <input name="height_cm" type="number" min="0" value="<?= h((int)($player['heightCm'] ?? 0)) ?>">
      </div>

      <div class="field">
        <label>Pied</label>
        <input name="preferred_foot" value="<?= h($player['preferredFoot'] ?? '') ?>" placeholder="Right / Left">
      </div>

      <div class="field">
        <label>Début contrat</label>
        <input type="date" name="contract_start" value="<?= h(msToYmd($player['contractStart'] ?? null)) ?>" required>
      </div>

      <div class="field">
        <label>Fin contrat</label>
        <input type="date" name="contract_end" value="<?= h(msToYmd($player['contractEnd'] ?? null)) ?>" required>
      </div>

      <div class="field full">
        <label>Valeur marché</label>
        <input name="market_value" value="<?= h((int)($player['marketValue'] ?? 0)) ?>" placeholder="ex: 185000000">
      </div>

      <div class="form-actions full">
        <button class="btn" type="submit">Enregistrer</button>
        <a class="pill btn-soft" href="/index.php?page=players">Annuler</a>
      </div>
    </form>
  </div>

</div>
</body>
</html>
