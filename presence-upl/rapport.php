<?php
require_once 'config.php'; require_once 'layout.php';
require_role('admin');
$pdo = db();
$du = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['du'] ?? '') ? $_GET['du'] : date('Y-m-01');
$au = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['au'] ?? '') ? $_GET['au'] : date('Y-m-d');
$st = $pdo->prepare("SELECT s.matricule, s.nom, s.lieu_stage,
    COUNT(*) FILTER (WHERE p.statut='Présent') AS presents,
    COUNT(*) FILTER (WHERE p.statut='Retard') AS retards,
    COUNT(*) FILTER (WHERE p.statut='Excusé') AS excuses,
    COUNT(*) FILTER (WHERE p.statut='Absent') AS absents,
    COUNT(p.id) AS total
    FROM stagiaires s LEFT JOIN presences p ON p.stagiaire_id = s.id AND p.date_presence BETWEEN ? AND ?
    WHERE s.actif GROUP BY s.id ORDER BY s.nom");
$st->execute([$du, $au]); $rows = $st->fetchAll();
$taux = fn($r) => $r['total'] ? (int)round(($r['presents'] + $r['retards']) * 100 / $r['total']) : 0;

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="presence_' . $du . '_' . $au . '.csv"');
    echo "\xEF\xBB\xBF"; $o = fopen('php://output', 'w');
    fputcsv($o, ['Matricule', 'Nom', 'Lieu de stage', 'Présents', 'Retards', 'Excusés', 'Absents', 'Jours pointés', 'Taux (%)'], ';');
    foreach ($rows as $r) fputcsv($o, [$r['matricule'], $r['nom'], $r['lieu_stage'], $r['presents'], $r['retards'], $r['excuses'], $r['absents'], $r['total'], $taux($r)], ';');
    exit;
}
page_start('Rapports');
?>
<div class="page-head"><div><h1>Rapports</h1><p class="muted">Synthèse de présence par période</p></div></div>
<form method="get" class="card filter">
  <label class="field">Du<input type="date" name="du" value="<?= h($du) ?>"></label>
  <label class="field">Au<input type="date" name="au" value="<?= h($au) ?>"></label>
  <button class="btn">Afficher</button>
  <button class="btn ghost" name="csv" value="1">Exporter en CSV</button>
</form>
<section class="card"><div class="table-wrap"><table>
  <tr><th>Matricule</th><th>Nom</th><th>Présents</th><th>Retards</th><th>Excusés</th><th>Absents</th><th>Taux de présence</th></tr>
  <?php foreach ($rows as $r): $t = $taux($r); ?>
  <tr><td><?= h($r['matricule']) ?></td><td><?= h($r['nom']) ?></td><td><?= $r['presents'] ?></td><td><?= $r['retards'] ?></td>
    <td><?= $r['excuses'] ?></td><td><?= $r['absents'] ?></td>
    <td><div class="bar"><i style="width:<?= $t ?>%"></i></div><span class="pct"><?= $t ?> %</span></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted">Aucune donnée.</td></tr><?php endif; ?>
</table></div></section>
<?php page_end();
