<?php
require_once 'config.php';
if (($_SESSION['role'] ?? '') === 'admin') { header('Location: index.php'); exit; }
if (($_SESSION['role'] ?? '') === 'stagiaire') { header('Location: moi.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = trim($_POST['identifiant'] ?? ''); $pw = $_POST['password'] ?? '';
    $pdo = db();
    $q = $pdo->prepare("SELECT * FROM administrateurs WHERE username = ?"); $q->execute([$id]);
    $a = $q->fetch();
    if ($a && password_verify($pw, $a['password'])) {
        session_regenerate_id(true);
        $_SESSION = ['role' => 'admin', 'uid' => $a['id'], 'name' => $a['username']];
        header('Location: index.php'); exit;
    }
    $q = $pdo->prepare("SELECT * FROM stagiaires WHERE matricule = ?"); $q->execute([$id]);
    $s = $q->fetch();
    if ($s && $s['actif'] && $s['password'] && password_verify($pw, $s['password'])) {
        if (!network_ok()) $err = network_message();
        else {
            session_regenerate_id(true);
            $_SESSION = ['role' => 'stagiaire', 'uid' => $s['id'], 'name' => $s['nom']];
            header('Location: moi.php'); exit;
        }
    } else { usleep(500000); $err = 'Identifiant ou mot de passe incorrect.'; }
}
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion | UPL Présence</title><link rel="stylesheet" href="style.css"></head>
<body class="auth">
<section class="auth-panel">
  <div class="auth-brand"><span class="mark lg">UPL</span>
    <h1>Gestion de la présence des stagiaires</h1>
    <p>Université Protestante de Lubumbashi</p></div>
</section>
<section class="auth-form">
  <form method="post" class="auth-box">
    <h2>Connexion</h2>
    <p class="muted">Les stagiaires doivent être connectés au réseau Wi-Fi de l'UPL.</p>
    <?php if ($err): ?><div class="alert err"><?= h($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label class="field">Matricule ou identifiant<input name="identifiant" required autofocus autocomplete="username"></label>
    <label class="field">Mot de passe<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn block">Se connecter</button>
  </form>
</section>
</body></html>
