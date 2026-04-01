<?php
/**
 * AÇÃO SILENCIOSA: Excluir Grupo
 */ 
include_once '../includes/db.php';
include_once '../includes/functions.php';
sec_session_start();

if (login_check($mysqli) == false || $_SESSION['permissao'] < 1) {
  header("Location: ../login.php"); exit();
}

if (isset($_GET['cod_grupo'])) {
    $cod_grupo = (int)$_GET['cod_grupo'];
    
    // Precisamos do nome do grupo para apagar nas outras tabelas
    $stmt = $mysqli->prepare("SELECT grupo FROM grupos WHERE cod_grupo = ? LIMIT 1");
    $stmt->bind_param('i', $cod_grupo);
    $stmt->execute(); 
    $stmt->bind_result($grupo);
    $stmt->fetch();
    $stmt->close();

    if ($grupo) {
        // Exclusão em Cascata para manter a BD limpa
        $mysqli->query("DELETE FROM grupo_usuario WHERE cod_grupo = $cod_grupo");
        $mysqli->query("DELETE FROM politica_grupo WHERE grupo = '$grupo'");
        $mysqli->query("DELETE FROM quota_usuario WHERE grupo = '$grupo'");
        $mysqli->query("DELETE FROM grupos WHERE cod_grupo = $cod_grupo");
    }
}

header("Location: index.php?msg=del");
exit();
?>