<?php
require_once 'config.php'; require_once 'layout.php';
require_role('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['horaires'])) {
        $o = $_POST['heure_ouverture'] ?? ''; $r = $_POST['heure_retard'] ?? ''; $c = $_POST['heure_cloture'] ?? '';
        $re = '/^([01]\d|2[0-3]):[0-5]\d$/';
        $jours = array_values(array_intersect(array_map('strval', $_POST['jours'] ?? []), ['1','2','3','4','5','6','7']));
        if (!preg_match($re, $o) || !preg_match($re, $r) || !preg_match($re, $c)) flash('err', 'Format d\'heure invalide.');
        elseif (!($o <= $r && $r <= $c)) flash('err', 'Les heures doivent respecter l\'ordre : ouverture, retard, clôture.');
        elseif (!$jours) flash('err', 'Sélectionnez au moins un jour de pointage.');
        else {
            save_setting('heure_ouverture', $o); save_setting('heure_retard', $r); save_setting('heure_cloture', $c);
            save_setting('jours_ouvres', implode(',', $jours));
            flash('ok', 'Horaires enregistrés.');
        }
    } elseif (isset($_POST['reseau'])) {
        $ok = []; $bad = [];
        foreach (preg_split('/\R/', $_POST['ips'] ?? '') as $line) {
            $line = trim(preg_replace('/#.*/', '', $line));
            if ($line === '') continue;
            valid_ip_or_cidr($line) ? $ok[] = $line : $bad[] = $line;
        }
        if ($bad) flash('err', 'Adresses invalides : ' . implode(', ', $bad));
        else {
            save_setting('restriction_reseau', isset($_POST['restriction']) ? '1' : '0');
            save_setting('nom_reseau', trim($_POST['nom_reseau'] ?? '') ?: 'UPL-WiFi');
            save_setting('ips_autorisees', implode("\n", array_unique($ok)));
            flash('ok', 'Configuration du réseau enregistrée.');
        }
    } elseif (isset($_POST['ajouter_ip'])) {
        $ip = client_ip();
        if (strpos($ip, ':') !== false) $ip = inet_ntop(substr(inet_pton($ip), 0, 8) . str_repeat("\0", 8)) . '/64';
        $list = allowed_ranges();
        if (!in_array($ip, $list)) { $list[] = $ip; save_setting('ips_autorisees', implode("\n", $list)); }
        flash('ok', "Adresse $ip ajoutée aux réseaux autorisés.");
    } elseif (isset($_POST['mdp'])) {
        $q = $pdo->prepare("SELECT password FROM administrateurs WHERE id = ?"); $q->execute([$_SESSION['uid']]);
        if (!password_verify($_POST['actuel'] ?? '', (string)$q->fetchColumn())) flash('err', 'Mot de passe actuel incorrect.');
        elseif (strlen($_POST['nouveau'] ?? '') < 8) flash('err', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
        elseif ($_POST['nouveau'] !== ($_POST['confirm'] ?? '')) flash('err', 'La confirmation ne correspond pas.');
        else {
            $pdo->prepare("UPDATE administrateurs SET password = ? WHERE id = ?")
                ->execute([password_hash($_POST['nouveau'], PASSWORD_DEFAULT), $_SESSION['uid']]);
            flash('ok', 'Mot de passe administrateur modifié.');
        }
    }
    header('Location: parametres.php'); exit;
}
$jn = ['1' => 'Lundi', '2' => 'Mardi', '3' => 'Mercredi', '4' => 'Jeudi', '5' => 'Vendredi', '6' => 'Samedi', '7' => 'Dimanche'];
$actifs = explode(',', setting('jours_ouvres'));
$ip = client_ip();
page_start('Paramètres');
?>
<div class="page-head"><div><h1>Paramètres</h1><p class="muted">Règles de pointage et contrôle d'accès</p></div></div>

<form method="post" class="card">
  <h3>Horaires de pointage</h3><?= csrf_field() ?>
  <div class="grid-form">
    <label class="field">Ouverture du pointage<input type="time" name="heure_ouverture" value="<?= h(setting('heure_ouverture')) ?>" required></label>
    <label class="field">Retard à partir de<input type="time" name="heure_retard" value="<?= h(setting('heure_retard')) ?>" required></label>
    <label class="field">Clôture du pointage<input type="time" name="heure_cloture" value="<?= h(setting('heure_cloture')) ?>" required></label>
  </div>
  <p class="hint">Avant l'ouverture et après la clôture, les stagiaires ne peuvent plus marquer leur présence. Entre l'heure de retard et la clôture, la présence est enregistrée comme retard.</p>
  <div class="days"><?php foreach ($jn as $k => $l): ?>
    <label class="chip"><input type="checkbox" name="jours[]" value="<?= $k ?>" <?= in_array((string)$k, $actifs) ? 'checked' : '' ?>><span><?= $l ?></span></label>
  <?php endforeach; ?></div>
  <button class="btn" name="horaires" value="1">Enregistrer les horaires</button>
</form>

<form method="post" class="card">
  <h3>Réseau Wi-Fi de l'UPL</h3><?= csrf_field() ?>
  <label class="switch"><input type="checkbox" name="restriction" <?= setting('restriction_reseau') === '1' ? 'checked' : '' ?>>
    <span>Limiter la connexion et le pointage des stagiaires au réseau de l'UPL</span></label>
  <label class="field">Nom du réseau affiché aux stagiaires<input name="nom_reseau" value="<?= h(setting('nom_reseau')) ?>"></label>
  <label class="field">Adresses IP publiques autorisées (une par ligne)
    <textarea name="ips" rows="5" placeholder="41.xxx.xxx.xxx&#10;197.xxx.xxx.0/24"><?= h(setting('ips_autorisees')) ?></textarea></label>
  <p class="hint">Saisissez l'adresse IP publique de la connexion Internet de l'UPL, ou une plage au format CIDR. Une adresse IPv6 peut être indiquée avec son préfixe, par exemple 2001:db8:abcd:12::/64.</p>
  <div class="ip-now">Adresse détectée pour votre appareil : <b><?= h($ip) ?></b>
    <span class="badge <?= network_ok() ? 'ok' : 'muted' ?>"><?= network_ok() ? 'Autorisée' : 'Non autorisée' ?></span></div>
  <div class="row">
    <button class="btn" name="reseau" value="1">Enregistrer le réseau</button>
    <button class="btn ghost" name="ajouter_ip" value="1" formnovalidate>Ajouter mon adresse actuelle</button>
  </div>
  <p class="hint">Pour utiliser « Ajouter mon adresse actuelle », connectez-vous d'abord au Wi-Fi de l'UPL avec cet appareil.</p>
</form>

<form method="post" class="card">
  <h3>Mot de passe administrateur</h3><?= csrf_field() ?>
  <div class="grid-form">
    <label class="field">Mot de passe actuel<input type="password" name="actuel" required></label>
    <label class="field">Nouveau mot de passe<input type="password" name="nouveau" minlength="8" required></label>
    <label class="field">Confirmation<input type="password" name="confirm" minlength="8" required></label>
  </div>
  <button class="btn" name="mdp" value="1">Modifier le mot de passe</button>
</form>
<?php page_end();
