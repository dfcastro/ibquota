<?php

/**
 * IFQUOTA - AÇÃO SILENCIOSA: Adicionar Grupo com Política
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grupo'])) {
  validar_csrf_token($_POST['csrf_token'] ?? '');

  $grupo = trim($_POST['grupo']);
  $cod_politica = isset($_POST['cod_politica']) ? (int)$_POST['cod_politica'] : 0;

  if (strlen($grupo) > 0) {
    $chk = $mysqli->prepare("SELECT cod_grupo FROM grupos WHERE grupo = ?");
    $chk->bind_param('s', $grupo);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows == 0) {
      // 1. Insere o Grupo
      $ins = $mysqli->prepare("INSERT INTO grupos (grupo) VALUES (?)");
      $ins->bind_param('s', $grupo);
      $ins->execute();

      // 2. A MÁGICA: Se escolheu uma política, vincula na hora!
      if ($cod_politica > 0) {
        $ins_pol = $mysqli->prepare("INSERT INTO politica_grupo (cod_politica, grupo) VALUES (?, ?)");
        $ins_pol->bind_param('is', $cod_politica, $grupo);
        $ins_pol->execute();
        $ins_pol->close();
      }

      header("Location: " . $BASE_URL . "/admin/grupos?msg=add");
      exit();
    } else {
      header("Location: " . $BASE_URL . "/admin/grupos?msg=erro");
      exit();
    }
  }
}
header("Location: " . $BASE_URL . "/admin/grupos");
exit();
