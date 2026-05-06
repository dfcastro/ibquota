<?php

/**
 * IFQUOTA - DASHBOARD ADMINISTRATIVO MODERNO
 * Resumo estatístico e alertas em tempo real.
 */

// ==========================================
// INCLUDES BLINDADOS COM __DIR__
// ==========================================
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

// Validação de Sessão e Rotas Limpas
if (!isset($_SESSION['usuario'])) {
    header("Location: " . $BASE_URL . "/login");
    exit();
}

if (!isset($_SESSION['permissao']) || $_SESSION['permissao'] < 2) {
    header("Location: " . $BASE_URL . "/meu-painel");
    exit();
}

// ==========================================
// BUSCA DE MÉTRICAS (RÁPIDO E DIRETO NO BANCO)
// ==========================================

// 1. Impressões Hoje
$res_hoje = $mysqli->query("SELECT IFNULL(SUM(paginas), 0) as total FROM impressoes WHERE DATE(data_impressao) = CURDATE() AND cod_status_impressao = 1");
$impressoes_hoje = $res_hoje->fetch_assoc()['total'];

// 2. Impressões no Mês
$res_mes = $mysqli->query("SELECT IFNULL(SUM(paginas), 0) as total FROM impressoes WHERE MONTH(data_impressao) = MONTH(CURDATE()) AND YEAR(data_impressao) = YEAR(CURDATE()) AND cod_status_impressao = 1");
$impressoes_mes = $res_mes->fetch_assoc()['total'];

// 3. Alertas de Fila Colorida (Pendentes)
$res_color = $mysqli->query("SELECT COUNT(*) as total FROM pedidos_coloridos WHERE status = 'Pendente'");
$fila_colorida = $res_color->fetch_assoc()['total'];

// 4. Alertas de Pedido de Cota (Pendentes)
$res_cota = $mysqli->query("SELECT COUNT(*) as total FROM solicitacoes_cota WHERE status = 'Pendente'");
$pedidos_cota = $res_cota->fetch_assoc()['total'];

// 5. Total de Impressões com Erro (Hoje)
$res_erro = $mysqli->query("SELECT COUNT(*) as total FROM impressoes WHERE DATE(data_impressao) = CURDATE() AND cod_status_impressao != 1");
$erros_hoje = $res_erro->fetch_assoc()['total'];

// 6. Status das Impressoras (Lido diretamente do CUPS)
$total_impressoras = 0;
$impressoras_online = 0;
$impressoras_offline = 0;

$cups_status = shell_exec('lpstat -p 2>/dev/null');

if ($cups_status) {
    $linhas = explode("\n", trim($cups_status));
    foreach ($linhas as $linha) {
        if (preg_match('/^printer\s+([^\s]+)/', $linha)) {
            $total_impressoras++;
            if (stripos($linha, 'disabled') !== false || stripos($linha, 'not ready') !== false) {
                $impressoras_offline++;
            } else {
                $impressoras_online++;
            }
        }
    }
}

include __DIR__ . '/../core/layout/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-speedometer2 text-primary me-2"></i> Painel de Controle</h3>
        <p class="text-muted mb-md-0 small">Visão geral do consumo e alertas do servidor de impressão IFQUOTA.</p>
    </div>
    <div>
        <!-- Rota atualizada para o relatório -->
        <a href="<?php echo $BASE_URL; ?>/admin/relatorios/impressoes" class="btn btn-outline-primary shadow-sm fw-bold">
            <i class="bi bi-bar-chart-fill me-1"></i> Relatório Geral
        </a>
    </div>
</div>

