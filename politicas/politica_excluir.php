<?php
/**
 * AÇÃO SILENCIOSA: Excluir Política e Limpar Vinculos
 */ 
include_once '../includes/db.php';
include_once '../includes/functions.php';
sec_session_start();

if (login_check($mysqli) == false || $_SESSION['permissao'] !== 2) {
  header("Location: ../login.php"); exit();
}

if (isset($_GET['cod_politica'])) {
    $cod_politica = (int)$_GET['cod_politica'];
    
    // Deleta os vínculos para evitar "sujeira" no banco
    $mysqli->query("DELETE FROM politica_grupo WHERE cod_politica = $cod_politica");
    $mysqli->query("DELETE FROM quota_usuario WHERE cod_politica = $cod_politica");
    
    // Deleta a política
    $mysqli->query("DELETE FROM politicas WHERE cod_politica = $cod_politica");
}
header("Location: index.php?msg=del");
exit();
?>