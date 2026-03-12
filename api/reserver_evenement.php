<?php
// api/reserver_evenement.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Méthode non autorisée.');

$data    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$nom     = sanitize($data['nom'] ?? '');
$email   = sanitize($data['email'] ?? '');
$tel     = sanitize($data['telephone'] ?? '');
$type    = sanitize($data['type_event'] ?? '');
$date    = sanitize($data['date_event'] ?? '');
$invites = (int)($data['nb_invites'] ?? 0);
$salle   = sanitize($data['salle'] ?? '');
$budget  = (float)($data['budget'] ?? 0);
$desc    = sanitize($data['description'] ?? '');

if (!$nom || !$email || !$type || !$date) jsonResponse(false, 'Champs obligatoires manquants.');
if (!validEmail($email)) jsonResponse(false, 'Email invalide.');

$pdo = getDB();
$ref = genererReference('EVT');

$stmt = $pdo->prepare("
    INSERT INTO event_reservations
        (reference, nom_client, email_client, telephone, type_event, date_event, nb_invites, salle, budget, description)
    VALUES (:ref,:nom,:email,:tel,:type,:date,:inv,:salle,:budget,:desc)
");
$stmt->execute([':ref'=>$ref,':nom'=>$nom,':email'=>$email,':tel'=>$tel,
                ':type'=>$type,':date'=>$date,':inv'=>$invites,
                ':salle'=>$salle,':budget'=>$budget,':desc'=>$desc]);

jsonResponse(true, 'Demande d\'événement enregistrée.', ['reference' => $ref]);
