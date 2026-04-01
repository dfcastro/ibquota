<?php 
/**
 * IBQUOTA 3 - Gerenciamento de Política (Edição e Grupos Vinculados)
 */  
include_once '../includes/db.php';
include_once '../includes/functions.php';
sec_session_start();

if (login_check($mysqli) == false || $_SESSION['permissao'] !== 2) {
  header("Location: ../login.php"); exit();
}

$cod_politica = isset($_GET['cod_politica']) ? (int)$_GET['cod_politica'] : (isset($_POST['cod_politica']) ? (int)$_POST['cod_politica'] : 0);
if ($cod_politica === 0) { header("Location: index.php"); exit(); }

$msg = ""; $tipo_msg = "";

// AÇÃO 1: SALVAR CONFIGURAÇÕES BÁSICAS
if (isset($_POST['acao']) && $_POST['acao'] == 'editar_politica') {
    $nome = trim($_POST['nome']);
    $quota_padrao = (int)$_POST['quota_padrao'];
    $quota_infinita = isset($_POST['quota_infinita']) ? 1 : 0;
    
    $upd = $mysqli->prepare("UPDATE politicas SET nome=?, quota_padrao=?, quota_infinita=? WHERE cod_politica=?");
    $upd->bind_param('siii', $nome, $quota_padrao, $quota_infinita, $cod_politica);
    $upd->execute(); $upd->close();
    $msg = "Configurações da política atualizadas com sucesso!"; $tipo_msg = "success";
}

// AÇÃO 2: SALVAR GRUPOS VINCULADOS
if (isset($_POST['acao']) && $_POST['acao'] == 'salvar_grupos') {
    // 1. Apaga todos os vínculos antigos desta política
    $mysqli->query("DELETE FROM politica_grupo WHERE cod_politica = $cod_politica");
    
    // 2. Insere os novos selecionados
    if (isset($_POST['grupos_marcados']) && is_array($_POST['grupos_marcados'])) {
        $ins = $mysqli->prepare("INSERT INTO politica_grupo (cod_politica, grupo) VALUES (?, ?)");
        foreach ($_POST['grupos_marcados'] as $nome_do_grupo) {
            // Nota: O banco original salva o NOME do grupo, não o ID nesta tabela
            $ins->bind_param('is', $cod_politica, $nome_do_grupo);
            $ins->execute();
        }
        $ins->close();
    }
    $msg = "Grupos vinculados sincronizados com sucesso!"; $tipo_msg = "success";
}

// BUSCA OS DADOS DA POLÍTICA
$stmt = $mysqli->prepare("SELECT nome, quota_padrao, quota_infinita FROM politicas WHERE cod_politica = ?");
$stmt->bind_param('i', $cod_politica);
$stmt->execute(); $stmt->store_result();
if ($stmt->num_rows < 1) { header("Location: index.php"); exit(); }
$stmt->bind_result($nome_politica, $quota_padrao, $quota_infinita);
$stmt->fetch(); $stmt->close();

// BUSCA OS GRUPOS ATUALMENTE VINCULADOS
$grupos_vinculados = [];
$res_vinculos = $mysqli->query("SELECT grupo FROM politica_grupo WHERE cod_politica = $cod_politica");
while ($v = $res_vinculos->fetch_assoc()) {
    $grupos_vinculados[] = $v['grupo'];
}

include '../includes/header.php';
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
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold py-3"><i class="bi bi-sliders me-2"></i>Regras Básicas</div>
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
                    <div class="form-check form-switch mt-4 p-3 bg-white border rounded">
                        <input class="form-check-input ms-0 mt-1" type="checkbox" name="quota_infinita" id="inf" value="1" <?php echo ($quota_infinita == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold text-danger ms-2" for="inf">Cota Infinita (Sem limites)</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-4 fw-bold"><i class="bi bi-save me-1"></i> Atualizar Regras</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-ifnmg text-white fw-bold py-3"><i class="bi bi-diagram-3 me-2"></i>Grupos Obedecendo a Esta Regra</div>
            <div class="card-body">
                <p class="text-muted small mb-3">Marque os grupos do campus que devem receber automaticamente as configurações desta política de impressão.</p>
                
                <form action="politica_gerenciar.php" method="post">
                    <input type="hidden" name="cod_politica" value="<?php echo $cod_politica; ?>">
                    <input type="hidden" name="acao" value="salvar_grupos">
                    
                    <div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
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
                                echo "<span class='fw-semibold text-dark'>{$nome_g}</span>";
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
    </div>
</div>

<?php include '../includes/footer.php'; ?>