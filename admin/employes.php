<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireSuperAdmin();

$pdo = getDB();
$page_active = 'employes';
$employes = $pdo->query("SELECT * FROM employees ORDER BY departement, nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employés — Admin · Hôtel Lumière</title>
  <link rel="stylesheet" href="../hotel-html/css/style.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="sidebar-logo">LUMIÈRE<br><small style="font-size:0.55rem;letter-spacing:0.3em;color:var(--gris)">ADMIN</small></div>
    <nav class="sidebar-nav">
      <a href="dashboard.php">📊 &nbsp;Dashboard</a>
      <a href="reservations.php">📋 &nbsp;Réservations</a>
      <a href="chambres.php">🛏️ &nbsp;Chambres</a>
      <a href="employes.php" class="actif">👥 &nbsp;Employés</a>
      <a href="statistiques.php">📈 &nbsp;Statistiques</a>
    </nav>
    <div style="position:absolute;bottom:2rem;left:0;right:0;padding:0 1.8rem">
      <a href="logout.php" style="font-size:0.78rem;color:var(--bordeaux)">⬚ &nbsp;Déconnexion</a>
    </div>
  </aside>
  <main class="admin-contenu">
    <h1 class="admin-titre">Gestion des Employés</h1>
    <div style="background:var(--noir-2);border:1px solid rgba(201,168,76,0.08);padding:2rem;overflow-x:auto">
      <table class="tableau">
        <thead><tr><th>Matricule</th><th>Nom</th><th>Prénom</th><th>Poste</th><th>Département</th><th>Salaire de base</th><th>Date embauche</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach ($employes as $e): ?>
          <tr>
            <td style="font-family:'Cinzel',serif;font-size:0.7rem;color:var(--or)"><?= htmlspecialchars($e['matricule']) ?></td>
            <td><?= htmlspecialchars($e['nom']) ?></td>
            <td><?= htmlspecialchars($e['prenom']) ?></td>
            <td><?= htmlspecialchars($e['poste']) ?></td>
            <td style="color:var(--gris)"><?= htmlspecialchars($e['departement']) ?></td>
            <td style="color:var(--or)"><?= number_format($e['salaire_base'],2,',',' ') ?> €</td>
            <td><?= date('d/m/Y', strtotime($e['date_embauche'])) ?></td>
            <td>
              <span class="badge-statut <?= $e['statut']==='actif'?'badge-confirmee':($e['statut']==='conge'?'badge-attente':'badge-annulee') ?>">
                <?= $e['statut'] ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
</body>
</html>
