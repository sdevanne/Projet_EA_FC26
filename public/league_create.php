<?php
declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\UTCDateTime;

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$db = ConnexionMongo::db();
$leaguesCol = $db->selectCollection("leagues");

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code    = strtoupper(trim((string)($_POST['code'] ?? '')));
    $name    = trim((string)($_POST['name'] ?? ''));
    $country = trim((string)($_POST['country'] ?? ''));
    $level   = (int)($_POST['level'] ?? 1);

    if ($code === '' || $name === '') {
        $error = "Code et nom requis.";
    } elseif (!preg_match('/^[A-Z0-9]{3,6}$/', $code)) {
        $error = "Code invalide (3 à 6 caractères A-Z / 0-9).";
    } elseif ($level < 1) {
        $error = "Niveau invalide.";
    } else {
        try {
            $leaguesCol->insertOne([
                'code' => $code,
                'name' => $name,
                'country' => $country,
                'level' => $level,
                'createdAt' => new UTCDateTime(),
            ]);
            header("Location: /index.php?page=leagues");
            exit;
        } catch (Throwable $e) {
            // index unique sur code => duplication
            $error = "Impossible d'ajouter : code déjà utilisé ?";
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Créer une ligue</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="wrap">

  <h1>Créer une ligue</h1>

  <div class="nav">
    <a class="pill" href="/index.php?page=leagues">← Retour</a>
  </div>

  <?php if ($error): ?>
    <div class="err"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="post" class="card">
    <div class="grid-2">
      <div>
        <label>Code</label>
        <input name="code" placeholder="ex: ANG1" value="<?= h((string)($_POST['code'] ?? '')) ?>" required>
      </div>

      <div>
        <label>Nom</label>
        <input name="name" placeholder="ex: Premier League" value="<?= h((string)($_POST['name'] ?? '')) ?>" required>
      </div>

      <div>
        <label>Pays</label>
        <input name="country" placeholder="ex: Angleterre" value="<?= h((string)($_POST['country'] ?? '')) ?>">
      </div>

      <div>
        <label>Niveau</label>
        <input name="level" type="number" min="1" value="<?= h((string)($_POST['level'] ?? '1')) ?>">
      </div>
    </div>

    <div class="actions">
      <button type="submit">Créer</button>
      <a class="pill" href="/index.php?page=leagues">Annuler</a>
    </div>
  </form>

</div>
</body>
</html>
