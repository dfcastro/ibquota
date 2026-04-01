<?php
/**
 * IBQUOTA 3
 * Configuração Geral e Manutenção do Sistema (Refatorado Bootstrap 5)
 */  
include_once 'includes/db.php';
include_once 'includes/functions.php';
sec_session_start();

if (login_check($mysqli) == false || $_SESSION['permissao'] != 2) {
   header("Location: login.php"); exit();
}

$msg = ""; $tipo_msg = "";

// ==========================================
// AÇÃO 1: SALVAR CONFIGURAÇÕES
// ==========================================
if (isset($_POST['acao']) && $_POST['acao'] == 'salvar_config') {
    $path_pkpgcounter = trim($_POST['path_pkpgcounter']);
    $path_python = trim($_POST['path_python']);
    $nivel_debug = (int)trim($_POST['nivel_debug']);
    $base_local = (int)trim($_POST['base_local']);
    $ldap_server = trim($_POST['ldap_server']);
    $ldap_porta = trim($_POST['ldap_porta']);
    $ldap_usuario = trim($_POST['ldap_usuario']);
    $ldap_senha = trim($_POST['ldap_senha']);
    $ldap_filtro = trim($_POST['ldap_filtro']);
    $ldap_base = trim($_POST['ldap_base']);

    if ($update_stmt = $mysqli->prepare("UPDATE config_geral SET path_pkpgcounter=?, path_python=?, base_local=?, LDAP_server=?, LDAP_port=?, LDAP_filter=?, LDAP_base=?, LDAP_user=?, LDAP_password=?, Debug=? WHERE id=1")) {
        $update_stmt->bind_param('ssissssssi', $path_pkpgcounter, $path_python, $base_local, $ldap_server, $ldap_porta, $ldap_filtro, $ldap_base, $ldap_usuario, $ldap_senha, $nivel_debug);
        if ($update_stmt->execute()) {
            $msg = "Configurações do sistema gravadas com sucesso!"; $tipo_msg = "success";
        } else {
            $msg = "Erro ao gravar configurações."; $tipo_msg = "danger";
        }
        $update_stmt->close();
    }
}

// ==========================================
// AÇÃO 2: MANUTENÇÃO (LIMPEZA DE LOGS)
// ==========================================
if (isset($_POST['acao']) && $_POST['acao'] == 'limpar_logs') {
    $anos_retencao = (int)$_POST['anos_retencao'];
    $data_limite = date('Y-m-d', strtotime("-{$anos_retencao} years"));

    // Conta os registros que serão apagados
    $res = $mysqli->query("SELECT COUNT(*) as total FROM impressoes WHERE data_impressao < '$data_limite'");
    $total_apagados = $res->fetch_assoc()['total'];

    if ($total_apagados > 0) {
        $mysqli->query("DELETE FROM impressoes WHERE data_impressao < '$data_limite'");
        $mysqli->query("OPTIMIZE TABLE impressoes"); // Recupera espaço no disco!
        $msg = "Manutenção concluída! <b>{$total_apagados}</b> registros antigos foram apagados e a tabela foi otimizada."; 
        $tipo_msg = "success";
    } else {
        $msg = "Nenhum registro anterior a " . date('d/m/Y', strtotime($data_limite)) . " foi encontrado. A base já está limpa!"; 
        $tipo_msg = "info";
    }
}

// Busca as configurações atuais
$stmt = $mysqli->prepare("SELECT path_pkpgcounter, path_python, base_local, LDAP_server, LDAP_port, LDAP_filter, LDAP_base, LDAP_user, LDAP_password, Debug FROM config_geral WHERE id = 1 LIMIT 1");
$stmt->execute();
$stmt->bind_result($path_pkpgcounter, $path_python, $base_local, $ldap_server, $ldap_porta, $ldap_filtro, $ldap_base, $ldap_usuario, $ldap_senha, $nivel_debug);
$stmt->fetch();
$stmt->close();

// Busca o tamanho atual da tabela de impressões para exibir no painel
$res_tamanho = $mysqli->query("SELECT COUNT(*) as total FROM impressoes");
$total_banco = $res_tamanho->fetch_assoc()['total'];

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-gear text-muted me-2"></i> Configurações do Sistema</h3>
        <p class="text-muted mb-0 small">Gerencie as integrações do servidor, Active Directory e manutenção do banco.</p>
    </div>
</div>

