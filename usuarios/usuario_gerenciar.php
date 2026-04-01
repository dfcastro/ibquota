<?php

/**
 * IBQUOTA 3 - Central de Gerenciamento do Usuário (All-in-One)
 * Combina: Edição, Grupos e Quotas Extras Inteligentes.
 */
include_once '../includes/db.php';
include_once '../includes/functions.php';
sec_session_start();

if (login_check($mysqli) == false || $_SESSION['permissao'] < 1) {
    header("Location: ../login.php");
    exit();
}

$cod_usuario = isset($_GET['cod_usuario']) ? (int)$_GET['cod_usuario'] : (isset($_POST['cod_usuario']) ? (int)$_POST['cod_usuario'] : 0);

if ($cod_usuario === 0) {
    header("Location: index.php");
    exit();
}

$msg = "";
$tipo_msg = "";

// 1. AÇÃO: ALTERAR NOME
if (isset($_POST['acao']) && $_POST['acao'] == 'editar_nome') {
    $novo_nome = trim($_POST['usuario_nome']);
    $usuario_antigo = trim($_POST['usuario_antigo']);

    $chk = $mysqli->prepare("SELECT cod_usuario FROM usuarios WHERE cod_usuario != ? AND usuario = ?");
    $chk->bind_param('is', $cod_usuario, $novo_nome);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows > 0) {
        $msg = "Já existe outro usuário com este nome!";
        $tipo_msg = "danger";
    } else {
        $mysqli->query("UPDATE usuarios SET usuario = '$novo_nome' WHERE cod_usuario = $cod_usuario");
        $mysqli->query("UPDATE quota_usuario SET usuario = '$novo_nome' WHERE usuario = '$usuario_antigo'");
        $msg = "Nome alterado com sucesso!";
        $tipo_msg = "success";
    }
    $chk->close();
}

// 2. AÇÃO: ATRIBUIR GRUPO
if (isset($_POST['acao']) && $_POST['acao'] == 'add_grupo') {
    $cod_grupo = (int)$_POST['cod_grupo'];
    $chk = $mysqli->query("SELECT * FROM grupo_usuario WHERE cod_usuario = $cod_usuario AND cod_grupo = $cod_grupo");
    if ($chk->num_rows > 0) {
        $msg = "Usuário já pertence a este grupo!";
        $tipo_msg = "warning";
    } else {
        $mysqli->query("INSERT INTO grupo_usuario (cod_usuario, cod_grupo) VALUES ($cod_usuario, $cod_grupo)");
        $msg = "Grupo atribuído com sucesso!";
        $tipo_msg = "success";
    }
}

// 3. AÇÃO: REMOVER GRUPO
if (isset($_GET['remove_grupo'])) {
    $rm_grupo = (int)$_GET['remove_grupo'];
    $mysqli->query("DELETE FROM grupo_usuario WHERE cod_usuario = $cod_usuario AND cod_grupo = $rm_grupo");
    $msg = "Grupo removido!";
    $tipo_msg = "success";
}

// 4. AÇÃO: QUOTA ADICIONAL INTELIGENTE
if (isset($_POST['acao']) && $_POST['acao'] == 'add_quota') {
    $usuario = trim($_POST['usuario_nome']);
    $cod_politica = (int)$_POST['cod_politica'];
    $quota_adicional = (int)$_POST['quota_adicional'];
    $motivo = trim($_POST['motivo']);
    $useradmin = $_SESSION['username'];

    $quota_antiga = quota_usuario($cod_politica, $usuario);
    $quota_atual = $quota_antiga + $quota_adicional;
    $grupo = grupo_usuario_politica($cod_politica, $usuario);

    if (strlen($grupo) < 1) {
        $msg = "Erro Crítico: Não foi possível identificar o grupo associado a esta política.";
        $tipo_msg = "danger";
    } else {
        $mysqli->query("INSERT INTO quota_adicional (cod_politica, usuario, quota_adicional, motivo, datahora, useradmin) 
                        VALUES ($cod_politica, '$usuario', $quota_adicional, '$motivo', NOW(), '$useradmin')");

        $chk = $mysqli->query("SELECT cod_quota_usuario FROM quota_usuario WHERE cod_politica = $cod_politica AND usuario = '$usuario' AND grupo = '$grupo'");
        if ($chk->num_rows < 1) {
            $mysqli->query("INSERT INTO quota_usuario (cod_politica, grupo, usuario, quota) VALUES ($cod_politica, '$grupo', '$usuario', $quota_atual)");
        } else {
            $mysqli->query("UPDATE quota_usuario SET quota = quota + $quota_adicional WHERE cod_politica = $cod_politica AND usuario = '$usuario' AND grupo = '$grupo'");
        }
        $msg = "Quota extra de $quota_adicional páginas inserida com sucesso! Novo saldo: $quota_atual páginas.";
        $tipo_msg = "success";
    }
}

// ==========================================
// BUSCA DADOS DO USUÁRIO
// ==========================================
$stmt = $mysqli->prepare("SELECT usuario FROM usuarios WHERE cod_usuario = ? LIMIT 1");
$stmt->bind_param('i', $cod_usuario);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows < 1) {
    header("Location: index.php");
    exit();
}
$stmt->bind_result($usuario);
$stmt->fetch();
$stmt->close();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-person-gear text-muted me-2"></i> Gerenciar Servidor</h3>
        <p class="text-muted mb-0 small">Painel de controle unificado para <b><?php echo htmlspecialchars($usuario); ?></b></p>
    </div>
    <div>
        <a href="index.php" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-left me-1"></i> Voltar à Lista</a>
    </div>
