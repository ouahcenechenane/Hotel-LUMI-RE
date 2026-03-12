<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireAnyAuth();
requirePermission('voir_chambres');
$pdo = getDB();
$page_active = 'res_chambres';
$msg = ''; $err = '';

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'statut' && $id) {
        $s = $_POST['statut'] ?? '';
        if (in_array($s, ['en_attente','confirmee','annulee','terminee'])) {
            $pdo->prepare("UPDATE reservations SET statut=:s WHERE id=:id")->execute([':s'=>$s,':id'=>$id]);
            $msg = "Statut mis à jour avec succès.";
        }
    }

    if ($action === 'modifier' && $id) {
        $pdo->prepare("UPDATE reservations SET nom_client=:nom, email_client=:email, telephone=:tel, nb_personnes=:pers, statut=:s WHERE id=:id")
            ->execute([':nom'=>sanitize($_POST['nom_client']??''), ':email'=>sanitize($_POST['email_client']??''), ':tel'=>sanitize($_POST['telephone']??''), ':pers'=>(int)($_POST['nb_personnes']??1), ':s'=>$_POST['statut']??'en_attente', ':id'=>$id]);
        $msg = "Réservation modifiée.";
    }

    if ($action === 'supprimer' && $id) {
        $pdo->prepare("DELETE FROM reservations WHERE id=:id")->execute([':id'=>$id]);
        $msg = "Réservation supprimée.";
    }
    header('Location: reservations_chambres.php?msg='.urlencode($msg).'&err='.urlencode($err)); exit;
}

if (!empty($_GET['msg'])) $msg = $_GET['msg'];
if (!empty($_GET['err'])) $err = $_GET['err'];

$filtre = $_GET['statut'] ?? 'tous';
$sql = "SELECT r.*, rt.nom AS type_chambre, rm.numero FROM reservations r JOIN rooms rm ON r.room_id=rm.id JOIN room_types rt ON rm.room_type_id=rt.id";
$params = [];
if ($filtre !== 'tous') { $sql .= " WHERE r.statut=:s"; $params[':s'] = $filtre; }
$sql .= " ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$reservations = $stmt->fetchAll();

$counts = [];
foreach(['en_attente','confirmee','annulee','terminee'] as $s) {
    $counts[$s] = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE statut='$s'")->fetchColumn();
}
$counts['tous'] = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Réservations Chambres — Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,400&family=Montserrat:wght@300;400;500&family=Cinzel:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../hotel-html/css/style.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-contenu">
    <h1 class="admin-titre">🛏️ Réservations Chambres</h1>
    <div class="admin-breadcrumb"><a href="dashboard.php">Dashboard</a> / Réservations Chambres</div>

    <?php if ($msg): ?><div class="alerte alerte-ok">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alerte alerte-err">✗ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- Mini stats -->
    <div class="mini-stat-row">
      <?php $labels=['tous'=>'Total','en_attente'=>'En attente','confirmee'=>'Confirmées','annulee'=>'Annulées','terminee'=>'Terminées'];
      $colors=['tous'=>'var(--or)','en_attente'=>'#FF9800','confirmee'=>'#4CAF50','annulee'=>'#E53935','terminee'=>'#29B6F6'];
      foreach($labels as $k=>$l): ?>
      <div class="mini-stat-chip"><strong style="color:<?= $colors[$k] ?>"><?= $counts[$k] ?></strong> <?= $l ?></div>
      <?php endforeach; ?>
    </div>

    <!-- Filtres -->
    <div class="filtres-bar">
      <?php foreach(['tous'=>'Toutes','en_attente'=>'En attente','confirmee'=>'Confirmées','annulee'=>'Annulées','terminee'=>'Terminées'] as $k=>$l): ?>
      <a href="?statut=<?= $k ?>" class="filtre-btn <?= $filtre===$k?'actif':'' ?>"><?= $l ?> (<?= $counts[$k] ?>)</a>
      <?php endforeach; ?>
      <input type="text" id="searchInput" class="search-input" placeholder="🔍 Rechercher client, référence..." oninput="filtrerTable()">
    </div>

    <div class="panel" style="padding:0">
      <div class="tableau-wrap" style="padding:0">
        <table class="tableau" id="mainTable">
          <thead><tr>
            <th>Référence</th><th>Client</th><th>Email</th><th>Tél.</th>
            <th>Chambre</th><th>Arrivée</th><th>Départ</th><th>Nuits</th>
            <th>Pers.</th><th>Total</th><th>Statut</th><th>Actions</th>
          </tr></thead>
          <tbody>
            <?php foreach ($reservations as $r): $nuits = nbNuits($r['date_arrivee'],$r['date_depart']); ?>
            <tr>
              <td style="font-family:'Cinzel',serif;font-size:0.66rem;color:var(--or)"><?= htmlspecialchars($r['reference']) ?></td>
              <td style="font-weight:500"><?= htmlspecialchars($r['nom_client']) ?></td>
              <td style="font-size:0.78rem;color:var(--gris)"><?= htmlspecialchars($r['email_client']) ?></td>
              <td style="font-size:0.78rem;color:var(--gris)"><?= htmlspecialchars($r['telephone']??'—') ?></td>
              <td><?= htmlspecialchars($r['type_chambre']) ?> <span style="color:var(--or)">#<?= $r['numero'] ?></span></td>
              <td><?= date('d/m/Y',strtotime($r['date_arrivee'])) ?></td>
              <td><?= date('d/m/Y',strtotime($r['date_depart'])) ?></td>
              <td style="text-align:center;color:var(--gris)"><?= $nuits ?></td>
              <td style="text-align:center;color:var(--gris)"><?= $r['nb_personnes'] ?></td>
              <td style="color:var(--or);font-weight:500"><?= number_format($r['prix_total'],0,',',' ') ?> €</td>
              <td><span class="badge-statut badge-<?= $r['statut'] ?>"><?= str_replace('_',' ',$r['statut']) ?></span></td>
              <td>
                <div style="display:flex;gap:0.3rem;flex-wrap:nowrap">
                  <button class="btn-icon btn-success" onclick="ouvrirEdit(<?= htmlspecialchars(json_encode($r)) ?>)" title="Modifier">✏️</button>
                  <button class="btn-icon btn-success" onclick="changerStatut(<?= $r['id'] ?>, '<?= $r['nom_client'] ?>')" title="Statut">⟳</button>
                  <button class="btn-icon btn-danger"  onclick="confirmerSupp(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['nom_client'])) ?>')" title="Supprimer">🗑️</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($reservations)): ?>
            <tr><td colspan="12" style="text-align:center;padding:2.5rem;color:var(--gris)">Aucune réservation trouvée.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Modal Modifier -->
