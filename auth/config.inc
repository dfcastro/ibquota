<?php
$ldap_debug = false;

$ldap_host = '192.168.4.3';
$ldap_user_attrib = 'cn';
$ldap_tls = false;
$auth ["type"] = "ldap";
$ldap_host = '192.168.4.3';
$ldap_v3 = true;
$ldap_tls = false;
$ldap_base_dn = "OU=IFNMG,DC=srv-debian,DC=ifnmg-almenara";
$ldap_user_attrib = "cn";

$ldap_name_attrib='name';

$ldap_unbind_between_attempts = false;

// Campo LDAP para pesquisa na conta do administrador
$ldap_dn_search_attrib = "sAMAccountName";

// Dados de um usuário com privilégios de administrador
$auth["admin"][]= "Administrator";
$ldap_dn_search_dn = "CN=Administrator,CN=Users,DC=srv-debian,DC=ifnmg-almenara";
$ldap_dn_search_password = 'Qyb58KWz';
