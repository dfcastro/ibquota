<?php

/**
 * IFQUOTA - Central de Gerenciamento do Usuário (Premium Edition)
 * Combina: Edição, Grupos e Quotas Extras com Auto-Injeção e Memória de Abas.
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 1) {
    header("Location: " . $BASE_URL . "/login");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf_token($_POST['csrf_token'] ?? '');
}

$cod_usuario = isset($_GET['cod_usuario']) ? (int)$_GET['cod_usuario'] : (isset($_POST['cod_usuario']) ? (int)$_POST['cod_usuario'] : 0);
$aba_ativa = isset($_GET['tab']) ? $_GET['tab'] : (isset($_POST['tab']) ? $_POST['tab'] : 'grupos');

if ($cod_usuario === 0) {
    header("Location: " . $BASE_URL . "/admin/contas");
    exit();
}

// BUSCA NOME DO USUÁRIO PRIMEIRO
$stmt = $mysqli->prepare("SELECT usuario FROM usuarios WHERE cod_usuario = ? LIMIT 1");
$stmt->bind_param('i', $cod_usuario);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows < 1) {
    header("Location: " . $BASE_URL . "/admin/contas");
    exit();
}
$stmt->bind_result($usuario);
$stmt->fetch();
$stmt->close();

$msg = "";
$tipo_msg = "";

// ==========================================
// 1. AÇÃO: ALTERAR NOME
// ==========================================
if (isset($_POST['acao']) && $_POST['acao'] == 'editar_nome') {
    $novo_nome = trim($_POST['usuario_nome']);
    $usuario_antigo = trim($_POST['usuario_antigo']);

    $chk = $mysqli->prepare("SELECT cod_usuario FROM usuarios WHERE cod_usuario != ? AND usuario = ?");
    $chk->bind_param('is', $cod_usuario, $novo_nome);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows > 0) {
        $msg = "Já existe outro usuário com o login <b>{$novo_nome}</b>!";
        $tipo_msg = "danger";
    } else {
        $upd1 = $mysqli->prepare("UPDATE usuarios SET usuario = ? WHERE cod_usuario = ?");
        $upd1->bind_param('si', $novo_nome, $cod_usuario);
        $upd1->execute();

        $upd2 = $mysqli->prepare("UPDATE quota_usuario SET usuario = ? WHERE usuario = ?");
        $upd2->bind_param('ss', $novo_nome, $usuario_antigo);
        $upd2->execute();

        $usuario = $novo_nome; // Atualiza a variável da página
        $msg = "Login alterado com sucesso! Atualizado no banco e nas quotas.";
        $tipo_msg = "success";
    }
    $chk->close();
}

// ==========================================
// 2. AÇÃO: ATRIBUIR GRUPO E AUTO-INJETAR COTA
// ==========================================
if (isset($_POST['acao']) && $_POST['acao'] == 'add_grupo') {
    $cod_grupo = (int)$_POST['cod_grupo'];

    $chk = $mysqli->prepare("SELECT cod_usuario FROM grupo_usuario WHERE cod_usuario = ? AND cod_grupo = ?");
    $chk->bind_param('ii', $cod_usuario, $cod_grupo);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows > 0) {
        $msg = "O usuário já pertence a este grupo!";
        $tipo_msg = "warning text-dark";
    } else {
        // Vincula o Grupo
        $ins_grp = $mysqli->prepare("INSERT INTO grupo_usuario (cod_usuario, cod_grupo) VALUES (?, ?)");
        $ins_grp->bind_param('ii', $cod_usuario, $cod_grupo);
        $ins_grp->execute();

        // MÁGICA: Auto-Injeção de Cota (Corrigida para ler o Nome do Grupo - IBQUOTA Legacy)
        $query_pol = "SELECT p.cod_politica, p.quota_padrao, g.grupo 
                      FROM grupos g 
                      JOIN politica_grupo pg ON g.grupo = pg.grupo 
                      JOIN politicas p ON pg.cod_politica = p.cod_politica 
                      WHERE g.cod_grupo = ?";

        $stmt_pol = $mysqli->prepare($query_pol);
        $stmt_pol->bind_param('i', $cod_grupo);
        $stmt_pol->execute();
        $res_pol = $stmt_pol->get_result();

        if ($pol = $res_pol->fetch_assoc()) {
            $cod_politica_auto = $pol['cod_politica'];
            $quota_padrao_auto = $pol['quota_padrao'];
            $nome_grupo_auto   = $pol['grupo'];

            // Verifica se o usuário já tem saldo nessa política
            $chk_q = $mysqli->prepare("SELECT cod_quota_usuario FROM quota_usuario WHERE usuario = ? AND cod_politica = ?");
            $chk_q->bind_param('si', $usuario, $cod_politica_auto);
            $chk_q->execute();
            $chk_q->store_result();

            if ($chk_q->num_rows == 0) {
                // Injeta a cota com o nome do grupo corretamente
                $ins_q = $mysqli->prepare("INSERT INTO quota_usuario (cod_politica, grupo, usuario, quota) VALUES (?, ?, ?, ?)");
                $ins_q->bind_param('issi', $cod_politica_auto, $nome_grupo_auto, $usuario, $quota_padrao_auto);
                $ins_q->execute();
                $ins_q->close();
                $msg = "Grupo vinculado e <b>Cota Inicial de {$quota_padrao_auto} páginas</b> injetada automaticamente!";
            } else {
                $msg = "Grupo vinculado com sucesso! (O usuário já possuía a cota desta política).";
            }
            $chk_q->close();
        } else {
            $msg = "Grupo vinculado com sucesso! (Atenção: Este grupo não possui política de impressão).";
        }
        $stmt_pol->close();
        $tipo_msg = "success";
    }
    $chk->close();
}

// ==========================================
// 3. AÇÃO: REMOVER GRUPO E LIMPAR COTA
// ==========================================
if (isset($_GET['remove_grupo'])) {
    $rm_grupo = (int)$_GET['remove_grupo'];

    $stmt_g = $mysqli->prepare("SELECT grupo FROM grupos WHERE cod_grupo = ?");
    $stmt_g->bind_param('i', $rm_grupo);
    $stmt_g->execute();
    $stmt_g->bind_result($nome_grupo_rm);
    $stmt_g->fetch();
    $stmt_g->close();

    $del_grp = $mysqli->prepare("DELETE FROM grupo_usuario WHERE cod_usuario = ? AND cod_grupo = ?");
    $del_grp->bind_param('ii', $cod_usuario, $rm_grupo);
    $del_grp->execute();
    $del_grp->close();

    if (!empty($nome_grupo_rm)) {
        // IBQUOTA Legacy: Tenta remover a cota vinculada ao nome do grupo
        $del_q = $mysqli->prepare("DELETE FROM quota_usuario WHERE usuario = ? AND grupo = ?");
        $del_q->bind_param('ss', $usuario, $nome_grupo_rm);
        $del_q->execute();
        $del_q->close();
    }

    $msg = "Grupo <b>{$nome_grupo_rm}</b> removido e saldo desvinculado com sucesso!";
    $tipo_msg = "success";
    $aba_ativa = "grupos"; // Força voltar para a aba de grupos
}

// ==========================================
// 4. AÇÃO: QUOTA ADICIONAL
// ==========================================
if (isset($_POST['acao']) && $_POST['acao'] == 'add_quota') {
    $cod_politica = (int)$_POST['cod_politica'];
    $quota_adicional = (int)$_POST['quota_adicional'];
    $motivo = trim($_POST['motivo']);
    $useradmin = $_SESSION['usuario'];

    $quota_antiga = quota_usuario($cod_politica, $usuario);
    $quota_atual = $quota_antiga + $quota_adicional;
    $grupo = grupo_usuario_politica($cod_politica, $usuario);

    $ins_extra = $mysqli->prepare("INSERT INTO quota_adicional (cod_politica, usuario, quota_adicional, motivo, datahora, useradmin) VALUES (?, ?, ?, ?, NOW(), ?)");
    $ins_extra->bind_param('isiss', $cod_politica, $usuario, $quota_adicional, $motivo, $useradmin);
    $ins_extra->execute();

    $chk_q = $mysqli->prepare("SELECT cod_quota_usuario FROM quota_usuario WHERE cod_politica = ? AND usuario = ?");
    $chk_q->bind_param('is', $cod_politica, $usuario);
    $chk_q->execute();
    $chk_q->store_result();

    if ($chk_q->num_rows < 1) {
        $ins_q = $mysqli->prepare("INSERT INTO quota_usuario (cod_politica, grupo, usuario, quota) VALUES (?, ?, ?, ?)");
        $ins_q->bind_param('issi', $cod_politica, $grupo, $usuario, $quota_atual);
        $ins_q->execute();
    } else {
        $upd_q = $mysqli->prepare("UPDATE quota_usuario SET quota = quota + ? WHERE cod_politica = ? AND usuario = ?");
        $upd_q->bind_param('iis', $quota_adicional, $cod_politica, $usuario);
        $upd_q->execute();
    }

    $msg = "Quota extra de <b>+{$quota_adicional} páginas</b> inserida! Novo saldo: <b>{$quota_atual} páginas</b>.";
    $tipo_msg = "success";
}

include __DIR__ . '/../../core/layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div class="d-flex align-items-center">
        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
            <i class="bi bi-person-gear text-primary fs-3"></i>
        </div>
        <div>
            <h3 class="fw-bold text-dark mb-0">Gerenciar Conta</h3>
            <p class="text-muted mb-0 small">Configurações para o utilizador: <b class="text-dark"><?php echo htmlspecialchars($usuario); ?></b></p>
        </div>
    </div>
    <div>
        <a href="<?php echo $BASE_URL; ?>/admin/contas" class="btn btn-outline-secondary shadow-sm fw-bold"><i class="bi bi-arrow-left me-1"></i> Voltar à Lista</a>
    </div>
</div>

<?php if ($msg != "") { ?>
    <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show shadow-sm border-0" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i> <?php echo $msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php } ?>

<div class="card shadow-sm border-0 border-top border-primary border-4 mb-5">
    <div class="card-header bg-white pt-3 pb-0 border-bottom-0">
        <ul class="nav nav-tabs" id="userTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($aba_ativa == 'grupos') ? 'active fw-bold' : ''; ?>" data-bs-toggle="tab" data-bs-target="#grupos" type="button" onclick="document.getElementById('current_tab_quota').value='grupos'; document.getElementById('current_tab_nome').value='grupos';"><i class="bi bi-diagram-3 me-1"></i> Grupos e Permissões</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($aba_ativa == 'quotas') ? 'active fw-bold text-success' : 'text-success'; ?>" data-bs-toggle="tab" data-bs-target="#quotas" type="button" onclick="document.getElementById('current_tab_quota').value='quotas';"><i class="bi bi-plus-circle me-1"></i> Injetar Cota Extra</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($aba_ativa == 'editar') ? 'active fw-bold text-warning' : 'text-warning'; ?>" data-bs-toggle="tab" data-bs-target="#editar" type="button" onclick="document.getElementById('current_tab_nome').value='editar';"><i class="bi bi-pencil me-1"></i> Alterar Login</button>
            </li>
        </ul>
    </div>

    <div class="card-body p-4 bg-light rounded-bottom">
        <div class="tab-content" id="userTabsContent">

            <div class="tab-pane fade <?php echo ($aba_ativa == 'grupos') ? 'show active' : ''; ?>" id="grupos" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 border-end-md pe-md-4 mb-4 mb-md-0">
                        <h5 class="fw-bold mb-3 text-dark">Grupos Vinculados</h5>
                        <ul class="list-group shadow-sm border-0">
                            <?php
                            $sem_grupo = true;
                            $res = $mysqli->query("SELECT g.cod_grupo, g.grupo FROM grupo_usuario gu JOIN grupos g ON g.cod_grupo = gu.cod_grupo WHERE gu.cod_usuario = $cod_usuario");
                            while ($row = $res->fetch_assoc()) {
                                $sem_grupo = false;
                                echo "<li class='list-group-item border-0 d-flex justify-content-between align-items-center py-3 mb-1 bg-white'>";
                                echo "<div>";
                                echo "<span class='fw-bold text-dark d-block'><i class='bi bi-folder-fill text-warning me-2'></i>{$row['grupo']}</span>";
                                echo "</div>";
                                echo "<a href='{$BASE_URL}/admin/contas/gerenciar?cod_usuario=$cod_usuario&remove_grupo={$row['cod_grupo']}' class='btn btn-sm btn-outline-danger shadow-sm' title='Remover do grupo' onclick=\"return confirm('Remover este usuário do grupo {$row['grupo']}?');\"><i class='bi bi-trash-fill'></i></a>";
                                echo "</li>";
                            }
                            if ($sem_grupo) echo "<li class='list-group-item text-muted fst-italic py-4 text-center border-0 bg-white'><i class='bi bi-exclamation-circle d-block fs-3 mb-2'></i>Usuário não pertence a nenhum grupo.</li>";
                            ?>
                        </ul>
                    </div>

                    <div class="col-md-6 ps-md-4">
                        <h5 class="fw-bold mb-3 text-primary">Vincular a um Novo Grupo</h5>
                        <form action="<?php echo $BASE_URL; ?>/admin/contas/gerenciar" method="post" class="card card-body border-0 shadow-sm bg-white p-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                            <input type="hidden" name="cod_usuario" value="<?php echo $cod_usuario; ?>">
                            <input type="hidden" name="acao" value="add_grupo">
                            <input type="hidden" name="tab" value="grupos">

                            <div class="alert alert-info border-0 small py-2"><i class="bi bi-info-circle-fill me-1"></i> Ao vincular, o sistema injetará automaticamente a cota padrão da política associada a este grupo.</div>

                            <div class="mb-4">
                                <label class="form-label text-muted fw-bold small">Selecione o Grupo Destino</label>
                                <select class="form-select border-primary" name="cod_grupo" required>
                                    <option value="" disabled selected>-- Escolha um Grupo --</option>
                                    <?php
                                    $g_res = $mysqli->query("SELECT cod_grupo, grupo FROM grupos ORDER BY grupo");
                                    while ($g_row = $g_res->fetch_assoc()) {
                                        echo "<option value='{$g_row['cod_grupo']}'>{$g_row['grupo']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm"><i class="bi bi-link-45deg me-1"></i> Vincular Grupo e Cota</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade <?php echo ($aba_ativa == 'quotas') ? 'show active' : ''; ?>" id="quotas" role="tabpanel">
                <?php
                $query_pols = "SELECT p.cod_politica, p.nome, p.quota_infinita, p.quota_padrao, g.grupo 
                               FROM politicas p 
                               JOIN politica_grupo pg ON p.cod_politica = pg.cod_politica 
                               JOIN grupos g ON g.grupo = pg.grupo 
                               JOIN grupo_usuario gu ON gu.cod_grupo = g.cod_grupo 
                               WHERE gu.cod_usuario = $cod_usuario";
                $pols_ativas = $mysqli->query($query_pols);

                if ($pols_ativas->num_rows == 0) {
                    echo "<div class='alert alert-warning shadow-sm border-0 p-4 text-center'><i class='bi bi-exclamation-triangle-fill fs-1 text-warning d-block mb-3'></i><h5 class='fw-bold'>Sem Política de Impressão</h5><p class='mb-0'>Este usuário não pertence a nenhum grupo com política de impressão definida. Por favor, adicione-o a um grupo na aba <b>Grupos</b> primeiro.</p></div>";
                } else {
                ?>
                    <form action="<?php echo $BASE_URL; ?>/admin/contas/gerenciar" method="post" class="row">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="cod_usuario" value="<?php echo $cod_usuario; ?>">
                        <input type="hidden" name="usuario_nome" value="<?php echo htmlspecialchars($usuario); ?>">
                        <input type="hidden" name="acao" value="add_quota">
                        <input type="hidden" id="current_tab_quota" name="tab" value="quotas">

                        <div class="col-md-5 mb-4 mb-md-0">
                            <div class="card card-body border-0 shadow-sm bg-white h-100 p-4">
                                <h5 class="fw-bold mb-4 text-success"><i class="bi bi-plus-circle-fill me-2"></i>Injetar Páginas Extras</h5>

                                <div class="mb-3">
                                    <label class="form-label text-muted fw-bold small">Quantidade a Adicionar</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-files"></i></span>
                                        <input type="number" class="form-control border-success" name="quota_adicional" min="1" placeholder="Ex: 50" required>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label text-muted fw-bold small">Justificativa / Motivo</label>
                                    <input type="text" class="form-control" name="motivo" placeholder="Ex: Autorizado via chamado #1234" required>
                                </div>

                                <button type="submit" class="btn btn-success w-100 fw-bold mt-auto shadow-sm" onclick="return confirm('Confirmar injeção de páginas extras?');"><i class="bi bi-check-circle-fill me-1"></i> Creditar Saldo</button>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <h5 class="fw-bold mb-3 text-dark">Política Vigente do Utilizador</h5>
                            <div class="row row-cols-1 g-2">
                                <?php
                                $checked = "checked";
                                while ($pol = $pols_ativas->fetch_assoc()) {
                                    $disabled = ($pol['quota_infinita'] == 1) ? 'disabled' : '';
                                    $bg_class = ($pol['quota_infinita'] == 1) ? 'bg-light text-muted border-0 opacity-75' : 'bg-white shadow-sm border-success border-start border-4';

                                    echo "<div class='col'>";
                                    echo "<label class='card card-body p-3 cursor-pointer {$bg_class} flex-row align-items-center border-0 mb-2'>";
                                    echo "<input class='form-check-input mt-0 me-3' style='transform: scale(1.2);' type='radio' name='cod_politica' value='{$pol['cod_politica']}' required {$disabled} {$checked}>";
                                    echo "<div>";
                                    echo "<span class='d-block fw-bold mb-1 text-dark fs-5'>{$pol['nome']}</span>";
                                    echo "<span class='badge bg-secondary bg-opacity-10 text-secondary border mb-2'><i class='bi bi-diagram-3 me-1'></i> Grupo: {$pol['grupo']}</span><br>";

                                    if ($pol['quota_infinita'] == 1) {
                                        echo "<span class='badge text-bg-info'><i class='bi bi-infinity'></i> Quota Infinita (Injeção não aplicável)</span>";
                                    } else {
                                        $quota_atual_banco = quota_usuario($pol['cod_politica'], $usuario);
                                        echo "<span class='text-muted small'>Saldo atual disponível: <b class='text-success fs-5 ms-1'>{$quota_atual_banco}</b> <span class='small'>págs</span></span>";
                                    }
                                    echo "</div></label></div>";
                                    $checked = "";
                                }
                                ?>
                            </div>
                        </div>
                    </form>
                <?php } ?>
            </div>

            <div class="tab-pane fade <?php echo ($aba_ativa == 'editar') ? 'show active' : ''; ?>" id="editar" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card card-body border-0 shadow-sm text-center p-5 bg-white">
                            <div class="mb-4">
                                <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex p-4 mb-3 text-warning">
                                    <i class="bi bi-person-exclamation fs-1"></i>
                                </div>
                                <h4 class="fw-bold text-dark">Alterar Login na Rede</h4>
                                <p class="text-muted small"><b>Cuidado:</b> Alterar o login aqui pode causar falha de sincronização com o Active Directory se o nome não for alterado lá também.</p>
                            </div>

                            <form action="<?php echo $BASE_URL; ?>/admin/contas/gerenciar" method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                <input type="hidden" name="cod_usuario" value="<?php echo $cod_usuario; ?>">
                                <input type="hidden" name="usuario_antigo" value="<?php echo htmlspecialchars($usuario); ?>">
                                <input type="hidden" name="acao" value="editar_nome">
                                <input type="hidden" id="current_tab_nome" name="tab" value="editar">

                                <div class="input-group input-group-lg shadow-sm border border-warning rounded">
                                    <span class="input-group-text bg-white border-0 text-warning"><i class="bi bi-person-fill"></i></span>
                                    <input type="text" class="form-control border-0" name="usuario_nome" value="<?php echo htmlspecialchars($usuario); ?>" required>
                                    <button type="submit" class="btn btn-warning fw-bold px-4 border-0 text-dark" onclick="return confirm('Confirma a alteração do login deste usuário?');">Salvar Alteração</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../../core/layout/footer.php'; ?>