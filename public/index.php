<?php
declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function getAny($doc, array $keys, $default = '') {
    foreach ($keys as $k) {
        if (isset($doc[$k]) && $doc[$k] !== null && $doc[$k] !== '') return $doc[$k];
    }
    return $default;
}

function msToYmdLocal($v): string {
    if ($v instanceof UTCDateTime) {
        return $v->toDateTime()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    }
    if ($v === null || $v === '') return '';
    if (is_numeric($v)) return gmdate('Y-m-d', ((int)$v) / 1000);
    return substr((string)$v, 0, 10);
}

function parseOverall($v): int {
    $s = trim((string)$v);
    if ($s === '') return 0;
    if (preg_match('/^(\d{1,2})/', $s, $m)) return (int)$m[1];
    return (int)$s;
}

function formatMoneyShort($n): string {
    $n = (int)$n;
    if ($n >= 1000000000) return round($n/1000000000, 1) . "B";
    if ($n >= 1000000) return round($n/1000000, 1) . "M";
    if ($n >= 1000) return round($n/1000, 1) . "K";
    return (string)$n;
}

function loadLatestReportsByPlayer($reportsCol, array $playerIds): array {
    $out = [];
    if (!count($playerIds)) return $out;

    foreach ($reportsCol->find(
        ['playerId' => ['$in' => $playerIds]],
        ['sort' => ['createdAt' => -1]]
    ) as $r) {
        $pid = (string)$r['playerId'];
        if (!isset($out[$pid])) $out[$pid] = $r;
    }
    return $out;
}

function oid(?string $id): ?ObjectId {
    $id = trim((string)$id);
    if ($id === '' || !preg_match('/^[a-f0-9]{24}$/i', $id)) return null;
    try { return new ObjectId($id); } catch (\Throwable $e) { return null; }
}

$db = ConnexionMongo::db();
$leaguesCol = $db->selectCollection("leagues");
$teamsCol   = $db->selectCollection("teams");
$playersCol = $db->selectCollection("players");
$reportsCol = $db->selectCollection("scout_reports");

$page = (string)($_GET['page'] ?? 'home'); // home | leagues | league | teams | team | players
$id   = $_GET['id'] ?? null;

// counts
$counts = [
  'leagues' => $leaguesCol->countDocuments(),
  'teams'   => $teamsCol->countDocuments(),
  'players' => $playersCol->countDocuments(),
  'reports' => count($reportsCol->distinct('playerId')),
];

// Map leagues (_id => label)
$leagueMap = [];
foreach ($leaguesCol->find([], ['projection' => ['code' => 1, 'name' => 1]]) as $lg) {
    $label = ($lg['code'] ?? '') ?: ($lg['name'] ?? '');
    $leagueMap[(string)$lg['_id']] = $label;
}

// Lists for filters
$leaguesList = $leaguesCol->find([], ['sort' => ['code' => 1]])->toArray();

// basic holders
$leagues = [];
$teams = [];
$players = [];
$latestPlayers = [];
$leagueDoc = null;
$teamDoc = null;
$reportsByPlayer = [];

/* ---------------- HOME ---------------- */
if ($page === 'home') {
    $latestPlayers = $playersCol->find([], ['sort' => ['_id' => -1], 'limit' => 10])->toArray();
}

/* ---------------- LEAGUES (with filter) ---------------- */
$ql = '';
$sortL = 'code_asc';
$totalLeagues = 0;

if ($page === 'leagues') {
    $ql = trim((string)($_GET['ql'] ?? ''));
    $sortL = (string)($_GET['sortL'] ?? 'code_asc');

    $filter = [];
    if ($ql !== '') {
        $filter['$or'] = [
            ['code'    => ['$regex' => $ql, '$options' => 'i']],
            ['name'    => ['$regex' => $ql, '$options' => 'i']],
            ['country' => ['$regex' => $ql, '$options' => 'i']],
        ];
    }

    $sortMapL = [
        'code_asc'  => ['code' => 1, '_id' => -1],
        'name_asc'  => ['name' => 1, '_id' => -1],
        'level_asc' => ['level' => 1, 'code' => 1, '_id' => -1],
    ];
    $sortMongoL = $sortMapL[$sortL] ?? $sortMapL['code_asc'];

    $totalLeagues = $leaguesCol->countDocuments($filter);
    $leagues = $leaguesCol->find($filter, ['sort' => $sortMongoL])->toArray();
}

