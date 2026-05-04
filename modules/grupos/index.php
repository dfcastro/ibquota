<?php

/**
 * IBQUOTA 3
 * GG - Gerenciador Grafico do IBQUOTA
 * Lista Grupos - Com Painel Raio-X Integrado (Bootstrap 5)
 */
include_once '../../core/db.php';
include_once '../../core/functions.php';

sec_session_start();

// Verificação de sessão atualizada com caminho corrigido (../../public/login.php)
if (!isset($_SESSION['usuario']) || (isset($_SESSION['permissao']) && $_SESSION['permissao'] < 1)) {
  header("Location: ../../public/login.php");
  exit();
}

// Caminho corrigido para o header
include '../../core/layout/header.php';

// SISTEMA DE BUSCA E PAGINAÇÃO
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$like_q = "%" . $q . "%";

// Garante que a constante existe ou define um padrão
if (!defined('QTDE_POR_PAGINA')) define('QTDE_POR_PAGINA', 20);

$p = (isset($_GET['p'])) ? (int)$_GET['p'] : 1;
$p = ($p < 1) ? 1 : $p;
$p_inicio = (QTDE_POR_PAGINA * $p) - QTDE_POR_PAGINA;
$p_qtde_por_pagina = (int)QTDE_POR_PAGINA;
$p_num_registros = 0;

if ($q != '') {
  if ($num_stmt = $mysqli->prepare("SELECT count(*) FROM grupos WHERE grupo LIKE ?")) {
    $num_stmt->bind_param('s', $like_q);
    $num_stmt->execute();
    $num_stmt->bind_result($p_num_registros);
    $num_stmt->fetch();
    $num_stmt->close();
  }
  $stmt = $mysqli->prepare("SELECT cod_grupo, grupo FROM grupos WHERE grupo LIKE ? ORDER BY grupo LIMIT ?, ?");
  $stmt->bind_param('sii', $like_q, $p_inicio, $p_qtde_por_pagina);
} else {
  if ($num_stmt = $mysqli->prepare("SELECT count(*) FROM grupos")) {
    $num_stmt->execute();
    $num_stmt->bind_result($p_num_registros);
    $num_stmt->fetch();
    $num_stmt->close();
  }
  $stmt = $mysqli->prepare("SELECT cod_grupo, grupo FROM grupos ORDER BY grupo LIMIT ?, ?");
  $stmt->bind_param('ii', $p_inicio, $p_qtde_por_pagina);
}

$stmt->execute();
$stmt->store_result();
$stmt->bind_result($cod_grupo, $grupo);
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
  <div>
    <h3 class="fw-bold text-dark mb-0"><i class="bi bi-diagram-3 text-muted me-2"></i> Grupos de Impressão</h3>
    <p class="text-muted mb-0 small">Visão geral dos departamentos, total de membros e políticas ativas.</p>
  </div>
  <div>
    <button type="button" class="btn btn-success shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddGrupo">
      <i class="bi bi-plus-circle me-1"></i> Novo Grupo
    </button>
  </div>
</div>

<?php
// Alertas de Sucesso
if (isset($_GET['msg'])) {
  $mensagens = [
    'add' => 'Grupo criado com sucesso!',
    'edit' => 'Nome do grupo alterado!',
    'del' => 'Grupo e todas as suas vinculações excluídas com sucesso.'
  ];
  $tipo = $_GET['msg'] == 'del' ? 'warning' : 'success';
  if (array_key_exists($_GET['msg'], $mensagens)) {
    echo "<div class='alert alert-{$tipo} alert-dismissible fade show shadow-sm' role='alert'>
                <i class='bi bi-check-circle-fill me-2'></i> {$mensagens[$_GET['msg']]}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
  }
}
?>

