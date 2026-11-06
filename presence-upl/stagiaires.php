<?php
require_once 'config.php'; require_once 'layout.php';
require_role('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['ajouter'])) {
        $pw = gen_password();
        try {
            $pdo->prepare("INSERT INTO stagiaires (matricule, nom, faculte, lieu_stage, date_debut, password) VALUES (?,?,?,?,?,?)")
                ->execute([trim($_POST['matricule']), trim($_POST['nom']), trim($_POST['faculte']), trim($_POST['lieu_stage']),
                           $_POST['date_debut'] ?: date('Y-m-d'), password_hash($pw, PASSWORD_DEFAULT)]);
            flash('ok', "Stagiaire ajouté. Mot de passe initial : $pw (à communiquer au stagiaire, il ne sera plus affiché).");
        } catch (PDOException $e) { flash('err', 'Ce matricule existe déjà ou les données sont invalides.'); }
    } elseif (isset($_POST['reinit'])) {
        $pw = gen_password();
        $pdo->prepare("UPDATE stagiaires SET password = ? WHERE id = ?")->execute([password_hash($pw, PASSWORD_DEFAULT), (int)$_POST['reinit']]);
        flash('ok', "Nouveau mot de passe : $pw (à communiquer au stagiaire, il ne sera plus affiché).");
    } elseif (isset($_POST['basculer'])) {
        $pdo->prepare("UPDATE stagiaires SET actif = NOT actif WHERE id = ?")->execute([(int)$_POST['basculer']]);
        flash('ok', 'Statut du stagiaire mis à jour.');
    } elseif (isset($_POST['supprimer'])) {
        $pdo->prepare("DELETE FROM stagiaires WHERE id = ?")->execute([(int)$_POST['supprimer']]);
        flash('ok', 'Stagiaire supprimé.');
    }
    header('Location: stagiaires.php'); exit;
}
$list = $pdo->query("SELECT * FROM stagiaires ORDER BY nom")->fetchAll();
page_start('Stagiaires');
?>
<div class="page-head"><div><h1>Stagiaires</h1><p class="muted">Création des comptes et gestion des accès</p></div></div>

<form method="post" class="card">
  <h3>Nouveau stagiaire</h3><?= csrf_field() ?>
  <div class="grid-form">
    <label class="field">Matricule<input name="matricule" required></label>
    <label class="field">Nom complet<input name="nom" required></label>
    <label class="field">Faculté ou département<input name="faculte"></label>
    <label class="field">Lieu de stage<input name="lieu_stage"></label>
    <label class="field">Date de début<input type="date" name="date_debut" value="<?= date('Y-m-d') ?>"></label>
  </div>
  <button class="btn" name="ajouter" value="1">Créer le compte</button>
</form>

<section class="card">
  <h3>Liste (<?= count($list) ?>)</h3>
  <div class="table-wrap"><table>
    <tr><th>Matricule</th><th>Nom</th><th>Faculté</th><th>Lieu de stage</th><th>Début</th><th>Compte</th><th>Actions</th></tr>
    <?php foreach ($list as $s): ?>
    <tr>
      <td><?= h($s['matricule']) ?></td><td><?= h($s['nom']) ?></td><td><?= h($s['faculte']) ?></td>
      <td><?= h($s['lieu_stage']) ?></td><td><?= date('d/m/Y', strtotime($s['date_debut'])) ?></td>
      <td><span class="badge <?= $s['actif'] ? 'ok' : 'muted' ?>"><?= $s['actif'] ? 'Actif' : 'Désactivé' ?></span></td>
      <td class="actions"><form method="post"><?= csrf_field() ?>
        <button class="btn small ghost" name="reinit" value="<?= $s['id'] ?>" onclick="return confirm('Générer un nouveau mot de passe ?')">Réinitialiser</button>
        <button class="btn small ghost" name="basculer" value="<?= $s['id'] ?>"><?= $s['actif'] ? 'Désactiver' : 'Activer' ?></button>
        <button class="btn small danger" name="supprimer" value="<?= $s['id'] ?>" onclick="return confirm('Supprimer ce stagiaire et tout son historique ?')">Supprimer</button>
      </form></td>
    </tr>
    <?php endforeach; if (!$list): ?><tr><td colspan="7" class="muted">Aucun stagiaire enregistré.</td></tr><?php endif; ?>
  </table></div>
</section>
<?php page_end();
