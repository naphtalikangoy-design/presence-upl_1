<?php
require_once 'config.php'; require_once 'layout.php';
require_role('admin');
$pdo = db();
$date = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['cloturer'])) {
        $n = $pdo->prepare("INSERT INTO presences (stagiaire_id, date_presence, statut, source)
            SELECT id, ?, 'Absent', 'admin' FROM stagiaires WHERE actif AND date_debut <= ? ON CONFLICT DO NOTHING");
        $n->execute([$date, $date]);
        flash('ok', $n->rowCount() . ' stagiaire(s) non pointé(s) marqué(s) absent.');
    } else {
        $q = $pdo->prepare("INSERT INTO presences (stagiaire_id, date_presence, statut, observation, source)
            VALUES (?,?,?,?,'admin') ON CONFLICT (stagiaire_id, date_presence) DO UPDATE
            SET statut = EXCLUDED.statut, observation = EXCLUDED.observation, source = 'admin'
            WHERE presences.statut IS DISTINCT FROM EXCLUDED.statut
               OR COALESCE(presences.observation,'') IS DISTINCT FROM COALESCE(EXCLUDED.observation,'')");
        $ok = ['Présent', 'Retard', 'Excusé', 'Absent'];
        foreach (($_POST['statut'] ?? []) as $id => $s)
            if (in_array($s, $ok, true)) $q->execute([(int)$id, $date, $s, substr(trim($_POST['obs'][$id] ?? ''), 0, 255)]);
        flash('ok', 'Modifications enregistrées.');
    }
    header('Location: index.php?date=' . $date); exit;
}

$st = $pdo->prepare("SELECT s.id, s.matricule, s.nom, s.lieu_stage, p.statut, p.observation, p.heure, p.source
    FROM stagiaires s LEFT JOIN presences p ON p.stagiaire_id = s.id AND p.date_presence = ?
    WHERE s.actif ORDER BY s.nom");
$st->execute([$date]); $rows = $st->fetchAll();
$c = ['Présent' => 0, 'Retard' => 0, 'Excusé' => 0, 'Absent' => 0]; $non = 0;
foreach ($rows as $r) { if ($r['statut']) $c[$r['statut']]++; else $non++; }
$ip = client_ip(); $nb = count(allowed_ranges());

page_start('Tableau de bord');
?>
<div class="page-head">
  <div><h1>Tableau de bord</h1><p class="muted">Présences du <?= date('d/m/Y', strtotime($date)) ?></p></div>
  <form method="get" class="inline"><label class="field sm">Date<input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()"></label></form>
</div>

<?php if (setting('restriction_reseau') === '1' && !$nb): ?>
<div class="alert warn">Le contrôle du réseau est activé mais aucune adresse n'est configurée : les stagiaires ne peuvent pas se connecter. <a href="parametres.php">Configurer le réseau</a></div>
<?php endif; ?>

<div class="stats">
  <div class="stat"><span>Stagiaires actifs</span><b><?= count($rows) ?></b></div>
  <div class="stat ok"><span>Présents</span><b><?= $c['Présent'] ?></b></div>
  <div class="stat warn"><span>Retards</span><b><?= $c['Retard'] ?></b></div>
  <div class="stat info"><span>Excusés</span><b><?= $c['Excusé'] ?></b></div>
  <div class="stat err"><span>Absents</span><b><?= $c['Absent'] ?></b></div>
  <div class="stat"><span>Non pointés</span><b><?= $non ?></b></div>
</div>

<form method="post" class="card">
  <?= csrf_field() ?><input type="hidden" name="date" value="<?= h($date) ?>">
  <div class="card-head"><h3>Pointage</h3>
    <button class="btn ghost small" name="cloturer" value="1" onclick="return confirm('Marquer absents tous les stagiaires non pointés à cette date ?')">Clôturer la journée</button></div>
  <div class="table-wrap"><table>
    <tr><th>Matricule</th><th>Nom</th><th>Heure</th><th>Origine</th><th>Statut</th><th>Observation</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= h($r['matricule']) ?></td><td><?= h($r['nom']) ?></td>
      <td><?= h(substr($r['heure'] ?? '', 0, 5) ?: '-') ?></td>
      <td class="muted"><?= $r['source'] ? ($r['source'] === 'stagiaire' ? 'Stagiaire' : 'Administrateur') : '-' ?></td>
      <td><select name="statut[<?= $r['id'] ?>]">
        <option value="">Non pointé</option>
        <?php foreach (array_keys($c) as $s): ?><option <?= $r['statut'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
      </select></td>
      <td><input name="obs[<?= $r['id'] ?>]" value="<?= h($r['observation']) ?>" maxlength="255"></td>
    </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">Aucun stagiaire actif.</td></tr><?php endif; ?>
  </table></div>
  <?php if ($rows): ?><button class="btn">Enregistrer les modifications</button><?php endif; ?>
</form>
<?php page_end();
