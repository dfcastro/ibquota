<?php

/**
 * IBQUOTA 3
 * GG - Gerenciador Grafico do IBQUOTA
 * * Funções definidas para utilização no GG
 * Refatoradas para Bootstrap 5, Performance SQL e Estabilidade de Sessões.
 */

include_once 'db.php';

/** Quantidades de registro por pagina - PAGINACAO **/
define('QTDE_POR_PAGINA', 20);

function sec_session_start()
{
    $session_name = 'sec_session_id';
    $secure = false;    // Impede JavaScript de acessar identificacao da sessao. (Mudar para true se usar HTTPS)
    $httponly = true;   // Forca sessao usar apenas cookies. 

    if (ini_set('session.use_only_cookies', 1) === FALSE) {
        header("Location: ../error.php?err=Could not initiate a safe session (ini_set)");
        exit();
    }

    $cookieParams = session_get_cookie_params();
    session_set_cookie_params(
        $cookieParams["lifetime"],
        $cookieParams["path"],
        $cookieParams["domain"],
        $secure,
        $httponly
    );

    session_name($session_name);

    // Só inicia a sessão se ela ainda não estiver ativa
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // NOTA: session_regenerate_id() removido daqui para evitar perda de sessão. Movido para o login().
}

function login($login, $password, $mysqli)
{
    if ($stmt = $mysqli->prepare("SELECT cod_adm_users, login, senha, permissao
        FROM adm_users
        WHERE login = ? LIMIT 1")) {
        $stmt->bind_param('s', $login);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($user_id, $nome, $db_password, $permissao);
        $stmt->fetch();

        if ($stmt->num_rows == 0) {
            return false;
        }

        // faz o hash da senha.
        $password = hash('sha256', $password, FALSE);

        if ($db_password == $password) {
            // A senha está correta!
            $user_browser = $_SERVER['HTTP_USER_AGENT'];

            // XSS protect
            $user_id = preg_replace("/[^0-9]+/", "", $user_id);
            $_SESSION['user_id'] = (int)$user_id;

            $nome = preg_replace("/[^a-zA-Z0-9_\-]+/", "", $nome);
            $_SESSION['username'] = $nome;
            $_SESSION['usuario'] = $nome; // Alias para compatibilidade visual

            $_SESSION['login_string'] = hash('sha256', $password . $user_browser);
            $_SESSION['permissao'] = (int)$permissao; // Garante que é número para os IFs do menu

            // Segurança: Regenera o ID da sessão apenas no momento de sucesso do login
            session_regenerate_id(true);

            return true;
        }
    }
    return false;
}


function login_check($mysqli)
{
    // Verifica se todas as variáveis das sessões foram definidas 
    if (isset(
        $_SESSION['user_id'],
        $_SESSION['username'],
        $_SESSION['login_string'],
        $_SESSION['permissao']
    )) {

        $user_id = $_SESSION['user_id'];
        $login_string = $_SESSION['login_string'];
        $username = $_SESSION['username'];
        $user_browser = $_SERVER['HTTP_USER_AGENT'];

        if ($stmt = $mysqli->prepare("SELECT senha FROM adm_users WHERE cod_adm_users = ? LIMIT 1")) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($password);
                $stmt->fetch();
                $login_check = hash('sha256', $password . $user_browser);

                if ($login_check == $login_string) {
                    return true; // Logado!!!
                }
            }
        }
    }
    return false; // Não foi logado 
}

function esc_url($url)
{
    if ('' == $url) {
        return $url;
    }

    $url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\\x80-\\xff]|i', '', $url);
    $strip = array('%0d', '%0a', '%0D', '%0A');
    $url = (string) $url;

    $count = 1;
    while ($count) {
        $url = str_replace($strip, '', $url, $count);
    }

    $url = str_replace(';//', '://', $url);
    $url = htmlentities($url);
    $url = str_replace('&amp;', '&#038;', $url);
    $url = str_replace("'", '&#039;', $url);

    if ($url[0] !== '/') {
        return '';
    } else {
        return $url;
    }
}


