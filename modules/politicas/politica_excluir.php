<?php

/**
 * IFQUOTA - AÇÃO SILENCIOSA: Excluir Política e Limpar Vinculos
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

if (isset($_GET['cod_politica'])) {
    $cod_politica = (int)$_GET['cod_politica'];

    $del1 = $mysqli->prepare("DELETE FROM politica_grupo WHERE cod_politica = ?");
    $del1->bind_param('i', $cod_politica);
    $del1->execute();
    $del1->close();

    $del2 = $mysqli->prepare("DELETE FROM quota_usuario WHERE cod_politica = ?");
    $del2->bind_param('i', $cod_politica);
    $del2->execute();
    $del2->close();

    $del3 = $mysqli->prepare("DELETE FROM politicas WHERE cod_politica = ?");
    $del3->bind_param('i', $cod_politica);
    $del3->execute();
    $del3->close();
}
header("Location: " . $BASE_URL . "/admin/politicas?msg=del");
exit();