<div class="row g-3 mb-4">

    <!-- CARTÃO 1: Impresso Hoje -->
    <div class="col-md-6 col-lg">
        <div class="card shadow-sm border-0 h-100 border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="text-muted fw-bold mb-0">Impresso Hoje</h6>
                    <div class="p-2 bg-success bg-opacity-10 text-success rounded"><i class="bi bi-file-earmark-text fs-5"></i></div>
                </div>
                <h3 class="fw-bold text-dark mb-0"><?php echo number_format($impressoes_hoje, 0, ',', '.'); ?> <span class="fs-6 text-muted fw-normal">págs</span></h3>
            </div>
        </div>
    </div>

    <!-- CARTÃO 2: Volume do Mês -->
    <div class="col-md-6 col-lg">
        <div class="card shadow-sm border-0 h-100 border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="text-muted fw-bold mb-0">Volume do Mês</h6>
                    <div class="p-2 bg-primary bg-opacity-10 text-primary rounded"><i class="bi bi-calendar-check fs-5"></i></div>
                </div>
                <h3 class="fw-bold text-dark mb-0"><?php echo number_format($impressoes_mes, 0, ',', '.'); ?> <span class="fs-6 text-muted fw-normal">págs</span></h3>
            </div>
        </div>
    </div>

    <!-- CARTÃO 3: Fila Colorida -->
    <div class="col-md-6 col-lg">
        <a href="<?php echo $BASE_URL; ?>/admin/coloridas" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100 border-start border-warning border-4 hover-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted fw-bold mb-0">Fila Colorida</h6>
                        <div class="p-2 bg-warning bg-opacity-10 text-warning rounded"><i class="bi bi-palette fs-5"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">
                        <?php echo $fila_colorida; ?>
                        <?php if ($fila_colorida > 0) {
                            echo "<span class='badge bg-danger ms-2 fs-6 blink'>Pendentes</span>";
                        } ?>
                    </h3>
                </div>
            </div>
        </a>
    </div>

    <!-- CARTÃO 4: Pedidos de Cota -->
    <div class="col-md-6 col-lg">
        <a href="<?php echo $BASE_URL; ?>/admin/solicitacoes" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100 border-start border-info border-4 hover-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted fw-bold mb-0">Pedidos de Cota</h6>
                        <div class="p-2 bg-info bg-opacity-10 text-info rounded"><i class="bi bi-inbox fs-5"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">
                        <?php echo $pedidos_cota; ?>
                        <?php if ($pedidos_cota > 0) {
                            echo "<span class='badge bg-info text-dark ms-2 fs-6'>Aguardando</span>";
                        } ?>
                    </h3>
                </div>
            </div>
        </a>
    </div>

    <!-- CARTÃO 5: Status da Rede -->
    <div class="col-md-6 col-lg">
        <a href="<?php echo $BASE_URL; ?>/admin/status-impressoras" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100 border-start border-secondary border-4 hover-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted fw-bold mb-0" style="font-size: 0.9rem;">Status da Rede</h6>
                        <div class="p-2 bg-secondary bg-opacity-10 text-secondary rounded"><i class="bi bi-router fs-5"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">
                        <?php echo $impressoras_online; ?> <span class="fs-6 text-muted fw-normal">/ <?php echo $total_impressoras; ?> ON</span>
                    </h3>

                    <?php if ($impressoras_offline > 0): ?>
                        <p class="text-danger small mt-2 mb-0 fw-bold blink">
                            <i class="bi bi-exclamation-circle-fill"></i> <?php echo $impressoras_offline; ?> offline
                        </p>
                    <?php else: ?>
                        <p class="text-success small mt-2 mb-0 fw-bold">
                            <i class="bi bi-check-circle-fill"></i> Operacional
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>

</div>

