<?php

/**
 * IFQUOTA - AÇÃO SILENCIOSA: Adicionar Política
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome'])) {

    validar_csrf_token($_POST['csrf_token'] ?? '');

    $nome = trim($_POST['nome']);
    $quota_padrao = (int)$_POST['quota_padrao'];
    $quota_infinita = isset($_POST['quota_infinita']) ? 1 : 0;

    if (strlen($nome) > 0) {
        $chk = $mysqli->prepare("SELECT cod_politica FROM politicas WHERE nome = ?");
        $chk->bind_param('s', $nome);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $chk->close();
            header("Location: " . $BASE_URL . "/admin/politicas?msg=duplicado");
            exit();
        }
        $chk->close();

        $ins = $mysqli->prepare("INSERT INTO politicas (nome, quota_padrao, quota_infinita) VALUES (?, ?, ?)");
        $ins->bind_param('sii', $nome, $quota_padrao, $quota_infinita);
        $ins->execute();
        $ins->close();
    }
}
header("Location: " . $BASE_URL . "/admin/politicas?msg=add");
exit();
