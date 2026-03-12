<?php
$nouveauMotDePasse = 'Admin@1234'; // Change ici
$hash = password_hash($nouveauMotDePasse, PASSWORD_BCRYPT);

require_once 'config/database.php';
$pdo = getDB();
$pdo->prepare("UPDATE admins SET password = :p WHERE email = 'admin@hotel-luxe.com'")
    ->execute([':p' => $hash]);

echo "✅ Mot de passe mis à jour avec succès !<br>";
echo "Nouveau hash : " . $hash;