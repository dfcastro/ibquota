<?php
/**
 * IBQUOTA 3 - ENCERRAMENTO DE SESSÃO
 */
session_start();

// 1. Esvazia todas as variáveis da sessão atual
$_SESSION = array();

// 2. Destrói o cookie de sessão no navegador do usuário (Segurança Extra)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destrói a sessão fisicamente no servidor
session_destroy();

// 4. Redireciona de volta para a tela de login


header("Location: /gg/login");
exit();