<?php

/**
 * IFQUOTA - Adicionar Regra de Mapeamento via Modal
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) sec_session_start();

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

// Bloqueia se NÃO for o NTI (Nível 2)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] != 2) {
    header("Location: " . $BASE_URL . "/admin/dashboard?msg=acesso_negado");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ou_ad']) && isset($_POST['cod_grupo'])) {
    validar_csrf_token($_POST['csrf_token'] ?? '');

    $ou_ad = trim($_POST['ou_ad']);
    $cargo_ad = trim($_POST['cargo_ad']);
    $cod_grupo = (int)$_POST['cod_grupo'];

    if (strlen($ou_ad) > 0 && $cod_grupo > 0) {
        $ins = $mysqli->prepare("INSERT INTO mapeamento_ad (ou_ad, cargo_ad, cod_grupo) VALUES (?, ?, ?)");
        $ins->bind_param('ssi', $ou_ad, $cargo_ad, $cod_grupo);
        $ins->execute();
        $ins->close();

        header("Location: " . $BASE_URL . "/admin/contas?msg=" . urlencode("Regra de AD adicionada com sucesso!") . "&tipo=success&modal=mapeamento"); exit();
        exit();
    }
}
header("Location: " . $BASE_URL . "/admin/contas");
exit();
