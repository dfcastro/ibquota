<?php

/**
 * IBQUOTA 3 - Gerenciamento de Política (Edição, Grupos e Impressoras)
 * Atualizado com Busca Dinâmica de Impressoras via CUPS e Segurança Contra SQLi
 */
include_once '../../core/db.php';
include_once '../../core/functions.php';
sec_session_start();

if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] !== 2) {
    header("Location: ../../public/login.php");
    exit();
}

$cod_politica = isset($_GET['cod_politica']) ? (int)$_GET['cod_politica'] : (isset($_POST['cod_politica']) ? (int)$_POST['cod_politica'] : 0);
if ($cod_politica === 0) {
    header("Location: index.php");
    exit();
}

$msg = "";
$tipo_msg = "";

// AÇÃO 1: SALVAR CONFIGURAÇÕES BÁSICAS
if (isset($_POST['acao']) && $_POST['acao'] == 'editar_politica') {
    $nome = trim($_POST['nome']);
    $quota_padrao = (int)$_POST['quota_padrao'];
    $quota_infinita = isset($_POST['quota_infinita']) ? 1 : 0;

    $upd = $mysqli->prepare("UPDATE politicas SET nome=?, quota_padrao=?, quota_infinita=? WHERE cod_politica=?");
    $upd->bind_param('siii', $nome, $quota_padrao, $quota_infinita, $cod_politica);
    $upd->execute();
    $upd->close();
    $msg = "Configurações da política atualizadas com sucesso!";
    $tipo_msg = "success";
}

// AÇÃO 2: SALVAR GRUPOS VINCULADOS (Corrigido para Prepared Statement)
if (isset($_POST['acao']) && $_POST['acao'] == 'salvar_grupos') {
    // Apaga os registos antigos com segurança
    $del_grupos = $mysqli->prepare("DELETE FROM politica_grupo WHERE cod_politica = ?");
    $del_grupos->bind_param('i', $cod_politica);
    $del_grupos->execute();
    $del_grupos->close();

    if (isset($_POST['grupos_marcados']) && is_array($_POST['grupos_marcados'])) {
        $ins = $mysqli->prepare("INSERT INTO politica_grupo (cod_politica, grupo) VALUES (?, ?)");
        foreach ($_POST['grupos_marcados'] as $nome_do_grupo) {
            $ins->bind_param('is', $cod_politica, $nome_do_grupo);
            $ins->execute();
        }
        $ins->close();
    }
    $msg = "Grupos vinculados sincronizados com sucesso!";
    $tipo_msg = "success";
}

// AÇÃO 3: ADICIONAR IMPRESSORA (Corrigido falha de SQL Injection)
if (isset($_POST['acao']) && $_POST['acao'] == 'add_impressora') {
    $impressora = trim($_POST['impressora']);
    $peso = isset($_POST['peso']) ? (int)$_POST['peso'] : 1;

    // Evita duplicar a mesma impressora na mesma política de forma SEGURA
    $chk = $mysqli->prepare("SELECT cod_politica_impressora FROM politica_impressora WHERE cod_politica = ? AND impressora = ?");
    $chk->bind_param('is', $cod_politica, $impressora);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows > 0) {
        $msg = "Esta impressora já está vinculada a esta política!";
        $tipo_msg = "warning";
    } elseif (!empty($impressora)) {
        $ins = $mysqli->prepare("INSERT INTO politica_impressora (cod_politica, impressora, peso) VALUES (?, ?, ?)");
        $ins->bind_param('isi', $cod_politica, $impressora, $peso);
        $ins->execute();
        $ins->close();
        $msg = "Impressora vinculada com sucesso!";
        $tipo_msg = "success";
    }
    $chk->close();
}

// AÇÃO 4: REMOVER IMPRESSORA
if (isset($_POST['acao']) && $_POST['acao'] == 'del_impressora') {
    $cod_politica_impressora = (int)$_POST['cod_politica_impressora'];
    $del = $mysqli->prepare("DELETE FROM politica_impressora WHERE cod_politica_impressora = ? AND cod_politica = ?");
    $del->bind_param('ii', $cod_politica_impressora, $cod_politica);
    $del->execute();
    $del->close();
    $msg = "Impressora removida com sucesso!";
    $tipo_msg = "success";
}

