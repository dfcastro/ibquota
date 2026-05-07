<?php
/**
 * IFQUOTA - AÇÃO SILENCIOSA: Editar Local
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cod_local']) && isset($_POST['nome_local'])) {
  validar_csrf_token($_POST['csrf_token'] ?? '');

  $cod_local = (int)$_POST['cod_local'];
  $nome_local = trim($_POST['nome_local']);
  
  if ($cod_local > 0 && strlen($nome_local) > 0) {
    $chk = $mysqli->prepare("SELECT cod_local FROM locais WHERE nome_local = ? AND cod_local != ?");
    $chk->bind_param('si', $nome_local, $cod_local);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows == 0) {
      $upd = $mysqli->prepare("UPDATE locais SET nome_local = ? WHERE cod_local = ?");
      $upd->bind_param('si', $nome_local, $cod_local);
      $upd->execute();
      
      header("Location: " . $BASE_URL . "/admin/locais?msg=edit");
      exit();
    } else {
      header("Location: " . $BASE_URL . "/admin/locais?msg=erro_duplicado");
      exit();
    }
  }
}
header("Location: " . $BASE_URL . "/admin/locais");
exit();