<?php if ($msg != "") { ?>
    <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show shadow-sm"><i class="bi bi-info-circle-fill me-2"></i> <?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php } ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white pt-3 pb-0 border-bottom-0">
        <ul class="nav nav-tabs" id="configTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#geral" type="button"><i class="bi bi-sliders me-1"></i> Geral & LDAP</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-danger" data-bs-toggle="tab" data-bs-target="#manutencao" type="button"><i class="bi bi-database-gear me-1"></i> Manutenção do Banco</button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4 bg-light rounded-bottom">
        <div class="tab-content" id="configTabsContent">
            
            <div class="tab-pane fade show active" id="geral" role="tabpanel">
                <form action="configuracao.php" method="post">
                    <input type="hidden" name="acao" value="salvar_config">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-header bg-ifnmg text-white fw-bold"><i class="bi bi-cpu me-2"></i>Motor do Sistema</div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Nível de Log (Debug)</label>
                                        <select class="form-select" name="nivel_debug">
                                            <option value="0" <?php echo ($nivel_debug == 0) ? "selected" : ""; ?>>Sem Log</option>
                                            <option value="1" <?php echo ($nivel_debug == 1) ? "selected" : ""; ?>>Log Mínimo</option>
                                            <option value="2" <?php echo ($nivel_debug == 2) ? "selected" : ""; ?>>Log Detalhado</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Caminho do <code>python</code></label>
                                        <input type="text" class="form-control font-monospace text-muted" name="path_python" value="<?php echo htmlspecialchars($path_python); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Caminho do <code>pkpgcounter</code></label>
                                        <input type="text" class="form-control font-monospace text-muted" name="path_pkpgcounter" value="<?php echo htmlspecialchars($path_pkpgcounter); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-header bg-dark text-white fw-bold"><i class="bi bi-server me-2"></i>Integração LDAP / AD</div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Base de Dados de Usuários</label>
                                        <select class="form-select border-dark" name="base_local">
                                            <option value="0" <?php echo ($base_local == 0) ? "selected" : ""; ?>>LDAP ou Active Directory (Recomendado)</option>
                                            <option value="1" <?php echo ($base_local == 1) ? "selected" : ""; ?>>LOCAL (Banco de Dados Interno)</option>
                                        </select>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-8">
                                            <label class="form-label fw-bold small">Servidor LDAP</label>
                                            <input type="text" class="form-control" name="ldap_server" value="<?php echo htmlspecialchars($ldap_server); ?>">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label fw-bold small">Porta</label>
                                            <input type="text" class="form-control" name="ldap_porta" value="<?php echo htmlspecialchars($ldap_porta); ?>">
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label fw-bold small">Base LDAP (DN)</label>
                                        <input type="text" class="form-control" name="ldap_base" value="<?php echo htmlspecialchars($ldap_base); ?>" placeholder="Ex: dc=ifnmg,dc=edu,dc=br">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label fw-bold small">Filtro LDAP</label>
                                        <input type="text" class="form-control font-monospace" name="ldap_filtro" value="<?php echo htmlspecialchars($ldap_filtro); ?>" placeholder="Ex: (sAMAccountName=$user)">
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label fw-bold small">Usuário Bind</label>
                                            <input type="text" class="form-control" name="ldap_usuario" value="<?php echo htmlspecialchars($ldap_usuario); ?>" placeholder="Deixe em branco para Anônimo">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label fw-bold small">Senha Bind</label>
                                            <input type="password" class="form-control" name="ldap_senha" value="<?php echo htmlspecialchars($ldap_senha); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary fw-bold px-5"><i class="bi bi-save me-1"></i> Gravar Configurações</button>
                    </div>
                </form>
            </div>

            <div class="tab-pane fade" id="manutencao" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card border-danger shadow-sm mt-3">
                            <div class="card-header bg-danger text-white fw-bold"><i class="bi bi-trash-fill me-2"></i>Limpeza de Histórico Antigo</div>
                            <div class="card-body p-4 text-center">
                                <h1 class="display-4 fw-bold text-dark mb-0"><?php echo number_format($total_banco, 0, ',', '.'); ?></h1>
                                <p class="text-muted mb-4">Total de registros de impressão salvos no banco de dados.</p>
                                
                                <div class="alert alert-warning text-start small mb-4">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Bancos de dados muito grandes deixam a geração de relatórios lenta. Utilize a ferramenta abaixo para apagar históricos antigos e liberar espaço em disco no servidor.
                                </div>

                                <form action="configuracao.php" method="post" class="bg-light p-3 border rounded">
                                    <input type="hidden" name="acao" value="limpar_logs">
                                    <div class="row align-items-center justify-content-center">
                                        <div class="col-auto">
                                            <label class="fw-bold">Apagar registros mais antigos que:</label>
                                        </div>
                                        <div class="col-auto">
                                            <select class="form-select border-danger text-danger fw-bold" name="anos_retencao">
                                                <option value="1">1 Ano (Recomendado)</option>
                                                <option value="2" selected>2 Anos</option>
                                                <option value="3">3 Anos</option>
                                                <option value="5">5 Anos</option>
                                            </select>
                                        </div>
                                        <div class="col-auto mt-3 mt-md-0">
                                            <button type="submit" class="btn btn-danger fw-bold" onclick="return confirm('ATENÇÃO: Esta ação é irreversível. O sistema irá apagar os registros antigos e otimizar o banco. Deseja continuar?');"><i class="bi bi-eraser-fill me-1"></i> Executar Limpeza</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>