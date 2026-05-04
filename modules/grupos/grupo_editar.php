<?php
/**
 * AÇÃO SILENCIOSA: Editar Grupo
 */ 
include_once '../../core/db.php';
include_once '../../core/functions.php';
sec_session_start();

// 1. Verificação de sessão atualizada (Sem login_check e com ../../)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 1) {
  header("Location: ../../public/login.php"); 
  exit();
}

if (isset($_POST['cod_grupo']) && isset($_POST['grupo_novo'])) {
    $cod_grupo = (int)$_POST['cod_grupo'];
    $grupo_novo = trim($_POST['grupo_novo']);
    $grupo_antigo = trim($_POST['grupo_antigo']);
    
    if ($cod_grupo > 0 && strlen($grupo_novo) > 0 && $grupo_novo != $grupo_antigo) {
        // Verifica se o novo nome já existe
        $chk = $mysqli->prepare("SELECT cod_grupo FROM grupos WHERE cod_grupo != ? AND grupo = ?");
        $chk->bind_param('is', $cod_grupo, $grupo_novo);
        $chk->execute(); 
        $chk->store_result();
        
        if ($chk->num_rows == 0) {
            // Atualiza em cascata nas 3 tabelas
            $upd1 = $mysqli->prepare("UPDATE grupos SET grupo = ? WHERE cod_grupo = ?");
            $upd1->bind_param('si', $grupo_novo, $cod_grupo);
            $upd1->execute();
            
            $upd2 = $mysqli->prepare("UPDATE politica_grupo SET grupo = ? WHERE grupo = ?");
            $upd2->bind_param('ss', $grupo_novo, $grupo_antigo);
            $upd2->execute();
            
            $upd3 = $mysqli->prepare("UPDATE quota_usuario SET grupo = ? WHERE grupo = ?");
            $upd3->bind_param('ss', $grupo_novo, $grupo_antigo);
            $upd3->execute();
        }
    }
}

// Retorna para a tela de grupos com a mensagem de sucesso
header("Location: index.php?msg=edit");
exit();
?>