<div class="card shadow-sm border-0 mb-4">
  <div class="card-body p-3 bg-light rounded">
    <form action="" method="GET" class="row gx-2 gy-2 align-items-center">
      <div class="col-md-9">
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" class="form-control border-start-0" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Buscar grupo por nome...">
        </div>
      </div>
      <div class="col-md-3 d-grid gap-2 d-md-flex justify-content-md-end">
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-search me-1"></i> Buscar</button>
        <?php if ($q != '') { ?>
          <a href="index.php" class="btn btn-outline-secondary">Limpar</a>
        <?php } ?>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="table-responsive">
    <table class="table table-hover table-striped align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="ps-4">Nome do Grupo</th>
          <th>Membros</th>
          <th>Política Vinculada</th>
          <th class="text-end pe-4">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($stmt->num_rows > 0) {
          while ($stmt->fetch()) {
            $grupo_seguro = htmlspecialchars($grupo, ENT_QUOTES);
            echo "<tr>";
            echo "<td class='ps-4 fw-semibold text-dark'><i class='bi bi-folder2 text-warning me-2'></i> {$grupo_seguro}</td>";

            // ===== COLUNA 2: CONTAGEM DE MEMBROS =====
            echo "<td>";
            $res_membros = $mysqli->query("SELECT count(*) as total FROM grupo_usuario WHERE cod_grupo = $cod_grupo");
            $total_membros = $res_membros->fetch_assoc()['total'];
            if ($total_membros > 0) {
              echo "<span class='badge bg-info text-dark shadow-sm'><i class='bi bi-people-fill me-1'></i> {$total_membros} usuários</span>";
            } else {
              echo "<span class='badge bg-light text-muted border'><i class='bi bi-person-x'></i> Vazio</span>";
            }
            echo "</td>";

            // ===== COLUNA 3: POLÍTICA VINCULADA =====
            echo "<td>";
            $stmt_pol = $mysqli->prepare("SELECT p.nome, p.quota_padrao, p.quota_infinita FROM politicas p JOIN politica_grupo pg ON p.cod_politica = pg.cod_politica WHERE pg.grupo = ?");
            $stmt_pol->bind_param('s', $grupo);
            $stmt_pol->execute();
            $res_pol = $stmt_pol->get_result();

            if ($res_pol->num_rows > 0) {
              while ($pol = $res_pol->fetch_assoc()) {
                if ($pol['quota_infinita'] == 1) {
                  echo "<span class='d-block small fw-bold text-success'><i class='bi bi-shield-check'></i> {$pol['nome']} (Ilimitada)</span>";
                } else {
                  echo "<span class='d-block small fw-bold text-primary'><i class='bi bi-shield-check'></i> {$pol['nome']} ({$pol['quota_padrao']} págs)</span>";
                }
              }
            } else {
              echo "<span class='text-danger small fw-bold'><i class='bi bi-exclamation-triangle'></i> Sem regra de impressão!</span>";
            }
            $stmt_pol->close();
            echo "</td>";

            // ===== COLUNA 4: AÇÕES =====
            echo "<td class='text-end pe-4'>";
            echo "<button onclick=\"abrirModalEdicao({$cod_grupo}, '{$grupo_seguro}')\" class='btn btn-sm btn-outline-primary me-1' title='Alterar Nome do Grupo'>";
            echo "<i class='bi bi-pencil'></i> Editar</button>";

            $aviso_del = ($total_membros > 0) ? "ATENÇÃO: Este grupo tem {$total_membros} usuário(s)! Excluí-lo removerá a cota de todos eles. Deseja continuar?" : "Deseja excluir este grupo vazio?";
            echo "<a href='grupo_excluir.php?cod_grupo={$cod_grupo}' class='btn btn-sm btn-outline-danger' title='Excluir Grupo' onclick=\"return confirm('{$aviso_del}');\">";
            echo "<i class='bi bi-trash'></i></a>";
            echo "</td>";
            echo "</tr>";
          }
        } else {
          echo "<tr><td colspan='4' class='text-center text-muted py-4'><i class='bi bi-inbox fs-3 d-block mb-2'></i>Nenhum grupo encontrado.</td></tr>";
        }
        ?>
      </tbody>
    </table>
  </div>
</div>

<div class="d-flex justify-content-center mt-3">
  <?php barra_de_paginas($p, $p_num_registros); ?>
</div>

<div class="modal fade" id="modalAddGrupo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-diagram-3 me-2"></i>Cadastrar Grupo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="grupo_add.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo gerar_csrf_token(); ?>">
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold">Nome do Grupo</label>
            <input type="text" class="form-control form-control-lg" name="grupo" placeholder="Ex: Professores, NTI, Direção..." required autofocus>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success fw-bold"><i class="bi bi-save me-1"></i> Salvar Grupo</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEditGrupo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Alterar Nome do Grupo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="grupo_editar.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo gerar_csrf_token(); ?>">
        <div class="modal-body p-4">
          <input type="hidden" name="cod_grupo" id="edit_cod_grupo">
          <input type="hidden" name="grupo_antigo" id="edit_grupo_antigo">

          <div class="mb-3">
            <label class="form-label fw-bold">Novo Nome</label>
            <input type="text" class="form-control form-control-lg" name="grupo_novo" id="edit_grupo_novo" required>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-save me-1"></i> Atualizar Nome</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  function abrirModalEdicao(id, nomeAtual) {
    document.getElementById('edit_cod_grupo').value = id;
    document.getElementById('edit_grupo_antigo').value = nomeAtual;
    document.getElementById('edit_grupo_novo').value = nomeAtual;

    var editModal = new bootstrap.Modal(document.getElementById('modalEditGrupo'));
    editModal.show();
  }
</script>

<?php include '../../core/layout/footer.php'; ?>