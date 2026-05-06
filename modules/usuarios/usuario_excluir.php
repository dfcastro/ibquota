<?php

/**
 * IFQUOTA - AÇÃO SILENCIOSA: Excluir Usuário e suas Cotas
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 1) {
    header("Location: " . $BASE_URL . "/login");
    exit();
}

if (isset($_GET['cod_usuario'])) {
    $cod_usuario = (int)$_GET['cod_usuario'];

    if ($cod_usuario > 0) {
        $stmt = $mysqli->prepare("SELECT usuario FROM usuarios WHERE cod_usuario = ?");
        if ($stmt) {
            $stmt->bind_param('i', $cod_usuario);
            $stmt->execute();
            $stmt->bind_result($nome_usuario);
            $stmt->fetch();
            $stmt->close();

            if (!empty($nome_usuario)) {
                $del1 = $mysqli->prepare("DELETE FROM quota_usuario WHERE usuario = ?");
                $del1->bind_param('s', $nome_usuario);
                $del1->execute();
                $del1->close();
            }
        }

        $del2 = $mysqli->prepare("DELETE FROM grupo_usuario WHERE cod_usuario = ?");
        if ($del2) {
            $del2->bind_param('i', $cod_usuario);
            $del2->execute();
            $del2->close();
        }

        $del3 = $mysqli->prepare("DELETE FROM usuarios WHERE cod_usuario = ?");
        if ($del3) {
            $del3->bind_param('i', $cod_usuario);
            $del3->execute();
            $del3->close();
        }
    }
}
header("Location: " . $BASE_URL . "/admin/contas?msg=del");
exit();
