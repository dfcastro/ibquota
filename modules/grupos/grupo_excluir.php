<?php
/**
 * AÇÃO SILENCIOSA: Excluir Grupo
 */
include_once '../../core/db.php';
include_once '../../core/functions.php';

sec_session_start();

// 1. Verificação de sessão corrigida (sem login_check e apontando para ../../)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 1) {
    header("Location: ../../public/login.php");
    exit();
}

if (isset($_GET['cod_grupo'])) {
    $cod_grupo = (int)$_GET['cod_grupo'];

    if ($cod_grupo > 0) {
        // Primeiro, descobrimos o nome do grupo (para apagar as políticas atreladas a ele)
        $stmt = $mysqli->prepare("SELECT grupo FROM grupos WHERE cod_grupo = ?");
        if ($stmt) {
            $stmt->bind_param('i', $cod_grupo);
            $stmt->execute();
            $stmt->bind_result($nome_grupo);
            $stmt->fetch();
            $stmt->close();

            if (!empty($nome_grupo)) {
                // A. Remove das políticas
                $del1 = $mysqli->prepare("DELETE FROM politica_grupo WHERE grupo = ?");
                $del1->bind_param('s', $nome_grupo);
                $del1->execute();
                $del1->close();
            }
        }

        // B. Remove os usuários que estavam neste grupo
        $del2 = $mysqli->prepare("DELETE FROM grupo_usuario WHERE cod_grupo = ?");
        if ($del2) {
            $del2->bind_param('i', $cod_grupo);
            $del2->execute();
            $del2->close();
        }

        // C. Finalmente, exclui o grupo em si
        $del3 = $mysqli->prepare("DELETE FROM grupos WHERE cod_grupo = ?");
        if ($del3) {
            $del3->bind_param('i', $cod_grupo);
            $del3->execute();
            $del3->close();
        }
    }
}

// Volta para a lista com o alerta amarelo de exclusão
header("Location: index.php?msg=del");
exit();
?>