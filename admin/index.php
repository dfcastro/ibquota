<?php

/**
 * IFQUOTA 3 - DASHBOARD ADMINISTRATIVO COMPLETO
 * Sincronizado com Backup, Datas Corrigidas e Links Restaurados.
 */

// ==========================================
// INCLUDES E CONFIGURAÇÕES DE SESSÃO
// ==========================================
include_once __DIR__ . '/../core/db.php';
include_once __DIR__ . '/../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || ($_SESSION['permissao'] != 2 && $_SESSION['permissao'] != 3)) {
    header("Location: " . $BASE_URL . "/meu-painel");
    exit();
}

// ==========================================
// BUSCA DE MÉTRICAS (RÁPIDO E DIRETO NO BANCO)
// ==========================================

$mes_atual = date('n'); // Ex: 5
$ano_atual = date('Y'); // Ex: 2026

// 1. Impressões Hoje (Soma tudo o que foi registrado hoje)
$res_hoje = $mysqli->query("SELECT IFNULL(SUM(paginas), 0) as total FROM impressoes WHERE data_impressao = CURDATE()");
$impressoes_hoje = $res_hoje->fetch_assoc()['total'];

// 2. Volume do Mês (O SQL agressivo sincronizado com o terminal)
$res_mes = $mysqli->query("SELECT IFNULL(SUM(paginas), 0) as total FROM impressoes WHERE MONTH(data_impressao) = $mes_atual AND YEAR(data_impressao) = $ano_atual");
$impressoes_mes = $res_mes->fetch_assoc()['total'];

// 3. Alertas de Fila Colorida (Pendentes)
$res_color = $mysqli->query("SELECT COUNT(*) as total FROM pedidos_coloridos WHERE status = 'Pendente'");
$fila_colorida = $res_color->fetch_assoc()['total'];

// 4. Alertas de Pedido de Cota (Pendentes)
$res_cota = $mysqli->query("SELECT COUNT(*) as total FROM solicitacoes_cota WHERE status = 'Pendente'");
$pedidos_cota = $res_cota->fetch_assoc()['total'];

// 5. Total de Impressões com Erro (Hoje)
$res_erro = $mysqli->query("SELECT COUNT(*) as total FROM impressoes WHERE data_impressao = CURDATE() AND cod_status_impressao != 1");
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

// Exibe a mensagem de erro caso ele tente acessar uma rota bloqueada e seja redirecionado pra cá
if (isset($_GET['msg']) && $_GET['msg'] === 'acesso_negado') {
    echo '
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-shield-lock-fill me-2 fs-5"></i> 
        <strong>Acesso Restrito:</strong> Você tentou acessar uma página técnica exclusiva da equipe do NTI.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-speedometer2 text-primary me-2"></i> Painel de Controle</h3>
        <p class="text-muted mb-md-0 small">Visão geral do consumo e alertas do servidor de impressão IFQUOTA.</p>
    </div>
    <div>
        <a href="<?php echo $BASE_URL; ?>/admin/relatorio" class="btn btn-outline-primary shadow-sm fw-bold">
            <i class="bi bi-bar-chart-fill me-1"></i> Relatório Geral
        </a>
    </div>
</div>

<div class="row g-3 mb-4">

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
        <div class="card shadow-sm border-0 h-100 border-top border-danger border-3">
            <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-exclamation-triangle text-danger me-2"></i>Últimos Erros (Falhas/Bloqueios)</span>
                <?php if ($erros_hoje > 0) {
                    echo "<span class='badge bg-danger rounded-pill'>{$erros_hoje} Hoje</span>";
                } ?>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php
                    $erros = $mysqli->query("SELECT usuario, nome_documento, impressora, cod_status_impressao, CONCAT(DATE_FORMAT(data_impressao, '%d/%m'), ' ', DATE_FORMAT(hora_impressao, '%H:%i')) as data_br FROM impressoes WHERE cod_status_impressao != 1 ORDER BY data_impressao DESC, hora_impressao DESC LIMIT 6");

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

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100 border-top border-success border-3">
            <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-check-circle-fill text-success me-2"></i>Últimas Impressões Bem-Sucedidas</span>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php
                    $sucessos = $mysqli->query("SELECT usuario, nome_documento, impressora, paginas, CONCAT(DATE_FORMAT(data_impressao, '%d/%m'), ' ', DATE_FORMAT(hora_impressao, '%H:%i')) as data_br FROM impressoes WHERE cod_status_impressao = 1 ORDER BY data_impressao DESC, hora_impressao DESC LIMIT 6");

                    if ($sucessos->num_rows > 0) {
                        while ($suc = $sucessos->fetch_assoc()) {
                            echo "<li class='list-group-item py-3'>";
                            echo "<div class='d-flex w-100 justify-content-between align-items-center mb-1'>";
                            echo "<h6 class='mb-0 fw-bold text-success'><i class='bi bi-printer me-1'></i>{$suc['impressora']}</h6>";
                            echo "<span class='badge bg-success bg-opacity-10 text-success border border-success'>+{$suc['paginas']} pág(s)</span>";
                            echo "</div>";

                            echo "<p class='mb-1 small text-truncate' style='max-width: 400px;' title='" . htmlspecialchars($suc['nome_documento']) . "'><b>Arquivo:</b> " . htmlspecialchars($suc['nome_documento']) . "</p>";

                            echo "<div class='d-flex w-100 justify-content-between align-items-center mt-1'>";
                            echo "<small class='text-muted'><i class='bi bi-person me-1'></i>Usuário: {$suc['usuario']}</small>";
                            echo "<small class='text-muted'>{$suc['data_br']}</small>";
                            echo "</div>";

                            echo "</li>";
                        }
                    } else {
                        echo "<li class='list-group-item text-center text-muted py-5'><i class='bi bi-inbox fs-1 d-block mb-3'></i>Nenhuma impressão com sucesso encontrada no banco.</li>";
                    }
                    ?>
                </ul>
            </div>
            <div class="card-footer bg-light text-center border-0">
                <span class="small text-muted"><i class="bi bi-info-circle me-1"></i>Apenas os itens acima somam no card "Impresso Hoje".</span>
            </div>
        </div>
    </div>

</div>

<div class="row g-4 mb-5">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold py-3"><i class="bi bi-trophy text-warning me-2"></i>Top 10 Usuários (Volume do Mês)</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php
                    // Usando $mes_atual e $ano_atual para casar perfeitamente com os 13 mil do painel
                    $top_users = $mysqli->query("SELECT usuario, SUM(paginas) as total FROM impressoes WHERE MONTH(data_impressao) = $mes_atual AND YEAR(data_impressao) = $ano_atual AND cod_status_impressao = 1 GROUP BY usuario ORDER BY total DESC LIMIT 10");

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