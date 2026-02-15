<?php
declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function oid(?string $id): ?ObjectId {
    $id = trim((string)$id);
    if ($id === '' || !preg_match('/^[a-f0-9]{24}$/i', $id)) return null;
    try { return new ObjectId($id); } catch (Throwable $e) { return null; }
}

$db = ConnexionMongo::db();
$leaguesCol = $db->selectCollection("leagues");

$id = $_GET['id'] ?? '';
$oid = oid($id);
if (!$oid) {
    http_response_code(400);
    exit("ID invalide");
}

$league = $leaguesCol->findOne(['_id' => $oid]);
if (!$league) {
    http_response_code(404);
    exit("Ligue introuvable");
}

$error = null;

// Valeurs par défaut (préremplies)
$code    = (string)($league['code'] ?? '');
$name    = (string)($league['name'] ?? '');
$country = (string)($league['country'] ?? '');
$level   = (int)($league['level'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codeNew    = strtoupper(trim((string)($_POST['code'] ?? '')));
    $nameNew    = trim((string)($_POST['name'] ?? ''));
    $countryNew = trim((string)($_POST['country'] ?? ''));
    $levelNew   = (int)($_POST['level'] ?? 1);

    // on ré-affiche le formulaire avec les valeurs saisies si erreur
    $code = $codeNew; $name = $nameNew; $country = $countryNew; $level = $levelNew;

    if ($codeNew === '' || $nameNew === '') {
        $error = "Code et nom requis.";
    } elseif (!preg_match('/^[A-Z0-9]{3,6}$/', $codeNew)) {
        $error = "Code invalide (3 à 6 caractères A-Z / 0-9).";
    } elseif ($levelNew < 1) {
        $error = "Niveau invalide.";
    } else {
        try {
            // si tu changes le code, vérifier qu'il n'existe pas déjà
            if ($codeNew !== (string)($league['code'] ?? '')) {
                $exists = $leaguesCol->findOne(['code' => $codeNew], ['projection' => ['_id' => 1]]);
                if ($exists) {
                    $error = "Ce code de ligue existe déjà.";
                }
            }

            if (!$error) {
                $leaguesCol->updateOne(
                    ['_id' => $league['_id']],
                    ['$set' => [
                        'code' => $codeNew,
                        'name' => $nameNew,
                        'country' => $countryNew,
                        'level' => $levelNew,
                        'updatedAt' => new UTCDateTime(),
                    ]]
                );

                header("Location: /index.php?page=leagues");
                exit;
            }
        } catch (Throwable $e) {
            $error = "Erreur lors de la modification.";
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Modifier une ligue</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="wrap">

  <h1>Modifier une ligue</h1>

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
        <input name="code" placeholder="ex: ANG1" value="<?= h($code) ?>" required>
      </div>

      <div>
        <label>Nom</label>
        <input name="name" placeholder="ex: Premier League" value="<?= h($name) ?>" required>
      </div>

      <div>
        <label>Pays</label>
        <input name="country" placeholder="ex: Angleterre" value="<?= h($country) ?>">
      </div>

      <div>
        <label>Niveau</label>
        <input name="level" type="number" min="1" value="<?= h((string)$level) ?>">
      </div>
    </div>

    <div class="actions">
      <button type="submit">Enregistrer</button>
      <a class="pill" href="/index.php?page=leagues">Annuler</a>
    </div>
  </form>

</div>
</body>
</html>
