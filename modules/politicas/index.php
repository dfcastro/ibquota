<?php

/**
 * IBQUOTA 3
 * Lista de Políticas - Refatorada (Bootstrap 5)
 */
include_once '../../core/db.php';
include_once '../../core/functions.php';
sec_session_start();

// 1. Verificação de sessão ajustada e caminho do login corrigido
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] !== 2) {
    header("Location: ../../public/login.php");
    exit();
}

// 2. Caminho do Header corrigido
include '../../core/layout/header.php';

// Paginação básica
$p = (isset($_GET['p'])) ? (int)$_GET['p'] : 1;
$p = ($p < 1) ? 1 : $p;
// Se não achar a constante, usa 20 como padrão
if (!defined('QTDE_POR_PAGINA')) define('QTDE_POR_PAGINA', 20);

$p_inicio = (QTDE_POR_PAGINA * $p) - QTDE_POR_PAGINA;
$p_qtde_por_pagina = (int)QTDE_POR_PAGINA;

$num_stmt = $mysqli->prepare("SELECT count(*) FROM politicas");
$num_stmt->execute();
$num_stmt->bind_result($p_num_registros);
$num_stmt->fetch();
$num_stmt->close();

$stmt = $mysqli->prepare("SELECT cod_politica, nome, quota_padrao, quota_infinita FROM politicas ORDER BY nome LIMIT ?, ?");
$stmt->bind_param('ii', $p_inicio, $p_qtde_por_pagina);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($cod_politica, $nome, $quota_padrao, $quota_infinita);
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-shield-lock text-muted me-2"></i> Políticas de Impressão</h3>
        <p class="text-muted mb-0 small">Crie regras de cota e aplique-as aos grupos do campus.</p>
    </div>
    <div>
        <button type="button" class="btn btn-success shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddPolitica">
            <i class="bi bi-plus-circle me-1"></i> Nova Política
        </button>
    </div>
</div>

<?php
if (isset($_GET['msg'])) {
    // Define a mensagem e a cor (tipo) baseado no que veio no link
    if ($_GET['msg'] == 'del') {
        $msg = 'Política excluída com sucesso!';
        $tipo = 'warning';
    } elseif ($_GET['msg'] == 'duplicado') {
        $msg = 'Atenção: Já existe uma política com este nome!';
        $tipo = 'danger'; // Cor vermelha
    } else {
        $msg = 'Política criada com sucesso!';
        $tipo = 'success'; // Cor verde
    }

    echo "<div class='alert alert-{$tipo} shadow-sm'><i class='bi bi-info-circle-fill me-2'></i> {$msg} <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}
?>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover table-striped align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Nome da Política</th>
                    <th>Regra de Cota</th>
                    <th class="text-end pe-4">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($stmt->num_rows > 0) {
                    while ($stmt->fetch()) {
                        echo "<tr>";
                        echo "<td class='ps-4 fw-bold text-dark'>{$nome}</td>";

                        if ($quota_infinita == 1) {
                            echo "<td><span class='badge text-bg-secondary'><i class='bi bi-infinity'></i> Infinita / Ilimitada</span></td>";
                        } else {
                            echo "<td><span class='badge text-bg-success'>{$quota_padrao} páginas</span> limitadas</td>";
                        }

                        echo "<td class='text-end pe-4'>";
                        echo "<a href='politica_gerenciar.php?cod_politica={$cod_politica}' class='btn btn-sm btn-outline-primary me-1' title='Gerenciar Regras e Grupos'><i class='bi bi-gear-fill'></i> Configurar</a>";
                        echo "<a href='politica_excluir.php?cod_politica={$cod_politica}' class='btn btn-sm btn-outline-danger' title='Excluir Política' onclick=\"return confirm('ATENÇÃO: Ao excluir esta política, os grupos associados ficarão sem regra! Deseja continuar?');\"><i class='bi bi-trash'></i></a>";
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='3' class='text-center text-muted py-4'>Nenhuma política encontrada.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-center mt-3">
    <?php barra_de_paginas($p, $p_num_registros); ?>
</div>

<div class="modal fade" id="modalAddPolitica" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-plus me-2"></i>Criar Política</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="politica_add.php" method="post">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nome da Política</label>
                        <input type="text" class="form-control" name="nome" placeholder="Ex: Cota Professores, Cota Alunos..." required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cota Padrão (Páginas)</label>
                        <input type="number" class="form-control" name="quota_padrao" value="100" min="0" required>
                    </div>
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" name="quota_infinita" id="infinita" value="1">
                        <label class="form-check-label fw-bold text-danger" for="infinita">Cota Infinita (Ignora o limite acima)</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold"><i class="bi bi-save me-1"></i> Salvar Política</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// 3. Caminho do Footer corrigido
include '../../core/layout/footer.php';
?>