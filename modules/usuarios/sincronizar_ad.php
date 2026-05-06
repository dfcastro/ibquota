<?php

/**
 * IFQUOTA - SINCRONIZAÇÃO INTELIGENTE COM ACTIVE DIRECTORY
 * Puxa usuários novos do AD e atribui automaticamente ao grupo correto.
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

// ==========================================
// DETEÇÃO INTELIGENTE DE AMBIENTE
// ==========================================
$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

// Apenas Admin (Nível 2).
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 2) {
    header("Location: " . $BASE_URL . "/login");
    exit();
}

// ============================================================================
// 1. DICIONÁRIO DE MAPEAMENTO (OU do AD -> NOME EXATO DO GRUPO NO IFQUOTA)
// ============================================================================
$mapeamento_ous = [
    'UO_NTI'           => 'GRUPO-NGTI',
    'UO_NPED'          => 'GRUPO-NPED',
    'UO_DOCENTE'       => 'GRUPO-DOCENTE-800',
    'UO_TAE'           => 'GRUPO-TAE-800',
    'UO_TERCEIRIZADOS' => 'GRUPO-TAE-800' 
];

$grupos_db = [];
$res_g = $mysqli->query("SELECT cod_grupo, grupo FROM grupos");
while ($row = $res_g->fetch_assoc()) {
    $grupos_db[$row['grupo']] = $row['cod_grupo'];
}

$stmt = $mysqli->prepare("SELECT LDAP_server, LDAP_port, LDAP_base, LDAP_user, LDAP_password FROM config_geral WHERE id = 1");
$stmt->execute();
$stmt->bind_result($ldap_server, $ldap_porta, $ldap_base, $ldap_usuario, $ldap_senha);
$stmt->fetch();
$stmt->close();

$ldapconn = @ldap_connect($ldap_server, $ldap_porta);
if (!$ldapconn) {
    header("Location: " . $BASE_URL . "/admin/contas?msg=" . urlencode("Erro: Não foi possível conectar ao servidor AD.") . "&tipo=danger");
    exit();
}

ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);

$bind = @ldap_bind($ldapconn, $ldap_usuario, $ldap_senha);
if (!$bind) {
    header("Location: " . $BASE_URL . "/admin/contas?msg=" . urlencode("Erro de credenciais do AD.") . "&tipo=danger");
    exit();
}

$filtro_sync = "(&(objectCategory=person)(objectClass=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2))(sAMAccountName=*))";
$search = @ldap_search($ldapconn, $ldap_base, $filtro_sync);
$info = ldap_get_entries($ldapconn, $search);

if (!$info || $info["count"] == 0) {
    header("Location: " . $BASE_URL . "/admin/contas?msg=" . urlencode("Nenhum usuário ativo encontrado no AD.") . "&tipo=warning");
    exit();
}

$usuarios_existentes = [];
$res_banco = $mysqli->query("SELECT usuario FROM usuarios");
while ($row = $res_banco->fetch_assoc()) {
    $usuarios_existentes[] = strtolower($row['usuario']);
}

$novos_cadastrados = 0;
$atribuidos_grupo = 0;

for ($i = 0; $i < $info["count"]; $i++) {
    if (isset($info[$i]["samaccountname"][0])) {
        $login_ad = strtolower(trim($info[$i]["samaccountname"][0]));
        $dn_ad = strtoupper($info[$i]["dn"]);

        if (!in_array($login_ad, $usuarios_existentes) && $login_ad != "") {
            $stmt_ins = $mysqli->prepare("INSERT INTO usuarios (usuario) VALUES (?)");
            $stmt_ins->bind_param('s', $login_ad);
            $stmt_ins->execute();
            $novo_cod_usuario = $stmt_ins->insert_id; 
            $stmt_ins->close();

            $novos_cadastrados++;
            $usuarios_existentes[] = $login_ad;

            $grupo_destino_cod = null;
            foreach ($mapeamento_ous as $nome_ou => $nome_grupo_alvo) {
                if (strpos($dn_ad, "OU=" . $nome_ou) !== false) {
                    if (isset($grupos_db[$nome_grupo_alvo])) {
                        $grupo_destino_cod = $grupos_db[$nome_grupo_alvo];
                    }
                    break;
                }
            }

            if ($grupo_destino_cod !== null) {
                $stmt_grp = $mysqli->prepare("INSERT INTO grupo_usuario (cod_grupo, cod_usuario) VALUES (?, ?)");
                $stmt_grp->bind_param('ii', $grupo_destino_cod, $novo_cod_usuario);
                $stmt_grp->execute();
                $stmt_grp->close();
                $atribuidos_grupo++;
            }
        }
    }
}
ldap_close($ldapconn);

$mensagem = "Sincronização concluída! <b>{$novos_cadastrados}</b> novos usuários importados. <b>{$atribuidos_grupo}</b> foram colocados nos grupos automaticamente.";
header("Location: " . $BASE_URL . "/admin/contas?msg=" . urlencode($mensagem) . "&tipo=success");
exit();