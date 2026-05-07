<?php
/**
 * IFQUOTA - AÇÃO SILENCIOSA: Excluir Configuração de Impressora
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) sec_session_start();

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

if (!isset($_SESSION['usuario']) || $_SESSION['permissao'] < 2) {
    header("Location: " . $BASE_URL . "/login"); exit();
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        $del = $mysqli->prepare("DELETE FROM impressoras_config WHERE id = ?");
        $del->bind_param('i', $id);
        $del->execute();
    }
}
header("Location: " . $BASE_URL . "/admin/impressoras?msg=del"); exit();