function barra_de_paginas($p, $p_registros)
{
    if ($p < 1) $p = 1;
    $p_total = ceil($p_registros / QTDE_POR_PAGINA);
    if ($p > $p_total) $p = $p_total;
    if ($p_total <= 1) return; // Não mostra barra se houver apenas 1 página

    $p_anterior = (($p - 1) <= 0) ? 1 : $p - 1;
    $p_posterior = (($p + 1) >= $p_total) ? $p_total : $p + 1;
    if ($p_posterior == 0) $p_posterior = 1;

    // Layout moderno Bootstrap 5
    echo "<nav aria-label=\"Navegação de páginas\" class=\"mt-4\">";
    echo " <ul class=\"pagination pagination-sm justify-content-center shadow-sm\">";

    $inicio_desabilitado = ($p <= 1) ? "disabled" : "";
    $fim_desabilitado = ($p_total <= $p) ? "disabled" : "";

    $urlbarra = $_SERVER["PHP_SELF"] . "?";
    if ($urlbarra <> $_SERVER["REQUEST_URI"]) {
        if (isset($_GET['cod_usuario'])) $urlbarra .= "cod_usuario=" . $_GET['cod_usuario'] . "&";
        if (isset($_GET['cod_grupo'])) $urlbarra .= "cod_grupo=" . $_GET['cod_grupo'] . "&";
        if (isset($_GET['q'])) $urlbarra .= "q=" . urlencode($_GET['q']) . "&";
    }

    echo "<li class=\"page-item $inicio_desabilitado\"><a class=\"page-link\" href=\"" . $urlbarra . "p=1\" aria-label=\"Primeira\">&laquo;</a></li>\n";
    echo "<li class=\"page-item $inicio_desabilitado\"><a class=\"page-link\" href=\"" . $urlbarra . "p=" . $p_anterior . "\" aria-label=\"Anterior\">&lsaquo; Anterior</a></li>\n";

    // Botao "..." inicial
    if ($p > 3) {
        echo "<li class=\"page-item disabled\"><a class=\"page-link\" href=\"#\">...</a></li>\n";
    }

    // botoes numerados
    if ($p < 4) {
        $p_botao_inicial = 1;
    } elseif (($p > 3) and ($p == $p_total)) {
        $p_botao_inicial = $p - 4;
    } elseif (($p > 3) and ($p == ($p_total - 1))) {
        $p_botao_inicial = $p - 3;
    } elseif (($p > 3) and ($p < ($p_total - 1))) {
        $p_botao_inicial = $p - 2;
    }

    $p_botao_inicial = max(1, $p_botao_inicial);

    for ($i = $p_botao_inicial; $i < ($p_botao_inicial + 5); $i++) {
        if ($i <= $p_total) {
            $active = ($p == $i) ? "active" : "";
            $bg_class = ($p == $i) ? "bg-ifnmg border-ifnmg text-white" : "text-dark";
            echo "<li class=\"page-item $active\"><a class=\"page-link fw-bold $bg_class\" href=\"" . $urlbarra . "p=" . $i . "\">$i</a></li>\n";
        }
    }

    // Botao "..." final
    if (($p_total > 5) and ($p_total - 2) > $p) {
        echo "<li class=\"page-item disabled\"><a class=\"page-link\" href=\"#\">...</a></li>\n";
    }

    echo " <li class=\"page-item $fim_desabilitado\"><a class=\"page-link\" href=\"" . $urlbarra . "p=" . $p_posterior . "\">Próxima &rsaquo;</a></li>\n";
    echo " <li class=\"page-item $fim_desabilitado\"><a class=\"page-link\" href=\"" . $urlbarra . "p=" . $p_total . "\">&raquo;</a></li>\n";
    echo "  </ul>\n</nav>";
}

function primeiro_acesso($mysqli)
{
    if ($stmt = $mysqli->prepare("SELECT senha FROM adm_users WHERE login = 'admin' LIMIT 1")) {
        $stmt->execute();
        $stmt->bind_result($password);
        $stmt->fetch();

        if (strlen($password) < 1) {
            return true; // Senha em branco
        }
        return false;
    }
    return false;
}

