<?php

/**
 * IBQUOTA 3 - PORTAL DO SERVIDOR (VERSÃO FINAL INTEGRADA)
 */
include_once 'includes/db.php';
include_once 'includes/functions.php';

sec_session_start();

// 1. Validação de Sessão
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario_logado = $_SESSION['usuario'];

// 2. BUSCA DE DADOS DO USUÁRIO, POLÍTICA E SALDO
// Esta query cruza o usuário -> seu grupo -> a política de cotas daquele grupo
$query = "
    SELECT 
        u.cod_usuario,
        p.nome AS nome_politica,
        p.quota_padrao AS limite_padrao,
        p.quota_infinita,
        (p.quota_padrao - IFNULL((SELECT SUM(paginas) FROM impressoes WHERE usuario = u.usuario), 0)) AS saldo_atual
    FROM usuarios u
    LEFT JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario
    LEFT JOIN grupos g ON gu.cod_grupo = g.cod_grupo
    LEFT JOIN politica_grupo pg ON g.cod_grupo = pg.grupo
    LEFT JOIN politicas p ON pg.cod_politica = p.cod_politica
    WHERE u.usuario = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('s', $usuario_logado);
$stmt->execute();
$stmt->bind_result($cod_usuario, $nome_politica, $limite_padrao, $quota_infinita, $saldo_atual);
$stmt->fetch();
$stmt->close();

// Fallback caso o usuário não tenha política definida ainda (ex: acabou de ser sincronizado)
if (empty($nome_politica)) {
    $nome_politica = "Padrão (Pendente)";
    $limite_padrao = 50;
    $saldo_atual = 50;
    $quota_infinita = 0;
}

// 3. Cálculo do Percentual e Cor da Barra
$percentual = 0;
$cor_barra = "bg-success";

if ($quota_infinita == 1) {
    $percentual = 100;
} elseif ($limite_padrao > 0) {
    $percentual = ($saldo_atual / $limite_padrao) * 100;
    if ($percentual < 0) $percentual = 0;
    if ($percentual > 100) $percentual = 100;

    // Mudar cor se a cota estiver acabando
    if ($percentual < 20) $cor_barra = "bg-danger";
    elseif ($percentual < 50) $cor_barra = "bg-warning";
}

// 4. Busca Histórico Recente
// Note: usamos apelidos (AS) para facilitar o uso no HTML abaixo
$res_hist = $mysqli->query("SELECT data_impressao, hora_impressao, nome_documento, paginas, cod_status_impressao FROM impressoes WHERE usuario = '$usuario_logado' ORDER BY cod_impressoes DESC LIMIT 5");

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Painel - Central de Impressões IFNMG</title>
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

        .progress {
            background-color: #e9ecef;
            border-radius: 50px;
            overflow: hidden;
        }

        .card {
            border-radius: 12px;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-ifnmg shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="meu_painel.php"><i class="bi bi-printer-fill me-2"></i> Impressões IFNMG</a>
            <div class="d-flex text-white align-items-center">
                <span class="me-3 d-none d-md-inline"><i class="bi bi-person-circle me-1"></i> Olá, <b><?php echo htmlspecialchars($usuario_logado); ?></b></span>
                <a href="logout.php" class="btn btn-sm btn-outline-light px-3"><i class="bi bi-box-arrow-right me-1"></i> Sair</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row mb-4">
            <div class="col-md-5 mb-3">
                <div class="card shadow-sm border-0 h-100 border-top border-success border-4">
                    <div class="card-body p-4 text-center">
                        <h5 class="text-muted fw-bold mb-3">Seu Saldo Atual</h5>

                        <?php if ($nome_politica == "Sem Política Atribuída") { ?>
                            <div class="display-3 text-danger mb-2"><i class="bi bi-exclamation-triangle"></i></div>
                            <h4 class="fw-bold text-dark">Usuário Bloqueado</h4>
                            <p class="text-muted small">Você não possui cota de impressão. Procure o NTI.</p>
                        <?php } elseif ($quota_infinita == 1) { ?>
                            <div class="display-3 text-primary mb-2"><i class="bi bi-infinity"></i></div>
                            <h4 class="fw-bold text-dark">Impressão Ilimitada</h4>
                            <p class="text-muted small">Política: <?php echo $nome_politica; ?></p>
                        <?php } else { ?>
                            <div class="display-1 fw-bold text-dark mb-0"><?php echo (int)$saldo_atual; ?></div>
                            <p class="text-muted mb-3">páginas restantes de <?php echo $limite_padrao; ?></p>

                            <div class="progress mb-2" style="height: 25px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated <?php echo $cor_barra; ?>" role="progressbar" style="width: <?php echo $percentual; ?>%;" aria-valuenow="<?php echo $saldo_atual; ?>" aria-valuemin="0" aria-valuemax="<?php echo $limite_padrao; ?>">
                                    <span class="fw-bold fs-6"><?php echo round($percentual); ?>%</span>
                                </div>
                            </div>
                            <p class="small text-muted mb-0">Regra aplicada: <b><?php echo $nome_politica; ?></b></p>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="col-md-7 mb-3">
                <div class="card shadow-sm border-0 h-100 bg-white">
                    <div class="card-body p-4 d-flex flex-column justify-content-center align-items-center text-center">
                        <div class="p-3 bg-light rounded-circle mb-3">
                            <i class="bi bi-cloud-arrow-up text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h4 class="fw-bold text-dark">Impressão Sem Fios (Web Print)</h4>
                        <p class="text-muted">Envie arquivos PDF diretamente do seu celular ou notebook pessoal para as impressoras do campus sem precisar de cabos.</p>
                        <button class="btn btn-primary disabled shadow-sm"><i class="bi bi-upload me-2"></i> Enviar Documento (Em breve)</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-5">
            <div class="card-header bg-white fw-bold p-3 border-bottom d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2"></i> Meu Histórico Recente</span>
                <span class="badge bg-light text-dark border">Últimas 5 atividades</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
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
                        if ($res_hist && $res_hist->num_rows > 0) {
                            while ($hist = $res_hist->fetch_assoc()) {
                                // Formata data de YYYY-MM-DD para DD/MM/YYYY
                                $data_br = date("d/m/Y", strtotime($hist['data_impressao']));

                                // Determina o Status (Ajuste a função status_impressao se ela não existir)
                                $status_cod = $hist['cod_status_impressao'];
                                $status_txt = "Processado";
                                $badge_class = "text-bg-success";

                                if ($status_cod != 1) {
                                    $status_txt = "Erro/Bloqueado";
                                    $badge_class = "text-bg-danger";
                                }

                                echo "<tr>";
                                echo "<td class='ps-4 text-muted small'>{$data_br} às {$hist['hora_impressao']}</td>";
                                echo "<td><span class='d-inline-block text-truncate' style='max-width: 350px;' title='" . htmlspecialchars($hist['nome_documento']) . "'><i class='bi bi-file-earmark-pdf me-2 text-danger'></i>" . htmlspecialchars($hist['nome_documento']) . "</span></td>";
                                echo "<td class='text-center fw-bold'>{$hist['paginas']}</td>";
                                echo "<td><span class='badge {$badge_class} shadow-sm px-3'>{$status_txt}</span></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='text-center text-muted py-5'><i class='bi bi-info-circle me-2'></i>Você ainda não realizou nenhuma impressão registrada.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>