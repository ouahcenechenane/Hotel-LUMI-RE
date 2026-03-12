<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireSuperAdmin();
$pdo = getDB();
$page_active = 'gestion_comptes';
$msg = ''; $err = '';

// ── Toutes les permissions disponibles ───────────────────────
$all_permissions = [];
try {
    $rows = $pdo->query("SELECT * FROM permissions_ref ORDER BY categorie,code")->fetchAll();
    foreach($rows as $r) $all_permissions[$r['categorie']][] = $r;
} catch(Exception $e) {}

// ── Liste des employés sans compte ───────────────────────────
$employes_sans_compte = $pdo->query("
    SELECT e.id, e.matricule, CONCAT(e.prenom,' ',e.nom) AS nom_complet, e.poste, e.departement
    FROM employees e
    WHERE e.id NOT IN (SELECT employee_id FROM employee_accounts)
    AND e.statut = 'actif'
    ORDER BY e.nom
")->fetchAll();

// ── Liste des comptes existants ───────────────────────────────
$comptes = $pdo->query("
    SELECT ea.*, CONCAT(emp.prenom,' ',emp.nom) AS nom_complet, emp.poste, emp.matricule
    FROM employee_accounts ea
    JOIN employees emp ON ea.employee_id = emp.id
    ORDER BY ea.service, nom_complet
")->fetchAll();

// ── Traitement formulaires ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'creer') {
        // ── Si "Nouvel employé" sélectionné : créer d'abord l'employé ──
        if (isset($_POST['new_employe']) && $_POST['new_employe'] === '1') {
            $mat      = sanitize($_POST['matricule'] ?? '');
            $new_nom  = sanitize($_POST['new_nom'] ?? '');
            $new_prenom = sanitize($_POST['new_prenom'] ?? '');
            $new_poste  = sanitize($_POST['new_poste'] ?? '');
            $new_dept   = sanitize($_POST['new_departement'] ?? '');
            $new_sal    = floatval($_POST['new_salaire'] ?? 0);
            $new_embauche = $_POST['new_date_embauche'] ?? date('Y-m-d');

            if (!$mat || !$new_nom || !$new_prenom || !$new_poste) {
                $err = "Veuillez remplir tous les champs obligatoires du nouvel employé (matricule, prénom, nom, poste).";
            } else {
                try {
                    $pdo->prepare("INSERT INTO employees (matricule,nom,prenom,poste,departement,salaire_base,date_embauche,statut) VALUES (?,?,?,?,?,?,?,'actif')")
                        ->execute([$mat, $new_nom, $new_prenom, $new_poste, $new_dept, $new_sal, $new_embauche]);
                    $_POST['employee_id'] = $pdo->lastInsertId();
                } catch(Exception $e) {
                    $err = "Erreur : ce matricule est déjà utilisé.";
                }
            }
        }

        if (!$err) {
            $employee_id = (int)($_POST['employee_id'] ?? 0);
            $email       = sanitize($_POST['email'] ?? '');
            $password    = $_POST['password'] ?? '';
            $service     = $_POST['service'] ?? '';
            $perms_post  = $_POST['permissions'] ?? [];

            if (!$employee_id || !$email || !$password || !$service) {
                $err = "Tous les champs obligatoires doivent être remplis.";
            } elseif (strlen($password) < 8) {
                $err = "Le mot de passe doit contenir au moins 8 caractères.";
            } else {
                $permissions = !empty($perms_post) ? array_values($perms_post) : permissionsParDefaut($service);
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                try {
                    $pdo->prepare("INSERT INTO employee_accounts (employee_id,email,password,service,permissions,created_by) VALUES (?,?,?,?,?,?)")
                        ->execute([$employee_id, $email, $hashed, $service, json_encode($permissions), $_SESSION['admin_id']]);
                    logActivity($pdo, 'creer_compte', "Compte créé pour employee_id=$employee_id, service=$service");
                    $msg = "✅ Compte créé avec succès.";
                    header("Location: gestion_comptes.php?msg=".urlencode($msg)); exit;
                } catch(Exception $e) {
                    $err = "Erreur : cet email est déjà utilisé.";
                }
            }
        }
    }

    if ($action === 'modifier_permissions') {
        $compte_id  = (int)($_POST['compte_id'] ?? 0);
        $perms_post = $_POST['permissions'] ?? [];
        $actif      = isset($_POST['actif']) ? 1 : 0;
        if ($compte_id) {
            $permissions = array_values($perms_post);
            $pdo->prepare("UPDATE employee_accounts SET permissions=?,actif=? WHERE id=?")
                ->execute([json_encode($permissions), $actif, $compte_id]);
            logActivity($pdo, 'modifier_compte', "Compte id=$compte_id modifié");
            $msg = "✅ Compte mis à jour.";
            header("Location: gestion_comptes.php?msg=".urlencode($msg)); exit;
        }
    }

    if ($action === 'reset_password') {
        $compte_id   = (int)($_POST['compte_id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';
        if ($compte_id && strlen($new_password) >= 8) {
            $pdo->prepare("UPDATE employee_accounts SET password=? WHERE id=?")
                ->execute([password_hash($new_password, PASSWORD_BCRYPT), $compte_id]);
            logActivity($pdo, 'reset_password', "Password reset compte id=$compte_id");
            $msg = "✅ Mot de passe réinitialisé.";
            header("Location: gestion_comptes.php?msg=".urlencode($msg)); exit;
        } else { $err = "Mot de passe trop court (8 caractères minimum)."; }
    }

    if ($action === 'supprimer') {
        $compte_id = (int)($_POST['compte_id'] ?? 0);
        if ($compte_id) {
            $pdo->prepare("DELETE FROM employee_accounts WHERE id=?")->execute([$compte_id]);
            logActivity($pdo, 'supprimer_compte', "Compte id=$compte_id supprimé");
            $msg = "✅ Compte supprimé.";
            header("Location: gestion_comptes.php?msg=".urlencode($msg)); exit;
        }
    }
}
if (!empty($_GET['msg'])) $msg = $_GET['msg'];
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Gestion des Comptes Employés · Admin Lumière</title>
<link rel="stylesheet" href="../hotel-html/css/style.css">
<link rel="stylesheet" href="css/admin.css">
<style>
.service-badge{display:inline-flex;align-items:center;gap:.4rem;padding:.25rem .75rem;border-radius:2px;font-size:.7rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase}
.sb-reception{background:rgba(33,150,243,.15);color:#64B5F6;border:1px solid rgba(33,150,243,.3)}
.sb-restaurant{background:rgba(255,152,0,.15);color:#FFB74D;border:1px solid rgba(255,152,0,.3)}
.sb-chambres{background:rgba(156,39,176,.15);color:#CE93D8;border:1px solid rgba(156,39,176,.3)}
.sb-spa{background:rgba(0,150,136,.15);color:#4DB6AC;border:1px solid rgba(0,150,136,.3)}
.sb-piscine{background:rgba(3,169,244,.15);color:#4FC3F7;border:1px solid rgba(3,169,244,.3)}
.perm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.5rem;margin-top:.5rem}
.perm-item{display:flex;align-items:center;gap:.6rem;padding:.5rem .7rem;background:rgba(255,255,255,.03);border:1px solid rgba(201,168,76,.07);cursor:pointer;transition:.15s}
.perm-item:hover{background:rgba(201,168,76,.06);border-color:rgba(201,168,76,.2)}
.perm-item input{accent-color:var(--or);width:15px;height:15px;flex-shrink:0}
.perm-item label{font-size:.78rem;color:var(--gris-clair);cursor:pointer;line-height:1.3}
.compte-card{background:var(--noir-2);border:1px solid rgba(201,168,76,.1);padding:1.5rem;margin-bottom:1rem;transition:.2s}
.compte-card:hover{border-color:rgba(201,168,76,.25)}
.compte-actions{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal-box{background:var(--noir-2);border:1px solid rgba(201,168,76,.2);padding:2rem;width:90%;max-width:680px;max-height:85vh;overflow-y:auto}
.modal-title{font-family:'Cinzel',serif;color:var(--or);font-size:1rem;margin-bottom:1.5rem;padding-bottom:.8rem;border-bottom:1px solid rgba(201,168,76,.15)}
.perm-categorie{margin-bottom:1.2rem}
.perm-cat-label{font-size:.65rem;letter-spacing:.2em;text-transform:uppercase;color:var(--or);margin-bottom:.5rem;font-weight:600}
.compte-inactive{opacity:.55}
.stat-bar{display:flex;gap:1.5rem;margin-bottom:2rem;flex-wrap:wrap}
.stat-item{background:var(--noir-2);border:1px solid rgba(201,168,76,.1);padding:1rem 1.5rem;flex:1;min-width:120px;text-align:center}
.stat-num{font-family:'Cinzel',serif;font-size:1.8rem;color:var(--or);display:block}
.stat-lbl{font-size:.65rem;color:var(--gris);letter-spacing:.15em;text-transform:uppercase}
.toggle-actif{background:none;border:1px solid;padding:.3rem .8rem;font-size:.7rem;cursor:pointer;letter-spacing:.1em;text-transform:uppercase;transition:.2s}
.toggle-on{border-color:#4CAF50;color:#4CAF50}.toggle-on:hover{background:rgba(76,175,80,.15)}
.toggle-off{border-color:#E53935;color:#E53935}.toggle-off:hover{background:rgba(229,57,53,.15)}

/* ── Bloc nouvel employé ── */
.bloc-nouvel-employe{
  display:none;
  grid-column:1/-1;
  border:1px solid rgba(201,168,76,.25);
  padding:1.2rem;
  background:rgba(201,168,76,.04);
  margin-top:.4rem;
  animation:fadeIn .2s ease;
}
@keyframes fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.bloc-nouvel-employe .bloc-titre{
  font-size:.65rem;letter-spacing:.2em;text-transform:uppercase;
  color:var(--or);margin-bottom:1rem;font-weight:600;
  display:flex;align-items:center;gap:.5rem;
}
.bloc-nouvel-employe .inner-grid{
  display:grid;grid-template-columns:1fr 1fr;gap:.9rem;
}
</style>
</head><body>
<div class="admin-layout">
<?php include 'includes/sidebar.php'; ?>
<main class="admin-contenu">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:2rem;flex-wrap:wrap;gap:1rem">
    <div>
      <h1 class="admin-titre" style="margin-bottom:.3rem">Gestion des Comptes Employés</h1>
      <p style="color:var(--gris);font-size:.82rem">Créez et gérez les accès des employés à l'espace d'administration.</p>
    </div>
    <button onclick="document.getElementById('modal-creer').classList.add('open')" class="btn btn-or">
      <span>＋ Nouveau compte</span>
    </button>
  </div>

  <?php if($msg):?><div class="alerte-succes" style="margin-bottom:1.5rem;padding:1rem;background:rgba(76,175,80,.1);border-left:3px solid #4CAF50;color:#81C784"><?=htmlspecialchars($msg)?></div><?php endif;?>
  <?php if($err):?><div class="alerte" style="margin-bottom:1.5rem;padding:1rem;background:rgba(229,57,53,.1);border-left:3px solid #E53935;color:#E53935"><?=htmlspecialchars($err)?></div><?php endif;?>

  <!-- Stats par service -->
  <div class="stat-bar">
    <?php
    $services = ['reception','restaurant','chambres','spa','piscine'];
    $emojis = ['reception'=>'🏨','restaurant'=>'🍽️','chambres'=>'🛏️','spa'=>'🧖','piscine'=>'🏊'];
    $labels = ['reception'=>'Réception','restaurant'=>'Restaurant','chambres'=>'Chambres','spa'=>'Spa','piscine'=>'Piscine'];
    foreach($services as $srv):
        $count = count(array_filter($comptes, fn($c)=>$c['service']===$srv));
    ?>
    <div class="stat-item">
      <span class="stat-num"><?=$emojis[$srv]?> <?=$count?></span>
      <span class="stat-lbl"><?=$labels[$srv]?></span>
    </div>
    <?php endforeach;?>
    <div class="stat-item">
      <span class="stat-num" style="color:#81C784"><?=count(array_filter($comptes,fn($c)=>$c['actif']))?></span>
      <span class="stat-lbl">Actifs</span>
    </div>
  </div>

  <!-- Comptes par service -->
  <?php foreach($services as $srv):
    $comptes_srv = array_filter($comptes, fn($c)=>$c['service']===$srv);
    if(empty($comptes_srv)) continue;
  ?>
  <div style="margin-bottom:2.5rem">
    <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:1rem;padding-bottom:.6rem;border-bottom:1px solid rgba(201,168,76,.12)">
      <span style="font-size:1.3rem"><?=$emojis[$srv]?></span>
      <h2 style="font-family:'Cinzel',serif;color:var(--or);font-size:.9rem;letter-spacing:.1em;text-transform:uppercase"><?=$labels[$srv]?></h2>
      <span style="background:rgba(201,168,76,.1);color:var(--or);padding:.15rem .6rem;font-size:.7rem;border-radius:2px"><?=count($comptes_srv)?></span>
    </div>
    <?php foreach($comptes_srv as $c):
      $perms = json_decode($c['permissions'], true) ?? [];
    ?>
    <div class="compte-card <?=$c['actif']?'':'compte-inactive'?>">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.8rem">
        <div>
          <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:.4rem">
            <strong style="color:var(--blanc);font-size:1rem"><?=htmlspecialchars($c['nom_complet'])?></strong>
            <span class="service-badge sb-<?=$srv?>"><?=$emojis[$srv]?> <?=$labels[$srv]?></span>
            <?php if(!$c['actif']):?><span style="color:#E53935;font-size:.7rem;letter-spacing:.1em">DÉSACTIVÉ</span><?php endif;?>
          </div>
          <div style="font-size:.8rem;color:var(--gris);display:flex;gap:1rem;flex-wrap:wrap">
            <span>📧 <?=htmlspecialchars($c['email'])?></span>
            <span>💼 <?=htmlspecialchars($c['poste'])?></span>
            <span>🏷️ <?=htmlspecialchars($c['matricule'])?></span>
            <?php if($c['last_login']):?><span>🕐 Dernière connexion: <?=date('d/m/Y H:i',strtotime($c['last_login']))?></span><?php endif;?>
          </div>
          <div style="margin-top:.7rem;display:flex;flex-wrap:wrap;gap:.3rem">
            <?php foreach($perms as $p):?>
            <span style="background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.15);padding:.15rem .5rem;font-size:.65rem;color:var(--gris-clair);letter-spacing:.05em"><?=htmlspecialchars($p)?></span>
            <?php endforeach;?>
          </div>
        </div>
      </div>
      <div class="compte-actions">
        <button onclick="ouvrirModalEdit(<?=htmlspecialchars(json_encode($c))?>,<?=htmlspecialchars(json_encode($all_permissions))?>,<?=htmlspecialchars(json_encode($perms))?>)" class="btn" style="font-size:.75rem;padding:.4rem .9rem;border-color:rgba(201,168,76,.3);color:var(--or)">✏️ Permissions</button>
        <button onclick="ouvrirModalReset(<?=$c['id']?>,<?=htmlspecialchars(json_encode($c['nom_complet']))?> )" class="btn" style="font-size:.75rem;padding:.4rem .9rem;border-color:rgba(33,150,243,.3);color:#64B5F6">🔑 Mot de passe</button>
        <button onclick="confirmerSuppression(<?=$c['id']?>,<?=htmlspecialchars(json_encode($c['nom_complet']))?> )" class="btn" style="font-size:.75rem;padding:.4rem .9rem;border-color:rgba(229,57,53,.3);color:#E57373">🗑️ Supprimer</button>
      </div>
    </div>
    <?php endforeach;?>
  </div>
  <?php endforeach;?>

  <?php if(empty($comptes)):?>
  <div style="text-align:center;padding:4rem;color:var(--gris)">
    <div style="font-size:3rem;margin-bottom:1rem">👥</div>
    <p>Aucun compte employé créé pour l'instant.</p>
    <button onclick="document.getElementById('modal-creer').classList.add('open')" class="btn btn-or" style="margin-top:1rem"><span>Créer le premier compte</span></button>
  </div>
  <?php endif;?>
</main>
</div>

<!-- ══ MODAL : Créer un compte ══════════════════════════════ -->
<div id="modal-creer" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
<div class="modal-box">
  <div class="modal-title">➕ Créer un compte employé</div>
  <form method="POST" id="form-creer">
    <input type="hidden" name="action" value="creer">
    <input type="hidden" name="new_employe" id="input-new-employe" value="0">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem">

      <!-- ── Sélection employé ── -->
      <div class="form-groupe" style="grid-column:1/-1">
        <label>Employé *</label>
        <select name="employee_id" id="select-employe" onchange="onChangeEmploye(this)">
          <option value="">— Sélectionner un employé —</option>
          <?php foreach($employes_sans_compte as $emp):?>
          <option value="<?=$emp['id']?>"><?=htmlspecialchars($emp['nom_complet'])?> — <?=htmlspecialchars($emp['poste'])?> (<?=htmlspecialchars($emp['matricule'])?>)</option>
          <?php endforeach;?>
          <option value="__nouveau__" style="color:#C9A84C;font-weight:600">＋ Nouvel employé...</option>
        </select>
      </div>

      <!-- ══ BLOC NOUVEL EMPLOYÉ (caché par défaut) ══ -->
      <div class="bloc-nouvel-employe" id="bloc-nouvel-employe" style="grid-column:1/-1">
        <div class="bloc-titre">👤 Informations du nouvel employé</div>
        <div class="inner-grid">
          <div class="form-groupe">
            <label>Matricule *</label>
            <input type="text" name="matricule" id="input-matricule" placeholder="EMP-XXX">
          </div>
          <div class="form-groupe">
            <label>Prénom *</label>
            <input type="text" name="new_prenom" id="input-new-prenom" placeholder="Jean">
          </div>
          <div class="form-groupe">
            <label>Nom *</label>
            <input type="text" name="new_nom" id="input-new-nom" placeholder="Dupont">
          </div>
          <div class="form-groupe">
            <label>Poste *</label>
            <input type="text" name="new_poste" id="input-new-poste" placeholder="Réceptionniste">
          </div>
          <div class="form-groupe">
            <label>Département</label>
            <input type="text" name="new_departement" placeholder="Réception">
          </div>
          <div class="form-groupe">
            <label>Salaire de base (€)</label>
            <input type="number" name="new_salaire" placeholder="2500" min="0" step="0.01">
          </div>
          <div class="form-groupe" style="grid-column:1/-1">
            <label>Date d'embauche</label>
            <input type="date" name="new_date_embauche" value="<?=date('Y-m-d')?>">
          </div>
        </div>
      </div>
      <!-- ══ FIN BLOC NOUVEL EMPLOYÉ ══ -->

      <div class="form-groupe">
        <label>Email de connexion *</label>
        <input type="email" name="email" required placeholder="prenom.nom@hotel-luxe.com">
      </div>
      <div class="form-groupe">
        <label>Mot de passe *</label>
        <input type="password" name="password" required minlength="8" placeholder="8 caractères minimum">
      </div>
      <div class="form-groupe" style="grid-column:1/-1">
        <label>Service *</label>
        <select name="service" id="select-service" required onchange="majPermissionsDefaut(this.value)">
          <option value="">— Sélectionner un service —</option>
          <option value="reception">🏨 Réception</option>
          <option value="restaurant">🍽️ Restaurant</option>
          <option value="chambres">🛏️ Chambres</option>
          <option value="spa">🧖 Spa</option>
          <option value="piscine">🏊 Piscine</option>
        </select>
      </div>
    </div>

    <div style="margin-bottom:1.5rem">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
        <label style="font-size:.8rem;color:var(--or);letter-spacing:.1em;text-transform:uppercase">Permissions</label>
        <div style="display:flex;gap:.6rem">
          <button type="button" onclick="cocheTout(true,'form-creer')" style="background:none;border:none;color:var(--or);font-size:.72rem;cursor:pointer;text-decoration:underline">Tout cocher</button>
          <button type="button" onclick="cocheTout(false,'form-creer')" style="background:none;border:none;color:var(--gris);font-size:.72rem;cursor:pointer;text-decoration:underline">Tout décocher</button>
        </div>
      </div>
      <div id="perms-creer">
        <?php foreach($all_permissions as $cat => $perms_cat):?>
        <div class="perm-categorie">
          <div class="perm-cat-label"><?=htmlspecialchars($cat)?></div>
          <div class="perm-grid">
            <?php foreach($perms_cat as $p):?>
            <div class="perm-item">
              <input type="checkbox" name="permissions[]" value="<?=htmlspecialchars($p['code'])?>" id="cp_<?=htmlspecialchars($p['code'])?>">
              <label for="cp_<?=htmlspecialchars($p['code'])?>"><?=htmlspecialchars($p['libelle'])?></label>
            </div>
            <?php endforeach;?>
          </div>
        </div>
        <?php endforeach;?>
      </div>
      <p style="font-size:.72rem;color:var(--gris);margin-top:.5rem">Si aucune permission n'est cochée, les permissions par défaut du service seront appliquées.</p>
    </div>

    <div style="display:flex;gap:1rem;justify-content:flex-end">
      <button type="button" onclick="document.getElementById('modal-creer').classList.remove('open')" class="btn" style="color:var(--gris);border-color:rgba(255,255,255,.1)">Annuler</button>
      <button type="submit" class="btn btn-or"><span>Créer le compte</span></button>
    </div>
  </form>
</div></div>

<!-- ══ MODAL : Modifier permissions ═══════════════════════ -->
<div id="modal-edit" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
<div class="modal-box">
  <div class="modal-title" id="modal-edit-title">✏️ Modifier le compte</div>
  <form method="POST" id="form-edit">
    <input type="hidden" name="action" value="modifier_permissions">
    <input type="hidden" name="compte_id" id="edit-compte-id">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;padding:.8rem;background:rgba(255,255,255,.03);border:1px solid rgba(201,168,76,.1)">
      <label style="font-size:.85rem;color:var(--gris-clair)">Compte actif :</label>
      <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer">
        <input type="checkbox" name="actif" id="edit-actif" style="accent-color:var(--or);width:16px;height:16px">
        <span style="font-size:.82rem;color:var(--gris)">Activé</span>
      </label>
    </div>
    <div style="margin-bottom:1.5rem">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
        <label style="font-size:.8rem;color:var(--or);letter-spacing:.1em;text-transform:uppercase">Permissions</label>
        <div style="display:flex;gap:.6rem">
          <button type="button" onclick="cocheTout(true,'form-edit')" style="background:none;border:none;color:var(--or);font-size:.72rem;cursor:pointer;text-decoration:underline">Tout cocher</button>
          <button type="button" onclick="cocheTout(false,'form-edit')" style="background:none;border:none;color:var(--gris);font-size:.72rem;cursor:pointer;text-decoration:underline">Tout décocher</button>
        </div>
      </div>
      <div id="perms-edit"></div>
    </div>
    <div style="display:flex;gap:1rem;justify-content:flex-end">
      <button type="button" onclick="document.getElementById('modal-edit').classList.remove('open')" class="btn" style="color:var(--gris);border-color:rgba(255,255,255,.1)">Annuler</button>
      <button type="submit" class="btn btn-or"><span>Enregistrer</span></button>
    </div>
  </form>
</div></div>

<!-- ══ MODAL : Reset mot de passe ═════════════════════════ -->
<div id="modal-reset" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
<div class="modal-box" style="max-width:420px">
  <div class="modal-title">🔑 Réinitialiser le mot de passe</div>
  <p id="reset-nom" style="color:var(--gris-clair);margin-bottom:1.5rem;font-size:.88rem"></p>
  <form method="POST">
    <input type="hidden" name="action" value="reset_password">
    <input type="hidden" name="compte_id" id="reset-compte-id">
    <div class="form-groupe" style="margin-bottom:1.5rem">
      <label>Nouveau mot de passe</label>
      <input type="password" name="new_password" required minlength="8" placeholder="8 caractères minimum">
    </div>
    <div style="display:flex;gap:1rem;justify-content:flex-end">
      <button type="button" onclick="document.getElementById('modal-reset').classList.remove('open')" class="btn" style="color:var(--gris);border-color:rgba(255,255,255,.1)">Annuler</button>
      <button type="submit" class="btn" style="border-color:rgba(33,150,243,.4);color:#64B5F6"><span>Réinitialiser</span></button>
    </div>
  </form>
</div></div>

<!-- Formulaire suppression (hidden) -->
<form id="form-suppr" method="POST" style="display:none">
  <input type="hidden" name="action" value="supprimer">
  <input type="hidden" name="compte_id" id="suppr-compte-id">
</form>

<script>
const defaultPerms = {
  reception:  ['voir_chambres','approuver_chambres','creer_chambres','voir_restaurant','approuver_restaurant','creer_restaurant','voir_piscine','approuver_piscine','creer_piscine','voir_spa','approuver_spa','creer_spa','voir_salles','approuver_salles','voir_clients','voir_calendrier'],
  restaurant: ['voir_restaurant','approuver_restaurant','creer_restaurant'],
  chambres:   ['voir_chambres','approuver_chambres','creer_chambres','gerer_chambres','voir_calendrier'],
  spa:        ['voir_spa','approuver_spa','creer_spa'],
  piscine:    ['voir_piscine','approuver_piscine','creer_piscine'],
};

// ── Gestion de l'option "Nouvel employé" ──────────────────────
function onChangeEmploye(select) {
  const bloc   = document.getElementById('bloc-nouvel-employe');
  const hidden = document.getElementById('input-new-employe');
  const champs = bloc.querySelectorAll('input');

  if (select.value === '__nouveau__') {
    // Afficher le bloc et activer les champs required
    bloc.style.display = 'block';
    hidden.value = '1';
    select.removeAttribute('required'); // employee_id sera défini côté PHP après INSERT

    // Générer un matricule suggéré automatiquement
    const ts = String(Date.now()).slice(-4);
    document.getElementById('input-matricule').value = 'EMP-' + ts;

    // Rendre obligatoires les champs clés
    ['input-matricule','input-new-prenom','input-new-nom','input-new-poste'].forEach(id => {
      document.getElementById(id).setAttribute('required','required');
    });
  } else {
    // Cacher le bloc et désactiver les required
    bloc.style.display = 'none';
    hidden.value = '0';
    select.setAttribute('required','required');
    champs.forEach(i => i.removeAttribute('required'));
  }
}

function majPermissionsDefaut(service) {
  const defaults = defaultPerms[service] || [];
  document.querySelectorAll('#form-creer input[name="permissions[]"]').forEach(cb => {
    cb.checked = defaults.includes(cb.value);
  });
}

function cocheTout(val, formId) {
  document.querySelectorAll('#'+formId+' input[name="permissions[]"]').forEach(cb => cb.checked = val);
}

function ouvrirModalEdit(compte, allPerms, currentPerms) {
  document.getElementById('edit-compte-id').value = compte.id;
  document.getElementById('modal-edit-title').textContent = '✏️ ' + compte.nom_complet + ' — ' + compte.service;
  document.getElementById('edit-actif').checked = compte.actif == 1;
  let html = '';
  for (const [cat, perms] of Object.entries(allPerms)) {
    html += '<div class="perm-categorie">';
    html += '<div class="perm-cat-label">' + cat + '</div>';
    html += '<div class="perm-grid">';
    perms.forEach(p => {
      const checked = currentPerms.includes(p.code) ? 'checked' : '';
      html += `<div class="perm-item"><input type="checkbox" name="permissions[]" value="${p.code}" id="ep_${p.code}" ${checked}><label for="ep_${p.code}">${p.libelle}</label></div>`;
    });
    html += '</div></div>';
  }
  document.getElementById('perms-edit').innerHTML = html;
  document.getElementById('modal-edit').classList.add('open');
}

function ouvrirModalReset(id, nom) {
  document.getElementById('reset-compte-id').value = id;
  document.getElementById('reset-nom').textContent = 'Réinitialiser le mot de passe de : ' + nom;
  document.getElementById('modal-reset').classList.add('open');
}

function confirmerSuppression(id, nom) {
  if (confirm('Supprimer le compte de ' + nom + ' ?\n\nCette action est irréversible.')) {
    document.getElementById('suppr-compte-id').value = id;
    document.getElementById('form-suppr').submit();
  }
}
</script>
</body></html>