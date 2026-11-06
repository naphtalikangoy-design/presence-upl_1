<?php
require_once 'config.php'; require_once 'layout.php';
require_role('stagiaire');
$pdo = db(); $uid = (int)$_SESSION['uid'];

$q = $pdo->prepare("SELECT * FROM stagiaires WHERE id = ?"); $q->execute([$uid]);
$me = $q->fetch();
if (!$me || !$me['actif']) { $_SESSION = []; header('Location: login.php'); exit; }

function etat_pointage(PDO $pdo, int $uid): array {
    $q = $pdo->prepare("SELECT * FROM presences WHERE stagiaire_id = ? AND date_presence = ?");
    $q->execute([$uid, date('Y-m-d')]); $p = $q->fetch();
    $now = date('H:i');
    if ($p) return ['deja', $p, "Votre présence est enregistrée pour aujourd'hui."];
    if (!in_array(date('N'), explode(',', setting('jours_ouvres')))) return ['ferme', null, "Aujourd'hui n'est pas un jour de pointage."];
    if ($now < setting('heure_ouverture')) return ['ferme', null, 'Le pointage ouvre à ' . setting('heure_ouverture') . '.'];
    if ($now > setting('heure_cloture')) return ['ferme', null, 'Le pointage est clos depuis ' . setting('heure_cloture') . '.'];
    if (!network_ok()) return ['reseau', null, network_message()];
    return ['ouvert', null, ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['marquer'])) {
        [$etat] = etat_pointage($pdo, $uid);
        if ($etat === 'ouvert') {
            $statut = date('H:i') > setting('heure_retard') ? 'Retard' : 'Présent';
            $pdo->prepare("INSERT INTO presences (stagiaire_id, date_presence, statut, heure, ip, source)
                VALUES (?,?,?,?,?,'stagiaire') ON CONFLICT DO NOTHING")
                ->execute([$uid, date('Y-m-d'), $statut, date('H:i:s'), client_ip()]);
            flash('ok', 'Présence enregistrée : ' . $statut . ' à ' . date('H:i') . '.');
        } else flash('err', 'Le pointage est impossible pour le moment.');
    } elseif (isset($_POST['mdp'])) {
        if (!password_verify($_POST['actuel'] ?? '', $me['password'])) flash('err', 'Mot de passe actuel incorrect.');
        elseif (strlen($_POST['nouveau'] ?? '') < 8) flash('err', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
        elseif (($_POST['nouveau'] ?? '') !== ($_POST['confirm'] ?? '')) flash('err', 'La confirmation ne correspond pas.');
        else {
            $pdo->prepare("UPDATE stagiaires SET password = ? WHERE id = ?")
                ->execute([password_hash($_POST['nouveau'], PASSWORD_DEFAULT), $uid]);
            flash('ok', 'Mot de passe modifié.');
        }
    }
    header('Location: moi.php'); exit;
}

[$etat, $p, $msg] = etat_pointage($pdo, $uid);
$hist = $pdo->prepare("SELECT * FROM presences WHERE stagiaire_id = ? ORDER BY date_presence DESC LIMIT 30");
$hist->execute([$uid]); $hist = $hist->fetchAll();
$mois = $pdo->prepare("SELECT statut, COUNT(*) c FROM presences WHERE stagiaire_id = ? AND date_presence >= date_trunc('month', CURRENT_DATE) GROUP BY statut");
$mois->execute([$uid]); $m = array_column($mois->fetchAll(), 'c', 'statut');
$jours = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
$reseau = network_ok();

page_start('Mon pointage');
?>
<div class="page-head"><div><h1>Bonjour, <?= h(explode(' ', trim($me['nom']))[0]) ?></h1>
  <p class="muted"><?= h($me['matricule']) ?> &middot; <?= h($me['lieu_stage'] ?: 'Lieu de stage non renseigné') ?></p></div></div>

<section class="card clock-card">
  <div class="clock-date"><?= $jours[(int)date('N')] ?> <?= date('d/m/Y') ?></div>
  <div class="clock" id="clk"><?= date('H:i:s') ?></div>
  <div class="clock-meta">
    <span>Ouverture <b><?= h(setting('heure_ouverture')) ?></b></span>
    <span>Retard après <b><?= h(setting('heure_retard')) ?></b></span>
    <span>Clôture <b><?= h(setting('heure_cloture')) ?></b></span>
  </div>
  <div class="net <?= $reseau ? 'on' : 'off' ?>">
    <i></i><?= setting('restriction_reseau') !== '1' ? 'Contrôle du réseau désactivé' : ($reseau ? 'Connecté au réseau de l\'UPL' : 'Hors du réseau de l\'UPL') ?>
  </div>

  <?php if ($etat === 'deja'): ?>
    <div class="state done"><?= badge($p['statut']) ?> <span>Enregistré à <?= h(substr($p['heure'] ?? '', 0, 5) ?: '--:--') ?></span></div>
  <?php elseif ($etat === 'ouvert'): ?>
    <form method="post"><?= csrf_field() ?><button class="btn big" name="marquer" value="1">Marquer ma présence</button></form>
  <?php else: ?>
    <div class="alert <?= $etat === 'reseau' ? 'err' : 'info' ?> flat"><?= h($msg) ?></div>
  <?php endif; ?>
</section>

<div class="stats">
  <?php foreach (['Présent', 'Retard', 'Excusé', 'Absent'] as $s): ?>
    <div class="stat"><span><?= $s ?> ce mois</span><b><?= (int)($m[$s] ?? 0) ?></b></div>
  <?php endforeach; ?>
</div>

<section class="card">
  <h3>Historique récent</h3>
  <div class="table-wrap"><table>
    <tr><th>Date</th><th>Heure</th><th>Statut</th><th>Remarque</th></tr>
    <?php foreach ($hist as $r): ?>
      <tr><td><?= date('d/m/Y', strtotime($r['date_presence'])) ?></td><td><?= h(substr($r['heure'] ?? '', 0, 5) ?: '-') ?></td>
      <td><?= badge($r['statut']) ?></td><td><?= h($r['observation']) ?></td></tr>
    <?php endforeach; if (!$hist): ?><tr><td colspan="4" class="muted">Aucun enregistrement.</td></tr><?php endif; ?>
  </table></div>
</section>

<details class="card">
  <summary>Modifier mon mot de passe</summary>
  <form method="post" class="stack"><?= csrf_field() ?>
    <label class="field">Mot de passe actuel<input type="password" name="actuel" required></label>
    <label class="field">Nouveau mot de passe<input type="password" name="nouveau" minlength="8" required></label>
    <label class="field">Confirmer<input type="password" name="confirm" minlength="8" required></label>
    <button class="btn" name="mdp" value="1">Enregistrer</button>
  </form>
</details>
<script>
function t(){document.getElementById('clk').textContent=new Date().toLocaleTimeString('fr-FR',{timeZone:'Africa/Lubumbashi'});}
setInterval(t,1000);
</script>
<?php page_end();