/* ---------------- LEAGUE DETAIL ---------------- */
if ($page === 'league' && $id) {
    $oid = oid((string)$id);
    if ($oid) $leagueDoc = $leaguesCol->findOne(['_id' => $oid]);
    if ($leagueDoc) {
        $teams = $teamsCol->find(['leagueId' => $leagueDoc['_id']], ['sort' => ['name' => 1]])->toArray();
    }
}

/* ---------------- TEAMS (with filter) ---------------- */
$qt = '';
$teamLeagueId = '';
$sortT = 'name_asc';
$totalTeams = 0;

if ($page === 'teams') {
    $qt = trim((string)($_GET['qt'] ?? ''));
    $teamLeagueId = trim((string)($_GET['leagueId'] ?? ''));
    $sortT = (string)($_GET['sortT'] ?? 'name_asc');

    $filter = [];
    if ($qt !== '') $filter['name'] = ['$regex' => $qt, '$options' => 'i'];
    if ($teamLeagueId !== '' && preg_match('/^[a-f0-9]{24}$/i', $teamLeagueId)) {
        $filter['leagueId'] = new ObjectId($teamLeagueId);
    }

    $sortMapT = [
        'name_asc'    => ['name' => 1, '_id' => -1],
        'rating_desc' => ['rating' => -1, 'name' => 1, '_id' => -1],
        'budget_desc' => ['budget' => -1, 'name' => 1, '_id' => -1],
    ];
    $sortMongoT = $sortMapT[$sortT] ?? $sortMapT['name_asc'];

    $totalTeams = $teamsCol->countDocuments($filter);
    $teams = $teamsCol->find($filter, ['sort' => $sortMongoT])->toArray();
}

/* ---------------- TEAM DETAIL ---------------- */
if ($page === 'team' && $id) {
    $oid = oid((string)$id);
    if ($oid) $teamDoc = $teamsCol->findOne(['_id' => $oid]);

    if ($teamDoc) {
        $players = $playersCol->find(
            ['$or' => [
                ['teamId' => $teamDoc['_id']],
                ['team' => ($teamDoc['name'] ?? null)],
                ['team_name' => ($teamDoc['name'] ?? null)],
                ['teamName' => ($teamDoc['name'] ?? null)],
            ]],
            ['sort' => ['overall' => -1, '_id' => -1], 'limit' => 200]
        )->toArray();

        $playerIds = array_map(fn($pl) => $pl['_id'], $players);
        $reportsByPlayer = loadLatestReportsByPlayer($reportsCol, $playerIds);
    }
}

/* ---------------- PLAYERS (filter + pagination) ---------------- */
$q = '';
$ovrMin = '';
$ovrMax = '';
$sort = 'overall_desc';
$p = 1;
$total = 0;
$totalPages = 1;

$teamsList = $teamsCol->find([], ['sort' => ['name' => 1], 'projection' => ['name' => 1]])->toArray();

