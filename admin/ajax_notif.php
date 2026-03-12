<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'lire_toutes') {
        try { $pdo->exec("UPDATE notifications SET lue=1"); } catch(Exception $e){}
    }
    if ($action === 'lire' && isset($_POST['id'])) {
        try { $pdo->prepare("UPDATE notifications SET lue=1 WHERE id=:id")->execute([':id'=>(int)$_POST['id']]); } catch(Exception $e){}
    }
}
header('Location: dashboard.php');
