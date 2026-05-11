<?php

/**
 * IFQUOTA - Central de Inicialização e Renovação de Cotas
 * Auto-Injeção inteligente sem dropdowns e reset manual.
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) sec_session_start();

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

// Bloqueia se NÃO for o NTI (Nível 2)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] != 2) {
    header("Location: " . $BASE_URL . "/admin/dashboard?msg=acesso_negado");
    exit();
}

$msg = "";
$tipo_msg = "";

$lista_politicas = [];
$res_pol = $mysqli->query("SELECT cod_politica, nome, quota_padrao, quota_infinita FROM politicas ORDER BY nome");
while ($p = $res_pol->fetch_assoc()) {
  $lista_politicas[] = $p;
}

// ==========================================================================
// AÇÃO 1: AUTO-INJETAR COTAS EM MASSA (Lê a política do grupo sozinho)
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'auto_injetar') {
  validar_csrf_token($_POST['csrf_token'] ?? '');

  // Procura todos os utilizadores sem cota que pertencem a um grupo COM política
  $query_pendentes = "SELECT u.usuario, p.cod_politica, p.quota_padrao, g.grupo as nome_grupo
                        FROM usuarios u
                        JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario
                        JOIN grupos g ON gu.cod_grupo = g.cod_grupo
                        JOIN politica_grupo pg ON g.grupo = pg.grupo
                        JOIN politicas p ON pg.cod_politica = p.cod_politica
                        LEFT JOIN quota_usuario qu ON u.usuario = qu.usuario
                        WHERE qu.usuario IS NULL";

  $res_pendentes = $mysqli->query($query_pendentes);

  if ($res_pendentes && $res_pendentes->num_rows > 0) {
    $atribuidos = 0;
    $stmt_ins = $mysqli->prepare("INSERT INTO quota_usuario (cod_politica, grupo, usuario, quota) VALUES (?, ?, ?, ?)");

    while ($row = $res_pendentes->fetch_assoc()) {
      $stmt_ins->bind_param('issi', $row['cod_politica'], $row['nome_grupo'], $row['usuario'], $row['quota_padrao']);
      $stmt_ins->execute();
      $atribuidos++;
    }
    $stmt_ins->close();
    $msg = "Mágica feita! <b>{$atribuidos}</b> utilizadores receberam os saldos com base nos seus grupos.";
    $tipo_msg = "success";
  } else {
    $msg = "Nenhum utilizador elegível para receber cota no momento.";
    $tipo_msg = "info";
  }
}

// ==========================================================================
// AÇÃO 2: RESETAR COTA POR POLÍTICA
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

    $msg = "O saldo de todos os utilizadores da política <b>{$nome}</b> foi reiniciado para <b>{$quota_padrao} páginas</b>.";
    $tipo_msg = "success";
  }
}

// ==========================================================================
// ANÁLISE DE PENDENTES 
// ==========================================================================
$total_pendentes = 0;
$query_analise = "SELECT count(DISTINCT u.usuario) as qtd
                  FROM usuarios u
                  JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario
                  JOIN grupos g ON gu.cod_grupo = g.cod_grupo
                  JOIN politica_grupo pg ON g.grupo = pg.grupo
                  LEFT JOIN quota_usuario qu ON u.usuario = qu.usuario
                  WHERE qu.usuario IS NULL";

$res_analise = $mysqli->query($query_analise);
if ($res_analise && $row = $res_analise->fetch_assoc()) {
  $total_pendentes = $row['qtd'];
}

include __DIR__ . '/../../core/layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
  <div>
    <h3 class="fw-bold text-dark mb-0"><i class="bi bi-wallet2 text-success me-2"></i> Gestão de Cotas</h3>
    <p class="text-muted mb-0 small">Injete saldos pendentes automaticamente ou recarregue limites manuais.</p>
  </div>
  <div>
    <a href="<?php echo $BASE_URL; ?>/admin/politicas" class="btn btn-outline-secondary shadow-sm fw-bold">
      <i class="bi bi-gear-fill me-1"></i> Políticas
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
          <i class="bi bi-magic fs-2"></i>
        </div>
        <h4 class="fw-bold text-dark mb-2">Injeção Inteligente</h4>
        <p class="text-muted small mb-4">O sistema cruza os utilizadores sem cota com as políticas dos seus respetivos grupos e injeta o saldo correto automaticamente.</p>

        <?php if ($total_pendentes > 0): ?>
          <div class="alert alert-warning border-0 shadow-sm mb-4 p-4">
            <h1 class="fw-bold text-dark mb-0"><?php echo $total_pendentes; ?></h1>
            <span class="text-dark fw-bold">Contas aguardando cota</span>
            <p class="small text-muted mt-2 mb-0">Isso ocorre após a Sincronização do AD ou após o CRON mensal de limpeza.</p>
          </div>

          <form action="<?php echo $BASE_URL; ?>/admin/init-quotas" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="acao" value="auto_injetar">
            <button type="submit" class="btn btn-success btn-lg w-100 fw-bold shadow-sm" onclick="return confirm('Injetar cotas para todas as <?php echo $total_pendentes; ?> contas pendentes agora?');">
              <i class="bi bi-play-circle-fill me-1"></i> Processar Todos
            </button>
          </form>
        <?php else: ?>
          <div class="alert alert-light border shadow-sm mb-4 text-success fw-bold py-5">
            <i class="bi bi-check-circle-fill fs-1 d-block mb-2"></i> Matriz de Cotas Sincronizada!
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-7 mb-4">
    <div class="card shadow-sm border-0 border-top border-primary border-4 h-100">
      <div class="card-body p-4">
        <h4 class="fw-bold text-dark mb-3"><i class="bi bi-arrow-clockwise text-primary me-2"></i>Renovação Manual (Sem CRON)</h4>
        <div class="alert alert-info text-dark shadow-sm border-0 mb-4 p-3">
          <p class="mb-0 small"><i class="bi bi-info-circle-fill me-1"></i> Se precisar reiniciar as cotas de uma política <b>fora da rotina mensal do CRON</b>, use esta opção. O saldo será sobrescrito pela Cota Padrão.</p>
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
            <button type="submit" class="btn btn-primary fw-bold shadow-sm" onclick="return confirm('Tem certeza absoluta? O saldo acumulado será sobrescrito!');"><i class="bi bi-arrow-repeat me-1"></i> Renovar Cota da Política</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../core/layout/footer.php'; ?>