if ($page === 'players') {
    $q = trim((string)($_GET['q'] ?? ''));
    $leagueId = trim((string)($_GET['leagueId'] ?? ''));
    $teamId = trim((string)($_GET['teamId'] ?? ''));

    $ovrMin = trim((string)($_GET['ovrMin'] ?? ''));
    $ovrMax = trim((string)($_GET['ovrMax'] ?? ''));
    $sort = (string)($_GET['sort'] ?? 'overall_desc');
    $p = max(1, (int)($_GET['p'] ?? 1));

    $limit = 50;
    $skip = ($p - 1) * $limit;

    $filter = [];

    if ($q !== '') $filter['playerName'] = ['$regex' => $q, '$options' => 'i'];
    if ($leagueId !== '' && preg_match('/^[a-f0-9]{24}$/i', $leagueId)) $filter['leagueId'] = new ObjectId($leagueId);
    if ($teamId !== '' && preg_match('/^[a-f0-9]{24}$/i', $teamId)) $filter['teamId'] = new ObjectId($teamId);

    $ovrCond = [];
    if ($ovrMin !== '' && is_numeric($ovrMin)) $ovrCond['$gte'] = (int)$ovrMin;
    if ($ovrMax !== '' && is_numeric($ovrMax)) $ovrCond['$lte'] = (int)$ovrMax;
    if ($ovrCond) $filter['overall'] = $ovrCond;

    $sortMap = [
        'overall_desc' => ['overall' => -1, '_id' => -1],
        'value_desc'   => ['marketValue' => -1, '_id' => -1],
        'age_asc'      => ['age' => 1, '_id' => -1],
        'name_asc'     => ['playerName' => 1, '_id' => -1],
    ];
    $sortMongo = $sortMap[$sort] ?? $sortMap['overall_desc'];

    $total = $playersCol->countDocuments($filter);
    $totalPages = max(1, (int)ceil($total / $limit));
    if ($p > $totalPages) { $p = $totalPages; $skip = ($p - 1) * $limit; }

    $players = $playersCol->find($filter, [
        'sort'  => $sortMongo,
        'skip'  => $skip,
        'limit' => $limit
    ])->toArray();

    $playerIds = array_map(fn($pl) => $pl['_id'], $players);
    $reportsByPlayer = loadLatestReportsByPlayer($reportsCol, $playerIds);
}

