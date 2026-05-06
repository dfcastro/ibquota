<?php

/**
 * IFQUOTA - Lista de Políticas de Impressão
 * Gerenciamento de cotas e regras de utilização.
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

include __DIR__ . '/../../core/layout/header.php';

$p = (isset($_GET['p'])) ? (int)$_GET['p'] : 1;
$p = ($p < 1) ? 1 : $p;
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
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-shield-lock-fill text-primary me-2"></i> Políticas de Impressão</h3>
        <p class="text-muted mb-0 small">Defina os limites de cota para os diferentes perfis do campus.</p>
    </div>
    <div>
        <button type="button" class="btn btn-success shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddPolitica">
            <i class="bi bi-plus-circle me-1"></i> Nova Política
        </button>
    </div>
</div>

<?php
if (isset($_GET['msg'])) {
    $alertas = [
        'del' => ['text' => 'Política removida com sucesso.', 'type' => 'warning'],
        'duplicado' => ['text' => 'Erro: Já existe uma política com este nome.', 'type' => 'danger'],
        'add' => ['text' => 'Nova política criada com sucesso!', 'type' => 'success'],
        'upd' => ['text' => 'Configurações atualizadas.', 'type' => 'success']
    ];

    $m = $_GET['msg'];
    if (array_key_exists($m, $alertas)) {
        echo "<div class='alert alert-{$alertas[$m]['type']} border-0 shadow-sm'><i class='bi bi-info-circle-fill me-2'></i> {$alertas[$m]['text']} <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-secondary">
                <tr>
                    <th class="ps-4">Nome da Política</th>
                    <th>Regra de Cota Mensal</th>
                    <th class="text-end pe-4">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($stmt->num_rows > 0): ?>
                    <?php while ($stmt->fetch()): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($nome); ?></td>
                            <td>
                                <?php if ($quota_infinita == 1): ?>
                                    <span class="badge rounded-pill bg-info text-dark"><i class="bi bi-infinity"></i> Ilimitada</span>
                                <?php else: ?>
                                    <span class="badge rounded-pill bg-success px-3"><?php echo $quota_padrao; ?> páginas</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="<?php echo $BASE_URL; ?>/admin/politicas/gerenciar?cod_politica=<?php echo $cod_politica; ?>" class="btn btn-sm btn-outline-primary shadow-sm me-1">
                                    <i class="bi bi-gear-fill"></i> Configurar
                                </a>
                                <a href="<?php echo $BASE_URL; ?>/admin/politicas/excluir?cod_politica=<?php echo $cod_politica; ?>" class="btn btn-sm btn-outline-danger shadow-sm" onclick="return confirm('ATENÇÃO: Ao excluir esta política, os grupos associados ficarão sem regra! Deseja continuar?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center text-muted py-5">Nenhuma política cadastrada no IFQUOTA.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-center mt-4">
    <?php barra_de_paginas($p, $p_num_registros); ?>
</div>

<div class="modal fade" id="modalAddPolitica" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-plus me-2"></i>Nova Política de Cota</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?php echo $BASE_URL; ?>/admin/politicas/add" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Descrição da Política</label>
                        <input type="text" class="form-control" name="nome" placeholder="Ex: Cota Docentes, Alunos Veteranos..." required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Quantidade de Páginas</label>
                        <input type="number" class="form-control" name="quota_padrao" value="100" min="0" required>
                    </div>
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" name="quota_infinita" id="infinita" value="1">
                        <label class="form-check-label fw-bold text-primary" for="infinita">Ativar Cota Ilimitada</label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-link text-secondary text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">Criar Política</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Correção Crítica do Caminho
include __DIR__ . '/../../core/layout/footer.php';
?>