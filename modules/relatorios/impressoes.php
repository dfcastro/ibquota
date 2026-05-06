<?php

/**
 * IFQUOTA - Relatório Único e Detalhado de Impressões
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 1) {
   header("Location: " . $BASE_URL . "/login");
   exit();
}

// TRATAMENTO DOS FILTROS (GET)
$filtro_usuario = isset($_GET['nome_usuario']) ? trim($_GET['nome_usuario']) : '';
$data_inicial = isset($_GET['data_inicial']) ? trim($_GET['data_inicial']) : '';
$data_final = isset($_GET['data_final']) ? trim($_GET['data_final']) : '';
$filtro_status = isset($_GET['status']) ? trim($_GET['status']) : '';

$query = "SELECT DATE_FORMAT(data_impressao, '%d/%m/%Y'), hora_impressao, job_id, impressora, 
                 usuario, estacao, nome_documento, paginas, cod_status_impressao 
          FROM impressoes WHERE 1=1 ";
$params = [];
$types = "";

if ($filtro_usuario != '') {
   $query .= " AND usuario = ? ";
   $params[] = $filtro_usuario;
   $types .= "s";
}
if ($data_inicial != '' && $data_final != '') {
   $query .= " AND data_impressao BETWEEN ? AND ? ";
   $params[] = $data_inicial;
   $params[] = $data_final;
   $types .= "ss";
}
if ($filtro_status != '') {
   $query .= " AND cod_status_impressao = ? ";
   $params[] = $filtro_status;
   $types .= "i";
}

$query .= " ORDER BY cod_impressoes DESC LIMIT 1000";

$stmt = $mysqli->prepare($query);
if ($types != "") {
   $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($data_impressao, $hora_impressao, $job_id, $impressora, $usuario, $estacao, $nome_documento, $paginas, $cod_status_impressao);

include __DIR__ . '/../../core/layout/header.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
   <div>
      <h3 class="fw-bold text-dark mb-0"><i class="bi bi-printer text-muted me-2"></i> Relatório de Impressões</h3>
      <p class="text-muted mb-0 small">Analise o histórico, filtre por período, utilizador ou status e exporte os dados.</p>
   </div>
   <div>
      <a href="<?php echo $BASE_URL; ?>/admin/dashboard" class="btn btn-outline-secondary shadow-sm fw-bold">
          <i class="bi bi-arrow-left me-1"></i> Voltar
      </a>
   </div>
</div>

<div class="card shadow-sm border-0 mb-4 border-top border-primary border-3">
   <div class="card-header bg-white fw-bold py-3"><i class="bi bi-funnel me-2"></i>Filtros de Pesquisa</div>
   <div class="card-body bg-light">
      <form action="<?php echo $BASE_URL; ?>/admin/relatorio" method="GET" class="row g-3 align-items-end">
         <div class="col-md-3">
            <label class="form-label fw-bold small text-muted">Usuário (Opcional)</label>
            <div class="input-group shadow-sm">
               <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
               <input type="text" class="form-control" name="nome_usuario" value="<?php echo htmlspecialchars($filtro_usuario); ?>" placeholder="Ex: joao.silva">
            </div>
         </div>

         <div class="col-md-2">
            <label class="form-label fw-bold small text-muted">Data Inicial</label>
            <input type="date" class="form-control shadow-sm" name="data_inicial" value="<?php echo htmlspecialchars($data_inicial); ?>">
         </div>
         <div class="col-md-2">
            <label class="form-label fw-bold small text-muted">Data Final</label>
            <input type="date" class="form-control shadow-sm" name="data_final" value="<?php echo htmlspecialchars($data_final); ?>">
         </div>

         <div class="col-md-3">
            <label class="form-label fw-bold small text-muted">Status da Impressão</label>
            <div class="input-group shadow-sm">
               <span class="input-group-text bg-white"><i class="bi bi-info-circle"></i></span>
               <select class="form-select" name="status">
                  <option value="">Todos os Status</option>
                  <?php
                  $res_status = $mysqli->query("SELECT cod_status_impressao, nome_status FROM status_impressao ORDER BY cod_status_impressao");
                  while ($st = $res_status->fetch_assoc()) {
                     $selected = ($filtro_status == $st['cod_status_impressao']) ? 'selected' : '';
                     $nome_exibicao = $st['nome_status'];

                     if (stripos($nome_exibicao, 'cadastrado') !== false || $st['cod_status_impressao'] == 3) {
                        $nome_exibicao = "Bloqueado: Sem Grupo/Cota";
                     } elseif (stripos($nome_exibicao, 'excedida') !== false) {
                        $nome_exibicao = "Bloqueado: Cota Excedida";
                     } elseif ($st['cod_status_impressao'] == 10) {
                        $nome_exibicao = "Erro Físico / Impressora Offline";
                     }

                     echo "<option value='{$st['cod_status_impressao']}' {$selected}>{$nome_exibicao}</option>";
                  }
                  ?>
               </select>
            </div>
         </div>

         <div class="col-md-2 d-grid gap-2">
            <button type="submit" class="btn btn-primary fw-bold shadow-sm"><i class="bi bi-search me-1"></i> Filtrar</button>
         </div>
      </form>
   </div>
</div>

<div class="card shadow-sm border-0 mb-4">
   <div class="card-body p-4">
      <div class="table-responsive">
         <table id="tabelaRelatorio" class="table table-hover table-striped align-middle mb-0 w-100">
            <thead class="table-light">
               <tr>
                  <th>Job ID</th>
                  <th>Data e Hora</th>
                  <th>Usuário</th>
                  <th>Impressora</th>
                  <th>Estação</th>
                  <th>Documento</th>
                  <th class="text-center">Páginas</th>
                  <th>Status</th>
               </tr>
            </thead>
            <tbody>
               <?php
               $total_paginas = 0;

               if ($stmt->num_rows > 0) {
                  while ($stmt->fetch()) {
                     $status_nome = status_impressao($cod_status_impressao);

                     if ($cod_status_impressao == 1) {
                        $total_paginas += $paginas;
                        $badge_class = 'text-bg-success';
                        $icone = 'bi-check-circle-fill';
                     } else {
                        $badge_class = 'text-bg-danger';
                        $icone = 'bi-x-circle-fill';

                        if (stripos($status_nome, 'cadastrado') !== false || $cod_status_impressao == 3) {
                           $status_nome = "Bloqueado: Sem Grupo/Cota";
                           $badge_class = 'text-bg-warning text-dark';
                           $icone = 'bi-exclamation-triangle-fill';
                        } elseif (stripos($status_nome, 'excedida') !== false) {
                           $status_nome = "Bloqueado: Cota Excedida";
                           $badge_class = 'bg-danger text-white border border-danger';
                           $icone = 'bi-slash-circle';
                        } elseif ($cod_status_impressao == 10) {
                           $status_nome = "Erro Físico / Offline";
                           $badge_class = 'text-bg-dark';
                           $icone = 'bi-printer-fill';
                        }
                     }
                     
                     // Ajuste para PHP 8.x: mb_convert_encoding é mais seguro que utf8_decode
                     $doc_seguro = htmlspecialchars(mb_convert_encoding($nome_documento, 'UTF-8', 'auto'));

                     echo "<tr>";
                     echo "<td><span class='text-muted small'>#{$job_id}</span></td>";
                     echo "<td><i class='bi bi-calendar3 me-1 text-muted small'></i> <span class='d-none'>" . date('Ymd', strtotime(str_replace('/', '-', $data_impressao))) . "</span>{$data_impressao} <span class='text-muted small ms-1'>{$hora_impressao}</span></td>";
                     echo "<td class='fw-bold text-dark'>{$usuario}</td>";
                     echo "<td>{$impressora}</td>";
                     echo "<td><span class='badge bg-light text-secondary border font-monospace'>{$estacao}</span></td>";
                     echo "<td><span class='small text-truncate d-inline-block' style='max-width: 200px;' title='{$doc_seguro}'>{$doc_seguro}</span></td>";
                     echo "<td class='text-center fw-bold'>{$paginas}</td>";
                     echo "<td><span class='badge {$badge_class} shadow-sm px-2 py-1'><i class='bi {$icone} me-1'></i>{$status_nome}</span></td>";
                     echo "</tr>\n";
                  }
               }
               ?>
            </tbody>
         </table>
      </div>
   </div>
</div>

<?php if ($total_paginas > 0) { ?>
   <div class="row mb-5">
      <div class="col-md-4 ms-auto">
         <div class="card bg-success text-white shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
               <div>
                  <h6 class="mb-0 text-white-50 text-uppercase fw-bold" style="font-size: 0.8rem;">Páginas Impressas com Sucesso</h6>
                  <h3 class="mb-0 fw-bold"><?php echo $total_paginas; ?> <span class="fs-6 fw-normal">págs</span></h3>
               </div>
               <i class="bi bi-layers fs-1 text-white-50"></i>
            </div>
         </div>
      </div>
   </div>
<?php } ?>

<?php include __DIR__ . '/../../core/layout/footer.php'; ?>

<!-- Scripts do DataTables para Exportação -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
   $(document).ready(function() {
      $('#tabelaRelatorio').DataTable({
         language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
         },
         pageLength: 25,
         dom: '<"row mb-3 align-items-center"<"col-md-6"B><"col-md-6"f>>rt<"row mt-3"<"col-md-6"i><"col-md-6"p>>',
         buttons: [{
               extend: 'excelHtml5',
               className: 'btn btn-sm btn-success shadow-sm me-1',
               text: '<i class="bi bi-file-earmark-excel"></i> Exportar Excel'
            },
            {
               extend: 'pdfHtml5',
               className: 'btn btn-sm btn-danger shadow-sm me-1',
               text: '<i class="bi bi-file-earmark-pdf"></i> Exportar PDF',
               orientation: 'landscape'
            },
            {
               extend: 'print',
               className: 'btn btn-sm btn-secondary shadow-sm',
               text: '<i class="bi bi-printer"></i> Imprimir'
            }
         ],
         order: [
            [1, 'desc'] // Ordena pela Data e Hora
         ],
         columnDefs: [{
            orderable: false,
            targets: 5 // O documento geralmente não precisa de ordenação e atrapalha
         }]
      });
   });
</script>