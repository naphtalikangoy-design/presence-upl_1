<?php
function badge(?string $s): string {
    $m = ['Présent' => 'ok', 'Retard' => 'warn', 'Absent' => 'err', 'Excusé' => 'info'];
    return '<span class="badge ' . ($m[$s] ?? 'muted') . '">' . h($s ?: 'Non pointé') . '</span>';
}

function page_start(string $title): void {
    $role = $_SESSION['role'] ?? '';
    $page = basename($_SERVER['PHP_SELF']);
    $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
    ?><!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> | UPL Présence</title>
<link rel="stylesheet" href="style.css">
</head><body>
<?php if ($role === 'admin'):
    $nav = ['index.php' => 'Tableau de bord', 'stagiaires.php' => 'Stagiaires', 'rapport.php' => 'Rapports', 'parametres.php' => 'Paramètres']; ?>
<div class="shell">
  <aside class="side">
    <div class="brand"><span class="mark">UPL</span><div><b>Présence</b><small>Gestion des stagiaires</small></div></div>
    <nav>
      <?php foreach ($nav as $f => $l): ?><a href="<?= $f ?>" class="<?= $page === $f ? 'on' : '' ?>"><?= $l ?></a><?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <small>Connecté en tant que</small><b><?= h($_SESSION['name']) ?></b>
      <a href="logout.php" class="logout">Se déconnecter</a>
    </div>
  </aside>
  <main class="content">
<?php else: ?>
<header class="bar">
  <div class="brand"><span class="mark">UPL</span><div><b>Présence</b><small>Espace stagiaire</small></div></div>
  <div class="bar-user"><span><?= h($_SESSION['name'] ?? '') ?></span><a href="logout.php">Se déconnecter</a></div>
</header>
<main class="narrow">
<?php endif;
    if ($flash) echo '<div class="alert ' . h($flash[0]) . '">' . h($flash[1]) . '</div>';
}

function page_end(): void {
    echo (($_SESSION['role'] ?? '') === 'admin') ? '</main></div>' : '</main>';
    echo '<footer class="foot">Université Protestante de Lubumbashi</footer></body></html>';
}