<div class="modal-overlay" id="m-edit">
  <div class="modal-box">
    <button class="modal-close" onclick="fermerModals()">✕</button>
    <div class="modal-titre">Modifier la Réservation</div>
    <form method="POST">
      <input type="hidden" name="action" value="modifier">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-grille-2">
        <div class="form-groupe"><label>Nom client</label><input type="text" name="nom_client" id="edit-nom" required></div>
        <div class="form-groupe"><label>Email</label><input type="email" name="email_client" id="edit-email" required></div>
        <div class="form-groupe"><label>Téléphone</label><input type="text" name="telephone" id="edit-tel"></div>
        <div class="form-groupe"><label>Nb personnes</label><input type="number" name="nb_personnes" id="edit-pers" min="1" max="10"></div>
      </div>
      <div class="form-groupe"><label>Statut</label>
        <select name="statut" id="edit-statut">
          <option value="en_attente">En attente</option>
          <option value="confirmee">Confirmée</option>
          <option value="annulee">Annulée</option>
          <option value="terminee">Terminée</option>
        </select>
      </div>
      <button type="submit" class="btn btn-or" style="width:100%;justify-content:center"><span>💾 Enregistrer</span></button>
    </form>
  </div>
</div>

<!-- Modal Statut Rapide -->
<div class="modal-overlay" id="m-statut">
  <div class="modal-box" style="max-width:380px">
    <button class="modal-close" onclick="fermerModals()">✕</button>
    <div class="modal-titre">Changer le Statut</div>
    <p style="color:var(--gris);font-size:0.85rem;margin-bottom:1.2rem">Client : <strong id="ms-nom" style="color:var(--ivoire)"></strong></p>
    <form method="POST">
      <input type="hidden" name="action" value="statut">
      <input type="hidden" name="id" id="ms-id">
      <div class="form-groupe"><label>Nouveau statut</label>
        <select name="statut">
          <option value="en_attente">⏳ En attente</option>
          <option value="confirmee">✅ Confirmée</option>
          <option value="annulee">❌ Annulée</option>
          <option value="terminee">🏁 Terminée</option>
        </select>
      </div>
      <button type="submit" class="btn btn-or" style="width:100%;justify-content:center"><span>Appliquer</span></button>
    </form>
  </div>
</div>

<!-- Modal Suppression -->
<div class="modal-overlay" id="m-supp">
  <div class="modal-box" style="max-width:380px">
    <button class="modal-close" onclick="fermerModals()">✕</button>
    <div class="modal-titre" style="color:#E53935">⚠️ Confirmer la suppression</div>
    <p style="color:var(--gris);margin-bottom:1.5rem">Supprimer la réservation de <strong id="supp-nom" style="color:var(--ivoire)"></strong> ? Cette action est irréversible.</p>
    <form method="POST" style="display:flex;gap:0.8rem">
      <input type="hidden" name="action" value="supprimer">
      <input type="hidden" name="id" id="supp-id">
      <button type="button" class="btn btn-contour" style="flex:1;justify-content:center" onclick="fermerModals()"><span>Annuler</span></button>
      <button type="submit" class="btn btn-danger" style="flex:1;justify-content:center"><span>🗑️ Supprimer</span></button>
    </form>
  </div>
</div>

<script>
function fermerModals() { document.querySelectorAll('.modal-overlay').forEach(m=>m.classList.remove('open')); }
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)fermerModals();}));

function ouvrirEdit(r) {
  document.getElementById('edit-id').value    = r.id;
  document.getElementById('edit-nom').value   = r.nom_client;
  document.getElementById('edit-email').value = r.email_client;
  document.getElementById('edit-tel').value   = r.telephone||'';
  document.getElementById('edit-pers').value  = r.nb_personnes;
  document.getElementById('edit-statut').value= r.statut;
  document.getElementById('m-edit').classList.add('open');
}
function changerStatut(id, nom) {
  document.getElementById('ms-id').value = id;
  document.getElementById('ms-nom').textContent = nom;
  document.getElementById('m-statut').classList.add('open');
}
function confirmerSupp(id, nom) {
  document.getElementById('supp-id').value = id;
  document.getElementById('supp-nom').textContent = nom;
  document.getElementById('m-supp').classList.add('open');
}
function filtrerTable() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('#mainTable tbody tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>
