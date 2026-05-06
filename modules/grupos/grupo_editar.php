<?php
/**
 * IFQUOTA - AÇÃO SILENCIOSA: Editar Grupo e Política
 */ 
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 1) {
  header("Location: " . $BASE_URL . "/login"); 
  exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cod_grupo']) && isset($_POST['grupo_novo'])) {
    
    validar_csrf_token($_POST['csrf_token'] ?? '');

    $cod_grupo = (int)$_POST['cod_grupo'];
    $grupo_novo = trim($_POST['grupo_novo']);
    $grupo_antigo = trim($_POST['grupo_antigo']);
    $cod_politica = isset($_POST['cod_politica']) ? (int)$_POST['cod_politica'] : 0;
    
    // Agora o if apenas exige que haja um nome (não exige mais que seja diferente do antigo, pois pode querer mudar só a política)
    if ($cod_grupo > 0 && strlen($grupo_novo) > 0) {
        
        // Verifica conflito de nome com outro ID
        $chk = $mysqli->prepare("SELECT cod_grupo FROM grupos WHERE cod_grupo != ? AND grupo = ?");
        $chk->bind_param('is', $cod_grupo, $grupo_novo);
        $chk->execute(); 
        $chk->store_result();
        
        if ($chk->num_rows == 0) {
            
            // Só executa o cascata se o nome do grupo tiver realmente mudado
            if ($grupo_novo !== $grupo_antigo) {
                $upd1 = $mysqli->prepare("UPDATE grupos SET grupo = ? WHERE cod_grupo = ?");
                $upd1->bind_param('si', $grupo_novo, $cod_grupo);
                $upd1->execute();
                
                $upd2 = $mysqli->prepare("UPDATE quota_usuario SET grupo = ? WHERE grupo = ?");
                $upd2->bind_param('ss', $grupo_novo, $grupo_antigo);
                $upd2->execute();
            }
            
            // -------------------------------------------------------------
            // ATUALIZAÇÃO INTELIGENTE DE POLÍTICA (Limpa a velha, põe a nova)
            // -------------------------------------------------------------
            $del_pol = $mysqli->prepare("DELETE FROM politica_grupo WHERE grupo = ? OR grupo = ?");
            $del_pol->bind_param('ss', $grupo_antigo, $grupo_novo);
            $del_pol->execute();
            
            if ($cod_politica > 0) {
                $ins_pol = $mysqli->prepare("INSERT INTO politica_grupo (cod_politica, grupo) VALUES (?, ?)");
                $ins_pol->bind_param('is', $cod_politica, $grupo_novo);
                $ins_pol->execute();
            }
            
            header("Location: " . $BASE_URL . "/admin/grupos?msg=edit");
            exit();
        } else {
            header("Location: " . $BASE_URL . "/admin/grupos?msg=erro");
            exit();
        }
    }
}
header("Location: " . $BASE_URL . "/admin/grupos");
exit();