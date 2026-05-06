<?php

/**
 * IFQUOTA - AÇÃO SILENCIOSA: Excluir Grupo
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

if (isset($_GET['cod_grupo'])) {
    $cod_grupo = (int)$_GET['cod_grupo'];

    if ($cod_grupo > 0) {
        $stmt = $mysqli->prepare("SELECT grupo FROM grupos WHERE cod_grupo = ?");
        if ($stmt) {
            $stmt->bind_param('i', $cod_grupo);
            $stmt->execute();
            $stmt->bind_result($nome_grupo);
            $stmt->fetch();
            $stmt->close();

            if (!empty($nome_grupo)) {
                $del1 = $mysqli->prepare("DELETE FROM politica_grupo WHERE grupo = ?");
                $del1->bind_param('s', $nome_grupo);
                $del1->execute();
                $del1->close();
            }
        }

        $del2 = $mysqli->prepare("DELETE FROM grupo_usuario WHERE cod_grupo = ?");
        if ($del2) {
            $del2->bind_param('i', $cod_grupo);
            $del2->execute();
            $del2->close();
        }

        $del3 = $mysqli->prepare("DELETE FROM grupos WHERE cod_grupo = ?");
        if ($del3) {
            $del3->bind_param('i', $cod_grupo);
            $del3->execute();
            $del3->close();
        }
    }
}
header("Location: " . $BASE_URL . "/admin/grupos?msg=del");
exit();
