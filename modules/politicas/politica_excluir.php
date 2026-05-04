<?php
/**
 * AÇÃO SILENCIOSA: Excluir Política e Limpar Vinculos
 */ 
include_once '../../core/db.php';
include_once '../../core/functions.php';
sec_session_start();

if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] !== 2) {
  header("Location: ../../public/login.php"); 
  exit();
}

if (isset($_GET['cod_politica'])) {
    $cod_politica = (int)$_GET['cod_politica'];
    
    // Deleta os vínculos para evitar "sujeira" no banco (Usando Prepared Statement)
    $del1 = $mysqli->prepare("DELETE FROM politica_grupo WHERE cod_politica = ?");
    $del1->bind_param('i', $cod_politica);
    $del1->execute();
    $del1->close();

    $del2 = $mysqli->prepare("DELETE FROM quota_usuario WHERE cod_politica = ?");
    $del2->bind_param('i', $cod_politica);
    $del2->execute();
    $del2->close();
    
    // Deleta a política
    $del3 = $mysqli->prepare("DELETE FROM politicas WHERE cod_politica = ?");
    $del3->bind_param('i', $cod_politica);
    $del3->execute();
    $del3->close();
}
header("Location: index.php?msg=del");
exit();
?>