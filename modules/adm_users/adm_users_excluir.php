<?php

/**
 * IFQUOTA - Gestão de Administradores
 * AÇÃO SILENCIOSA: Excluir Administrador
 */

include_once __DIR__ . '/../core/db.php';
include_once __DIR__ . '/../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
  sec_session_start();
}

// ==========================================
// DETEÇÃO INTELIGENTE DE AMBIENTE
// ==========================================
$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

// 1. Proteção: Apenas Admin (Nível 2)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 2) {
  header("Location: " . $BASE_URL . "/login");
  exit();
}

if (isset($_GET['cod_adm_users'])) {
  $cod_adm_users = (int)$_GET['cod_adm_users'];

  if ($cod_adm_users > 0) {
    // 2. Busca o login do usuário que está prestes a ser excluído
    $stmt_busca = $mysqli->prepare("SELECT login FROM adm_users WHERE cod_adm_users = ? LIMIT 1");
    $stmt_busca->bind_param('i', $cod_adm_users);
    $stmt_busca->execute();
    $stmt_busca->store_result();

    if ($stmt_busca->num_rows > 0) {
      $stmt_busca->bind_result($login_alvo);
      $stmt_busca->fetch();
      $stmt_busca->close();

      // 3. Trava de segurança: O administrador logado não pode excluir a si mesmo!
      if (strtolower(trim($login_alvo)) !== strtolower(trim($_SESSION['usuario']))) {
        // 4. Exclui com segurança via Prepared Statement
        $stmt_del = $mysqli->prepare("DELETE FROM adm_users WHERE cod_adm_users = ?");
        $stmt_del->bind_param('i', $cod_adm_users);
        $stmt_del->execute();
        $stmt_del->close();

        // Retorna para a Rota Limpa com a mensagem amarela de sucesso
        header("Location: " . $BASE_URL . "/admin/usuarios?msg=del");
        exit();
      }
    } else {
      $stmt_busca->close();
    }
  }
}

// Se deu algo errado (ou tentou se auto-excluir), volta sem a mensagem de deleção
header("Location: " . $BASE_URL . "/admin/usuarios");
exit();
