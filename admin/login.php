<?php
session_start();
if (!empty($_SESSION['admin_id']) || !empty($_SESSION['employe_id'])) { header('Location: dashboard.php'); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? ''); $pass = trim($_POST['password'] ?? '');
    if ($email && $pass) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id,nom,password,role FROM admins WHERE email=:e LIMIT 1");
        $stmt->execute([':e'=>$email]); $admin = $stmt->fetch();
        if ($admin && password_verify($pass, $admin['password'])) {
            $_SESSION['admin_id']=$admin['id']; $_SESSION['admin_nom']=$admin['nom']; $_SESSION['admin_role']=$admin['role'];
            try{$pdo->prepare("UPDATE admins SET last_login=NOW() WHERE id=?")->execute([$admin['id']]);}catch(Exception $e){}
            header('Location: dashboard.php'); exit;
        }
        $stmt2 = $pdo->prepare("SELECT ea.*,CONCAT(emp.prenom,' ',emp.nom) AS nom_complet FROM employee_accounts ea JOIN employees emp ON ea.employee_id=emp.id WHERE ea.email=:e AND ea.actif=1 LIMIT 1");
        $stmt2->execute([':e'=>$email]); $employe = $stmt2->fetch();
        if ($employe && password_verify($pass, $employe['password'])) {
            $permissions = json_decode($employe['permissions'], true) ?? [];
            $_SESSION['employe_id']=$employe['id']; $_SESSION['employe_employee_id']=$employe['employee_id'];
            $_SESSION['employe_nom']=$employe['nom_complet']; $_SESSION['employe_email']=$employe['email'];
            $_SESSION['employe_service']=$employe['service']; $_SESSION['employe_permissions']=$permissions;
            try{$pdo->prepare("UPDATE employee_accounts SET last_login=NOW() WHERE id=?")->execute([$employe['id']]);}catch(Exception $e){}
            header('Location: dashboard.php'); exit;
        }
        $erreur = 'Email ou mot de passe incorrect.';
    } else { $erreur = 'Veuillez remplir tous les champs.'; }
}
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion · Grand Hôtel Lumière</title>
<link rel="stylesheet" href="../hotel-html/css/style.css">
<style>
body{display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--noir)}
.login-wrap{width:100%;max-width:460px;padding:1rem}
.login-box{padding:3.5rem;background:var(--noir-2);border:1px solid rgba(201,168,76,0.15)}
.login-logo{font-family:'Cinzel',serif;font-size:1.6rem;color:var(--or);letter-spacing:.12em;text-align:center;margin-bottom:.3rem}
.login-sub{text-align:center;font-size:.62rem;color:var(--gris);letter-spacing:.3em;text-transform:uppercase;margin-bottom:2.5rem}
.alerte{background:rgba(229,57,53,.1);border-left:3px solid #E53935;padding:.8rem 1rem;font-size:.85rem;margin-bottom:1.5rem;color:#E53935}
.login-info{background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.15);padding:1rem 1.2rem;margin-top:1.5rem;font-size:.78rem;color:var(--gris);line-height:1.7}
.login-info strong{color:var(--or);display:block;margin-bottom:.4rem;font-size:.7rem;letter-spacing:.15em;text-transform:uppercase}
</style></head><body>
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">LUMIÈRE</div>
    <div class="login-sub">Espace Administration &amp; Employés</div>
    <?php if($erreur):?><div class="alerte"><?=htmlspecialchars($erreur)?></div><?php endif;?>
    <?php if(!empty($_SESSION['flash_error'])):?><div class="alerte"><?=htmlspecialchars($_SESSION['flash_error'])?></div><?php unset($_SESSION['flash_error']);endif;?>
    <form method="POST" novalidate style="display:flex;flex-direction:column;gap:1.3rem">
      <div class="form-groupe"><label>Email</label><input type="email" name="email" value="<?=htmlspecialchars($_POST['email']??'')?>" required autofocus placeholder="votre@email.com"></div>
      <div class="form-groupe"><label>Mot de passe</label><input type="password" name="password" required></div>
      <button type="submit" class="btn btn-or" style="width:100%;justify-content:center;padding:1rem;margin-top:.5rem"><span>Se connecter</span></button>
    </form>
    <div class="login-info"><strong>Accès Personnel</strong>Cette interface est réservée au personnel autorisé.<br>Vos identifiants vous ont été fournis par votre responsable.</div>
  </div>
  <p style="text-align:center;margin-top:1.5rem;font-size:.75rem;color:var(--gris)"><a href="../hotel-html/index.html" style="color:var(--or)">← Retour au site</a></p>
</div>
</body></html>
