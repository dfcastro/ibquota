<?php
/**
 * IFQUOTA - AÇÃO SILENCIOSA: Editar Configuração de Impressora
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) sec_session_start();

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

if (!isset($_SESSION['usuario']) || $_SESSION['permissao'] < 2) {
    header("Location: " . $BASE_URL . "/login"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
  validar_csrf_token($_POST['csrf_token'] ?? '');

  $id = (int)$_POST['id'];
  $cod_local = empty($_POST['cod_local']) ? NULL : (int)$_POST['cod_local'];
  $is_colorida = isset($_POST['is_colorida']) ? 1 : 0;
  
  if ($id > 0) {
      $upd = $mysqli->prepare("UPDATE impressoras_config SET cod_local = ?, is_colorida = ? WHERE id = ?");
      $upd->bind_param('iii', $cod_local, $is_colorida, $id);
      $upd->execute();
      
      header("Location: " . $BASE_URL . "/admin/impressoras?msg=edit"); exit();
  }
}
header("Location: " . $BASE_URL . "/admin/impressoras"); exit();