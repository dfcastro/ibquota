<?php

/**
 * IFQUOTA - Central de Inicialização e Renovação de Cotas
 * Atribuição inteligente por grupos e reset por política.
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

$msg = "";
$tipo_msg = "";

// Carrega todas as políticas para os menus dropdown
$lista_politicas = [];
$res_pol = $mysqli->query("SELECT cod_politica, nome, quota_padrao, quota_infinita FROM politicas ORDER BY nome");
while ($p = $res_pol->fetch_assoc()) {
  $lista_politicas[] = $p;
}

// ==========================================================================
// AÇÃO 1: ATRIBUIR COTAS PARA NOVOS UTILIZADORES DE UM GRUPO ESPECÍFICO
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'atribuir_grupo') {
  validar_csrf_token($_POST['csrf_token'] ?? '');
  $cod_grupo = (int)$_POST['cod_grupo'];
  $cod_politica = (int)$_POST['cod_politica'];

  if ($cod_grupo > 0 && $cod_politica > 0) {
    // Pega os dados da política escolhida
    $stmt_p = $mysqli->prepare("SELECT quota_padrao, nome FROM politicas WHERE cod_politica = ?");
    $stmt_p->bind_param('i', $cod_politica);
    $stmt_p->execute();
    $stmt_p->bind_result($quota_padrao, $nome_pol);
    $stmt_p->fetch();
    $stmt_p->close();

    // Busca apenas os utilizadores Deste Grupo que estão Sem Cota
    $query_pendentes = "SELECT u.usuario FROM usuarios u 
                            JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario 
                            LEFT JOIN quota_usuario qu ON u.usuario = qu.usuario
                            WHERE gu.cod_grupo = ? AND qu.usuario IS NULL";
    $stmt_u = $mysqli->prepare($query_pendentes);
    $stmt_u->bind_param('i', $cod_grupo);
    $stmt_u->execute();
    $res_u = $stmt_u->get_result();

    $atribuidos = 0;
    $stmt_ins = $mysqli->prepare("INSERT INTO quota_usuario (usuario, cod_politica, quota) VALUES (?, ?, ?)");
    while ($row = $res_u->fetch_assoc()) {
      $stmt_ins->bind_param('sii', $row['usuario'], $cod_politica, $quota_padrao);
      $stmt_ins->execute();
      $atribuidos++;
    }
    $stmt_ins->close();
    $stmt_u->close();

    $msg = "Sucesso! <b>{$atribuidos}</b> contas do grupo receberam a política <b>{$nome_pol}</b>.";
    $tipo_msg = "success";
  }
}

// ==========================================================================
// AÇÃO 2: RESETAR COTA POR POLÍTICA (Virada de Semestre)
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'resetar_quota') {
  validar_csrf_token($_POST['csrf_token'] ?? '');
  $cod_politica = (int)$_POST['cod_politica'];

  if ($cod_politica > 0) {
    $stmt_pol = $mysqli->prepare("SELECT nome, quota_padrao FROM politicas WHERE cod_politica = ?");
    $stmt_pol->bind_param('i', $cod_politica);
    $stmt_pol->execute();
    $stmt_pol->bind_result($nome, $quota_padrao);
    $stmt_pol->fetch();
    $stmt_pol->close();

    $update_stmt = $mysqli->prepare("UPDATE quota_usuario SET quota = ? WHERE cod_politica = ?");
    $update_stmt->bind_param('ii', $quota_padrao, $cod_politica);
    $update_stmt->execute();
    $update_stmt->close();

    $msg = "Sucesso! O saldo de todos os utilizadores da política <b>{$nome}</b> foi reiniciado para <b>{$quota_padrao} páginas</b>.";
    $tipo_msg = "success";
  }
}

// ==========================================================================
// ANÁLISE DE PENDENTES (Para o Painel Esquerdo)
// ==========================================================================
$total_pendentes = 0;
$grupos_pendentes = [];

$query_analise = "SELECT g.cod_grupo, g.grupo, count(DISTINCT u.usuario) as qtd
                  FROM usuarios u
                  JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario
                  JOIN grupos g ON gu.cod_grupo = g.cod_grupo
                  LEFT JOIN quota_usuario qu ON u.usuario = qu.usuario
                  WHERE qu.usuario IS NULL
                  GROUP BY g.cod_grupo, g.grupo";

$res_analise = $mysqli->query($query_analise);
if ($res_analise) {
  while ($row = $res_analise->fetch_assoc()) {
    $grupos_pendentes[] = $row;
    $total_pendentes += $row['qtd'];
  }
}

include __DIR__ . '/../../core/layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
  <div>
    <h3 class="fw-bold text-dark mb-0"><i class="bi bi-wallet2 text-success me-2"></i> Gestão de Cotas</h3>
    <p class="text-muted mb-0 small">Atribua saldos iniciais para novos usuários ou reinicie o limite na virada do semestre.</p>
  </div>
  <div>
    <a href="<?php echo $BASE_URL; ?>/admin/politicas" class="btn btn-outline-secondary shadow-sm fw-bold">
      <i class="bi bi-gear-fill me-1"></i> Configurar Políticas
    </a>
  </div>
</div>

<?php if ($msg != "") { ?>
  <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show shadow-sm border-0">
    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $msg; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php } ?>

<div class="row align-items-stretch">

  <div class="col-md-5 mb-4">
    <div class="card shadow-sm border-0 border-top border-success border-4 h-100">
      <div class="card-body p-4 text-center">
        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3 text-success">
          <i class="bi bi-person-lines-fill fs-2"></i>
        </div>
        <h4 class="fw-bold text-dark mb-2">Novos Usuários</h4>
        <p class="text-muted small mb-4">Selecione qual política deseja aplicar às contas recém-sincronizadas.</p>

        <?php if ($total_pendentes > 0): ?>
          <div class="alert alert-warning border-0 shadow-sm mb-4">
            <h3 class="fw-bold text-dark mb-0"><?php echo $total_pendentes; ?></h3>
            <span class="small text-dark">Contas aguardando cota</span>
          </div>

          <h6 class="fw-bold text-dark text-start mb-3 border-bottom pb-2">Pendências por Grupo:</h6>

          <?php foreach ($grupos_pendentes as $gp): ?>
            <form action="<?php echo $BASE_URL; ?>/admin/init-quotas" method="POST" class="mb-3 p-3 bg-light rounded border border-warning border-opacity-25 text-start shadow-sm">
              <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
              <input type="hidden" name="acao" value="atribuir_grupo">
              <input type="hidden" name="cod_grupo" value="<?php echo $gp['cod_grupo']; ?>">

              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold text-dark"><i class="bi bi-diagram-3-fill text-muted me-1"></i> <?php echo htmlspecialchars($gp['grupo']); ?></span>
                <span class="badge bg-warning text-dark shadow-sm"><?php echo $gp['qtd']; ?> sem cota</span>
              </div>

              <div class="input-group input-group-sm">
                <select class="form-select border-success" name="cod_politica" required>
                  <option value="" disabled selected>Aplicar Política...</option>
                  <?php foreach ($lista_politicas as $pol): ?>
                    <option value="<?php echo $pol['cod_politica']; ?>"><?php echo htmlspecialchars($pol['nome']); ?> (<?php echo $pol['quota_padrao']; ?> págs)</option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-success fw-bold" onclick="return confirm('Deseja aplicar esta política a todos os pendentes deste grupo?');"><i class="bi bi-check-lg"></i> Injetar</button>
              </div>
            </form>
          <?php endforeach; ?>

        <?php else: ?>
          <div class="alert alert-light border shadow-sm mb-4 text-success fw-bold py-4">
            <i class="bi bi-check-circle-fill fs-3 d-block mb-2"></i> Todos já possuem cota!
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-7 mb-4">
    <div class="card shadow-sm border-0 border-top border-primary border-4 h-100">
      <div class="card-body p-4">
        <h4 class="fw-bold text-dark mb-3"><i class="bi bi-arrow-clockwise text-primary me-2"></i>Renovação de Semestre</h4>
        <div class="alert alert-info text-dark shadow-sm border-0 mb-4 p-3">
          <p class="mb-0 small"><i class="bi bi-info-circle-fill me-1"></i> Escolha uma política abaixo para recarregar. O saldo de todos os utilizadores vinculados a ela será sobrescrito pela <b>Cota Padrão</b> original.</p>
        </div>

        <form action="<?php echo $BASE_URL; ?>/admin/init-quotas" method="post">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
          <input type="hidden" name="acao" value="resetar_quota">

          <div class="row row-cols-1 g-2 mb-4" style="max-height: 250px; overflow-y: auto;">
            <?php foreach ($lista_politicas as $pol): ?>
              <?php
              $disabled = ($pol['quota_infinita'] == 1) ? "disabled" : "";
              $bg_class = ($pol['quota_infinita'] == 1) ? "bg-light border-light opacity-50" : "bg-white border-primary border-start border-4 shadow-sm cursor-pointer";
              ?>
              <div class='col'>
                <label class='card card-body p-2 <?php echo $bg_class; ?> flex-row align-items-center' style='cursor: pointer;'>
                  <input class='form-check-input mt-0 me-3' type='radio' name='cod_politica' value='<?php echo $pol['cod_politica']; ?>' required <?php echo $disabled; ?>>
                  <div>
                    <span class='d-block fw-bold text-dark' style='font-size: 0.9rem;'><?php echo htmlspecialchars($pol['nome']); ?></span>
                    <?php if ($pol['quota_infinita'] == 1): ?>
                      <span class='small text-muted' style='font-size: 0.75rem;'><i class='bi bi-infinity'></i> Ilimitada</span>
                    <?php else: ?>
                      <span class='text-primary fw-bold' style='font-size: 0.8rem;'><?php echo $pol['quota_padrao']; ?> págs</span>
                    <?php endif; ?>
                  </div>
                </label>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-primary fw-bold shadow-sm" onclick="return confirm('Tem certeza absoluta? O saldo acumulado de todos os usuários desta política será sobrescrito!');"><i class="bi bi-arrow-repeat me-1"></i> Renovar Cota da Política</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../core/layout/footer.php'; ?>