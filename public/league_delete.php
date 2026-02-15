<?php
declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\ObjectId;

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$db = ConnexionMongo::db();
$leaguesCol = $db->selectCollection("leagues");

$id = trim((string)($_GET['id'] ?? ''));
if ($id === '' || !preg_match('/^[a-f0-9]{24}$/i', $id)) {
    http_response_code(400);
    exit("ID invalide");
}

$league = $leaguesCol->findOne(['_id' => new ObjectId($id)]);
if (!$league) {
    http_response_code(404);
    exit("Ligue introuvable");
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $name = trim((string)($_POST['name'] ?? ''));
    $country = trim((string)($_POST['country'] ?? ''));
    $level = (int)($_POST['level'] ?? 1);

    if ($code === '' || $name === '') {
        $error = "Code et nom requis.";
    } elseif (!preg_match('/^[A-Z0-9]{3,10}$/', $code)) {
        $error = "Code invalide (A-Z / 0-9, 3 à 10 caractères).";
    } else {
        try {
            // Si on change le code, il doit rester unique : on teste
            $exists = $leaguesCol->findOne(['code' => $code, '_id' => ['$ne' => $league['_id']]]);
            if ($exists) {
                $error = "Ce code est déjà utilisé.";
            } else {
                $leaguesCol->updateOne(
                    ['_id' => $league['_id']],
                    ['$set' => [
                        'code' => $code,
                        'name' => $name,
                        'country' => $country,
                        'level' => $level,
                    ]]
                );
                header("Location: /index.php?page=leagues");
                exit;
            }
        } catch (Throwable $e) {
            $error = "Impossible de modifier la ligue.";
        }
    }
}

$codeV = (string)($league['code'] ?? '');
$nameV = (string)($league['name'] ?? '');
$countryV = (string)($league['country'] ?? '');
$levelV = (string)($league['level'] ?? 1);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Modifier ligue</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="wrap">

  <h1>Modifier ligue</h1>

  <div class="nav">
    <a href="/index.php?page=leagues">← Retour</a>
    <a class="active" href="#">Modifier</a>
  </div>

  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <form class="card" method="post">
    <div class="row">
      <div>
        <label>Code</label>
        <input name="code" value="<?= h((string)($_POST['code'] ?? $codeV)) ?>" required>
      </div>
      <div>
        <label>Nom</label>
        <input name="name" value="<?= h((string)($_POST['name'] ?? $nameV)) ?>" required>
      </div>
    </div>

    <div style="height:10px"></div>

    <div class="row">
      <div>
        <label>Pays</label>
        <input name="country" value="<?= h((string)($_POST['country'] ?? $countryV)) ?>">
      </div>
      <div>
        <label>Niveau</label>
        <input name="level" type="number" min="1" max="10" value="<?= h((string)($_POST['level'] ?? $levelV)) ?>">
      </div>
    </div>

    <div style="height:14px"></div>

    <button type="submit">Enregistrer</button>
    <a class="pill" href="/index.php?page=leagues" style="margin-left:10px;">Annuler</a>
  </form>

</div>
</body>
</html>
