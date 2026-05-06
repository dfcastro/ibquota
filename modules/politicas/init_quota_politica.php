<?php

/**
 * IFQUOTA - Inicialização Manual de Quota de Usuários (Renovação de Saldo)
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

    $deleta_stmt = $mysqli->prepare("DELETE FROM quota_usuario WHERE cod_politica = ?");
    $deleta_stmt->bind_param('i', $cod_politica);
    $deleta_stmt->execute();
    $deleta_stmt->close();

    $msg = "Sucesso! O saldo de todos os utilizadores da política <b>{$nome}</b> foi reiniciado para <b>{$quota_padrao} páginas</b>.";
    $tipo_msg = "success";
  }
}

include __DIR__ . '/../../core/layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
  <div>
    <h3 class="fw-bold text-dark mb-0"><i class="bi bi-arrow-clockwise text-primary me-2"></i> Reiniciar Quotas</h3>
    <p class="text-muted mb-0 small">Restaure o saldo de impressão dos grupos para o valor padrão da política.</p>
  </div>
  <div>
    <a href="<?php echo $BASE_URL; ?>/admin/dashboard" class="btn btn-outline-secondary shadow-sm fw-bold">
      <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
  </div>
</div>

<?php if ($msg != "") { ?>
  <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php } ?>

<div class="row justify-content-center">
  <div class="col-md-8">
    <div class="card shadow-sm border-0 border-top border-primary border-4">
      <div class="card-body p-4">

        <div class="alert alert-warning text-dark shadow-sm border-0 mb-4">
          <h5 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Atenção, Administrador!</h5>
          <p class="mb-0 small">Este procedimento irá apagar o saldo restante/acumulado de todos os utilizadores vinculados à política selecionada. Eles voltarão a ter exatamente a "Cota Padrão" definida. <b>Esta ação não pode ser desfeita.</b></p>
        </div>

        <form action="<?php echo $BASE_URL; ?>/admin/init-quotas" method="post">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
          <input type="hidden" name="acao" value="resetar_quota">

          <h5 class="fw-bold mb-3">Selecione a Política Alvo</h5>
          <div class="row row-cols-1 g-3 mb-4">
            <?php
            $res_pol = $mysqli->query("SELECT cod_politica, nome, quota_padrao, quota_infinita FROM politicas ORDER BY nome");

            while ($pol = $res_pol->fetch_assoc()) {
              $disabled = ($pol['quota_infinita'] == 1) ? "disabled" : "";
              $bg_class = ($pol['quota_infinita'] == 1) ? "bg-light border-light opacity-50" : "bg-white border-primary border-start border-4 shadow-sm cursor-pointer";

              echo "<div class='col'>";
              echo "<label class='card card-body p-3 {$bg_class} flex-row align-items-center' style='cursor: pointer;'>";
              echo "<input class='form-check-input mt-0 me-3' type='radio' name='cod_politica' value='{$pol['cod_politica']}' required {$disabled}>";
              echo "<div>";
              echo "<span class='d-block fw-bold'>{$pol['nome']}</span>";
              if ($pol['quota_infinita'] == 1) {
                echo "<span class='small text-muted'><i class='bi bi-infinity'></i> Não aplicável (Cota Ilimitada)</span>";
              } else {
                echo "<span class='small text-muted'>Os utilizadores voltarão a ter: <b class='text-primary'>{$pol['quota_padrao']} págs</b></span>";
              }
              echo "</div></label></div>";
            }
            ?>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm" onclick="return confirm('Tem certeza absoluta? O saldo acumulado dos utilizadores será perdido e a cota será reiniciada!');"><i class="bi bi-arrow-repeat me-1"></i> Reiniciar Saldo dos Utilizadores</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../core/layout/footer.php'; ?>