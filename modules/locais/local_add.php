<?php
/**
 * IFQUOTA - AÇÃO SILENCIOSA: Adicionar Local
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome_local'])) {
  validar_csrf_token($_POST['csrf_token'] ?? '');

  $nome_local = trim($_POST['nome_local']);
  
  if (strlen($nome_local) > 0) {
    $chk = $mysqli->prepare("SELECT cod_local FROM locais WHERE nome_local = ?");
    $chk->bind_param('s', $nome_local);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows == 0) {
      $ins = $mysqli->prepare("INSERT INTO locais (nome_local) VALUES (?)");
      $ins->bind_param('s', $nome_local);
      $ins->execute();
      
      header("Location: " . $BASE_URL . "/admin/locais?msg=add");
      exit();
    } else {
      header("Location: " . $BASE_URL . "/admin/locais?msg=erro_duplicado");
      exit();
    }
  }
}
header("Location: " . $BASE_URL . "/admin/locais");
exit();