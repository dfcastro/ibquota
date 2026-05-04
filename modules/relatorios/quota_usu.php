<?php

/**
 * IBQUOTA 3
 * GG - Gerenciador Gráfico do IBQUOTA
 * Relatório de Quotas e Impressões do Usuário Logado (Refatorado)
 */

include_once '../../core/db.php';
include_once '../../core/functions.php';
sec_session_start();

// Verifica se o utilizador está logado
if (!isset($_SESSION['usuario'])) {
  header("Location: ../../public/login.php");
  exit();
}

$logado = trim($_SESSION['usuario']);

// Inclui o cabeçalho padrão do sistema
include '../../core/layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
  <div>
    <h3 class="fw-bold text-primary mb-0"><i class="bi bi-person-badge text-primary me-2"></i> Minha Quota e Relatórios</h3>
    <p class="text-muted mb-0 small">Consulte os seus limites de impressão e o histórico de uso deste mês.</p>
  </div>
</div>

<div class="row">
  <!-- COLUNA 1: Resumo da Quota -->
  <div class="col-lg-4 mb-4">
    <div class="card shadow-sm border-0 border-top border-primary border-4">
      <div class="card-header bg-white fw-bold py-3">
        <i class="bi bi-pie-chart-fill me-2 text-primary"></i> Resumo de Quota
      </div>
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-4">Política</th>
              <th class="text-end pe-4">Saldo Disponível</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $stmt = $mysqli->prepare("SELECT cod_politica, nome, quota_infinita FROM politicas");
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($cod_politica, $nome_politica, $quota_infinita);

            $tem_politica = false;
            if ($logado != "aluno") {
              while ($stmt->fetch()) {
                $grupo = grupo_usuario_politica($cod_politica, $logado);
                if ($grupo != "") {
                  $tem_politica = true;
                  echo "<tr>";
                  echo "<td class='ps-4 fw-bold text-dark'>{$nome_politica}</td>";

                  if ($quota_infinita == 1) {
                    echo "<td class='text-end pe-4'><span class='badge bg-secondary'><i class='bi bi-infinity'></i> Ilimitada</span></td>";
                  } else {
                    $saldo = quota_usuario($cod_politica, $logado);
                    echo "<td class='text-end pe-4'><span class='badge bg-primary fs-6'>{$saldo} págs</span></td>";
                  }
                  echo "</tr>";
                }
              }
            }

            if (!$tem_politica) {
              echo "<tr><td colspan='2' class='text-center text-muted py-4'>Nenhuma quota atribuída.</td></tr>";
            }
            $stmt->close();
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- COLUNA 2: Histórico do Mês -->
  <div class="col-lg-8 mb-4">
    <div class="card shadow-sm border-0 border-top border-dark border-4">
      <div class="card-header bg-white fw-bold py-3">
        <i class="bi bi-list-check me-2 text-dark"></i> Meu Histórico (Mês Atual)
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-striped align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th class="ps-4">Data/Hora</th>
                <th>Impressora</th>
                <th>Documento</th>
                <th class="text-center pe-4">Págs</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $hoje_mes = date('m');
              $hoje_ano = date('y');
              $totalImpressoes = 0;

              // Nova query segura com Prepared Statements
              $sql_hist = "SELECT DATE_FORMAT(data_impressao, '%d/%m/%Y'), hora_impressao, impressora, nome_documento, paginas 
                                         FROM impressoes 
                                         WHERE usuario = ? AND DATE_FORMAT(data_impressao, '%m') = ? AND DATE_FORMAT(data_impressao, '%y') = ? 
                                         ORDER BY cod_impressoes DESC";

              $stmt_hist = $mysqli->prepare($sql_hist);
              $stmt_hist->bind_param('sss', $logado, $hoje_mes, $hoje_ano);
              $stmt_hist->execute();
              $stmt_hist->store_result();
              $stmt_hist->bind_result($d_imp, $h_imp, $imp, $doc, $pags);

              if ($stmt_hist->num_rows > 0) {
                while ($stmt_hist->fetch()) {
                  echo "<tr>";
                  echo "<td class='ps-4 small text-muted'>{$d_imp} <span class='ms-1'>{$h_imp}</span></td>";
                  echo "<td>{$imp}</td>";
                  echo "<td><span class='text-truncate d-inline-block' style='max-width: 250px;' title='" . htmlspecialchars(utf8_decode($doc)) . "'>" . htmlspecialchars(utf8_decode($doc)) . "</span></td>";
                  echo "<td class='text-center pe-4 fw-bold'>{$pags}</td>";
                  echo "</tr>";
                  $totalImpressoes += $pags;
                }
              } else {
                echo "<tr><td colspan='4' class='text-center text-muted py-5'>Nenhuma impressão realizada este mês.</td></tr>";
              }
              $stmt_hist->close();
              ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if ($totalImpressoes > 0) { ?>
        <div class="card-footer bg-light text-end py-3">
          <span class="text-muted me-2">Total Impresso no Mês:</span>
          <span class="fs-5 fw-bold text-dark"><?php echo $totalImpressoes; ?> páginas</span>
        </div>
      <?php } ?>
    </div>
  </div>
</div>

<?php include '../../core/layout/footer.php'; ?>