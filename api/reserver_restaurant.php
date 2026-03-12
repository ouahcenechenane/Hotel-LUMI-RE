<?php
// api/reserver_restaurant.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Méthode non autorisée.');

$data    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$nom     = sanitize($data['nom'] ?? '');
$email   = sanitize($data['email'] ?? '');
$tel     = sanitize($data['telephone'] ?? '');
$date    = sanitize($data['date_res'] ?? '');
$heure   = sanitize($data['heure_res'] ?? '');
$couverts= (int)($data['nb_couverts'] ?? 2);
$menu    = sanitize($data['menu'] ?? 'carte');
$msg     = sanitize($data['message'] ?? '');

if (!$nom || !$email || !$date || !$heure) jsonResponse(false, 'Champs obligatoires manquants.');
if (!validEmail($email)) jsonResponse(false, 'Email invalide.');
if (!in_array($menu, ['decouverte','prestige','carte'])) $menu = 'carte';

$pdo = getDB();
$ref = genererReference('RST');

$stmt = $pdo->prepare("
    INSERT INTO restaurant_reservations
        (reference, nom_client, email_client, telephone, date_res, heure_res, nb_couverts, menu, message)
    VALUES (:ref,:nom,:email,:tel,:date,:heure,:couverts,:menu,:msg)
");
$stmt->execute([':ref'=>$ref,':nom'=>$nom,':email'=>$email,':tel'=>$tel,
                ':date'=>$date,':heure'=>$heure,':couverts'=>$couverts,
                ':menu'=>$menu,':msg'=>$msg]);

jsonResponse(true, 'Table réservée avec succès.', ['reference' => $ref]);
