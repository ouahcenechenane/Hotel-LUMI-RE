<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pdo = getDB();

// Action : changer le statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id     = (int)$_POST['id'];
    $action = $_POST['action'];
    $statuts_valides = ['confirmee','annulee','terminee','en_attente'];
    if (in_array($action, $statuts_valides)) {
        $pdo->prepare("UPDATE reservations SET statut=:s WHERE id=:id")->execute([':s'=>$action,':id'=>$id]);
    }
    header('Location: reservations.php'); exit;
}

// Filtres
$filtre = $_GET['statut'] ?? 'tous';
$sql = "SELECT r.*, rt.nom as type_chambre, rm.numero
        FROM reservations r
        JOIN rooms rm ON r.room_id = rm.id
        JOIN room_types rt ON rm.room_type_id = rt.id";
$params = [];
if ($filtre !== 'tous') {
    $sql .= " WHERE r.statut = :s";
    $params[':s'] = $filtre;
}
$sql .= " ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Réservations — Admin · Hôtel Lumière</title>
  <link rel="stylesheet" href="../hotel-html/css/style.css">
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="sidebar-logo">LUMIÈRE<br><small style="font-size:0.55rem;letter-spacing:0.3em;color:var(--gris)">ADMIN</small></div>
    <nav class="sidebar-nav">
      <a href="dashboard.php">📊 &nbsp;Dashboard</a>
      <a href="reservations.php" class="actif">📋 &nbsp;Réservations</a>
      <a href="chambres.php">🛏️ &nbsp;Chambres</a>
      <a href="employes.php">👥 &nbsp;Employés</a>
      <a href="statistiques.php">📈 &nbsp;Statistiques</a>
    </nav>
    <div style="position:absolute;bottom:2rem;left:0;right:0;padding:0 1.8rem">
      <div style="font-size:0.78rem;color:var(--gris);margin-bottom:0.8rem">👤 <?= htmlspecialchars($_SESSION['admin_nom']) ?></div>
      <a href="logout.php" style="font-size:0.78rem;color:var(--bordeaux)">⬚ &nbsp;Déconnexion</a>
    </div>
  </aside>

  <main class="admin-contenu">
    <h1 class="admin-titre">Réservations Chambres</h1>

    <!-- Filtres -->
    <div style="display:flex;gap:0.8rem;margin-bottom:2rem;flex-wrap:wrap">
      <?php foreach (['tous','en_attente','confirmee','annulee','terminee'] as $s): ?>
      <a href="?statut=<?= $s ?>" class="btn <?= $filtre===$s ? 'btn-or' : 'btn-contour' ?>" style="padding:0.5rem 1.2rem;font-size:0.58rem">
        <span><?= ucfirst(str_replace('_',' ',$s)) ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <div style="background:var(--noir-2);border:1px solid rgba(201,168,76,0.08);padding:2rem;overflow-x:auto">
      <table class="tableau">
        <thead>
          <tr><th>Référence</th><th>Client</th><th>Email</th><th>Chambre</th><th>Arrivée</th><th>Départ</th><th>Pers.</th><th>Total</th><th>Statut</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($reservations as $r): ?>
          <tr>
            <td style="font-family:'Cinzel',serif;font-size:0.68rem;color:var(--or)"><?= htmlspecialchars($r['reference']) ?></td>
            <td><?= htmlspecialchars($r['nom_client']) ?></td>
            <td style="font-size:0.78rem;color:var(--gris)"><?= htmlspecialchars($r['email_client']) ?></td>
            <td><?= htmlspecialchars($r['type_chambre']) ?> #<?= htmlspecialchars($r['numero']) ?></td>
            <td><?= date('d/m/Y', strtotime($r['date_arrivee'])) ?></td>
            <td><?= date('d/m/Y', strtotime($r['date_depart'])) ?></td>
            <td style="text-align:center"><?= (int)$r['nb_personnes'] ?></td>
            <td style="color:var(--or)"><?= number_format($r['prix_total'],0,',',' ') ?> €</td>
            <td><span class="badge-statut badge-<?= $r['statut'] ?>"><?= $r['statut'] ?></span></td>
            <td>
              <?php if ($r['statut'] === 'en_attente'): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button type="submit" name="action" value="confirmee" style="background:rgba(76,175,80,0.15);color:#4CAF50;border:none;padding:0.3rem 0.8rem;cursor:pointer;font-size:0.72rem;margin-right:4px">✓ Confirmer</button>
                <button type="submit" name="action" value="annulee"   style="background:rgba(229,57,53,0.15);color:#E53935;border:none;padding:0.3rem 0.8rem;cursor:pointer;font-size:0.72rem">✗ Annuler</button>
              </form>
              <?php elseif ($r['statut'] === 'confirmee'): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button type="submit" name="action" value="terminee" style="background:rgba(201,168,76,0.15);color:var(--or);border:none;padding:0.3rem 0.8rem;cursor:pointer;font-size:0.72rem">✓ Terminée</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($reservations)): ?>
          <tr><td colspan="10" style="text-align:center;padding:2rem;color:var(--gris)">Aucune réservation trouvée.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
</body>
</html>
