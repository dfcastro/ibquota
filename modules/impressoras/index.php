<?php
/**
 * IFQUOTA - Configuração de Impressoras
 * Vincula as impressoras do CUPS aos Locais (Setores) e define se são Coloridas.
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

// 1. Busca os Locais para preencher os select boxes
$locais = [];
$res_locais = $mysqli->query("SELECT cod_local, nome_local FROM locais ORDER BY nome_local");
if ($res_locais) {
    while ($l = $res_locais->fetch_assoc()) {
        $locais[] = $l;
    }
}

// 2. Busca as Impressoras do CUPS (Linux) para facilitar a vida do NTI
$impressoras_cups = [];
$saida_lpstat = @shell_exec('lpstat -a 2>/dev/null');
if (!empty($saida_lpstat)) {
    $linhas = explode("\n", trim($saida_lpstat));
    foreach ($linhas as $linha) {
        $partes = explode(" ", $linha);
        if (!empty($partes[0])) $impressoras_cups[] = $partes[0];
    }
    sort($impressoras_cups);
}

// 3. Busca a configuração atual no Banco de Dados
$query = "SELECT i.id, i.nome_impressora, i.is_colorida, i.cod_local, l.nome_local 
          FROM impressoras_config i 
          LEFT JOIN locais l ON i.cod_local = l.cod_local 
          ORDER BY i.nome_impressora";
$stmt = $mysqli->prepare($query);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $nome_impressora, $is_colorida, $cod_local_db, $nome_local);
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
  <div>
    <h3 class="fw-bold text-dark mb-0"><i class="bi bi-printer-fill text-primary me-2"></i> Setup de Impressoras</h3>
    <p class="text-muted mb-0 small">Organize o parque de impressão por setores e sinalize equipamentos coloridos.</p>
  </div>
  <div>
    <button type="button" class="btn btn-success shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddImpressora">
      <i class="bi bi-plus-circle me-1"></i> Configurar Nova
    </button>
  </div>
</div>

<?php
if (isset($_GET['msg'])) {
  $mensagens = [
    'add' => 'Impressora configurada com sucesso!',
    'edit' => 'Configurações da impressora atualizadas!',
    'del' => 'Configuração removida do sistema.',
    'erro_duplicado' => 'Esta impressora já está configurada no banco de dados.',
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
          <th class="ps-4">Nome no CUPS</th>
          <th>Departamento / Local</th>
          <th>Tipo de Impressão</th>
          <th class="text-end pe-4">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($stmt->num_rows > 0) {
          while ($stmt->fetch()) {
            $imp_seguro = htmlspecialchars($nome_impressora, ENT_QUOTES);
            $local_exibicao = $nome_local ? htmlspecialchars($nome_local) : "<span class='text-danger fst-italic'>Sem Setor Definido</span>";
            
            echo "<tr>";
            echo "<td class='ps-4 fw-bold text-dark'>{$imp_seguro}</td>";
            echo "<td><i class='bi bi-geo-alt text-muted me-1'></i> {$local_exibicao}</td>";
            
            echo "<td>";
            if ($is_colorida == 1) {
                echo "<span class='badge bg-danger shadow-sm'><i class='bi bi-palette-fill me-1'></i> Colorida</span>";
            } else {
                echo "<span class='badge bg-dark shadow-sm'><i class='bi bi-circle-half me-1'></i> Preto e Branco</span>";
            }
            echo "</td>";
            
            echo "<td class='text-end pe-4'>";
            echo "<button onclick=\"abrirModalEdicao({$id}, '{$imp_seguro}', '{$cod_local_db}', {$is_colorida})\" class='btn btn-sm btn-outline-primary shadow-sm me-1' title='Editar Setup'>";
            echo "<i class='bi bi-pencil'></i> Editar</button>";
            
            echo "<a href='{$BASE_URL}/admin/impressoras/excluir?id={$id}' class='btn btn-sm btn-outline-danger shadow-sm' title='Remover Configuração' onclick=\"return confirm('Remover a configuração desta impressora?');\">";
            echo "<i class='bi bi-trash'></i></a>";
            echo "</td>";
            echo "</tr>";
          }
        } else {
          echo "<tr><td colspan='4' class='text-center text-muted py-5'><i class='bi bi-printer fs-3 d-block mb-2'></i>Nenhuma impressora configurada.</td></tr>";
        }
        $stmt->close();
        ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Adicionar -->
<div class="modal fade" id="modalAddImpressora" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-printer me-2"></i>Configurar Nova Impressora</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="<?php echo $BASE_URL; ?>/admin/impressoras/add" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        <div class="modal-body p-4">
          
          <div class="mb-3">
            <label class="form-label fw-bold text-muted small">Selecionar Impressora (CUPS)</label>
            <?php if (!empty($impressoras_cups)): ?>
                <select class="form-select bg-light" name="nome_impressora" required>
                    <option value="" disabled selected>Escolha o equipamento...</option>
                    <?php foreach ($impressoras_cups as $imp): ?>
                        <option value="<?php echo htmlspecialchars($imp); ?>"><?php echo htmlspecialchars($imp); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="text" class="form-control bg-light" name="nome_impressora" placeholder="Nome exato no servidor Linux" required>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold text-muted small">Local / Departamento</label>
            <select class="form-select border-primary" name="cod_local">
                <option value="">-- Sem Setor Específico --</option>
                <?php foreach ($locais as $loc): ?>
                    <option value="<?php echo $loc['cod_local']; ?>"><?php echo htmlspecialchars($loc['nome_local']); ?></option>
                <?php endforeach; ?>
            </select>
          </div>

          <div class="form-check form-switch mt-4 p-3 bg-light border rounded">
            <input class="form-check-input ms-0 mt-1" type="checkbox" name="is_colorida" id="is_color_add" value="1">
            <label class="form-check-label fw-bold text-danger ms-2" for="is_color_add"><i class="bi bi-palette-fill me-1"></i> Suporta Impressão Colorida</label>
          </div>

        </div>
        <div class="modal-footer bg-light border-0">
          <button type="button" class="btn btn-link text-secondary text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success fw-bold shadow-sm"><i class="bi bi-save me-1"></i> Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditImpressora" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Configuração</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="<?php echo $BASE_URL; ?>/admin/impressoras/editar" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        <div class="modal-body p-4">
          <input type="hidden" name="id" id="edit_id">
          
          <div class="mb-3">
            <label class="form-label fw-bold text-muted small">Impressora</label>
            <input type="text" class="form-control bg-light text-muted" id="edit_nome_impressora" readonly>
            <small class="text-muted">O nome não pode ser alterado. Remova e adicione novamente se necessário.</small>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold text-muted small">Local / Departamento</label>
            <select class="form-select border-primary" name="cod_local" id="edit_cod_local">
                <option value="">-- Sem Setor Específico --</option>
                <?php foreach ($locais as $loc): ?>
                    <option value="<?php echo $loc['cod_local']; ?>"><?php echo htmlspecialchars($loc['nome_local']); ?></option>
                <?php endforeach; ?>
            </select>
          </div>

          <div class="form-check form-switch mt-4 p-3 bg-light border rounded">
            <input class="form-check-input ms-0 mt-1" type="checkbox" name="is_colorida" id="edit_is_colorida" value="1">
            <label class="form-check-label fw-bold text-danger ms-2" for="edit_is_colorida"><i class="bi bi-palette-fill me-1"></i> Suporta Impressão Colorida</label>
          </div>

        </div>
        <div class="modal-footer bg-light border-0">
          <button type="button" class="btn btn-link text-secondary text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary fw-bold shadow-sm"><i class="bi bi-save me-1"></i> Atualizar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  function abrirModalEdicao(id, nome, codLocal, isColorida) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nome_impressora').value = nome;
    document.getElementById('edit_cod_local').value = codLocal;
    
    // Marca o checkbox se for 1
    document.getElementById('edit_is_colorida').checked = (isColorida === 1);

    var editModal = new bootstrap.Modal(document.getElementById('modalEditImpressora'));
    editModal.show();
  }
</script>

<?php include __DIR__ . '/../../core/layout/footer.php'; ?>