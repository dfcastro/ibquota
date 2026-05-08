<?php
/**
 * IFQUOTA - AÇÃO SILENCIOSA: Mover Vários Usuários de Grupo
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'mover_grupo') {
    
    validar_csrf_token($_POST['csrf_token'] ?? '');

    // Verifica se recebemos as caixinhas marcadas e se escolheram um grupo de destino
    if (!empty($_POST['usuarios_selecionados']) && !empty($_POST['novo_cod_grupo'])) {
        
        $novo_cod_grupo = (int)$_POST['novo_cod_grupo'];
        $lista_usuarios = $_POST['usuarios_selecionados']; // Isto é um Array de IDs

        // Prepara as ferramentas de banco de dados UMA só vez para máxima performance
        $del_stmt = $mysqli->prepare("DELETE FROM grupo_usuario WHERE cod_usuario = ?");
        $ins_stmt = $mysqli->prepare("INSERT INTO grupo_usuario (cod_usuario, cod_grupo) VALUES (?, ?)");

        // Faz o loop por cada utilizador marcado
        foreach ($lista_usuarios as $id_usuario) {
            $uid = (int)$id_usuario;
            
            if ($uid > 0) {
                // 1. Remove qualquer vínculo de grupo anterior (Garante que só fica em UM grupo)
                $del_stmt->bind_param('i', $uid);
                $del_stmt->execute();

                // 2. Insere no Grupo Novo escolhido no menu
                $ins_stmt->bind_param('ii', $uid, $novo_cod_grupo);
                $ins_stmt->execute();
            }
        }
        
        $del_stmt->close();
        $ins_stmt->close();

        // O utilizador na próxima impressão já puxará a Política do novo Grupo!
        header("Location: " . $BASE_URL . "/admin/contas?msg=lote_ok");
        exit();

    } else {
        // Se alguém clicou no botão mas se esqueceu de marcar as pessoas ou o grupo
        header("Location: " . $BASE_URL . "/admin/contas?msg=lote_vazio");
        exit();
    }
}

// Em caso de acesso direto à página sem POST
header("Location: " . $BASE_URL . "/admin/contas");
exit();