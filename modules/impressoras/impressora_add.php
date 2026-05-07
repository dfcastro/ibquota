<?php
/**
 * IFQUOTA - AÇÃO SILENCIOSA: Adicionar Configuração de Impressora
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) sec_session_start();

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

if (!isset($_SESSION['usuario']) || $_SESSION['permissao'] < 2) {
    header("Location: " . $BASE_URL . "/login"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome_impressora'])) {
  validar_csrf_token($_POST['csrf_token'] ?? '');

  $nome = trim($_POST['nome_impressora']);
  $cod_local = empty($_POST['cod_local']) ? NULL : (int)$_POST['cod_local'];
  $is_colorida = isset($_POST['is_colorida']) ? 1 : 0;
  
  if (strlen($nome) > 0) {
    $chk = $mysqli->prepare("SELECT id FROM impressoras_config WHERE nome_impressora = ?");
    $chk->bind_param('s', $nome);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows == 0) {
      $ins = $mysqli->prepare("INSERT INTO impressoras_config (nome_impressora, cod_local, is_colorida) VALUES (?, ?, ?)");
      $ins->bind_param('sii', $nome, $cod_local, $is_colorida);
      $ins->execute();
      
      header("Location: " . $BASE_URL . "/admin/impressoras?msg=add"); exit();
    } else {
      header("Location: " . $BASE_URL . "/admin/impressoras?msg=erro_duplicado"); exit();
    }
  }
}
header("Location: " . $BASE_URL . "/admin/impressoras"); exit();