<div class="row g-4 mb-5">

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold py-3"><i class="bi bi-trophy text-warning me-2"></i>Top 10 Usuários (Este Mês)</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php
                    $top_users = $mysqli->query("SELECT usuario, SUM(paginas) as total FROM impressoes WHERE MONTH(data_impressao) = MONTH(CURDATE()) AND YEAR(data_impressao) = YEAR(CURDATE()) AND cod_status_impressao = 1 GROUP BY usuario ORDER BY total DESC LIMIT 10");

                    if ($top_users->num_rows > 0) {
                        $pos = 1;
                        while ($user = $top_users->fetch_assoc()) {
                            $medalha = "";
                            if ($pos == 1) $medalha = "<i class='bi bi-award-fill text-warning me-1'></i>";
                            elseif ($pos == 2) $medalha = "<i class='bi bi-award-fill text-secondary me-1'></i>";
                            elseif ($pos == 3) $medalha = "<i class='bi bi-award-fill text-danger me-1' style='color: #cd7f32 !important;'></i>";

                            echo "<li class='list-group-item d-flex justify-content-between align-items-center py-3'>";
                            echo "<div><span class='fw-bold text-muted me-2'>#{$pos}</span> {$medalha} <span class='fw-semibold'>{$user['usuario']}</span></div>";
                            echo "<span class='badge bg-light text-dark border rounded-pill px-3 py-2'>{$user['total']} págs</span>";
                            echo "</li>";
                            $pos++;
                        }
                    } else {
                        echo "<li class='list-group-item text-center text-muted py-4'>Nenhuma impressão registrada neste mês.</li>";
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100 border-top border-danger border-3">
            <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-exclamation-triangle text-danger me-2"></i>Últimos Erros do CUPS</span>
                <?php if ($erros_hoje > 0) {
                    echo "<span class='badge bg-danger rounded-pill'>{$erros_hoje} Hoje</span>";
                } ?>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php
                    $erros = $mysqli->query("SELECT usuario, nome_documento, impressora, cod_status_impressao, DATE_FORMAT(data_impressao, '%d/%m %H:%i') as data_br FROM impressoes WHERE cod_status_impressao != 1 ORDER BY data_impressao DESC, hora_impressao DESC LIMIT 8");

                    if ($erros->num_rows > 0) {
                        while ($erro = $erros->fetch_assoc()) {

                            $status_nome = status_impressao($erro['cod_status_impressao']);
                            $badge_erro = "<span class='badge bg-danger'>Erro Desconhecido</span>";

                            if ($erro['cod_status_impressao'] == 10) {
                                $badge_erro = "<span class='badge bg-dark'><i class='bi bi-printer-fill me-1'></i>Físico/Offline</span>";
                            } elseif ($erro['cod_status_impressao'] == 3 || stripos($status_nome, 'cadastrado') !== false) {
                                $badge_erro = "<span class='badge bg-warning text-dark'><i class='bi bi-exclamation-triangle-fill me-1'></i>Sem Cota/Grupo</span>";
                            } elseif (stripos($status_nome, 'excedida') !== false) {
                                $badge_erro = "<span class='badge bg-danger'><i class='bi bi-slash-circle me-1'></i>Cota Excedida</span>";
                            }

                            echo "<li class='list-group-item py-3'>";
                            echo "<div class='d-flex w-100 justify-content-between align-items-center mb-1'>";
                            echo "<h6 class='mb-0 fw-bold text-danger'><i class='bi bi-printer me-1'></i>{$erro['impressora']}</h6>";
                            echo "{$badge_erro}";
                            echo "</div>";

                            echo "<p class='mb-1 small text-truncate' style='max-width: 400px;' title='" . htmlspecialchars($erro['nome_documento']) . "'><b>Arquivo:</b> " . htmlspecialchars($erro['nome_documento']) . "</p>";

                            echo "<div class='d-flex w-100 justify-content-between align-items-center mt-1'>";
                            echo "<small class='text-muted'><i class='bi bi-person me-1'></i>Usuário: {$erro['usuario']}</small>";
                            echo "<small class='text-muted'>{$erro['data_br']}</small>";
                            echo "</div>";

                            echo "</li>";
                        }
                    } else {
                        echo "<li class='list-group-item text-center text-muted py-5'><i class='bi bi-check-circle text-success fs-1 d-block mb-3'></i>Tudo limpo! Nenhum erro de impressão recente.</li>";
                    }
                    ?>
                </ul>
            </div>
            <div class="card-footer bg-light text-center border-0">
                <a href="<?php echo $BASE_URL; ?>/admin/relatorios/erros" class="text-decoration-none small text-danger fw-bold">Ver todos os erros <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>

</div>

<style>
    .hover-card {
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }

    .hover-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15) !important;
    }

    @keyframes blinker {
        50% {
            opacity: 0;
        }
    }

    .blink {
        animation: blinker 1.5s linear infinite;
    }
</style>

<?php include __DIR__ . '/../core/layout/footer.php'; ?>