<?php
/**
 * IFQUOTA - Gestão de Locais (Departamentos)
 * Interface para cadastrar os setores onde as impressoras ficam fisicamente.
 */

include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

// Apenas Admin (Nível 2) pode gerir locais
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 2) {
    header("Location: " . $BASE_URL . "/login");
    exit();
}

include __DIR__ . '/../../core/layout/header.php';

// Busca os locais cadastrados
$stmt = $mysqli->prepare("SELECT cod_local, nome_local FROM locais ORDER BY nome_local");
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($cod_local, $nome_local);
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
  <div>
    <h3 class="fw-bold text-dark mb-0"><i class="bi bi-geo-alt-fill text-primary me-2"></i> Gestão de Locais</h3>
    <p class="text-muted mb-0 small">Cadastre os departamentos do campus para organizar as impressoras no Web Print.</p>
  </div>
  <div>
    <button type="button" class="btn btn-success shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddLocal">
      <i class="bi bi-plus-circle me-1"></i> Novo Local
    </button>
  </div>
</div>

<?php
// Sistema de Alertas
if (isset($_GET['msg'])) {
  $mensagens = [
    'add' => 'Local cadastrado com sucesso!',
    'edit' => 'Nome do local atualizado!',
    'del' => 'Local removido com sucesso.',
    'erro_duplicado' => 'Já existe um local com este nome.',
    'erro' => 'Ocorreu um erro interno.'
  ];
  $tipo = ($_GET['msg'] == 'del' || $_GET['msg'] == 'erro_duplicado') ? 'warning' : ($_GET['msg'] == 'erro' ? 'danger' : 'success');
  if (array_key_exists($_GET['msg'], $mensagens)) {
    echo "<div class='alert alert-{$tipo} alert-dismissible border-0 shadow-sm mb-4' role='alert'>
            <i class='bi bi-info-circle-fill me-2'></i> <strong>{$mensagens[$_GET['msg']]}</strong>
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
          </div>";
  }
}
?>

<div class="card shadow-sm border-0 border-top border-primary border-4">
  <div class="table-responsive">
    <table class="table table-hover table-striped align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="ps-4">Nome do Departamento / Local</th>
          <th class="text-end pe-4">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($stmt->num_rows > 0) {
          while ($stmt->fetch()) {
            $local_seguro = htmlspecialchars($nome_local, ENT_QUOTES);
            echo "<tr>";
            echo "<td class='ps-4 fw-semibold text-dark'><i class='bi bi-building text-muted me-2'></i> {$local_seguro}</td>";
            
            echo "<td class='text-end pe-4'>";
            echo "<button onclick=\"abrirModalEdicao({$cod_local}, '{$local_seguro}')\" class='btn btn-sm btn-outline-primary shadow-sm me-1' title='Editar Local'>";
            echo "<i class='bi bi-pencil'></i> Editar</button>";
            
            echo "<a href='{$BASE_URL}/admin/locais/excluir?cod_local={$cod_local}' class='btn btn-sm btn-outline-danger shadow-sm' title='Excluir Local' onclick=\"return confirm('Deseja realmente excluir este local? As impressoras atreladas a ele ficarão sem setor definido.');\">";
            echo "<i class='bi bi-trash'></i></a>";
            echo "</td>";
            echo "</tr>";
          }
        } else {
          echo "<tr><td colspan='2' class='text-center text-muted py-5'><i class='bi bi-inbox fs-3 d-block mb-2'></i>Nenhum local cadastrado.</td></tr>";
        }
        $stmt->close();
        ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Adicionar -->
<div class="modal fade" id="modalAddLocal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-geo-alt me-2"></i>Cadastrar Local</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="<?php echo $BASE_URL; ?>/admin/locais/add" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold text-muted small">Nome do Local</label>
            <input type="text" class="form-control form-control-lg bg-light" name="nome_local" placeholder="Ex: Biblioteca, Bloco A, NTI..." required autofocus>
          </div>
        </div>
        <div class="modal-footer bg-light border-0">
          <button type="button" class="btn btn-link text-secondary text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success fw-bold shadow-sm"><i class="bi bi-save me-1"></i> Salvar Local</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditLocal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Local</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="<?php echo $BASE_URL; ?>/admin/locais/editar" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        <div class="modal-body p-4">
          <input type="hidden" name="cod_local" id="edit_cod_local">
          <div class="mb-3">
            <label class="form-label fw-bold text-muted small">Novo Nome</label>
            <input type="text" class="form-control form-control-lg bg-light" name="nome_local" id="edit_nome_local" required>
          </div>
        </div>
        <div class="modal-footer bg-light border-0">
          <button type="button" class="btn btn-link text-secondary text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary fw-bold shadow-sm"><i class="bi bi-save me-1"></i> Atualizar Nome</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  function abrirModalEdicao(id, nomeAtual) {
    document.getElementById('edit_cod_local').value = id;
    document.getElementById('edit_nome_local').value = nomeAtual;
    var editModal = new bootstrap.Modal(document.getElementById('modalEditLocal'));
    editModal.show();
  }
</script>

<?php include __DIR__ . '/../../core/layout/footer.php'; ?>