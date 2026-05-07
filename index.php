<?php

/**
 * IBQUOTA 3 / IFQUOTA - Roteador Principal e Autenticação (Front Controller)
 */
include_once 'core/db.php';
include_once 'core/functions.php';

// Inicia a sessão segura
if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

// O CAMINHO ABSOLUTO DO TEU PROJETO NO SERVIDOR
$BASE_URL = '/gg';

// Captura a URL digitada. Se estiver vazia, definimos a flag 'raiz'
$url = isset($_GET['url']) ? trim($_GET['url'], '/') : 'raiz';

// Correção extra para o XAMPP: se a pasta 'gg' vier agarrada na URL, nós cortamo-la!
if (strpos($url, 'gg/') === 0) {
    $url = substr($url, 3);
}

// ==========================================
// BLOQUEIO DE "PEDIDOS FANTASMA" E ERROS DO APACHE
// ==========================================
// Ignoramos pedidos de background do navegador ou do Apache para não quebrarem o layout
$pedidos_ignorados = ['error.php', 'favicon.ico', 'favicon.png'];

if (in_array($url, $pedidos_ignorados)) {
    http_response_code(404);
    exit(); // Para o script silenciosamente
}

// ==========================================
// 1. LÓGICA DE REDIRECIONAMENTO INICIAL
// ==========================================
if ($url === 'raiz') {
    if (!isset($_SESSION['usuario'])) {
        header("Location: " . $BASE_URL . "/login");
        exit();
    }

    // Se for Admin (2) ou Diretor (3), manda pro Dashboard do NTI
    if (isset($_SESSION['permissao']) && $_SESSION['permissao'] >= 2) {
        header("Location: " . $BASE_URL . "/admin/dashboard");
        exit();
    } else {
        // Usuário comum, manda pro Painel Pessoal
        header("Location: " . $BASE_URL . "/meu-painel");
        exit();
    }
}

// ==========================================
// 2. DICIONÁRIO DE ROTAS
// ==========================================
$rotas = [
    // Rotas Públicas
    'login'              => 'public/login.php',
    'logout'             => 'core/auth/logout.php',
    'trocar-senha'       => 'trocarsenha.php',
    'ajuda'              => 'ajuda.php',

    // Rotas do Utilizador Comum
    'meu-painel'         => 'public/meu_painel.php',
    'web-print'          => 'public/web_print.php',
    'solicitar-cota'     => 'public/solicitar_cota.php',
    'meu-historico'      => 'public/meu_historico.php',
    'ajax/status'        => 'public/ajax_status.php',

    // Rotas do Administrador (Dashboard e Relatórios)
    'admin/dashboard'    => 'admin/index.php',
    'admin/status-rede'  => 'modules/admin/status_impressoras.php',
    'admin/erros-cups'   => 'modules/relatorios/impressoes_com_erro.php',
    'admin/relatorio'    => 'modules/relatorios/impressoes.php',
    'admin/logs'         => 'modules/relatorios/ibquota_logs.php',
    'admin/auditoria'    => 'admin/logs_acesso.php',
    'admin/status-impressoras'  => 'admin/status_impressoras.php',

    // Rotas de Configuração e Manutenção
    'admin/coloridas'    => 'admin/gerenciar_coloridas.php',
    'admin/solicitacoes' => 'admin/solicitacoes.php',
    'admin/configuracao' => 'admin/configuracao.php',
    'admin/teste-ldap'   => 'admin/test_ldap.php',
    'admin/documentacao' => 'admin/documentacao.php',

    // Rotas do Módulo: Políticas
    'admin/politicas'           => 'modules/politicas/index.php',
    'admin/politicas/gerenciar' => 'modules/politicas/politica_gerenciar.php',
    'admin/politicas/add'       => 'modules/politicas/politica_add.php',
    'admin/politicas/excluir'   => 'modules/politicas/politica_excluir.php',
    'admin/init-quotas'         => 'modules/politicas/init_quota_politica.php',

    // Rotas do Módulo: Grupos
    'admin/grupos'            => 'modules/grupos/index.php',
    'admin/grupos/add'        => 'modules/grupos/grupo_add.php',
    'admin/grupos/editar'     => 'modules/grupos/grupo_editar.php',
    'admin/grupos/excluir'    => 'modules/grupos/grupo_excluir.php',

    // Rotas do Módulo: Administradores do Painel (NTI/Direção)
    'admin/usuarios'         => 'modules/adm_users/index.php',
    'admin/usuarios/add'     => 'modules/adm_users/adm_users_add.php',
    'admin/usuarios/editar'  => 'modules/adm_users/adm_users_editar.php',
    'admin/usuarios/excluir' => 'modules/adm_users/adm_users_excluir.php',

    // ========================================================
    // NOVAS ROTAS DO MÓDULO: Contas de Utilizadores (Rede/AD)
    // ========================================================
    'admin/contas'             => 'modules/usuarios/index.php',
    'admin/contas/sincronizar' => 'modules/usuarios/sincronizar_ad.php',
    'admin/contas/add'         => 'modules/usuarios/usuario_add.php',
    'admin/contas/gerenciar'   => 'modules/usuarios/usuario_gerenciar.php',
    'admin/contas/excluir'     => 'modules/usuarios/usuario_excluir.php',


    // Rotas do Módulo: Locais (Departamentos)
    'admin/locais'         => 'modules/locais/index.php',
    'admin/locais/add'     => 'modules/locais/local_add.php',
    'admin/locais/editar'  => 'modules/locais/local_editar.php',
    'admin/locais/excluir' => 'modules/locais/local_excluir.php',

    // Rotas do Módulo: Impressoras (Vinculação de Hardware)
    'admin/impressoras'         => 'modules/impressoras/index.php',
    'admin/impressoras/add'     => 'modules/impressoras/impressora_add.php',
    'admin/impressoras/editar'  => 'modules/impressoras/impressora_editar.php',
    'admin/impressoras/excluir' => 'modules/impressoras/impressora_excluir.php',

];

// ==========================================
// 3. SEGURANÇA E PROCESSAMENTO
// ==========================================
if (array_key_exists($url, $rotas)) {

    // Barreira de Login
    if ($url !== 'login' && !isset($_SESSION['usuario'])) {
        header("Location: " . $BASE_URL . "/login");
        exit();
    }

    // Barreira de Administrador
    if (strpos($url, 'admin/') === 0 && (!isset($_SESSION['permissao']) || $_SESSION['permissao'] < 2)) {
        http_response_code(403);
        echo "<h1>Acesso Negado 403</h1><p>Não tem permissões de administrador.</p>";
        exit();
    }

    $ficheiro_destino = $rotas[$url];

    if (file_exists($ficheiro_destino)) {
        require $ficheiro_destino;
    } else {
        http_response_code(500);
        echo "<h1>Erro Interno 500</h1><p>O ficheiro físico não foi encontrado no servidor: <b>{$ficheiro_destino}</b></p>";
    }
} else {
    // Erro 404 Melhorado para ajudar no Debugging
    http_response_code(404);
    echo "<div style='font-family: sans-serif; padding: 40px; text-align: center;'>";
    echo "<h1 style='color: #dc3545;'>Erro 404 - Página Não Encontrada</h1>";
    echo "<p>O sistema tentou aceder a uma rota que não existe no dicionário.</p>";

    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; display: inline-block;'>";
    echo "A URL capturada pelo sistema foi: <strong>'{$url}'</strong>";
    echo "</div>";

    echo "<br><br><a href='" . $BASE_URL . "/'>Voltar ao Início</a>";
    echo "</div>";
}
