<?php

/**
 * IFQUOTA - Sincronização Dinâmica com Active Directory (UNIVERSAL & SNIPER)
 * Lê a tabela mapeamento_ad e varre as sub-pastas.
 * AGORA: Captura e atualiza o e-mail dos utilizadores automaticamente!
 */

include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 2) {
    header("Location: " . $BASE_URL . "/login");
    exit();
}

// ============================================================================
// 1. CARREGAR REGRAS DE MAPEAMENTO (Ordenadas para testar cargos primeiro)
// ============================================================================
$mapeamentos = [];
$res_map = $mysqli->query("SELECT ou_ad, cargo_ad, cod_grupo FROM mapeamento_ad ORDER BY cargo_ad DESC");
if ($res_map) {
    while ($row = $res_map->fetch_assoc()) {
        $mapeamentos[] = $row;
    }
}

// ============================================================================
// 2. CONFIGURAÇÃO E LIGAÇÃO AO LDAP
// ============================================================================
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

// ============================================================================
// 3. BUSCA DE UTILIZADORES
// ============================================================================
$filtro_sync = "(&(objectCategory=person)(objectClass=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2))(sAMAccountName=*))";

// NOVIDADE: Pedimos o atributo "mail" ao servidor AD
$attributes = ["sAMAccountName", "distinguishedName", "title", "mail"];

$search = @ldap_search($ldapconn, $ldap_base, $filtro_sync, $attributes);
$info = ldap_get_entries($ldapconn, $search);

if (!$info || $info["count"] == 0) {
    header("Location: " . $BASE_URL . "/admin/contas?msg=" . urlencode("Nenhum usuário ativo encontrado no AD.") . "&tipo=warning");
    exit();
}

$novos_cadastrados = 0;
$atribuidos_grupo = 0;

for ($i = 0; $i < $info["count"]; $i++) {
    if (!isset($info[$i]["samaccountname"][0])) continue;

    $login_ad = strtolower(trim($info[$i]["samaccountname"][0]));
    $dn_ad    = strtoupper($info[$i]["dn"]); 
    $cargo_ad = isset($info[$i]["title"][0]) ? trim($info[$i]["title"][0]) : '';
    
    // NOVIDADE: Captura o e-mail de forma segura
    $email_ad = isset($info[$i]["mail"][0]) ? strtolower(trim($info[$i]["mail"][0])) : null;

    // ==============================================================
    // A. O UTILIZADOR JÁ EXISTE NO IFQUOTA? (E Atualização de E-mail)
    // ==============================================================
    $cod_usuario = 0;
    $chk_user = $mysqli->prepare("SELECT cod_usuario, email FROM usuarios WHERE usuario = ?");
    $chk_user->bind_param('s', $login_ad);
    $chk_user->execute();
    $chk_user->store_result();

    if ($chk_user->num_rows > 0) {
        // Já existe! Vamos pegar os dados dele.
        $chk_user->bind_result($cod_usuario, $email_atual);
        $chk_user->fetch();
        
        // Se o AD enviou um e-mail diferente do que temos no banco, nós atualizamos!
        if (!empty($email_ad) && $email_ad !== $email_atual) {
            $upd_user = $mysqli->prepare("UPDATE usuarios SET email = ? WHERE cod_usuario = ?");
            $upd_user->bind_param('si', $email_ad, $cod_usuario);
            $upd_user->execute();
            $upd_user->close();
        }
        
    } else {
        // NOVIDADE: Inserimos o usuário NOVO já com a coluna de e-mail!
        $ins_user = $mysqli->prepare("INSERT INTO usuarios (usuario, email) VALUES (?, ?)");
        $ins_user->bind_param('ss', $login_ad, $email_ad);
        $ins_user->execute();
        $cod_usuario = $ins_user->insert_id;
        $ins_user->close();
        $novos_cadastrados++;
    }
    $chk_user->close();

    // ==============================================================
    // B. O UTILIZADOR JÁ TEM GRUPO? (Se sim, não mexe!)
    // ==============================================================
    $tem_grupo = false;
    $chk_vinculo = $mysqli->prepare("SELECT cod_grupo FROM grupo_usuario WHERE cod_usuario = ?");
    $chk_vinculo->bind_param('i', $cod_usuario);
    $chk_vinculo->execute();
    $chk_vinculo->store_result();
    if ($chk_vinculo->num_rows > 0) {
        $tem_grupo = true;
    }
    $chk_vinculo->close();

    // ==============================================================
    // C. ATRIBUIÇÃO INTELIGENTE (Só para utilizadores virgens no sistema)
    // ==============================================================
    if (!$tem_grupo) {
        $grupo_destino_cod = 0;

        foreach ($mapeamentos as $map) {
            if (stripos($dn_ad, "OU=" . $map['ou_ad']) !== false) {

                if (!empty($map['cargo_ad'])) {
                    if (stripos($cargo_ad, $map['cargo_ad']) !== false) {
                        $grupo_destino_cod = $map['cod_grupo'];
                        break; 
                    }
                } else {
                    $grupo_destino_cod = $map['cod_grupo'];
                    break;
                }
            }
        }

        if ($grupo_destino_cod > 0) {
            $ins_vinculo = $mysqli->prepare("INSERT INTO grupo_usuario (cod_usuario, cod_grupo) VALUES (?, ?)");
            $ins_vinculo->bind_param('ii', $cod_usuario, $grupo_destino_cod);
            $ins_vinculo->execute();
            $ins_vinculo->close();
            $atribuidos_grupo++;
        }
    }
}
ldap_unbind($ldapconn);

$mensagem = "Sincronização concluída! <b>{$novos_cadastrados}</b> contas criadas. <b>{$atribuidos_grupo}</b> foram mapeadas automaticamente.";
header("Location: " . $BASE_URL . "/admin/contas?msg=" . urlencode($mensagem) . "&tipo=success");
exit();