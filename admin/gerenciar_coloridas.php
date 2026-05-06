<?php

/**
 * IFQUOTA - GERENCIADOR DE FILA DE IMPRESSÃO COLORIDA
 * Aprovação NTI (Até 500 págs) / Direção (Acima de 500)
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

// Garante que o Token CSRF existe na sessão antes de carregar a página
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 2. Proteção da página: Admins (2) e Diretores (3)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || ($_SESSION['permissao'] != 2 && $_SESSION['permissao'] != 3)) {
    header("Location: " . $BASE_URL . "/login");
    exit();
}

$admin_logado = $_SESSION['usuario'];
$msg = "";
$tipo_msg = "";

// ========================================================================
// ⚙️ CONFIGURAÇÃO DE DIRETORES
// O Diretor agora é validado pelo nível de permissão (3) no banco
// ========================================================================
$is_diretor = ($_SESSION['permissao'] == 3);

// ========================================================================
// 1. VISUALIZADOR DE PDF EMBUTIDO (Disparado via GET)
// ========================================================================
if (isset($_GET['view'])) {
    $id_view = (int)$_GET['view'];
    $stmt_v = $mysqli->prepare("SELECT arquivo_caminho, arquivo_nome FROM pedidos_coloridos WHERE id = ?");
    $stmt_v->bind_param('i', $id_view);
    $stmt_v->execute();
    $stmt_v->bind_result($path, $name);

    if ($stmt_v->fetch()) {
        // O caminho correto saindo do 'admin/' e entrando no 'public/'
        $caminho_real = __DIR__ . "/../public/" . $path;

        if (file_exists($caminho_real)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($name) . '"');
            readfile($caminho_real);
            exit();
        }
    }
    die("Erro: Arquivo PDF não encontrado no servidor.");
}

// ========================================================================
// 2. PROCESSAMENTO DE APROVAÇÃO / REJEIÇÃO (Disparado via POST)
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'], $_POST['id_pedido'])) {
    $token_recebido = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    validar_csrf_token($token_recebido);

    $id_pedido = (int)$_POST['id_pedido'];
    $acao = $_POST['acao'];

    // Busca dados do pedido
    $stmt_ped = $mysqli->prepare("SELECT usuario, arquivo_nome, arquivo_caminho, paginas, paginas_especificas, copias, impressora FROM pedidos_coloridos WHERE id = ? AND status = 'Pendente'");
    $stmt_ped->bind_param('i', $id_pedido);
    $stmt_ped->execute();
    $res_ped = $stmt_ped->get_result();

    if ($row = $res_ped->fetch_assoc()) {

        $caminho_real = __DIR__ . "/../public/" . $row['arquivo_caminho'];

        $total_paginas_pedido = $row['paginas'] * $row['copias'];

        // --- CALCULA O CONSUMO MENSAL ATUAL ---
        $res_cota = $mysqli->query("SELECT SUM(paginas * copias) as total FROM pedidos_coloridos WHERE status = 'Aprovado' AND MONTH(data_pedido) = MONTH(CURRENT_DATE()) AND YEAR(data_pedido) = YEAR(CURRENT_DATE())");
        $total_consumido = $res_cota->fetch_assoc()['total'] ?? 0;
        $novo_total = $total_consumido + $total_paginas_pedido;

        if ($acao == 'aprovar') {

            // Trava de Segurança das 500 Cotas
            if ($novo_total > 500 && !$is_diretor) {
                $msg = "Aprovação Negada! Este pedido fará o campus ultrapassar as 500 cotas coloridas mensais. <b>Somente a Direção</b> pode liberar esta impressão.";
                $tipo_msg = "danger";
            } else {
                // APROVADO! Dispara para o CUPS
                $cmd_impressora = escapeshellarg($row['impressora']);
                $cmd_usuario = escapeshellarg($row['usuario']);
                $cmd_titulo = escapeshellarg("Autorizado-" . $row['arquivo_nome']);
                $cmd_arquivo = escapeshellarg(realpath($caminho_real));

                // Extração inteligente de páginas
                $paginas_alvo = $row['paginas_especificas'];
                $cmd_page_ranges = !empty($paginas_alvo) ? "-o page-ranges=" . escapeshellarg($paginas_alvo) : "";

                $comando = "lp -d {$cmd_impressora} -n {$row['copias']} {$cmd_page_ranges} -t {$cmd_titulo} -U {$cmd_usuario} {$cmd_arquivo} 2>&1";
                $saida_shell = shell_exec($comando);

                if (strpos(strtolower($saida_shell), 'request id is') !== false || strpos(strtolower($saida_shell), 'id da requisição') !== false) {
                    $upd = $mysqli->prepare("UPDATE pedidos_coloridos SET status = 'Aprovado', aprovado_por = ? WHERE id = ?");
                    $upd->bind_param('si', $admin_logado, $id_pedido);
                    $upd->execute();

                    @unlink($caminho_real); // Limpa o disco

                    $msg = "Impressão liberada! Apenas as páginas coloridas foram enviadas para a impressora.";
                    $tipo_msg = "success";
                } else {
                    $msg = "Erro ao processar no servidor CUPS: <br><small>" . htmlspecialchars($saida_shell) . "</small>";
                    $tipo_msg = "danger";
                }
            }
        } elseif ($acao == 'rejeitar') {
            // REJEITADO
            $upd = $mysqli->prepare("UPDATE pedidos_coloridos SET status = 'Rejeitado', aprovado_por = ? WHERE id = ?");
            $upd->bind_param('si', $admin_logado, $id_pedido);
            $upd->execute();

            @unlink($caminho_real); // Limpa o disco

            $msg = "Pedido rejeitado. O documento foi descartado.";
            $tipo_msg = "warning";
        }
    }
    $stmt_ped->close();
}

// ========================================================================
// 3. RECARREGA OS INDICADORES PARA A TELA
// ========================================================================
$res_cota = $mysqli->query("SELECT SUM(paginas * copias) as total FROM pedidos_coloridos WHERE status = 'Aprovado' AND MONTH(data_pedido) = MONTH(CURRENT_DATE()) AND YEAR(data_pedido) = YEAR(CURRENT_DATE())");
$total_mes = $res_cota->fetch_assoc()['total'] ?? 0;
$limite_mensal = 500;
$percentual_uso = min(100, ($total_mes / $limite_mensal) * 100);

$cor_barra = 'bg-success';
if ($percentual_uso > 75) $cor_barra = 'bg-warning';
if ($percentual_uso >= 100) $cor_barra = 'bg-danger';

include __DIR__ . '/../core/layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-palette-fill text-danger me-2"></i> Retenção de Coloridas</h3>
        <p class="text-muted mb-0 small">Fila de aprovação de documentos coloridos</p>
    </div>
    <a href="<?php echo $BASE_URL; ?>/admin/dashboard" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-left me-1"></i> Voltar ao Painel</a>
</div>

<?php if ($msg != "") { ?>
    <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible shadow-sm">
        <i class="bi bi-info-circle-fill me-2"></i> <?php echo $msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php } ?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-dark"><i class="bi bi-graph-up text-primary me-2"></i>Cota Mensal do Campus (Colorida)</span>
                    <span class="fw-bold <?php echo ($total_mes >= $limite_mensal) ? 'text-danger' : 'text-success'; ?>">
                        <?php echo $total_mes; ?> / <?php echo $limite_mensal; ?> páginas
                    </span>
                </div>
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar progress-bar-striped <?php echo $cor_barra; ?>" role="progressbar" style="width: <?php echo $percentual_uso; ?>%;">
                        <?php echo number_format($percentual_uso, 1); ?>%
                    </div>
                </div>
                <?php if ($total_mes >= $limite_mensal && !$is_diretor) { ?>
                    <div class="alert alert-danger mt-3 mb-0 small py-2">
                        <i class="bi bi-lock-fill me-1"></i> O limite institucional de 500 cópias coloridas foi atingido. Novos pedidos exigem autenticação da Direção Geral.
                    </div>
                <?php } elseif ($total_mes >= $limite_mensal && $is_diretor) { ?>
                    <div class="alert alert-warning mt-3 mb-0 small py-2">
                        <i class="bi bi-unlock-fill me-1"></i> <b>Modo Diretor Ativo:</b> O limite foi atingido, mas você possui permissão de exceção para continuar aprovando.
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-primary border-3">
    <div class="card-header bg-white fw-bold py-3">
        <i class="bi bi-hourglass-split me-2 text-primary"></i> Aguardando Liberação
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Data/Hora</th>
                        <th>Solicitante</th>
                        <th>Documento</th>
                        <th class="text-center">Total (Págs)</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $res_pendentes = $mysqli->query("SELECT * FROM pedidos_coloridos WHERE status = 'Pendente' ORDER BY data_pedido ASC");

                    if ($res_pendentes->num_rows > 0) {
                        while ($p = $res_pendentes->fetch_assoc()) {
                            $data_formatada = date('d/m/Y H:i', strtotime($p['data_pedido']));
                            $total_folhas = $p['paginas'] * $p['copias'];
                            $extrapola = (($total_mes + $total_folhas) > $limite_mensal);
                            $bloquear_botao = ($extrapola && !$is_diretor) ? "disabled title='Bloqueado: Requer Direção'" : "";

                            echo "<tr>";
                            echo "<td class='ps-4 text-muted small'>{$data_formatada}</td>";
                            echo "<td class='fw-bold text-dark'><i class='bi bi-person me-1'></i>" . htmlspecialchars($p['usuario']) . "</td>";
                            echo "<td><span class='text-truncate d-inline-block' style='max-width: 250px;' title='" . htmlspecialchars($p['arquivo_nome']) . "'><i class='bi bi-file-earmark-pdf text-danger me-1'></i>" . htmlspecialchars($p['arquivo_nome']) . "</span></td>";

                            // Mostra total de páginas e quais são coloridas
                            echo "<td class='text-center fw-bold'>";
                            echo "{$p['paginas']} pág(s) x {$p['copias']} cpy<br><span class='badge bg-dark rounded-pill'>= {$total_folhas}</span>";
                            if (!empty($p['paginas_especificas'])) {
                                echo "<br><small class='text-primary' style='font-size: 0.75rem;'>Págs a imprimir: " . htmlspecialchars($p['paginas_especificas']) . "</small>";
                            }
                            echo "</td>";

                            // BOTÕES DE AÇÃO
                            echo "<td class='text-center'>";
                            echo "<div class='d-flex justify-content-center gap-2'>";

                            // ROTA LIMPA NO VISUALIZADOR
                            echo "<a href='{$BASE_URL}/admin/coloridas?view={$p['id']}' target='_blank' class='btn btn-sm btn-outline-primary' title='Ler PDF'><i class='bi bi-eye'></i></a>";

                            // ROTA LIMPA NO FORM ACTION
                            echo "<form action='{$BASE_URL}/admin/coloridas' method='post' class='m-0 d-flex gap-2'>";

                            $token_seguro = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
                            echo "<input type='hidden' name='csrf_token' value='{$token_seguro}'>";

                            echo "<input type='hidden' name='id_pedido' value='{$p['id']}'>";
                            echo "<button type='submit' name='acao' value='aprovar' class='btn btn-sm btn-success fw-bold' {$bloquear_botao} onclick='return confirm(\"Aprovar a impressão deste documento?\")'><i class='bi bi-check-circle me-1'></i>Aprovar</button>";
                            echo "<button type='submit' name='acao' value='rejeitar' class='btn btn-sm btn-danger' title='Negar pedido' onclick='return confirm(\"Rejeitar e apagar este arquivo?\")'><i class='bi bi-x-circle'></i></button>";
                            echo "</form></div></td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-5 text-muted'><i class='bi bi-cup-hot display-4 d-block mb-3'></i>Fila vazia! Não há impressões coloridas pendentes no momento.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../core/layout/footer.php'; ?>