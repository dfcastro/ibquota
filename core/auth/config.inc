<?php
/**
 * Configurações de Conexão LDAP / AD
 * Refatorado para utilizar variáveis de ambiente (.env)
 */

// Carrega o nosso leitor de .env (voltamos uma pasta, pois estamos em core/auth)
require_once __DIR__ . '/../carrega_env.php';

$ldap_debug = false;

// Busca as configurações de forma segura na memória
$ldap_host = isset($_ENV['LDAP_HOST']) ? $_ENV['LDAP_HOST'] : '127.0.0.1';
$ldap_base_dn = isset($_ENV['LDAP_BASE_DN']) ? $_ENV['LDAP_BASE_DN'] : '';

$auth["type"] = "ldap";
$ldap_v3 = true;
$ldap_tls = false;
$ldap_user_attrib = "cn";
$ldap_name_attrib = 'name';
$ldap_unbind_between_attempts = false;

// Campo LDAP para pesquisa na conta do administrador
$ldap_dn_search_attrib = "sAMAccountName";

// Dados de um usuário com privilégios de administrador (puxando a senha do .env)
$auth["admin"][] = "Administrator";
$ldap_dn_search_dn = isset($_ENV['LDAP_ADMIN_DN']) ? $_ENV['LDAP_ADMIN_DN'] : '';
$ldap_dn_search_password = isset($_ENV['LDAP_ADMIN_PASS']) ? $_ENV['LDAP_ADMIN_PASS'] : '';

?>