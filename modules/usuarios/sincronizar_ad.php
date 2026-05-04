<?php

/**
 * IBQUOTA 3 - SINCRONIZAÇÃO INTELIGENTE COM ACTIVE DIRECTORY
 * Puxa usuários novos do AD e atribui automaticamente ao grupo correto baseado na OU.
 * Refatorado para Mapeamento Dinâmico (Sem IDs Fixos).
 */
include_once '../../core/db.php';
include_once '../../core/functions.php';
sec_session_start();

// Apenas Admin (Nível 2).
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] != 2) {
    header("Location: ../../public/login.php");
    exit();
}

// ============================================================================
// 1. DICIONÁRIO DE MAPEAMENTO (OU do AD -> NOME EXATO DO GRUPO NO IBQUOTA)
// ============================================================================
$mapeamento_ous = [
    'UO_NTI'           => 'GRUPO-NGTI',
    'UO_NPED'          => 'GRUPO-NPED',
    'UO_DOCENTE'       => 'GRUPO-DOCENTE-800',
    'UO_TAE'           => 'GRUPO-TAE-800',
    'UO_TERCEIRIZADOS' => 'GRUPO-TAE-800' // Terceirizados herdam a mesma regra dos TAEs
];

// 2. Carrega os IDs reais dos grupos diretamente do banco (Evita IDs Hardcoded)
$grupos_db = [];
$res_g = $mysqli->query("SELECT cod_grupo, grupo FROM grupos");
while ($row = $res_g->fetch_assoc()) {
    // Cria um dicionário interno: ['GRUPO-NGTI' => 10, 'GRUPO-NPED' => 12...]
    $grupos_db[$row['grupo']] = $row['cod_grupo'];
}

// 3. Conecta ao AD
$stmt = $mysqli->prepare("SELECT LDAP_server, LDAP_port, LDAP_base, LDAP_user, LDAP_password FROM config_geral WHERE id = 1");
$stmt->execute();
$stmt->bind_result($ldap_server, $ldap_porta, $ldap_base, $ldap_usuario, $ldap_senha);
$stmt->fetch();
$stmt->close();

$ldapconn = @ldap_connect($ldap_server, $ldap_porta);
if (!$ldapconn) {
    header("Location: index.php?msg=" . urlencode("Erro: Não foi possível conectar ao servidor AD.") . "&tipo=danger");
    exit();
}

ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);

$bind = @ldap_bind($ldapconn, $ldap_usuario, $ldap_senha);
if (!$bind) {
    header("Location: index.php?msg=" . urlencode("Erro de credenciais do AD.") . "&tipo=danger");
    exit();
}

// 4. Busca apenas PESSOAS ATIVAS
$filtro_sync = "(&(objectCategory=person)(objectClass=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2))(sAMAccountName=*))";
$search = @ldap_search($ldapconn, $ldap_base, $filtro_sync);
$info = ldap_get_entries($ldapconn, $search);

if (!$info || $info["count"] == 0) {
    header("Location: index.php?msg=" . urlencode("Nenhum usuário ativo encontrado no AD.") . "&tipo=warning");
    exit();
}

// 5. Carrega quem já está no banco local para não duplicar
$usuarios_existentes = [];
$res_banco = $mysqli->query("SELECT usuario FROM usuarios");
while ($row = $res_banco->fetch_assoc()) {
    $usuarios_existentes[] = strtolower($row['usuario']);
}

// 6. Inicia a Mágica da Sincronização
$novos_cadastrados = 0;
$atribuidos_grupo = 0;

for ($i = 0; $i < $info["count"]; $i++) {
    if (isset($info[$i]["samaccountname"][0])) {
        $login_ad = strtolower(trim($info[$i]["samaccountname"][0]));
        $dn_ad = strtoupper($info[$i]["dn"]);

        // Se a pessoa NÃO existe no IBQuota, cadastra!
        if (!in_array($login_ad, $usuarios_existentes) && $login_ad != "") {

            // A. Insere na tabela 'usuarios'
            $stmt_ins = $mysqli->prepare("INSERT INTO usuarios (usuario) VALUES (?)");
            $stmt_ins->bind_param('s', $login_ad);
            $stmt_ins->execute();
            $novo_cod_usuario = $stmt_ins->insert_id; // Pega o ID (cod_usuario) recém criado!
            $stmt_ins->close();

            $novos_cadastrados++;
            $usuarios_existentes[] = $login_ad; // Adiciona na lista para evitar duplicidade na mesma rodada

            // B. Descobre o ID real do grupo baseado na OU
            $grupo_destino_cod = null;
            foreach ($mapeamento_ous as $nome_ou => $nome_grupo_alvo) {
                // Verifica se o caminho do AD contém a OU e se o grupo alvo existe no banco
                if (strpos($dn_ad, "OU=" . $nome_ou) !== false) {
                    if (isset($grupos_db[$nome_grupo_alvo])) {
                        $grupo_destino_cod = $grupos_db[$nome_grupo_alvo];
                    }
                    break; // Já achou a regra, para de procurar
                }
            }

            // C. Se achou uma regra correspondente e o grupo existe, vincula!
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

// 7. Retorno de Sucesso com Estatísticas
$mensagem = "Sincronização concluída! <b>{$novos_cadastrados}</b> novos usuários importados. <b>{$atribuidos_grupo}</b> foram colocados nos grupos automaticamente.";
header("Location: index.php?msg=" . urlencode($mensagem) . "&tipo=success");
exit();