// BUSCA OS DADOS DA POLÍTICA
$stmt = $mysqli->prepare("SELECT nome, quota_padrao, quota_infinita FROM politicas WHERE cod_politica = ?");
$stmt->bind_param('i', $cod_politica);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows < 1) {
    header("Location: index.php");
    exit();
}
$stmt->bind_result($nome_politica, $quota_padrao, $quota_infinita);
$stmt->fetch();
$stmt->close();

// BUSCA OS GRUPOS ATUALMENTE VINCULADOS
$grupos_vinculados = [];
$res_vinculos = $mysqli->query("SELECT grupo FROM politica_grupo WHERE cod_politica = $cod_politica");
while ($v = $res_vinculos->fetch_assoc()) {
    $grupos_vinculados[] = $v['grupo'];
}

// ==========================================
// LEITURA INTELIGENTE DE IMPRESSORAS (CUPS)
// ==========================================
$impressoras_servidor = [];
// Roda o comando no Linux e silencia erros (caso rode em Windows)
$saida_lpstat = @shell_exec('lpstat -a 2>/dev/null');

if (!empty($saida_lpstat)) {
    $linhas = explode("\n", trim($saida_lpstat));
    foreach ($linhas as $linha) {
        $partes = explode(" ", $linha);
        // O nome da impressora é sempre a primeira palavra da linha no lpstat
        if (!empty($partes[0])) {
            $impressoras_servidor[] = $partes[0];
        }
    }
    sort($impressoras_servidor); // Coloca em ordem alfabética
}

include '../../core/layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-shield-check text-success me-2"></i> Configurar Política</h3>
        <p class="text-muted mb-0 small">Regra atual: <b><?php echo htmlspecialchars($nome_politica); ?></b></p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</div>

