<?php
/**
 * IFQUOTA - AÇÃO SILENCIOSA: Adicionar Usuário e Grupo
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usuario'])) {
    
    // Proteção CSRF aplicada!
    validar_csrf_token($_POST['csrf_token'] ?? '');

    $usuario = trim(strtolower($_POST['usuario']));
    $cod_grupo = isset($_POST['cod_grupo']) ? (int)$_POST['cod_grupo'] : 0;
    
    if (strlen($usuario) > 0) {
        $select_stmt = $mysqli->prepare("SELECT cod_usuario FROM usuarios WHERE usuario = ?");
        $select_stmt->bind_param('s', $usuario);
        $select_stmt->execute();
        $select_stmt->store_result();
        
        if ($select_stmt->num_rows == 0) {
            $select_stmt->close();
            
            $insert_stmt = $mysqli->prepare("INSERT INTO usuarios (usuario) VALUES (?)");
            $insert_stmt->bind_param('s', $usuario);
            $insert_stmt->execute();
            $novo_cod_usuario = $insert_stmt->insert_id; 
            $insert_stmt->close();
            
            if ($cod_grupo > 0 && $novo_cod_usuario > 0) {
                $stmt_grp = $mysqli->prepare("INSERT INTO grupo_usuario (cod_usuario, cod_grupo) VALUES (?, ?)");
                $stmt_grp->bind_param('ii', $novo_cod_usuario, $cod_grupo);
                $stmt_grp->execute();
                $stmt_grp->close();
            }
            
            header("Location: " . $BASE_URL . "/admin/contas?msg=add");
            exit();
        } else {
            $select_stmt->close();
            header("Location: " . $BASE_URL . "/admin/contas?msg=erro_existe");
            exit();
        }
    }
}
header("Location: " . $BASE_URL . "/admin/contas");
exit();