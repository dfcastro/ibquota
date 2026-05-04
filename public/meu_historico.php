<?php

/**
 * IBQUOTA 3 - Meu Histórico Completo
 */
include_once '../core/db.php';
include_once '../core/functions.php';

sec_session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario_logado = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico Completo - IFNMG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background-color: #f4f6f9;
        }

        .bg-ifnmg {
            background-color: #32a041;
            color: white;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-ifnmg shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="meu_painel.php"><i class="bi bi-printer-fill me-2"></i> Impressões IFNMG</a>
            <div class="d-flex text-white align-items-center">
                <span class="me-3"><i class="bi bi-person-circle me-1"></i> Olá, <b><?php echo htmlspecialchars($usuario_logado); ?></b></span>
                <a href="../core/auth/logout.php" class="btn btn-sm btn-outline-light px-3"><i class="bi bi-box-arrow-right me-1"></i> Sair</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-search text-primary me-2"></i> Histórico Completo</h3>
            <a href="meu_painel.php" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-left me-1"></i> Voltar ao Painel</a>
        </div>

        <div class="card shadow-sm border-0 mb-5">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table id="tabelaHistorico" class="table table-hover align-middle mb-0 w-100">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Data/Hora</th>
                                <th>Documento</th>
                                <th class="text-center">Páginas</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query_hist = "SELECT DATE_FORMAT(data_impressao, '%d/%m/%Y') as data_imp, hora_impressao, impressora, nome_documento, paginas, cod_status_impressao 
                                           FROM impressoes 
                                           WHERE usuario = ? 
                                           ORDER BY cod_impressoes DESC";
                            $stmt_hist = $mysqli->prepare($query_hist);
                            $stmt_hist->bind_param('s', $usuario_logado);
                            $stmt_hist->execute();
                            $res_hist = $stmt_hist->get_result();

                            while ($row = $res_hist->fetch_assoc()) {
                                $status_nome = status_impressao($row['cod_status_impressao']);

                                if ($row['cod_status_impressao'] == 1) {
                                    $cor = 'text-bg-success';
                                    $icone = 'bi-check-circle-fill';
                                    $texto = 'Impresso com Sucesso';
                                } elseif ($row['cod_status_impressao'] == 3 || stripos($status_nome, 'cadastrado') !== false) {
                                    $cor = 'text-bg-warning';
                                    $icone = 'bi-exclamation-triangle-fill';
                                    $texto = 'Bloqueado (Sem Cota)';
                                } elseif (stripos($status_nome, 'excedida') !== false) {
                                    $cor = 'text-bg-danger';
                                    $icone = 'bi-slash-circle';
                                    $texto = 'Cota Excedida';
                                } else {
                                    $cor = 'text-bg-secondary';
                                    $icone = 'bi-info-circle';
                                    $texto = $status_nome;
                                }

                                echo "<tr>";
                                echo "<td class='ps-4 text-muted small'><span class='d-none'>" . date('Ymd', strtotime(str_replace('/', '-', $row['data_imp']))) . "</span>{$row['data_imp']} às {$row['hora_impressao']}</td>";
                                echo "<td>
                                        <span class='d-inline-block text-truncate fw-semibold' style='max-width: 350px;' title='" . htmlspecialchars($row['nome_documento']) . "'>
                                            <i class='bi bi-file-earmark-pdf me-2 text-danger'></i>" . htmlspecialchars($row['nome_documento']) . "
                                        </span>
                                        <br><small class='text-muted'><i class='bi bi-printer me-1'></i>{$row['impressora']}</small>
                                      </td>";
                                echo "<td class='text-center fw-bold'>{$row['paginas']}</td>";
                                echo "<td><span class='badge {$cor} shadow-sm px-3 py-2'><i class='bi {$icone} me-1'></i>{$texto}</span></td>";
                                echo "</tr>";
                            }
                            $stmt_hist->close();
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tabelaHistorico').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                },
                order: [
                    [0, 'desc']
                ],
                pageLength: 25,
                dom: '<"row mb-3"<"col-md-6"f><"col-md-6 text-end">>rt<"row mt-3"<"col-md-6"i><"col-md-6"p>>'
            });
        });
    </script>
</body>

</html>