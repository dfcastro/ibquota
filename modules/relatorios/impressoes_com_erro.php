<?php

/**
 * IBQUOTA 3
 * Relatório de Impressões com Erro/Bloqueio - Refatorado (Bootstrap 5 + DataTables)
 */
include_once '../../core/db.php';
include_once '../../core/functions.php';

sec_session_start();

if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 1) {
   header("Location: ../../public/login.php");
   exit();
}
include '../../core/layout/header.php';

// PAGINACAO
$p = (isset($_GET['p'])) ? (int)$_GET['p'] : 1;
$p = ($p < 1) ? 1 : $p;
$p_qtde_por_pagina = 100; // Aumentado para 100 para facilitar exportação
$p_inicio = ($p_qtde_por_pagina * $p) - $p_qtde_por_pagina;
$p_num_registros = 0;

if ($num_stmt = $mysqli->prepare("SELECT count(*) FROM impressoes WHERE cod_status_impressao != 1")) {
   $num_stmt->execute();
   $num_stmt->bind_result($p_num_registros);
   $num_stmt->fetch();
   $num_stmt->close();
}

// Busca impressoes com erro no banco de dados (Status diferente de 1)
if ($stmt = $mysqli->prepare("SELECT DATE_FORMAT(data_impressao, '%d/%m/%Y'), hora_impressao, job_id, impressora,
              usuario, estacao, nome_documento, paginas, cod_politica, cod_status_impressao
      FROM impressoes
      WHERE cod_status_impressao != 1
      ORDER BY cod_impressoes DESC LIMIT ?, ?")) {
   $stmt->bind_param('ii', $p_inicio, $p_qtde_por_pagina);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($data_impressao, $hora_impressao, $job_id, $impressora, $usuario, $estacao, $nome_documento, $paginas, $cod_politica, $cod_status_impressao);
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
   <div>
      <h3 class="fw-bold text-danger mb-0"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i> Impressões com Erro ou Bloqueadas</h3>
      <p class="text-muted mb-0 small">Registo de trabalhos rejeitados por falta de cota, falha no CUPS ou problemas de permissão.</p>
   </div>
</div>

<div class="card shadow-sm border-0 border-top border-danger border-4 mb-4">
   <div class="card-body p-4">
      <div class="table-responsive">
         <table id="tabelaErros" class="table table-hover table-striped align-middle mb-0 w-100">
            <thead class="table-light">
               <tr>
                  <th>Job ID</th>
                  <th>Data e Hora</th>
                  <th>Usuário</th>
                  <th>Impressora</th>
                  <th>Estação</th>
                  <th>Documento</th>
                  <th class="text-center">Páginas</th>
                  <th>Motivo do Bloqueio</th>
               </tr>
            </thead>
            <tbody>
               <?php
               $sem_grupo = 1;
               while ($stmt->fetch()) {
                  $sem_grupo = 0;

                  // O Nosso Tradutor Visual de Status
                  $status_nome = status_impressao($cod_status_impressao);
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
                  }

                  echo "<tr>";
                  echo "<td><span class='text-muted small'>#{$job_id}</span></td>";
                  // Corrigido: Agora mostramos a Data e Hora que faltava no original!
                  echo "<td><i class='bi bi-calendar3 me-1 text-muted small'></i> <span class='d-none'>" . date('Ymd', strtotime(str_replace('/', '-', $data_impressao))) . "</span>{$data_impressao} <span class='text-muted small ms-1'>{$hora_impressao}</span></td>";
                  echo "<td class='fw-bold text-dark'>{$usuario}</td>";
                  echo "<td>{$impressora}</td>";
                  echo "<td><span class='badge bg-light text-secondary border font-monospace'>{$estacao}</span></td>";
                  echo "<td><span class='small text-truncate d-inline-block' style='max-width: 200px;' title='" . htmlspecialchars(utf8_decode($nome_documento)) . "'>" . htmlspecialchars(utf8_decode($nome_documento)) . "</span></td>";
                  echo "<td class='text-center fw-bold text-muted'>{$paginas}</td>";
                  echo "<td><span class='badge {$badge_class} shadow-sm px-2 py-1'><i class='bi {$icone} me-1'></i>{$status_nome}</span></td>";
                  echo "</tr>\n";
               }

               if ($sem_grupo == 1) {
                  echo "<tr><td colspan='8' class='text-center text-muted py-5'><i class='bi bi-shield-check fs-1 text-success d-block mb-2'></i>Tudo limpo! Nenhuma impressão com erro.</td></tr>";
               }
               ?>
            </tbody>
         </table>
      </div>
   </div>
</div>

<div class="d-flex justify-content-center mt-3">
   <?php barra_de_paginas($p, $p_num_registros); ?>
</div>

<?php include '../../core/layout/footer.php'; ?>

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
      $('#tabelaErros').DataTable({
         language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
         },
         paging: false,
         info: false,
         dom: '<"row mb-3"<"col-md-6"B><"col-md-6 text-end"f>>t',
         buttons: [{
               extend: 'excelHtml5',
               className: 'btn btn-sm btn-success shadow-sm',
               text: '<i class="bi bi-file-earmark-excel"></i> Excel'
            },
            {
               extend: 'pdfHtml5',
               className: 'btn btn-sm btn-danger shadow-sm',
               text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
               orientation: 'landscape'
            },
            {
               extend: 'print',
               className: 'btn btn-sm btn-secondary shadow-sm',
               text: '<i class="bi bi-printer"></i> Imprimir'
            }
         ],
         order: [
            [1, 'desc']
         ], // Ordena pela Data/Hora
         columnDefs: [{
            orderable: false,
            targets: 5
         }]
      });
   });
</script>