<?php

/**
 * IBQUOTA 3
 * Relatório de Logs do Sistema (CUPS/Backend) - Refatorado
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__.'/../../core/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 1) {
   header("Location: /gg/login");
   exit();
}

include __DIR__ . '/../../core/layout/header.php';

// Busca os últimos 500 logs RELEVANTES[cite: 4]
$query = "SELECT id, mensagem, DATE_FORMAT(datahora, '%d/%m/%Y %H:%i') as datahora 
          FROM log_ibquota 
          WHERE TRIM(mensagem) != 'IBQUOTA started.' 
          ORDER BY id DESC LIMIT 500";
$resultado = $mysqli->query($query);
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
   <div>
      <h3 class="fw-bold text-dark mb-0"><i class="bi bi-terminal text-dark me-2"></i> Logs do Backend (CUPS)</h3>
      <p class="text-muted mb-0 small">Registo de eventos do sistema e erros de comunicação com o daemon.</p>
   </div>
</div>

<div class="card shadow-sm border-0 border-top border-dark border-4 mb-4">
   <div class="card-body p-4">
      <div class="table-responsive">
         <table id="tabelaLogs" class="table table-hover table-striped align-middle mb-0 w-100 font-monospace small">
            <thead class="table-dark">
               <tr>
                  <th style="width: 200px;">Data e Hora</th>
                  <th>Mensagem do Sistema</th>
               </tr>
            </thead>
            <tbody>
               <?php
               while ($log = $resultado->fetch_assoc()) {
                  $mensagem = htmlspecialchars(utf8_decode($log['mensagem']));
                  // NOVIDADE: Adicionadas as palavras "bloqueio" e "offline" geradas pelo Perl[cite: 4]
                  if (stripos($mensagem, 'erro') !== false || stripos($mensagem, 'fail') !== false || stripos($mensagem, 'bloqueio') !== false || stripos($mensagem, 'offline') !== false) {
                     $mensagem = "<span class='text-danger fw-bold'><i class='bi bi-x-circle me-1'></i>{$mensagem}</span>";
                  }

                  echo "<tr>";
                  echo "<td><i class='bi bi-clock me-1 text-muted'></i> {$log['datahora']}</td>";
                  echo "<td>{$mensagem}</td>";
                  echo "</tr>\n";
               }
               ?>
            </tbody>
         </table>
      </div>
   </div>
</div>

<?php include __DIR__.'/../../core/layout/footer.php'; ?>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<script>
   $(document).ready(function() {
      $('#tabelaLogs').DataTable({
         language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
         },
         pageLength: 25,
         dom: '<"row mb-3"<"col-md-6"B><"col-md-6 text-end"f>>rt<"row mt-3"<"col-md-6"i><"col-md-6"p>>',
         buttons: [{
            extend: 'excelHtml5',
            className: 'btn btn-sm btn-success shadow-sm',
            text: '<i class="bi bi-file-earmark-excel"></i> Exportar Logs'
         }],
         order: [
            [0, 'desc']
         ]
      });
   });
</script>