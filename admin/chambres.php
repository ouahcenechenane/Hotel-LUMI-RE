<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireAnyAuth();
requirePermission('gerer_chambres');

$pdo = getDB();
$page_active = 'chambres';
$message = '';
$erreur  = '';

// ── Ajouter colonne disponible_le si elle n'existe pas encore ──
try {
    $pdo->exec("ALTER TABLE rooms ADD COLUMN disponible_le DATE NULL DEFAULT NULL AFTER statut");
} catch (PDOException $e) { /* colonne déjà existante */ }

// ── Libérer automatiquement les chambres planifiées ──────────
$pdo->exec("UPDATE rooms SET statut='disponible', disponible_le=NULL
            WHERE disponible_le IS NOT NULL AND disponible_le <= CURDATE()");

// ── Traitement POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Changer statut
    if ($action === 'statut') {
        $id     = (int)$_POST['room_id'];
        $statut = $_POST['statut'];
        if (in_array($statut, ['disponible','occupee','maintenance'])) {
            $pdo->prepare("UPDATE rooms SET statut=:s, disponible_le=NULL WHERE id=:id")
                ->execute([':s'=>$statut, ':id'=>$id]);
            $message = "Statut mis à jour.";
        }
    }

    // Changer prix du type
    if ($action === 'prix') {
        $type_id = (int)$_POST['type_id'];
        $prix    = (float)$_POST['nouveau_prix'];
        if ($prix > 0) {
            $pdo->prepare("UPDATE room_types SET prix_nuit=:p WHERE id=:id")
                ->execute([':p'=>$prix, ':id'=>$type_id]);
            $message = "Tarif mis à jour avec succès.";
        } else {
            $erreur = "Le prix doit être supérieur à 0.";
        }
    }

    // Planifier disponibilité
    if ($action === 'planifier') {
        $id   = (int)$_POST['room_id'];
        $date = $_POST['disponible_le'];
        if ($date && $date >= date('Y-m-d')) {
            $pdo->prepare("UPDATE rooms SET statut='maintenance', disponible_le=:d WHERE id=:id")
                ->execute([':d'=>$date, ':id'=>$id]);
            $message = "Disponibilité planifiée au " . date('d/m/Y', strtotime($date)) . ".";
        } else {
            $erreur = "Date invalide — elle doit être aujourd'hui ou dans le futur.";
        }
    }

    header('Location: chambres.php?msg=' . urlencode($message) . '&err=' . urlencode($erreur));
    exit;
}

if (!empty($_GET['msg'])) $message = $_GET['msg'];
if (!empty($_GET['err'])) $erreur  = $_GET['err'];