</div>

<?php if ($msg != "") { ?>
    <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i> <?php echo $msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php } ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white pt-3 pb-0 border-bottom-0">
        <ul class="nav nav-tabs" id="userTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#grupos" type="button"><i class="bi bi-diagram-3 me-1"></i> Grupos</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#quotas" type="button"><i class="bi bi-plus-circle me-1"></i> Quota Adicional</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#editar" type="button"><i class="bi bi-pencil me-1"></i> Editar Nome</button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4 bg-light rounded-bottom">
        <div class="tab-content" id="userTabsContent">

            <div class="tab-pane fade show active" id="grupos" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 border-end">
                        <h5 class="fw-bold mb-3">Grupos Atuais</h5>
                        <ul class="list-group shadow-sm mb-4">
                            <?php
                            $sem_grupo = true;
                            $res = $mysqli->query("SELECT g.cod_grupo, g.grupo FROM grupo_usuario gu JOIN grupos g ON g.cod_grupo = gu.cod_grupo WHERE gu.cod_usuario = $cod_usuario");
                            while ($row = $res->fetch_assoc()) {
                                $sem_grupo = false;
                                echo "<li class='list-group-item d-flex justify-content-between align-items-center py-2'>";
                                echo "<span class='fw-semibold text-dark'><i class='bi bi-folder2 text-primary me-2'></i>{$row['grupo']}</span>";
                                echo "<a href='usuario_gerenciar.php?cod_usuario=$cod_usuario&remove_grupo={$row['cod_grupo']}' class='btn btn-sm btn-outline-danger' title='Remover do grupo'><i class='bi bi-x-lg'></i></a>";
                                echo "</li>";
                            }
                            if ($sem_grupo) echo "<li class='list-group-item text-muted fst-italic py-3 text-center'>Usuário não pertence a nenhum grupo.</li>";
                            ?>
                        </ul>
                    </div>
                    <div class="col-md-6 ps-md-4">
                        <h5 class="fw-bold mb-3">Adicionar ao Grupo</h5>
                        <form action="usuario_gerenciar.php" method="post" class="card card-body border-0 shadow-sm">
                            <input type="hidden" name="cod_usuario" value="<?php echo $cod_usuario; ?>">
                            <input type="hidden" name="acao" value="add_grupo">
                            <div class="mb-3">
                                <label class="form-label text-muted small">Selecione um grupo disponível</label>
                                <select class="form-select" name="cod_grupo" required>
                                    <option value="">-- Escolha --</option>
                                    <?php
                                    $g_res = $mysqli->query("SELECT cod_grupo, grupo FROM grupos");
                                    while ($g_row = $g_res->fetch_assoc()) {
                                        echo "<option value='{$g_row['cod_grupo']}'>{$g_row['grupo']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-ifnmg w-100 fw-bold"><i class="bi bi-link-45deg me-1"></i> Vincular Grupo</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="quotas" role="tabpanel">
                <?php
                // O SQL Mágico: Procura as políticas atreladas aos grupos que o utilizador faz parte
                $query_pols = "SELECT p.cod_politica, p.nome, p.quota_infinita, p.quota_padrao, g.grupo 
                               FROM politicas p 
                               JOIN politica_grupo pg ON p.cod_politica = pg.cod_politica 
                               JOIN grupos g ON g.grupo = pg.grupo 
                               JOIN grupo_usuario gu ON gu.cod_grupo = g.cod_grupo 
                               WHERE gu.cod_usuario = $cod_usuario";
                $pols_ativas = $mysqli->query($query_pols);

                if ($pols_ativas->num_rows == 0) {
                    // Proteção: O utilizador não tem grupo, logo não pode receber quota!
                    echo "<div class='alert alert-warning shadow-sm border-0'><i class='bi bi-exclamation-triangle-fill me-2'></i><b>Atenção:</b> Este usuário não pertence a nenhum grupo com política de impressão definida. Por favor, adicione-o a um grupo na aba <b>'Grupos'</b> primeiro.</div>";
                } else {
                ?>
                    <form action="usuario_gerenciar.php" method="post" class="row">
                        <input type="hidden" name="cod_usuario" value="<?php echo $cod_usuario; ?>">
                        <input type="hidden" name="usuario_nome" value="<?php echo htmlspecialchars($usuario); ?>">
                        <input type="hidden" name="acao" value="add_quota">

                        <div class="col-md-5">
                            <div class="card card-body border-0 shadow-sm h-100">
                                <h5 class="fw-bold mb-3 text-success">Injetar Páginas</h5>
                                <div class="mb-3">
                                    <label class="form-label text-muted small">Quantidade de Páginas Extras</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-file-earmark-plus"></i></span>
                                        <input type="number" class="form-control" name="quota_adicional" min="1" placeholder="Ex: 100" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label text-muted small">Motivo da Adição</label>
                                    <input type="text" class="form-control" name="motivo" placeholder="Ex: Impressão de Provas P1" required>
                                </div>
                                <button type="submit" class="btn btn-ifnmg fw-bold py-2 mt-auto"><i class="bi bi-save me-1"></i> Registrar Quota Extra</button>
                            </div>
                        </div>

                        <div class="col-md-7 mt-3 mt-md-0">
                            <h5 class="fw-bold mb-3 ms-2">Política Vinculada Automaticamente</h5>
                            <div class="row row-cols-1 g-2">
                                <?php
                                $checked = "checked"; // O primeiro grupo que o sistema achar já fica marcado
                                while ($pol = $pols_ativas->fetch_assoc()) {
                                    $disabled = ($pol['quota_infinita'] == 1) ? 'disabled' : '';
                                    $bg_class = ($pol['quota_infinita'] == 1) ? 'bg-secondary bg-opacity-10 text-muted border-0' : 'bg-white shadow-sm border-success border-start border-4';

                                    echo "<div class='col'>";
                                    echo "<label class='card card-body p-3 cursor-pointer {$bg_class} flex-row align-items-center'>";
                                    // Input radio (Se houver só 1 política, o admin nem precisa clicar)
                                    echo "<input class='form-check-input mt-0 me-3' type='radio' name='cod_politica' value='{$pol['cod_politica']}' required {$disabled} {$checked}>";
                                    echo "<div>";
                                    echo "<span class='d-block fw-bold mb-1'>{$pol['nome']} <span class='badge bg-light text-dark border ms-2'><i class='bi bi-diagram-3'></i> Grupo Associado: {$pol['grupo']}</span></span>";

                                    if ($pol['quota_infinita'] == 1) {
                                        echo "<span class='badge text-bg-secondary'><i class='bi bi-infinity'></i> Quota Infinita (Não é necessário adicionar páginas)</span>";
                                    } else {
                                        // A MÁGICA: O sistema já calcula e mostra o saldo atual dele!
                                        $quota_atual_banco = quota_usuario($pol['cod_politica'], $usuario);
                                        echo "<span class='small text-muted'>Saldo atual do servidor: <b class='text-dark fs-6'>{$quota_atual_banco}</b> páginas</span>";
                                    }
                                    echo "</div></label></div>";
                                    $checked = ""; // Só marca o primeiro
                                }
                                ?>
                            </div>
                        </div>
                    </form>
                <?php } ?>
            </div>

            <div class="tab-pane fade" id="editar" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card card-body border-0 shadow-sm text-center p-5">
                            <div class="mb-4">
                                <i class="bi bi-person-exclamation fs-1 text-warning"></i>
                                <h4 class="fw-bold mt-2">Alterar Login na Rede</h4>
                                <p class="text-muted small">Cuidado: Alterar o login pode dessincronizar o usuário com o Active Directory/LDAP se ele não tiver sido alterado no servidor principal primeiro.</p>
                            </div>
                            <form action="usuario_gerenciar.php" method="post">
                                <input type="hidden" name="cod_usuario" value="<?php echo $cod_usuario; ?>">
                                <input type="hidden" name="usuario_antigo" value="<?php echo htmlspecialchars($usuario); ?>">
                                <input type="hidden" name="acao" value="editar_nome">

                                <div class="input-group input-group-lg mb-4">
                                    <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" name="usuario_nome" value="<?php echo htmlspecialchars($usuario); ?>" required>
                                    <button type="submit" class="btn btn-warning fw-bold px-4">Salvar Alteração</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>