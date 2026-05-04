<?php

/**
 * IBQUOTA 3 - Roteador Principal (Recepção)
 */
include_once 'core/db.php';
include_once 'core/functions.php';

// 🚨 O SEGREDO ESTÁ AQUI: Usar a sessão segura do sistema!
sec_session_start();

// 1. Se não estiver logado na sessão segura, manda para o login
if (!isset($_SESSION['usuario'])) {
    header("Location: public/login.php");
    exit();
}

// 2. Se for Admin (2) ou Diretor (3), manda pro Dashboard do NTI
if (isset($_SESSION['permissao']) && $_SESSION['permissao'] >= 2) {
    header("Location: admin/index.php");
    exit();
} else {
    // 3. Se for usuário comum/professor (0 ou 1), manda pro Web Print / Painel Pessoal
    header("Location: public/meu_painel.php");
    exit();
}