function cardHref(string $key): string {
    return match($key){
        'leagues' => '/index.php?page=leagues',
        'teams'   => '/index.php?page=teams',
        'players' => '/index.php?page=players',
        'reports' => '/scout_reports.php',
        default   => '/index.php',
    };
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Projet FC26 (MongoDB)</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="wrap">

  <h1>Projet FC26 (MongoDB)</h1>

  <!-- Cards nav (toujours visible) -->
  <div class="cards">
    <a class="card <?= in_array($page,['leagues','league'],true)?'active':'' ?>" href="<?= h(cardHref('leagues')) ?>">
      <strong>Leagues</strong>
      <div class="big"><?= (int)$counts['leagues'] ?></div>
      <div class="sub">Voir toutes les ligues</div>
    </a>

    <a class="card <?= in_array($page,['teams','team'],true)?'active':'' ?>" href="<?= h(cardHref('teams')) ?>">
      <strong>Teams</strong>
      <div class="big"><?= (int)$counts['teams'] ?></div>
      <div class="sub">Voir tous les clubs</div>
    </a>

    <a class="card <?= $page==='players'?'active':'' ?>" href="<?= h(cardHref('players')) ?>">
      <strong>Players</strong>
      <div class="big"><?= (int)$counts['players'] ?></div>
      <div class="sub">Rechercher / filtrer</div>
    </a>

    <a class="card" href="<?= h(cardHref('reports')) ?>">
      <strong>Scout Reports</strong>
      <div class="big"><?= (int)$counts['reports'] ?></div>
      <div class="sub">Voir les rapports</div>
    </a>
  </div>

  <?php if ($page !== 'home'): ?>
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:8px 0 6px;">
      <a class="pill" href="/index.php">← Retour</a>
      <?php if ($page === 'leagues'): ?>
        <a class="btn" href="/league_create.php">+ Ajouter ligue</a>
      <?php elseif ($page === 'teams'): ?>
        <a class="btn" href="/team_create.php">+ Ajouter team</a>
      <?php elseif ($page === 'players'): ?>
        <a class="btn" href="/player_create.php">+ Ajouter joueur</a>
      <?php elseif ($page === 'league' && $leagueDoc): ?>
        <a class="btn" href="/team_create.php?leagueId=<?= h((string)$leagueDoc['_id']) ?>">+ Ajouter team</a>
      <?php elseif ($page === 'team' && $teamDoc): ?>
        <a class="btn" href="/player_create.php">+ Ajouter joueur</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($page === 'home'): ?>
    <h2>Derniers joueurs ajoutés</h2>
    <table>
      <thead><tr><th>Nom</th><th>Équipe</th><th>OVR</th><th>Contrat</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($latestPlayers as $pDoc): ?>
          <?php
            $name = getAny($pDoc, ['playerName','player_name','name','player','nom'], '');
            $team = getAny($pDoc, ['team','team_name','teamName','club','club_name'], '');
            $ovr  = parseOverall(getAny($pDoc, ['overall','ovr','OVR'], 0));
            $cs   = getAny($pDoc, ['contractStart','contract_start','start'], null);
            $ce   = getAny($pDoc, ['contractEnd','contract_end','end'], null);
            $pid  = (string)$pDoc['_id'];
          ?>
          <tr>
            <td><?= h($name) ?></td>
            <td><?= h($team) ?></td>
            <td><?= h($ovr) ?></td>
            <td><?= h(msToYmdLocal($cs)) ?> → <?= h(msToYmdLocal($ce)) ?></td>
            <td>
              <div class="actions">
                <a class="pill" href="/player_edit.php?id=<?= h($pid) ?>">Modifier</a>
                <form class="inline" method="post" action="/player_delete.php" onsubmit="return confirm('Supprimer ce joueur ?');">
                  <input type="hidden" name="id" value="<?= h($pid) ?>">
                  <button type="submit" class="pill btn-danger">Supprimer</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!count($latestPlayers)): ?>
          <tr><td colspan="5" class="muted">Aucun joueur en base.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($page === 'leagues'): ?>
    <h2>Leagues</h2>

    <div class="panel" style="margin:12px 0;">
      <form class="filter" method="get">
        <input type="hidden" name="page" value="leagues">

        <div class="field wide">
          <label>Recherche</label>
          <input name="ql" value="<?= h($ql) ?>" placeholder="Code, nom ou pays">
        </div>

        <div class="field">
          <label>Tri</label>
          <select name="sortL">
            <option value="code_asc"  <?= $sortL==='code_asc'?'selected':'' ?>>Code A→Z</option>
            <option value="name_asc"  <?= $sortL==='name_asc'?'selected':'' ?>>Nom A→Z</option>
            <option value="level_asc" <?= $sortL==='level_asc'?'selected':'' ?>>Niveau ↑</option>
          </select>
        </div>

        <div>
          <button type="submit">Filtrer</button>
          <a class="pill" href="/index.php?page=leagues" style="margin-left:8px;">Reset</a>
        </div>
      </form>
    </div>

    <p class="muted">Résultats : <?= (int)$totalLeagues ?></p>

    <table>
      <thead><tr><th>Code</th><th>Nom</th><th>Pays</th><th>Niveau</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($leagues as $lg): ?>
          <tr>
            <td><?= h(getAny($lg, ['code'], '')) ?></td>
            <td><?= h(getAny($lg, ['name','nom'], '')) ?></td>
            <td><?= h(getAny($lg, ['country','pays'], '')) ?></td>
            <td><?= h(getAny($lg, ['level','niveau'], '')) ?></td>
            <td>
              <div class="actions">
                <a class="pill" href="/index.php?page=league&id=<?= h((string)$lg['_id']) ?>">Voir teams</a>
                <a class="pill" href="/league_edit.php?id=<?= h((string)$lg['_id']) ?>">Modifier</a>
                <form class="inline" method="post" action="/league_delete.php" onsubmit="return confirm('Supprimer cette ligue ? (Refusé si teams existent)');">
                  <input type="hidden" name="id" value="<?= h((string)$lg['_id']) ?>">
                  <button type="submit" class="pill btn-danger">Supprimer</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!count($leagues)): ?>
          <tr><td colspan="5" class="muted">Aucune ligue.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($page === 'league'): ?>
    <?php if (!$leagueDoc): ?>
      <p class="muted">Ligue introuvable.</p>
    <?php else: ?>
      <?php $lgLabel = ($leagueDoc['code'] ?? '') ?: ($leagueDoc['name'] ?? ''); ?>
      <h2>Détail ligue : <?= h($lgLabel) ?></h2>

      <table>
        <thead><tr><th>Club</th><th>OVR</th><th>Valeur club</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($teams as $t): ?>
            <tr>
              <td><?= h($t['name'] ?? '') ?></td>
              <td><?= h((int)($t['rating'] ?? 0)) ?></td>
              <td><?= h(formatMoneyShort((int)($t['budget'] ?? 0))) ?></td>
              <td>
                <div class="actions">
                  <a class="pill" href="/index.php?page=team&id=<?= h((string)$t['_id']) ?>">Voir joueurs</a>
                  <a class="pill" href="/team_edit.php?id=<?= h((string)$t['_id']) ?>">Modifier</a>
                  <form class="inline" method="post" action="/team_delete.php" onsubmit="return confirm('Supprimer cette team ? (Refusé si players existent)');">
                    <input type="hidden" name="id" value="<?= h((string)$t['_id']) ?>">
                    <button type="submit" class="pill btn-danger">Supprimer</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!count($teams)): ?>
            <tr><td colspan="4" class="muted">Aucune team.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($page === 'teams'): ?>
    <h2>Teams</h2>

    <div class="panel" style="margin:12px 0;">
      <form class="filter" method="get">
        <input type="hidden" name="page" value="teams">

        <div class="field wide">
          <label>Ligue</label>
          <select name="leagueId">
            <option value="">-- toutes --</option>
            <?php foreach ($leaguesList as $lg): ?>
              <?php $val = (string)$lg['_id']; $label = ($lg['code'] ?? '') . ' - ' . ($lg['name'] ?? ''); ?>
              <option value="<?= h($val) ?>" <?= $teamLeagueId===$val ? 'selected' : '' ?>>
                <?= h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field wide">
          <label>Recherche</label>
          <input name="qt" value="<?= h($qt) ?>" placeholder="Nom du club">
        </div>

        <div class="field">
          <label>Tri</label>
          <select name="sortT">
            <option value="name_asc"    <?= $sortT==='name_asc'?'selected':'' ?>>Nom A→Z</option>
            <option value="rating_desc" <?= $sortT==='rating_desc'?'selected':'' ?>>OVR ↓</option>
            <option value="budget_desc" <?= $sortT==='budget_desc'?'selected':'' ?>>Budget ↓</option>
          </select>
        </div>

        <div>
          <button type="submit">Filtrer</button>
          <a class="pill" href="/index.php?page=teams" style="margin-left:8px;">Reset</a>
        </div>
      </form>
    </div>

    <p class="muted">Résultats : <?= (int)$totalTeams ?></p>

    <table>
      <thead><tr><th>Ligue</th><th>Club</th><th>OVR</th><th>Valeur club</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($teams as $t): ?>
          <?php
            $lgId = isset($t['leagueId']) ? (string)$t['leagueId'] : '';
            $lgLabel = $leagueMap[$lgId] ?? '';
          ?>
          <tr>
            <td><?= h($lgLabel) ?></td>
            <td><?= h($t['name'] ?? '') ?></td>
            <td><?= h((int)($t['rating'] ?? 0)) ?></td>
            <td><?= h(formatMoneyShort((int)($t['budget'] ?? 0))) ?></td>
            <td>
              <div class="actions">
                <a class="pill" href="/index.php?page=team&id=<?= h((string)$t['_id']) ?>">Voir joueurs</a>
                <a class="pill" href="/team_edit.php?id=<?= h((string)$t['_id']) ?>">Modifier</a>
                <form class="inline" method="post" action="/team_delete.php" onsubmit="return confirm('Supprimer cette team ? (Refusé si players existent)');">
                  <input type="hidden" name="id" value="<?= h((string)$t['_id']) ?>">
                  <button type="submit" class="pill btn-danger">Supprimer</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!count($teams)): ?>
          <tr><td colspan="5" class="muted">Aucune team.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($page === 'team'): ?>
    <?php if (!$teamDoc): ?>
      <p class="muted">Team introuvable.</p>
    <?php else: ?>
      <?php
        $tName = $teamDoc['name'] ?? '';
        $lgId = isset($teamDoc['leagueId']) ? (string)$teamDoc['leagueId'] : '';
        $lgLabel = $leagueMap[$lgId] ?? '';
        $ovrTeam = (int)($teamDoc['rating'] ?? 0);
        $clubValue = (int)($teamDoc['budget'] ?? 0);
      ?>
      <h2>Détail team : <?= h($tName) ?> <span class="pill"><?= h($lgLabel) ?></span></h2>
      <p class="muted">OVR: <?= h($ovrTeam) ?> | Valeur: <?= h(formatMoneyShort($clubValue)) ?></p>

      <h3>Joueurs (top 200)</h3>
      <table>
        <thead><tr><th>Nom</th><th>Postes</th><th>OVR</th><th>Âge</th><th>Contrat</th><th>Valeur</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($players as $pDoc): ?>
            <?php
              $pid  = (string)$pDoc['_id'];
              $name = getAny($pDoc, ['playerName','player_name','name','player','nom'], '');
              $pos  = getAny($pDoc, ['positions','pos','position'], '');
              $ovrP = parseOverall(getAny($pDoc, ['overall','ovr','OVR'], 0));
              $age  = (int)getAny($pDoc, ['age'], 0);
              $cs   = getAny($pDoc, ['contractStart','contract_start','start'], null);
              $ce   = getAny($pDoc, ['contractEnd','contract_end','end'], null);
              $val  = (int)getAny($pDoc, ['marketValue','market_value','value'], 0);
              $rep = $reportsByPlayer[$pid] ?? null;
            ?>
            <tr>
              <td><?= h($name) ?></td>
              <td><?= h($pos) ?></td>
              <td><?= h($ovrP) ?></td>
              <td><?= h($age) ?></td>
              <td><?= h(msToYmdLocal($cs)) ?> → <?= h(msToYmdLocal($ce)) ?></td>
              <td><?= h(formatMoneyShort($val)) ?></td>
              <td>
                <div class="actions">
                  <a class="pill" href="/player_edit.php?id=<?= h($pid) ?>">Modifier</a>
                  <form class="inline" method="post" action="/player_delete.php" onsubmit="return confirm('Supprimer ce joueur ?');">
                    <input type="hidden" name="id" value="<?= h($pid) ?>">
                    <button type="submit" class="pill btn-danger">Supprimer</button>
                  </form>
                  <a class="btn btn-soft" href="/scout_report_create.php?playerId=<?= h($pid) ?>">+ Rapport</a>

                  <?php if ($rep): ?>
                    <span class="toggle" data-toggle="<?= h($pid) ?>" title="Voir le dernier rapport">
                      <span class="chev">▼</span>
                    </span>
                  <?php else: ?>
                    <span class="toggle" aria-disabled="true" title="Aucun rapport">
                      <span class="chev">▼</span>
                    </span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>

            <?php if ($rep): ?>
              <?php
                $rating = (int)($rep['rating'] ?? 0);
                $strengths = (array)($rep['strengths'] ?? []);
                $weaknesses = (array)($rep['weaknesses'] ?? []);
                $notes = (string)($rep['notes'] ?? '');
                $created = $rep['createdAt'] ?? null;
                $repId = (string)($rep['_id'] ?? '');
              ?>
              <tr class="report-row" id="report-<?= h($pid) ?>">
                <td colspan="7">
                  <div class="report-grid">
                    <b>Rapport</b>
                    <div>
                      Note : <strong><?= h($rating) ?>/10</strong>
                      <?= $created ? '<span class="muted">(' . h(msToYmdLocal($created)) . ')</span>' : '' ?>
                      <?php if ($repId && preg_match('/^[a-f0-9]{24}$/i', $repId)): ?>
                        <a class="pill" href="/scout_report_edit.php?id=<?= h($repId) ?>" style="margin-left:10px;">Modifier</a>
                        <form class="inline" method="post" action="/scout_report_delete.php" onsubmit="return confirm('Supprimer ce rapport ?');">
                          <input type="hidden" name="id" value="<?= h($repId) ?>">
                          <button type="submit" class="pill btn-danger" style="margin-left:6px;">Supprimer</button>
                        </form>
                      <?php endif; ?>
                    </div>
                    <b>Forces</b><div><?= h(implode(', ', $strengths)) ?></div>
                    <b>Faiblesses</b><div><?= h(implode(', ', $weaknesses)) ?></div>
                    <?php if (trim($notes) !== ''): ?>
                      <b>Commentaire</b><div><?= h($notes) ?></div>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>

          <?php if (!count($players)): ?>
            <tr><td colspan="7" class="muted">Aucun joueur trouvé.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($page === 'players'): ?>
    <h2>Players</h2>

    <div class="panel" style="margin:12px 0;">
      <form class="filter" method="get">
        <input type="hidden" name="page" value="players">

        <div class="field wide">
          <label>Ligue</label>
          <select name="leagueId">
            <option value="">-- toutes --</option>
            <?php foreach ($leaguesList as $lg): ?>
              <?php $val = (string)$lg['_id']; $label = ($lg['code'] ?? '') . ' - ' . ($lg['name'] ?? ''); ?>
              <option value="<?= h($val) ?>" <?= ((string)($_GET['leagueId'] ?? ''))===$val ? 'selected' : '' ?>>
                <?= h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field wide">
          <label>Équipe</label>
          <select name="teamId">
            <option value="">-- toutes --</option>
            <?php foreach ($teamsList as $t): ?>
              <?php $val = (string)$t['_id']; ?>
              <option value="<?= h($val) ?>" <?= ((string)($_GET['teamId'] ?? ''))===$val ? 'selected' : '' ?>>
                <?= h($t['name'] ?? '') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field wide">
          <label>Recherche</label>
          <input name="q" value="<?= h($q) ?>" placeholder="Nom joueur">
        </div>

        <div class="field">
          <label>OVR min</label>
          <input name="ovrMin" type="number" min="0" value="<?= h($ovrMin) ?>">
        </div>

        <div class="field">
          <label>OVR max</label>
          <input name="ovrMax" type="number" min="0" value="<?= h($ovrMax) ?>">
        </div>

        <div class="field">
          <label>Tri</label>
          <select name="sort">
            <option value="overall_desc" <?= $sort==='overall_desc'?'selected':'' ?>>OVR ↓</option>
            <option value="value_desc" <?= $sort==='value_desc'?'selected':'' ?>>Valeur ↓</option>
            <option value="age_asc" <?= $sort==='age_asc'?'selected':'' ?>>Âge ↑</option>
            <option value="name_asc" <?= $sort==='name_asc'?'selected':'' ?>>Nom A→Z</option>
          </select>
        </div>

        <div>
          <button type="submit">Filtrer</button>
          <a class="pill" href="/index.php?page=players" style="margin-left:8px;">Reset</a>
        </div>
      </form>
    </div>

    <p class="muted">Résultats : <?= (int)$total ?> — Page <?= (int)$p ?> / <?= (int)$totalPages ?></p>

    <table>
      <thead><tr><th>Nom</th><th>Équipe</th><th>Postes</th><th>OVR</th><th>Âge</th><th>Contrat</th><th>Valeur</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($players as $pDoc): ?>
          <?php
            $pid  = (string)$pDoc['_id'];
            $name = getAny($pDoc, ['playerName','player_name','name','player','nom'], '');
            $team = getAny($pDoc, ['team','team_name','teamName','club','club_name'], '');
            $pos  = getAny($pDoc, ['positions','pos','position'], '');
            $ovr  = parseOverall(getAny($pDoc, ['overall','ovr','OVR'], 0));
            $age  = (int)getAny($pDoc, ['age'], 0);
            $cs   = getAny($pDoc, ['contractStart','contract_start','start'], null);
            $ce   = getAny($pDoc, ['contractEnd','contract_end','end'], null);
            $val  = (int)getAny($pDoc, ['marketValue','market_value','value'], 0);
            $rep = $reportsByPlayer[$pid] ?? null;
          ?>
          <tr>
            <td><?= h($name) ?></td>
            <td><?= h($team) ?></td>
            <td><?= h($pos) ?></td>
            <td><?= h($ovr) ?></td>
            <td><?= h($age) ?></td>
            <td><?= h(msToYmdLocal($cs)) ?> → <?= h(msToYmdLocal($ce)) ?></td>
            <td><?= h(formatMoneyShort($val)) ?></td>
            <td>
              <div class="actions">
                <a class="pill" href="/player_edit.php?id=<?= h($pid) ?>">Modifier</a>
                <form class="inline" method="post" action="/player_delete.php" onsubmit="return confirm('Supprimer ce joueur ?');">
                  <input type="hidden" name="id" value="<?= h($pid) ?>">
                  <button type="submit" class="pill btn-danger">Supprimer</button>
                </form>
                <a class="btn btn-soft" href="/scout_report_create.php?playerId=<?= h($pid) ?>">+ Rapport</a>

                <?php if ($rep): ?>
                  <span class="toggle" data-toggle="<?= h($pid) ?>" title="Voir le dernier rapport">
                    <span class="chev">▼</span>
                  </span>
                <?php else: ?>
                  <span class="toggle" aria-disabled="true" title="Aucun rapport">
                    <span class="chev">▼</span>
                  </span>
                <?php endif; ?>
              </div>
            </td>
          </tr>

          <?php if ($rep): ?>
            <?php
              $rating = (int)($rep['rating'] ?? 0);
              $strengths = (array)($rep['strengths'] ?? []);
              $weaknesses = (array)($rep['weaknesses'] ?? []);
              $notes = (string)($rep['notes'] ?? '');
              $created = $rep['createdAt'] ?? null;
              $repId = (string)($rep['_id'] ?? '');
            ?>
            <tr class="report-row" id="report-<?= h($pid) ?>">
              <td colspan="8">
                <div class="report-grid">
                  <b>Rapport</b>
                  <div>
                    Note : <strong><?= h($rating) ?>/10</strong>
                    <?= $created ? '<span class="muted">(' . h(msToYmdLocal($created)) . ')</span>' : '' ?>
                    <?php if ($repId && preg_match('/^[a-f0-9]{24}$/i', $repId)): ?>
                      <a class="pill" href="/scout_report_edit.php?id=<?= h($repId) ?>" style="margin-left:10px;">Modifier</a>
                      <form class="inline" method="post" action="/scout_report_delete.php" onsubmit="return confirm('Supprimer ce rapport ?');">
                        <input type="hidden" name="id" value="<?= h($repId) ?>">
                        <button type="submit" class="pill btn-danger" style="margin-left:6px;">Supprimer</button>
                      </form>
                    <?php endif; ?>
                  </div>
                  <b>Forces</b><div><?= h(implode(', ', $strengths)) ?></div>
                  <b>Faiblesses</b><div><?= h(implode(', ', $weaknesses)) ?></div>
                  <?php if (trim($notes) !== ''): ?>
                    <b>Commentaire</b><div><?= h($notes) ?></div>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>

        <?php if (!count($players)): ?>
          <tr><td colspan="8" class="muted">Aucun joueur trouvé.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
      <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
        <?php
          $paramsPrev = $_GET; $paramsNext = $_GET;
          $paramsPrev['page'] = 'players'; $paramsNext['page'] = 'players';
        ?>
        <?php if ($p > 1): $paramsPrev['p'] = $p - 1; ?>
          <a class="pill" href="/index.php?<?= h(http_build_query($paramsPrev)) ?>">← Précédent</a>
        <?php endif; ?>
        <?php if ($p < $totalPages): $paramsNext['p'] = $p + 1; ?>
          <a class="pill" href="/index.php?<?= h(http_build_query($paramsNext)) ?>">Suivant →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</div>

<script>
document.querySelectorAll('[data-toggle]').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.getAttribute('data-toggle');
    const row = document.getElementById('report-' + id);
    if (!row) return;
    const open = row.classList.toggle('is-open');
    btn.classList.toggle('is-open', open);
  });
});
</script>
</body>
</html>
