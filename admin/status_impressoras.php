<?php

/**
 * IFQUOTA - STATUS DA REDE E IMPRESSORAS
 * Lê e exibe o estado em tempo real das impressoras configuradas no CUPS.
 */

// 1. INCLUDES BLINDADOS
include_once __DIR__ . '/../core/db.php';
include_once __DIR__ . '/../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

// ==========================================
// DETEÇÃO INTELIGENTE DE AMBIENTE
// ==========================================
$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

// Validação de acesso: Apenas administradores (nível 2 ou superior)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 2) {
    header("Location: " . $BASE_URL . "/login");
    exit();
}

include __DIR__ . '/../core/layout/header.php';

// ==========================================
// LÓGICA DE EXTRAÇÃO DE DADOS DO CUPS
// ==========================================

$impressoras = [];

// Comando 1: Busca as URIs (Endereços) das impressoras instaladas
$lpstat_v = shell_exec('lpstat -v 2>/dev/null');
$uris_impressoras = [];

if ($lpstat_v) {
    $linhas_v = explode("\n", trim($lpstat_v));
    foreach ($linhas_v as $linha) {
        if (preg_match('/device for (.*?):\s+(.*)/', $linha, $matches)) {
            $nome_impressora = trim($matches[1]);
            $uri_completa = trim($matches[2]);
            $uris_impressoras[$nome_impressora] = $uri_completa;
        }
    }
}

// Comando 2: Busca o estado atual de cada impressora
$lpstat_p = shell_exec('lpstat -p 2>/dev/null');

if ($lpstat_p) {
    $linhas_p = explode("\n", trim($lpstat_p));
    foreach ($linhas_p as $linha) {
        if (preg_match('/printer\s+([^\s]+)\s+(.*)/', $linha, $matches)) {
            $nome = trim($matches[1]);
            $texto_status = strtolower(trim($matches[2]));

            $status_visual = 'Desconhecido';
            $badge_cor = 'bg-secondary';
            $icone = 'bi-question-circle';

            // Tradução e categorização do retorno do CUPS
            if (strpos($texto_status, 'disabled') !== false || strpos($texto_status, 'not ready') !== false) {
                $status_visual = 'Offline / Erro';
                $badge_cor = 'bg-danger';
                $icone = 'bi-exclamation-triangle-fill';
            } elseif (strpos($texto_status, 'paused') !== false) {
                $status_visual = 'Pausada';
                $badge_cor = 'bg-warning text-dark';
                $icone = 'bi-pause-circle-fill';
            } elseif (strpos($texto_status, 'is idle') !== false) {
                $status_visual = 'Pronta / Online';
                $badge_cor = 'bg-success';
                $icone = 'bi-check-circle-fill';
            } else {
                $status_visual = 'Processando / Ocupada';
                $badge_cor = 'bg-primary';
                $icone = 'bi-printer-fill';
            }

            // Descobrir o IP através da URI
            $uri_desta = isset($uris_impressoras[$nome]) ? $uris_impressoras[$nome] : 'Desconhecida';
            $ip = '-';

            if (preg_match('/:\/\/([^\/:]+)/', $uri_desta, $ip_matches)) {
                $ip = $ip_matches[1];
            }

            $impressoras[] = [
                'nome' => $nome,
                'status' => $status_visual,
                'badge' => $badge_cor,
                'icone' => $icone,
                'ip' => $ip,
                'detalhe' => ucfirst($texto_status)
            ];
        }
    }
}
?>

<!-- Injetar CSS do DataTables apenas nesta página -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-router text-secondary me-2"></i> Status da Rede e Equipamentos</h3>
        <p class="text-muted mb-0 small">Monitorização em tempo real das impressoras comunicando com o servidor.</p>
    </div>
    <div class="d-flex gap-2">
        <!-- O reload do JS continua seguro e prático -->
        <button onclick="window.location.reload();" class="btn btn-primary shadow-sm fw-bold">
            <i class="bi bi-arrow-clockwise me-1"></i> Atualizar Status
        </button>
        <a href="<?php echo $BASE_URL; ?>/admin/dashboard" class="btn btn-outline-secondary shadow-sm fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-secondary border-4 mb-4">
    <div class="card-body p-4">

        <?php if (empty($impressoras)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox-fill display-1 text-light mb-3"></i>
                <h4 class="fw-bold">Nenhuma impressora detetada.</h4>
                <p>Verifique se as filas estão corretamente configuradas no CUPS no servidor Linux.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table id="tabelaImpressoras" class="table table-hover table-striped align-middle mb-0 w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Nome da Impressora / Fila</th>
                            <th>Endereço IP</th>
                            <th>Estado de Conexão</th>
                            <th>Resposta Bruta do Servidor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($impressoras as $imp): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-dark"><i class="bi bi-printer me-2 text-muted"></i> <?php echo htmlspecialchars($imp['nome']); ?></span>
                                </td>
                                <td>
                                    <span class="font-monospace text-primary bg-light px-2 py-1 border rounded shadow-sm">
                                        <?php echo htmlspecialchars($imp['ip']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $imp['badge']; ?> shadow-sm px-3 py-2 fs-6">
                                        <i class="bi <?php echo $imp['icone']; ?> me-1"></i> <?php echo $imp['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-muted fst-italic" style="max-width: 300px; display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($imp['detalhe']); ?>">
                                        <?php echo htmlspecialchars($imp['detalhe']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../core/layout/footer.php'; ?>

<!-- Iniciar DataTables para Pesquisa Rápida de IPs/Nomes -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        if ($('#tabelaImpressoras').length) {
            $('#tabelaImpressoras').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                },
                order: [
                    [2, 'asc'], // Ordena primeiro pelo estado de conexão (Erros aparecem primeiro)
                    [0, 'asc']  // Depois ordena pelo nome
                ],
                pageLength: 25,
                dom: '<"row mb-3"<"col-md-6"f><"col-md-6 text-end">>rt<"row mt-3"<"col-md-6"i><"col-md-6"p>>'
            });
        }
    });
</script>