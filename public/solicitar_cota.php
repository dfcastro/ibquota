<?php

/**
 * IBQUOTA 3 - Solicitação de Cota Extra (Usuário)
 * Com Inteligência de Contexto e Alertas Dinâmicos por E-mail
 * Corrigido: Integração completa com o Roteador (URLs Amigáveis)
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

// ==========================================
// DETEÇÃO INTELIGENTE DE AMBIENTE E ROTAS
// ==========================================
$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

if (!isset($_SESSION['usuario'])) {
    header("Location: " . $BASE_URL . "/login");
    exit();
}

$usuario_logado = $_SESSION['usuario'];
$msg = "";
$tipo_msg = "";

// ==========================================
// 1. ANÁLISE DO PERFIL DO UTILIZADOR
// ==========================================
$tem_grupo = false;
$quota_infinita = false;
$saldo_atual = 0;
$nome_politica = "";
$nomes_grupos = [];

// A. Busca o Código do Usuário
$cod_usuario = 0;
$stmt_u = $mysqli->prepare("SELECT cod_usuario FROM usuarios WHERE usuario = ?");
$stmt_u->bind_param('s', $usuario_logado);
$stmt_u->execute();
$stmt_u->bind_result($cod_usuario);
$stmt_u->fetch();
$stmt_u->close();

// B. Busca os Grupos
if ($cod_usuario > 0) {
    $stmt_g = $mysqli->prepare("SELECT g.grupo FROM grupo_usuario gu JOIN grupos g ON gu.cod_grupo = g.cod_grupo WHERE gu.cod_usuario = ?");
    $stmt_g->bind_param('i', $cod_usuario);
    $stmt_g->execute();
    $res_g = $stmt_g->get_result();
    while ($row = $res_g->fetch_assoc()) {
        $nomes_grupos[] = $row['grupo'];
    }
    $stmt_g->close();
}
$tem_grupo = count($nomes_grupos) > 0;

// C. Busca o Saldo e a Política Atual
$stmt_q = $mysqli->prepare("SELECT qu.quota, p.quota_infinita, p.nome FROM quota_usuario qu JOIN politicas p ON qu.cod_politica = p.cod_politica WHERE qu.usuario = ?");
$stmt_q->bind_param('s', $usuario_logado);
$stmt_q->execute();
$res_q = $stmt_q->get_result();
if ($row = $res_q->fetch_assoc()) {
    $saldo_atual = $row['quota'];
    $quota_infinita = ($row['quota_infinita'] == 1);
    $nome_politica = $row['nome'];
}
$stmt_q->close();

// ==========================================
// 2. PROCESSA O ENVIO DO PEDIDO
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['paginas'])) {
    $token_recebido = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    validar_csrf_token($token_recebido);

    // Bloqueia tentativas de envio forçado via código se o utilizador não tiver permissão
    if (!$tem_grupo || $quota_infinita) {
        $msg = "Ação não permitida para o seu perfil atual.";
        $tipo_msg = "danger";
    } else {
        $paginas = (int)$_POST['paginas'];
        $motivo = trim($_POST['motivo']);

        if ($paginas > 0 && !empty($motivo)) {
            $chk = $mysqli->prepare("SELECT id FROM solicitacoes_cota WHERE usuario = ? AND status = 'Pendente'");
            $chk->bind_param('s', $usuario_logado);
            $chk->execute();
            $chk->store_result();

            if ($chk->num_rows > 0) {
                $msg = "Você já possui uma solicitação em análise. Aguarde a resposta do NTI.";
                $tipo_msg = "warning text-dark";
            } else {
                $stmt = $mysqli->prepare("INSERT INTO solicitacoes_cota (usuario, paginas, motivo) VALUES (?, ?, ?)");
                $stmt->bind_param('sis', $usuario_logado, $paginas, $motivo);

                if ($stmt->execute()) {

                    // ----------------------------------------------------
                    // NOVO: DISPARA O ALERTA DE E-MAIL INTELIGENTE PARA O NTI
                    // ----------------------------------------------------
                    disparar_alerta_gestor('cota', $usuario_logado, $paginas . " páginas", $motivo);

                    $msg = "Solicitação enviada com sucesso! O NTI analisará seu pedido em breve.";
                    $tipo_msg = "success";
                } else {
                    $msg = "Erro ao enviar a solicitação no banco de dados.";
                    $tipo_msg = "danger";
                }
                $stmt->close();
            }
            $chk->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Cota Extra - IFNMG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
            <a class="navbar-brand fw-bold" href="<?php echo $BASE_URL; ?>/meu-painel"><i class="bi bi-printer-fill me-2"></i> Impressões IFNMG</a>
            <div class="d-flex text-white align-items-center">
                <span class="me-3"><i class="bi bi-person-circle me-1"></i> Olá, <b><?php echo htmlspecialchars($usuario_logado); ?></b></span>
                <a href="<?php echo $BASE_URL; ?>/logout" class="btn btn-sm btn-outline-light px-3">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container" style="max-width: 800px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-plus-circle text-success me-2"></i> Solicitar Mais Páginas</h3>
            <a href="<?php echo $BASE_URL; ?>/meu-painel" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-left me-1"></i> Voltar ao Painel</a>
        </div>

        <?php if ($msg != "") { ?>
            <div class="alert alert-<?php echo $tipo_msg; ?> shadow-sm border-0"><i class="bi bi-info-circle-fill me-2"></i><?php echo $msg; ?></div>
        <?php } ?>

        <?php if (!$tem_grupo): ?>
            <div class="alert alert-warning shadow-sm border-0 border-start border-warning border-4 p-4 mb-4">
                <h5 class="fw-bold text-dark"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Acesso Aguardando Liberação</h5>
                <p class="mb-0 text-dark">A sua conta de rede foi sincronizada com sucesso, mas <strong>ainda não foi vinculada a um grupo</strong> com permissões de impressão.<br>Por favor, aguarde a organização pelo NTI ou entre em contato com o suporte para liberação.</p>
            </div>

        <?php elseif ($quota_infinita): ?>
            <div class="alert alert-info shadow-sm border-0 border-start border-info border-4 p-4 mb-4">
                <h5 class="fw-bold"><i class="bi bi-infinity text-info me-2"></i> Cota Ilimitada</h5>
                <p class="mb-0">Você está vinculado à política <b><?php echo htmlspecialchars($nome_politica); ?></b>, que possui impressões ilimitadas no sistema.<br>Não é necessário solicitar páginas adicionais.</p>
            </div>

        <?php else: ?>
            <div class="row mb-4">
                <div class="col-md-6 mb-2 mb-md-0">
                    <div class="card bg-white border-0 shadow-sm h-100">
                        <div class="card-body py-3 d-flex align-items-center">
                            <div class="bg-light rounded p-2 me-3 text-secondary"><i class="bi bi-diagram-3 fs-4"></i></div>
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Seu Grupo Atual</div>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars(implode(', ', $nomes_grupos)); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-white border-0 shadow-sm h-100">
                        <div class="card-body py-3 d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 rounded p-2 me-3 text-success"><i class="bi bi-files fs-4"></i></div>
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Saldo Disponível</div>
                                <div class="fw-bold <?php echo ($saldo_atual < 20) ? 'text-danger' : 'text-success'; ?> fs-5"><?php echo $saldo_atual; ?> páginas</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 border-top border-success border-4 mb-4">
                <div class="card-body p-4">
                    <form action="<?php echo $BASE_URL; ?>/solicitar-cota" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo gerar_csrf_token(); ?>">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold text-muted small">Quantidade Necessária</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-files"></i></span>
                                    <input type="number" class="form-control" name="paginas" min="1" max="1000" placeholder="Ex: 50" required>
                                </div>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label fw-bold text-muted small">Justificativa / Motivo</label>
                                <input type="text" class="form-control" name="motivo" placeholder="Ex: Impressão de provas bimestrais (Turma 2A)" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-bold mt-2"><i class="bi bi-send-fill me-2"></i> Enviar Solicitação</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <h5 class="fw-bold text-dark mb-3 mt-5"><i class="bi bi-clock-history text-muted me-2"></i> Histórico de Solicitações</h5>
        <div class="card shadow-sm border-0 mb-5">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Data</th>
                            <th>Motivo</th>
                            <th class="text-center">Qtd</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res_hist = $mysqli->query("SELECT *, DATE_FORMAT(data_solicitacao, '%d/%m/%Y') as data_br FROM solicitacoes_cota WHERE usuario = '$usuario_logado' ORDER BY id DESC LIMIT 10");
                        if ($res_hist->num_rows > 0) {
                            while ($h = $res_hist->fetch_assoc()) {
                                $badge = ($h['status'] == 'Aprovado') ? 'success' : (($h['status'] == 'Negado') ? 'danger' : 'warning text-dark');
                                echo "<tr>";
                                echo "<td class='ps-4 text-muted small'>{$h['data_br']}</td>";
                                echo "<td><span class='d-inline-block text-truncate' style='max-width: 250px;' title='" . htmlspecialchars($h['motivo']) . "'>" . htmlspecialchars($h['motivo']) . "</span></td>";
                                echo "<td class='text-center fw-bold'>{$h['paginas']}</td>";
                                echo "<td><span class='badge bg-{$badge} shadow-sm'>{$h['status']}</span></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='text-center py-4 text-muted'>Nenhuma solicitação encontrada.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>