function status_impressao($cod_status_impressao)
{
    global $mysqli; // Otimização: Usa a ligação já existente em vez de criar uma nova!
    if ($stmt = $mysqli->prepare("SELECT nome_status FROM status_impressao WHERE cod_status_impressao = ? LIMIT 1")) {
        $stmt->bind_param('i', $cod_status_impressao);
        $stmt->execute();
        $stmt->bind_result($nome_status);
        $stmt->fetch();
        if (strlen($nome_status) < 1) {
            return "NONE";
        }
    }
    return $nome_status;
}

function is_base_local($mysqli)
{
    $stmt_base_local = $mysqli->prepare("SELECT base_local FROM config_geral LIMIT 1");
    $stmt_base_local->execute();
    $stmt_base_local->bind_result($base_local);
    $stmt_base_local->fetch();
    if ($base_local) {
        $stmt_base_local->close();
        return true;
    }
    return false;
}

function quota_padrao($cod_politica)
{
    global $mysqli; // Otimização: Usa a ligação já existente!
    $stmt_quota = $mysqli->prepare("SELECT quota_padrao FROM politicas WHERE cod_politica = ?");
    $stmt_quota->bind_param('i', $cod_politica);
    $stmt_quota->execute();
    $stmt_quota->bind_result($quota_padrao);
    $stmt_quota->fetch();
    $stmt_quota->close();
    return $quota_padrao;
}

function grupo_usuario_politica($cod_politica, $usuario)
{
    global $mysqli; // Otimização: Usa a ligação já existente!
    $base_local = is_base_local($mysqli);

    $stmt = $mysqli->prepare("SELECT grupo FROM politica_grupo WHERE cod_politica = ?");
    $stmt->bind_param('i', $cod_politica);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    while ($row = $result->fetch_assoc()) {
        $grupo = $row['grupo'];
        if ($base_local) {
            $stmt_grupo = $mysqli->prepare("SELECT usuarios.usuario
                                FROM grupos,grupo_usuario,usuarios
                                WHERE grupos.grupo = ? AND 
                                 grupos.cod_grupo = grupo_usuario.cod_grupo AND 
                                 usuarios.usuario = ? AND 
                                 usuarios.cod_usuario = grupo_usuario.cod_usuario");
            $stmt_grupo->bind_param('ss', $grupo, $usuario);
            $stmt_grupo->execute();
            $stmt_grupo->store_result();
            if ($stmt_grupo->num_rows > 0) {
                $stmt_grupo->close();
                return $grupo;
            }
        } else {
            // LDAP (A implementar futuramente)
        }
    }
    return "";
}

function quota_usuario($cod_politica, $usuario)
{
    global $mysqli; // Otimização: Usa a ligação já existente!
    $stmt = $mysqli->prepare("SELECT quota FROM quota_usuario WHERE cod_politica = ? AND usuario = ? LIMIT 1");
    $stmt->bind_param('is', $cod_politica, $usuario);
    $stmt->execute();
    $stmt->bind_result($quota);
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->fetch();
        return $quota;
    } else {
        return quota_padrao($cod_politica);
    }
}


/**
 * ==========================================
 * SISTEMA DE SEGURANÇA CSRF
 * ==========================================
 */

/**
 * Gera e retorna um Token CSRF único para a sessão atual.
 * 
 * @return string O código de segurança gerado.
 */
function gerar_csrf_token()
{
    // Só gera um novo token se a sessão ainda não tiver um
    if (empty($_SESSION['csrf_token'])) {
        // random_bytes(32) cria um código altamente seguro e bin2hex converte para texto legível
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida se o Token CSRF recebido pelo formulário é igual ao da sessão.
 * 
 * @param string $token_recebido O token que veio do $_POST.
 * @return boolean Retorna true se for válido, ou encerra a página se for inválido.
 */
function validar_csrf_token($token_recebido)
{
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token_recebido)) {
        // hash_equals compara as strings de forma segura contra ataques de timing
        die("ERRO CRÍTICO DE SEGURANÇA: Token CSRF inválido ou ausente. Ação bloqueada para proteger o sistema.");
    }
    return true;
}
