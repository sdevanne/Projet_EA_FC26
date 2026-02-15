<?php
declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function msToYmdLocal($v): string {
    if ($v instanceof UTCDateTime) {
        return $v->toDateTime()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    }
    if ($v === null || $v === '') return '';
    if (is_numeric($v)) return gmdate('Y-m-d', ((int)$v) / 1000);
    return substr((string)$v, 0, 10);
}

function oid(?string $id): ?ObjectId {
    $id = trim((string)$id);
    if ($id === '' || !preg_match('/^[a-f0-9]{24}$/i', $id)) return null;
    try { return new ObjectId($id); } catch (\Throwable $e) { return null; }
}

$db = ConnexionMongo::db();
$reportsCol = $db->selectCollection("scout_reports");
$playersCol = $db->selectCollection("players");
$teamsCol   = $db->selectCollection("teams");

$q = trim((string)($_GET['q'] ?? ''));
$p = max(1, (int)($_GET['p'] ?? 1));

$limit = 25;
$skip = ($p - 1) * $limit;

$filter = [];
if ($q !== '') {
    // on cherche d'abord les players correspondants puis on filtre les reports
    $playerIds = [];
    foreach ($playersCol->find(['playerName' => ['$regex' => $q, '$options' => 'i']], ['projection' => ['_id' => 1]]) as $pl) {
        $playerIds[] = $pl['_id'];
        if (count($playerIds) >= 5000) break;
    }
    if (!$playerIds) {
        $total = 0;
        $totalPages = 1;
        $rows = [];
        goto render;
    }
    $filter['playerId'] = ['$in' => $playerIds];
}

$total = $reportsCol->countDocuments($filter);
$totalPages = max(1, (int)ceil($total / $limit));
if ($p > $totalPages) { $p = $totalPages; $skip = ($p - 1) * $limit; }

$rows = $reportsCol->find($filter, [
    'sort' => ['createdAt' => -1, '_id' => -1],
    'skip' => $skip,
    'limit' => $limit,
])->toArray();

render:

// hydrate players + teams
$playerMap = [];
$teamMap = [];

$playerIds = [];
foreach ($rows as $r) {
    if (!empty($r['playerId'])) $playerIds[] = $r['playerId'];
}

if ($playerIds) {
    foreach ($playersCol->find(['_id' => ['$in' => $playerIds]], ['projection' => ['playerName'=>1,'teamId'=>1,'team'=>1]]) as $pl) {
        $playerMap[(string)$pl['_id']] = $pl;
        if (!empty($pl['teamId'])) $teamMap[(string)$pl['teamId']] = null;
    }
}

$teamIds = [];
foreach (array_keys($teamMap) as $tid) $teamIds[] = new ObjectId($tid);
if ($teamIds) {
    foreach ($teamsCol->find(['_id' => ['$in' => $teamIds]], ['projection' => ['name'=>1]]) as $t) {
        $teamMap[(string)$t['_id']] = $t['name'] ?? '';
    }
}

?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Scout Reports</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="wrap">

<div class="topbar">
  <h1>Scout Reports</h1>
</div>

<div class="backrow">
  <a class="pill" href="/index.php">← Retour</a>
</div>

<div class="panel">
  <form class="filter" method="get">
    <div style="min-width:260px">
      <label>Recherche joueur</label>
      <input name="q" value="<?= h($q) ?>" placeholder="Nom du joueur">
    </div>

    <div>
      <button type="submit">Rechercher</button>
      <a class="pill" href="/scout_reports.php" style="margin-left:8px;">Reset</a>
    </div>
  </form>

  <div style="margin-top:10px" class="muted">
    Résultats : <?= (int)$total ?> — Page <?= (int)$p ?> / <?= (int)$totalPages ?>
  </div>
</div>

<div style="margin-top:14px;">
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Joueur</th>
        <th>Équipe</th>
        <th>Note</th>
        <th>Forces</th>
        <th>Faiblesses</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          $rid = (string)($r['_id'] ?? '');
          $pid = (string)($r['playerId'] ?? '');
          $pl  = $playerMap[$pid] ?? null;

          $playerName = $pl['playerName'] ?? '—';
          $teamName = '—';
          if ($pl) {
              if (!empty($pl['teamId'])) $teamName = $teamMap[(string)$pl['teamId']] ?? ($pl['team'] ?? '—');
              else $teamName = $pl['team'] ?? '—';
          }

          $date = msToYmdLocal($r['createdAt'] ?? null);
          $rating = (int)($r['rating'] ?? 0);
          $strengths = (array)($r['strengths'] ?? []);
          $weaknesses = (array)($r['weaknesses'] ?? []);
        ?>
        <tr>
          <td><?= h($date) ?></td>
          <td><?= h($playerName) ?></td>
          <td><?= h($teamName) ?></td>
          <td><?= h($rating) ?>/10</td>
          <td><?= h(implode(', ', $strengths)) ?></td>
          <td><?= h(implode(', ', $weaknesses)) ?></td>
          <td>
            <div class="actions">
              <a class="pill" href="/scout_report_edit.php?id=<?= h($rid) ?>">Modifier</a>
              <form class="inline" method="post" action="/scout_report_delete.php" onsubmit="return confirm('Supprimer ce rapport ?');">
                <input type="hidden" name="id" value="<?= h($rid) ?>">
                <button type="submit" class="pill btn-danger">Supprimer</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!count($rows)): ?>
        <tr><td colspan="7" class="muted">Aucun rapport.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
  <div class="backrow" style="margin-top:12px;">
    <?php
      $paramsPrev = $_GET; $paramsNext = $_GET;
    ?>
    <?php if ($p > 1): $paramsPrev['p'] = $p - 1; ?>
      <a class="pill" href="/scout_reports.php?<?= h(http_build_query($paramsPrev)) ?>">← Précédent</a>
    <?php endif; ?>
    <?php if ($p < $totalPages): $paramsNext['p'] = $p + 1; ?>
      <a class="pill" href="/scout_reports.php?<?= h(http_build_query($paramsNext)) ?>">Suivant →</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

</div>
</body>
</html>
