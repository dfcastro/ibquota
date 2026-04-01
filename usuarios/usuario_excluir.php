<?php
/**
 * AÇÃO SILENCIOSA: Excluir Usuário
 * Processa a exclusão e volta para a lista instantaneamente.
 */ 
include_once '../includes/db.php';
include_once '../includes/functions.php';
sec_session_start();

if (login_check($mysqli) == false || $_SESSION['permissao'] < 1) {
  header("Location: ../login.php");
  exit();
}

if (isset($_GET['cod_usuario'])) {
    $cod_usuario = (int)$_GET['cod_usuario'];
    
    $stmt = $mysqli->prepare("SELECT usuario FROM usuarios WHERE cod_usuario = ?");
    $stmt->bind_param('i', $cod_usuario);
    $stmt->execute(); 
    $stmt->bind_result($usuario);
    $stmt->fetch();
    $stmt->close();

    if ($usuario) {
        $mysqli->query("DELETE FROM grupo_usuario WHERE cod_usuario = $cod_usuario");
        $mysqli->query("DELETE FROM quota_usuario WHERE usuario = '$usuario'");
        $mysqli->query("DELETE FROM usuarios WHERE cod_usuario = $cod_usuario");
    }
}
header("Location: index.php");
exit();
?>