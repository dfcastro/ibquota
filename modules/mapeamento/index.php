<?php
/**
 * IFQUOTA - Mapeamento do Active Directory
 * Interface para criar as regras que o sincronizador usa automaticamente.
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

// Busca os grupos para o formulário
$grupos = [];
$res_grupos = $mysqli->query("SELECT cod_grupo, grupo FROM grupos ORDER BY grupo");
if ($res_grupos) {
    while ($g = $res_grupos->fetch_assoc()) {
        $grupos[] = $g;
    }
}

// Busca as regras atuais cadastradas
$query = "SELECT m.id, m.ou_ad, m.cargo_ad, g.grupo 
          FROM mapeamento_ad m 
          LEFT JOIN grupos g ON m.cod_grupo = g.cod_grupo 
          ORDER BY m.ou_ad, m.cargo_ad";
$stmt = $mysqli->prepare($query);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $ou_ad, $cargo_ad, $nome_grupo);
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
  <div>
    <h3 class="fw-bold text-dark mb-0"><i class="bi bi-diagram-3-fill text-primary me-2"></i> Mapeamento do AD</h3>
    <p class="text-muted mb-0 small">Crie regras automáticas para vincular as pastas do Windows aos Grupos do IFQUOTA.</p>
  </div>
  <div>
    <button type="button" class="btn btn-success shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddRegra">
      <i class="bi bi-plus-circle me-1"></i> Nova Regra
    </button>
  </div>
</div>

<?php
if (isset($_GET['msg'])) {
  $mensagens = [
    'add' => 'Regra de mapeamento criada com sucesso!',
    'del' => 'Regra removida. Os utilizadores já sincronizados não serão afetados.',
    'erro' => 'Ocorreu um erro ao processar a regra.'
  ];
  $tipo = ($_GET['msg'] == 'del') ? 'warning' : ($_GET['msg'] == 'erro' ? 'danger' : 'success');
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
          <th class="ps-4">Unidade Organizacional (OU) no AD</th>
          <th>Condição: Cargo (Title)</th>
          <th><i class="bi bi-arrow-right text-muted"></i> Grupo de Destino (IFQUOTA)</th>
          <th class="text-end pe-4">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($stmt->num_rows > 0) {
          while ($stmt->fetch()) {
            $ou_segura = htmlspecialchars($ou_ad, ENT_QUOTES);
            $cargo_seguro = !empty($cargo_ad) ? htmlspecialchars($cargo_ad, ENT_QUOTES) : "<span class='badge bg-secondary opacity-50'>Qualquer (Regra Geral)</span>";
            $grupo_exibicao = $nome_grupo ? htmlspecialchars($nome_grupo) : "<span class='text-danger fst-italic'>Grupo Apagado</span>";
            
            echo "<tr>";
            echo "<td class='ps-4 fw-bold text-dark'><i class='bi bi-folder text-warning me-2'></i>{$ou_segura}</td>";
            echo "<td>{$cargo_seguro}</td>";
            echo "<td><span class='badge bg-primary px-3 py-2'><i class='bi bi-people-fill me-1'></i> {$grupo_exibicao}</span></td>";
            
            echo "<td class='text-end pe-4'>";
            echo "<a href='{$BASE_URL}/admin/mapeamento/excluir?id={$id}' class='btn btn-sm btn-outline-danger shadow-sm' title='Remover Regra' onclick=\"return confirm('Remover esta regra de mapeamento?');\">";
            echo "<i class='bi bi-trash'></i> Excluir</a>";
            echo "</td>";
            echo "</tr>";
          }
        } else {
          echo "<tr><td colspan='4' class='text-center text-muted py-5'><i class='bi bi-link-45deg fs-3 d-block mb-2'></i>Nenhuma regra de mapeamento configurada.</td></tr>";
        }
        $stmt->close();
        ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="modalAddRegra" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-link-45deg me-2"></i>Nova Regra de AD</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="<?php echo $BASE_URL; ?>/admin/mapeamento/add" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        <div class="modal-body p-4">
          
          <div class="alert alert-info small py-2 border-0 shadow-sm">
             O sistema vai procurar os utilizadores nesta OU. Se o Cargo bater certo, move para o grupo escolhido.
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold text-muted small">Nome Exato da OU (ex: UO_Docentes)</label>
            <input type="text" class="form-control bg-light" name="ou_ad" required autofocus>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold text-muted small">Cargo Exato (Opcional)</label>
            <input type="text" class="form-control bg-light" name="cargo_ad" placeholder="Ex: Substituto (Deixe em branco para todos)">
            <div class="form-text text-muted" style="font-size: 0.75rem;">Se preenchido, esta regra só afeta utilizadores com este cargo no AD.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold text-muted small">Mover para Grupo (IFQUOTA)</label>
            <select class="form-select border-success" name="cod_grupo" required>
                <option value="" disabled selected>Escolha o destino...</option>
                <?php foreach ($grupos as $g): ?>
                    <option value="<?php echo $g['cod_grupo']; ?>"><?php echo htmlspecialchars($g['grupo']); ?></option>
                <?php endforeach; ?>
            </select>
          </div>

        </div>
        <div class="modal-footer bg-light border-0">
          <button type="button" class="btn btn-link text-secondary text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success fw-bold shadow-sm"><i class="bi bi-save me-1"></i> Gravar Regra</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../core/layout/footer.php'; ?>