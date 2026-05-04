<?php
/**
 * IBQUOTA 3 - Solicitação de Cota Extra (Usuário)
 */
include_once '../core/db.php';
include_once '../core/functions.php';
sec_session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario_logado = $_SESSION['usuario'];
$msg = ""; $tipo_msg = "";

// Processa o Envio do Pedido
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['paginas'])) {
    $paginas = (int)$_POST['paginas'];
    $motivo = trim($_POST['motivo']);

    if ($paginas > 0 && !empty($motivo)) {
        // Verifica se ele já tem um pedido Pendente para evitar spam
        $chk = $mysqli->prepare("SELECT id FROM solicitacoes_cota WHERE usuario = ? AND status = 'Pendente'");
        $chk->bind_param('s', $usuario_logado);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $msg = "Você já possui uma solicitação em análise. Aguarde a resposta do NTI.";
            $tipo_msg = "warning";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO solicitacoes_cota (usuario, paginas, motivo) VALUES (?, ?, ?)");
            $stmt->bind_param('sis', $usuario_logado, $paginas, $motivo);
            if ($stmt->execute()) {
                $msg = "Solicitação enviada com sucesso! O NTI analisará seu pedido em breve.";
                $tipo_msg = "success";
            } else {
                $msg = "Erro ao enviar a solicitação.";
                $tipo_msg = "danger";
            }
            $stmt->close();
        }
        $chk->close();
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
    <style>body { background-color: #f4f6f9; } .bg-ifnmg { background-color: #32a041; color: white; }</style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-ifnmg shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="meu_painel.php"><i class="bi bi-printer-fill me-2"></i> Impressões IFNMG</a>
            <div class="d-flex text-white align-items-center">
                <span class="me-3"><i class="bi bi-person-circle me-1"></i> Olá, <b><?php echo htmlspecialchars($usuario_logado); ?></b></span>
                <a href="../core/auth/logout.php" class="btn btn-sm btn-outline-light px-3">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container" style="max-width: 800px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-plus-circle text-success me-2"></i> Solicitar Mais Páginas</h3>
            <a href="meu_painel.php" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-left me-1"></i> Voltar ao Painel</a>
        </div>

        <?php if ($msg != "") { ?>
            <div class="alert alert-<?php echo $tipo_msg; ?> shadow-sm"><i class="bi bi-info-circle-fill me-2"></i><?php echo $msg; ?></div>
        <?php } ?>

        <div class="card shadow-sm border-0 border-top border-success border-4 mb-4">
            <div class="card-body p-4">
                <form action="solicitar_cota.php" method="post">
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

        <h5 class="fw-bold text-dark mb-3 mt-5"><i class="bi bi-clock-history text-muted me-2"></i> Histórico de Solicitações</h5>
        <div class="card shadow-sm border-0">
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
                                echo "<td><span class='d-inline-block text-truncate' style='max-width: 250px;' title='".htmlspecialchars($h['motivo'])."'>".htmlspecialchars($h['motivo'])."</span></td>";
                                echo "<td class='text-center fw-bold'>{$h['paginas']}</td>";
                                echo "<td><span class='badge bg-{$badge}'>{$h['status']}</span></td>";
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