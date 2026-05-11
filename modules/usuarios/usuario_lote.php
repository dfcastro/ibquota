<?php

/**
 * IFQUOTA - Ações em Lote para Utilizadores (Com Auto-Injeção de Cota)
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) sec_session_start();

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

// Bloqueia se NÃO for o NTI (Nível 2)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] != 2) {
    header("Location: " . $BASE_URL . "/admin/dashboard?msg=acesso_negado");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'mover_grupo') {
    validar_csrf_token($_POST['csrf_token'] ?? '');

    $novo_cod_grupo = isset($_POST['novo_cod_grupo']) ? (int)$_POST['novo_cod_grupo'] : 0;
    $usuarios_selecionados = isset($_POST['usuarios_selecionados']) ? $_POST['usuarios_selecionados'] : [];

    if (empty($usuarios_selecionados) || $novo_cod_grupo == 0) {
        header("Location: " . $BASE_URL . "/admin/contas?msg=lote_vazio");
        exit();
    }

    // 1. Descobre os dados da política associada a este novo grupo
    $query_pol = "SELECT p.cod_politica, p.quota_padrao, g.grupo 
                  FROM grupos g 
                  LEFT JOIN politica_grupo pg ON g.grupo = pg.grupo 
                  LEFT JOIN politicas p ON pg.cod_politica = p.cod_politica 
                  WHERE g.cod_grupo = ?";
    $stmt_pol = $mysqli->prepare($query_pol);
    $stmt_pol->bind_param('i', $novo_cod_grupo);
    $stmt_pol->execute();
    $res_pol = $stmt_pol->get_result();
    $pol_data = $res_pol->fetch_assoc();
    $stmt_pol->close();

    $cod_politica_nova = $pol_data['cod_politica'] ?? null;
    $quota_padrao_nova = $pol_data['quota_padrao'] ?? 0;
    $nome_grupo_novo   = $pol_data['grupo'] ?? '';

    // Prepara os statements para o Loop
    $stmt_get_user = $mysqli->prepare("SELECT usuario FROM usuarios WHERE cod_usuario = ?");
    $stmt_del_grp  = $mysqli->prepare("DELETE FROM grupo_usuario WHERE cod_usuario = ?");
    $stmt_ins_grp  = $mysqli->prepare("INSERT INTO grupo_usuario (cod_usuario, cod_grupo) VALUES (?, ?)");
    $stmt_del_qta  = $mysqli->prepare("DELETE FROM quota_usuario WHERE usuario = ?");

    if ($cod_politica_nova) {
        $stmt_ins_qta = $mysqli->prepare("INSERT INTO quota_usuario (cod_politica, grupo, usuario, quota) VALUES (?, ?, ?, ?)");
    }

    // 2. Loop Mágico
    foreach ($usuarios_selecionados as $cod_usuario) {
        $cod_usuario = (int)$cod_usuario;

        // Pega o nome do utilizador
        $stmt_get_user->bind_param('i', $cod_usuario);
        $stmt_get_user->execute();
        $res_u = $stmt_get_user->get_result();
        if ($u_data = $res_u->fetch_assoc()) {
            $nome_user = $u_data['usuario'];

            // Remove grupos antigos e cota antiga
            $stmt_del_grp->bind_param('i', $cod_usuario);
            $stmt_del_grp->execute();

            $stmt_del_qta->bind_param('s', $nome_user);
            $stmt_del_qta->execute();

            // Insere no novo grupo
            $stmt_ins_grp->bind_param('ii', $cod_usuario, $novo_cod_grupo);
            $stmt_ins_grp->execute();

            // Se o novo grupo tem política, injeta a cota nova na hora!
            if ($cod_politica_nova) {
                $stmt_ins_qta->bind_param('issi', $cod_politica_nova, $nome_grupo_novo, $nome_user, $quota_padrao_nova);
                $stmt_ins_qta->execute();
            }
        }
    }

    header("Location: " . $BASE_URL . "/admin/contas?msg=lote_ok");
    exit();
}
header("Location: " . $BASE_URL . "/admin/contas");
exit();
