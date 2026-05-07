<?php
/**
 * IFQUOTA - AÇÃO SILENCIOSA: Excluir Local
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 2) {
    header("Location: " . $BASE_URL . "/login");
    exit();
}

if (isset($_GET['cod_local'])) {
    $cod_local = (int)$_GET['cod_local'];

    if ($cod_local > 0) {
        $del = $mysqli->prepare("DELETE FROM locais WHERE cod_local = ?");
        $del->bind_param('i', $cod_local);
        $del->execute();
    }
}
header("Location: " . $BASE_URL . "/admin/locais?msg=del");
exit();