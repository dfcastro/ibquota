<?php

/**
 * AÇÃO SILENCIOSA: Excluir Usuário e suas Cotas
 */
include_once '../../core/db.php';
include_once '../../core/functions.php';

sec_session_start();

// 1. Verificação de sessão atualizada (../../)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 1) {
    header("Location: ../../public/login.php");
    exit();
}

if (isset($_GET['cod_usuario'])) {
    $cod_usuario = (int)$_GET['cod_usuario'];

    if ($cod_usuario > 0) {
        // Primeiro, descobrimos o nome de rede (login) do usuário
        $stmt = $mysqli->prepare("SELECT usuario FROM usuarios WHERE cod_usuario = ?");
        if ($stmt) {
            $stmt->bind_param('i', $cod_usuario);
            $stmt->execute();
            $stmt->bind_result($nome_usuario);
            $stmt->fetch();
            $stmt->close();

            if (!empty($nome_usuario)) {
                // A. Apaga o saldo de cotas ativas (tabela quota_usuario usa o nome em texto)
                $del1 = $mysqli->prepare("DELETE FROM quota_usuario WHERE usuario = ?");
                $del1->bind_param('s', $nome_usuario);
                $del1->execute();
                $del1->close();
            }
        }

        // B. Remove os vínculos deste usuário com qualquer grupo
        $del2 = $mysqli->prepare("DELETE FROM grupo_usuario WHERE cod_usuario = ?");
        if ($del2) {
            $del2->bind_param('i', $cod_usuario);
            $del2->execute();
            $del2->close();
        }

        // C. Finalmente, exclui o usuário do sistema
        $del3 = $mysqli->prepare("DELETE FROM usuarios WHERE cod_usuario = ?");
        if ($del3) {
            $del3->bind_param('i', $cod_usuario);
            $del3->execute();
            $del3->close();
        }
    }
}

// Retorna para o index disparando a mensagem amarela de Exclusão
header("Location: index.php?msg=del");
exit();
