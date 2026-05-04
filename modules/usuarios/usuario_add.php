<?php
/**
 * AÇÃO SILENCIOSA: Adicionar Usuário e Grupo
 * Processa o insert e volta para a lista.
 */ 
include_once '../../core/db.php';
include_once '../../core/functions.php';
sec_session_start();

// Proteção perfeita, apontando para o lugar certo
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 1) {
    header("Location: ../../public/login.php");
    exit();
}

if (isset($_POST['usuario'])) {
    $usuario = trim(strtolower($_POST['usuario'])); // Padronizando para minúsculas
    $cod_grupo = isset($_POST['cod_grupo']) ? (int)$_POST['cod_grupo'] : 0;
    
    if (strlen($usuario) > 0) {
        // Verifica se o usuário já existe
        $select_stmt = $mysqli->prepare("SELECT cod_usuario FROM usuarios WHERE usuario = ?");
        $select_stmt->bind_param('s', $usuario);
        $select_stmt->execute();
        $select_stmt->store_result();
        
        if ($select_stmt->num_rows == 0) {
            $select_stmt->close();
            
            // 1. Insere o novo usuário
            $insert_stmt = $mysqli->prepare("INSERT INTO usuarios (usuario) VALUES (?)");
            $insert_stmt->bind_param('s', $usuario);
            $insert_stmt->execute();
            
            // A MÁGICA: Pega o ID (cod_usuario) que o banco acabou de criar!
            $novo_cod_usuario = $insert_stmt->insert_id; 
            $insert_stmt->close();
            
            // 2. Se o Admin escolheu um grupo, insere logo a relação!
            if ($cod_grupo > 0 && $novo_cod_usuario > 0) {
                $stmt_grp = $mysqli->prepare("INSERT INTO grupo_usuario (cod_usuario, cod_grupo) VALUES (?, ?)");
                $stmt_grp->bind_param('ii', $novo_cod_usuario, $cod_grupo);
                $stmt_grp->execute();
                $stmt_grp->close();
            }
            
            // Redireciona com mensagem de SUCESSO
            header("Location: index.php?msg=add");
            exit();
            
        } else {
            $select_stmt->close();
            // Redireciona com mensagem de ERRO (Usuário já existe)
            header("Location: index.php?msg=erro_existe");
            exit();
        }
    }
}

// Se chegou até aqui sem entrar no fluxo principal, apenas volta.
header("Location: index.php");
exit();
?>