// ── Données ───────────────────────────────────────────────────
$rooms = $pdo->query("
    SELECT r.*, rt.nom as type_nom, rt.prix_nuit, rt.id as type_id, rt.capacite_max
    FROM rooms r
    JOIN room_types rt ON r.room_type_id = rt.id
    ORDER BY r.etage ASC, r.numero ASC
")->fetchAll();

$types = $pdo->query("SELECT * FROM room_types ORDER BY prix_nuit ASC")->fetchAll();

$stats = ['disponibles'=>0,'occupees'=>0,'maintenance'=>0,'planifiees'=>0];
foreach ($rooms as $r) {
    if ($r['statut'] === 'disponible')  $stats['disponibles']++;
    if ($r['statut'] === 'occupee')     $stats['occupees']++;
    if ($r['statut'] === 'maintenance') $stats['maintenance']++;
    if (!empty($r['disponible_le']))    $stats['planifiees']++;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chambres — Admin · Hôtel Lumière</title>
  <link rel="stylesheet" href="../hotel-html/css/style.css">
  <style>
    /* ─ Modals ─ */
    .modal-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,0.8); z-index:9999;
      align-items:center; justify-content:center;
    }
    .modal-overlay.open { display:flex; }
    .modal-box {
      background:var(--noir-2);
      border:1px solid rgba(201,168,76,0.3);
      padding:2.5rem; width:100%; max-width:450px;
      position:relative; animation:fadeInUp .3s ease;
    }
    .modal-titre { font-family:'Cormorant Garamond',serif; font-size:1.7rem; color:var(--or); margin-bottom:1.5rem; }
    .modal-close { position:absolute; top:1rem; right:1.2rem; background:none; border:none; color:var(--gris); font-size:1.4rem; cursor:pointer; transition:.2s; }
    .modal-close:hover { color:var(--ivoire); }
    .modal-box label { display:block; font-family:'Cinzel',serif; font-size:.6rem; letter-spacing:.2em; color:var(--or); margin-bottom:.4rem; }
    .modal-box input, .modal-box select {
      width:100%; background:rgba(255,255,255,.04);
      border:1px solid rgba(201,168,76,.2); color:var(--ivoire);
      padding:.85rem 1rem; font-family:var(--ff-corps); font-size:.9rem;
      margin-bottom:1.2rem; outline:none; border-radius:0; -webkit-appearance:none;
      transition:border-color .3s;
    }
    .modal-box input:focus, .modal-box select:focus { border-color:var(--or); }
    .modal-box select option { background:var(--noir-2); }

    /* ─ Action buttons ─ */
    .btn-act {
      display:inline-flex; align-items:center; gap:.3rem;
      padding:.32rem .75rem; font-size:.65rem; font-family:'Cinzel',serif;
      letter-spacing:.08em; border:1px solid; cursor:pointer; transition:.25s;
      white-space:nowrap;
    }
    .btn-act:hover { opacity:.8; }
    .btn-statut  { background:rgba(255,152,0,.1);  color:#FF9800; border-color:rgba(255,152,0,.3); }
    .btn-planif  { background:rgba(76,175,80,.1);   color:#4CAF50; border-color:rgba(76,175,80,.3); }
    .btn-prix-t  { background:rgba(201,168,76,.1);  color:var(--or); border-color:rgba(201,168,76,.3); }

    /* ─ Mini stats ─ */
    .mini-stats { display:flex; gap:1rem; margin-bottom:2rem; flex-wrap:wrap; }
    .mini-stat  { flex:1; min-width:130px; background:var(--noir-2); border:1px solid rgba(201,168,76,.1); padding:1.1rem 1.4rem; position:relative; overflow:hidden; }
    .mini-stat-bar { position:absolute; left:0; top:0; bottom:0; width:3px; }
    .mini-stat-val { font-family:'Cormorant Garamond',serif; font-size:2rem; line-height:1; }
    .mini-stat-label { font-size:.7rem; color:var(--gris); margin-top:.3rem; letter-spacing:.06em; }

    /* ─ Prix cards ─ */
    .prix-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:1rem; margin-top:1.4rem; }
    .prix-card { background:var(--noir-3); border:1px solid rgba(201,168,76,.1); padding:1.2rem 1.5rem; }
    .prix-card-nom { font-family:'Cormorant Garamond',serif; font-size:1.05rem; color:var(--ivoire-2); margin-bottom:.3rem; }
    .prix-card-val { font-family:'Cormorant Garamond',serif; font-size:1.9rem; color:var(--or); margin-bottom:.3rem; }
    .prix-card-val small { font-size:.75rem; color:var(--gris); }

    /* ─ Alertes ─ */
    .alerte { padding:.8rem 1.1rem; margin-bottom:1.5rem; font-size:.88rem; border-left:3px solid; }
    .alerte-ok  { background:rgba(76,175,80,.1);  border-color:#4CAF50; color:#4CAF50; }
    .alerte-err { background:rgba(229,57,53,.1); border-color:#E53935; color:#E53935; }

    .planif-badge { font-size:.68rem; color:#29B6F6; display:block; margin-top:3px; }

    @keyframes fadeInUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
  </style>
</head>
<body>
<div class="admin-layout">

  <!-- SIDEBAR -->
  <aside class="admin-sidebar">
    <div class="sidebar-logo">LUMIÈRE<br><small style="font-size:.55rem;letter-spacing:.3em;color:var(--gris)">ADMIN</small></div>
    <nav class="sidebar-nav">
      <a href="dashboard.php">📊 &nbsp;Dashboard</a>
      <a href="reservations.php">📋 &nbsp;Réservations</a>
      <a href="chambres.php" class="actif">🛏️ &nbsp;Chambres</a>
      <a href="employes.php">👥 &nbsp;Employés</a>
      <a href="statistiques.php">📈 &nbsp;Statistiques</a>
    </nav>
    <div style="position:absolute;bottom:2rem;left:0;right:0;padding:0 1.8rem">
      <div style="font-size:.78rem;color:var(--gris);margin-bottom:.8rem">👤 <?= htmlspecialchars($_SESSION['admin_nom']) ?></div>
      <a href="logout.php" style="font-size:.78rem;color:var(--bordeaux)">⬚ &nbsp;Déconnexion</a>
    </div>
  </aside>

  <!-- CONTENU -->
  <main class="admin-contenu">
    <h1 class="admin-titre">Gestion des Chambres</h1>

    <?php if ($message): ?><div class="alerte alerte-ok">✓ <?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte alerte-err">✗ <?= htmlspecialchars($erreur) ?></div><?php endif; ?>

    <!-- Mini stats -->
    <div class="mini-stats">
      <div class="mini-stat">
        <div class="mini-stat-bar" style="background:#4CAF50"></div>
        <div class="mini-stat-val" style="color:#4CAF50"><?= $stats['disponibles'] ?></div>
        <div class="mini-stat-label">Disponibles</div>
      </div>
      <div class="mini-stat">
        <div class="mini-stat-bar" style="background:#FF9800"></div>
        <div class="mini-stat-val" style="color:#FF9800"><?= $stats['occupees'] ?></div>
        <div class="mini-stat-label">Occupées</div>
      </div>
      <div class="mini-stat">
        <div class="mini-stat-bar" style="background:#E53935"></div>
        <div class="mini-stat-val" style="color:#E53935"><?= $stats['maintenance'] ?></div>
        <div class="mini-stat-label">Maintenance</div>
      </div>
      <div class="mini-stat">
        <div class="mini-stat-bar" style="background:#29B6F6"></div>
        <div class="mini-stat-val" style="color:#29B6F6"><?= $stats['planifiees'] ?></div>
        <div class="mini-stat-label">Dates planifiées</div>
      </div>
      <div class="mini-stat">
        <div class="mini-stat-bar" style="background:var(--or)"></div>
        <div class="mini-stat-val" style="color:var(--or)"><?= count($rooms) ?></div>
        <div class="mini-stat-label">Total chambres</div>
      </div>
    </div>

    <!-- ══ TARIFS PAR TYPE ══ -->
    <div style="background:var(--noir-2);border:1px solid rgba(201,168,76,.08);padding:2rem;margin-bottom:2rem">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.5rem">
          Tarifs par type de chambre
        </h2>
        <span style="font-size:.75rem;color:var(--gris)">Cliquez pour modifier</span>
      </div>
      <div class="prix-grid">
        <?php foreach ($types as $t): ?>
        <div class="prix-card">
          <div class="prix-card-nom"><?= htmlspecialchars($t['nom']) ?></div>
          <div class="prix-card-val">
            <?= number_format($t['prix_nuit'],0,',',' ') ?> <small>€/nuit</small>
          </div>
          <div style="font-size:.72rem;color:var(--gris);margin-bottom:.8rem">
            Max <?= (int)$t['capacite_max'] ?> personnes
          </div>
          <button class="btn-act btn-prix-t" style="width:100%;justify-content:center"
            onclick="ouvrirPrix(<?= $t['id'] ?>, '<?= addslashes($t['nom']) ?>', <?= $t['prix_nuit'] ?>)">
            ✏️ Modifier le tarif
          </button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ══ LISTE CHAMBRES ══ -->
    <div style="background:var(--noir-2);border:1px solid rgba(201,168,76,.08);padding:2rem;overflow-x:auto">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
        <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.5rem">
          Toutes les chambres — <?= count($rooms) ?> au total
        </h2>
        <!-- Filtres -->
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
          <button class="btn-act btn-prix-t"  onclick="filtrer('tous',this)"        style="font-weight:700">Toutes</button>
          <button class="btn-act btn-planif"   onclick="filtrer('disponible',this)"  >Disponibles</button>
          <button class="btn-act btn-statut"   onclick="filtrer('occupee',this)"     >Occupées</button>
          <button class="btn-act" style="background:rgba(229,57,53,.1);color:#E53935;border-color:rgba(229,57,53,.3)"
                  onclick="filtrer('maintenance',this)">Maintenance</button>
        </div>
      </div>

      <table class="tableau" id="tbl">
        <thead>
          <tr>
            <th>Chambre</th>
            <th>Étage</th>
            <th>Type</th>
            <th style="text-align:center">Capacité</th>
            <th>Prix / nuit</th>
            <th>Statut</th>
            <th>Disponible le</th>
            <th style="text-align:center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rooms as $r):
            $bclass = ['disponible'=>'badge-confirmee','occupee'=>'badge-attente','maintenance'=>'badge-annulee'][$r['statut']] ?? 'badge-attente';
          ?>
          <tr data-statut="<?= $r['statut'] ?>">
            <td><span style="font-family:'Cinzel',serif;color:var(--or);font-size:.78rem">#<?= htmlspecialchars($r['numero']) ?></span></td>
            <td style="color:var(--gris)">Étage <?= (int)$r['etage'] ?></td>
            <td><?= htmlspecialchars($r['type_nom']) ?></td>
            <td style="text-align:center;color:var(--gris)"><?= (int)$r['capacite_max'] ?> pers.</td>
            <td>
              <span style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;color:var(--or)">
                <?= number_format($r['prix_nuit'],0,',',' ') ?> €
              </span>
            </td>
            <td><span class="badge-statut <?= $bclass ?>"><?= $r['statut'] ?></span></td>
            <td>
              <?php if (!empty($r['disponible_le'])): ?>
                <span class="planif-badge">📅 <?= date('d/m/Y', strtotime($r['disponible_le'])) ?></span>
              <?php else: ?>
                <span style="color:var(--gris);font-size:.75rem">—</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:.4rem;justify-content:center;flex-wrap:wrap">
                <button class="btn-act btn-statut"
                  onclick="ouvrirStatut(<?= $r['id'] ?>, '<?= htmlspecialchars($r['numero']) ?>', '<?= $r['statut'] ?>')">
                  ⟳ Statut
                </button>
                <button class="btn-act btn-planif"
                  onclick="ouvrirPlanif(<?= $r['id'] ?>, '<?= htmlspecialchars($r['numero']) ?>', '<?= $r['disponible_le'] ?? '' ?>')">
                  📅 Planifier
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<!-- ══ MODAL Statut ══ -->
<div class="modal-overlay" id="m-statut">
  <div class="modal-box">
    <button class="modal-close" onclick="fermer()">✕</button>
    <div class="modal-titre">Changer le Statut</div>
    <p style="color:var(--gris);font-size:.85rem;margin-bottom:1.5rem">
      Chambre <strong id="ms-num" style="color:var(--ivoire)"></strong>
    </p>
    <form method="POST">
      <input type="hidden" name="action" value="statut">
      <input type="hidden" name="room_id" id="ms-id">
      <label>Nouveau statut</label>
      <select name="statut" id="ms-val">
        <option value="disponible">✅ Disponible</option>
        <option value="occupee">🟠 Occupée</option>
        <option value="maintenance">🔴 En maintenance</option>
      </select>
      <button type="submit" class="btn btn-or" style="width:100%;justify-content:center;padding:.9rem">
        <span>Appliquer</span>
      </button>
    </form>
  </div>
</div>

<!-- ══ MODAL Planification ══ -->
<div class="modal-overlay" id="m-planif">
  <div class="modal-box">
    <button class="modal-close" onclick="fermer()">✕</button>
    <div class="modal-titre">Planifier la Disponibilité</div>
    <p style="color:var(--gris);font-size:.85rem;margin-bottom:1.5rem">
      Chambre <strong id="mp-num" style="color:var(--ivoire)"></strong><br>
      <span style="font-size:.78rem;line-height:1.6">
        La chambre passera en <strong style="color:#FF9800">maintenance</strong> et redeviendra
        <strong style="color:#4CAF50">disponible</strong> automatiquement à la date choisie.
      </span>
    </p>
    <form method="POST">
      <input type="hidden" name="action" value="planifier">
      <input type="hidden" name="room_id" id="mp-id">
      <label>Date de remise en disponibilité</label>
      <input type="date" name="disponible_le" id="mp-date" required>
      <button type="submit" class="btn btn-or" style="width:100%;justify-content:center;padding:.9rem">
        <span>📅 Confirmer</span>
      </button>
    </form>
  </div>
</div>

<!-- ══ MODAL Prix ══ -->
<div class="modal-overlay" id="m-prix">
  <div class="modal-box">
    <button class="modal-close" onclick="fermer()">✕</button>
    <div class="modal-titre">Modifier le Tarif</div>
    <p style="color:var(--gris);font-size:.85rem;margin-bottom:.5rem">
      Type : <strong id="mpr-nom" style="color:var(--ivoire)"></strong>
    </p>
    <div id="mpr-actuel" style="font-family:'Cormorant Garamond',serif;font-size:2rem;color:var(--or);margin-bottom:1.5rem"></div>
    <form method="POST">
      <input type="hidden" name="action" value="prix">
      <input type="hidden" name="type_id" id="mpr-id">
      <label>Nouveau tarif (€ / nuit)</label>
      <input type="number" name="nouveau_prix" id="mpr-val" min="1" step="1" placeholder="Ex : 350" required>
      <p style="font-size:.75rem;color:#FF9800;margin-bottom:1.2rem">
        ⚠️ Ce tarif s'appliquera à toutes les chambres de ce type.
      </p>
      <button type="submit" class="btn btn-or" style="width:100%;justify-content:center;padding:.9rem">
        <span>✓ Enregistrer</span>
      </button>
    </form>
  </div>
</div>

<script>
const today = new Date().toISOString().split('T')[0];
document.getElementById('mp-date').min = today;

function fermer() {
  document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open'));
}
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) fermer(); });
});

