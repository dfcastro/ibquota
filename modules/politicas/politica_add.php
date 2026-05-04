<?php
/**
 * AÇÃO SILENCIOSA: Adicionar Política
 */ 
include_once '../../core/db.php';
include_once '../../core/functions.php';
sec_session_start();

if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] !== 2) {
  header("Location: ../../public/login.php"); 
  exit();
}

if (isset($_POST['nome'])) {
    $nome = trim($_POST['nome']);
    $quota_padrao = (int)$_POST['quota_padrao'];
    $quota_infinita = isset($_POST['quota_infinita']) ? 1 : 0;
    
    if (strlen($nome) > 0) {
        
        // 1. O GUARDA: Verifica se o nome já existe na base de dados
        $chk = $mysqli->prepare("SELECT cod_politica FROM politicas WHERE nome = ?");
        $chk->bind_param('s', $nome);
        $chk->execute();
        $chk->store_result();
        
        // Se encontrou mais de 0 resultados, é porque já existe!
        if ($chk->num_rows > 0) {
            $chk->close();
            // Redireciona com um aviso de erro
            header("Location: index.php?msg=duplicado");
            exit();
        }
        $chk->close();

        // 2. SE PASSOU PELO GUARDA: Insere a nova política com segurança
        $ins = $mysqli->prepare("INSERT INTO politicas (nome, quota_padrao, quota_infinita) VALUES (?, ?, ?)");
        $ins->bind_param('sii', $nome, $quota_padrao, $quota_infinita);
        $ins->execute();
        $ins->close();
    }
}

// Redireciona com sucesso se tudo correu bem
header("Location: index.php?msg=add");
exit();
?>