<?php if ($msg != "") { ?>
    <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible shadow-sm"><i class="bi bi-info-circle-fill me-2"></i> <?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php } ?>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold py-3"><i class="bi bi-sliders me-2 text-primary"></i>Regras Básicas</div>
            <div class="card-body bg-light">
                <form action="politica_gerenciar.php" method="post">
                    <input type="hidden" name="cod_politica" value="<?php echo $cod_politica; ?>">
                    <input type="hidden" name="acao" value="editar_politica">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Nome da Política</label>
                        <input type="text" class="form-control" name="nome" value="<?php echo htmlspecialchars($nome_politica); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cota Padrão (Páginas)</label>
                        <input type="number" class="form-control" name="quota_padrao" value="<?php echo $quota_padrao; ?>" required>
                    </div>
                    <div class="form-check form-switch mt-4 p-3 bg-white border rounded shadow-sm">
                        <input class="form-check-input ms-0 mt-1" type="checkbox" name="quota_infinita" id="inf" value="1" <?php echo ($quota_infinita == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold text-danger ms-2" for="inf">Cota Infinita (Sem limites)</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-4 fw-bold"><i class="bi bi-save me-1"></i> Atualizar Regras</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7 mb-4">

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-ifnmg text-white fw-bold py-3"><i class="bi bi-diagram-3 me-2"></i>Grupos Obedecendo a Esta Regra</div>
            <div class="card-body">
                <p class="text-muted small mb-3">Marque os grupos do campus que devem receber automaticamente as configurações desta política de impressão.</p>

                <form action="politica_gerenciar.php" method="post">
                    <input type="hidden" name="cod_politica" value="<?php echo $cod_politica; ?>">
                    <input type="hidden" name="acao" value="salvar_grupos">

                    <div class="row row-cols-1 row-cols-md-2 g-3 mb-4 max-h-300" style="max-height: 250px; overflow-y: auto; overflow-x: hidden;">
                        <?php
                        $res_grupos = $mysqli->query("SELECT grupo FROM grupos ORDER BY grupo");
                        if ($res_grupos->num_rows > 0) {
                            while ($g = $res_grupos->fetch_assoc()) {
                                $nome_g = htmlspecialchars($g['grupo']);
                                $marcado = in_array($g['grupo'], $grupos_vinculados) ? "checked" : "";
                                $bg_class = $marcado ? "bg-success bg-opacity-10 border-success" : "bg-light border-light";

                                echo "<div class='col'>";
                                echo "<label class='card card-body p-2 px-3 cursor-pointer {$bg_class} flex-row align-items-center shadow-sm' style='cursor: pointer;'>";
                                echo "<input class='form-check-input mt-0 me-3' type='checkbox' name='grupos_marcados[]' value='{$nome_g}' {$marcado}>";
                                echo "<span class='fw-semibold text-dark text-truncate'>{$nome_g}</span>";
                                echo "</label></div>";
                            }
                        } else {
                            echo "<div class='alert alert-warning w-100'>Nenhum grupo cadastrado no sistema ainda.</div>";
                        }
                        ?>
                    </div>

                    <button type="submit" class="btn btn-success fw-bold"><i class="bi bi-link-45deg me-1"></i> Sincronizar Grupos</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 border-top border-dark border-3">
            <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-printer-fill me-2 text-dark"></i>Impressoras Permitidas</span>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php
                    $stmt_imp = $mysqli->prepare("SELECT cod_politica_impressora, impressora, peso FROM politica_impressora WHERE cod_politica = ? ORDER by impressora");
                    $stmt_imp->bind_param('i', $cod_politica);
                    $stmt_imp->execute();
                    $stmt_imp->store_result();
                    $stmt_imp->bind_result($cod_politica_impressora, $impressora, $peso);

                    $tem_impressora = false;
                    while ($stmt_imp->fetch()) {
                        $tem_impressora = true;
                        echo "<li class='list-group-item d-flex justify-content-between align-items-center py-3 bg-light'>";
                        echo "<div><span class='fw-bold text-dark d-block'>{$impressora}</span><small class='text-muted'>Peso de impressão: {$peso}</small></div>";

                        echo "<form action='politica_gerenciar.php' method='post' class='m-0'>";
                        echo "<input type='hidden' name='cod_politica' value='{$cod_politica}'>";
                        echo "<input type='hidden' name='acao' value='del_impressora'>";
                        echo "<input type='hidden' name='cod_politica_impressora' value='{$cod_politica_impressora}'>";
                        echo "<button type='submit' class='btn btn-outline-danger btn-sm' title='Remover Impressora' onclick='return confirm(\"Remover esta impressora da política?\")'><i class='bi bi-trash3'></i></button>";
                        echo "</form>";
                        echo "</li>";
                    }
                    if (!$tem_impressora) {
                        echo "<li class='list-group-item text-danger fw-bold text-center py-4'><i class='bi bi-exclamation-triangle me-2'></i>Nenhuma impressora liberada. Impressões serão bloqueadas.</li>";
                    }
                    $stmt_imp->close();
                    ?>

                    <li class="list-group-item py-3">
                        <form action="politica_gerenciar.php" method="post">
                            <input type="hidden" name="cod_politica" value="<?php echo $cod_politica; ?>">
                            <input type="hidden" name="acao" value="add_impressora">
                            <div class="input-group">
                                <?php if (!empty($impressoras_servidor)) { ?>
                                    <select class="form-select border-dark" name="impressora" required>
                                        <option value="" disabled selected>Escolha a impressora na rede...</option>
                                        <?php foreach ($impressoras_servidor as $imp) { ?>
                                            <option value="<?php echo htmlspecialchars($imp); ?>"><?php echo htmlspecialchars($imp); ?></option>
                                        <?php } ?>
                                    </select>
                                <?php } else { ?>
                                    <input type="text" class="form-control border-dark" placeholder="Nome exato da Impressora no CUPS" name="impressora" required>
                                <?php } ?>

                                <input type="number" class="form-control" placeholder="Peso" name="peso" value="1" min="1" style="max-width: 90px;" title="Peso por página">
                                <button type="submit" class="btn btn-dark text-white fw-bold"><i class="bi bi-plus-circle me-1"></i>Add</button>
                            </div>
                            <small class="text-muted mt-2 d-block"><i class="bi bi-info-circle me-1"></i>
                                <?php echo !empty($impressoras_servidor) ? "Lista puxada automaticamente do servidor CUPS local." : "O nome é <b>Case-sensitive</b> e precisa ser idêntico ao do servidor."; ?>
                            </small>
                        </form>
                    </li>
                </ul>
            </div>
        </div>

    </div>
</div>

<?php include '../../core/layout/footer.php'; ?>