function ouvrirStatut(id, num, statut) {
  document.getElementById('ms-id').value  = id;
  document.getElementById('ms-num').textContent = '#' + num;
  document.getElementById('ms-val').value = statut;
  document.getElementById('m-statut').classList.add('open');
}
function ouvrirPlanif(id, num, date) {
  document.getElementById('mp-id').value   = id;
  document.getElementById('mp-num').textContent = '#' + num;
  document.getElementById('mp-date').value = date || '';
  document.getElementById('m-planif').classList.add('open');
}
function ouvrirPrix(typeId, nom, prix) {
  document.getElementById('mpr-id').value  = typeId;
  document.getElementById('mpr-nom').textContent = nom;
  document.getElementById('mpr-actuel').textContent = prix.toLocaleString('fr-FR') + ' €/nuit';
  document.getElementById('mpr-val').value = prix;
  document.getElementById('m-prix').classList.add('open');
}

function filtrer(statut, btn) {
  document.querySelectorAll('#tbl tbody tr').forEach(tr => {
    tr.style.display = (statut === 'tous' || tr.dataset.statut === statut) ? '' : 'none';
  });
  // Feedback visuel sur le bouton actif
  btn.style.fontWeight = '700';
  setTimeout(() => btn.style.fontWeight = '', 800);
}
</script>
</body>
</html>