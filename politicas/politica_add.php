<?php
/**
 * AÇÃO SILENCIOSA: Adicionar Política
 */ 
include_once '../includes/db.php';
include_once '../includes/functions.php';
sec_session_start();

if (login_check($mysqli) == false || $_SESSION['permissao'] !== 2) {
  header("Location: ../login.php"); exit();
}

if (isset($_POST['nome'])) {
    $nome = trim($_POST['nome']);
    $quota_padrao = (int)$_POST['quota_padrao'];
    $quota_infinita = isset($_POST['quota_infinita']) ? 1 : 0;
    
    if (strlen($nome) > 0) {
        $ins = $mysqli->prepare("INSERT INTO politicas (nome, quota_padrao, quota_infinita) VALUES (?, ?, ?)");
        $ins->bind_param('sii', $nome, $quota_padrao, $quota_infinita);
        $ins->execute();
    }
}
header("Location: index.php?msg=add");
exit();
?>