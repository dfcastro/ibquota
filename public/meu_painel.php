<?php
/**
 * IBQUOTA 3 - PORTAL DO SERVIDOR (Tempo Real)
 * Com layout responsivo em Cards para as impressões
 */
include_once '../core/db.php';
include_once '../core/functions.php';

sec_session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario_logado = $_SESSION['usuario'];

// BUSCA DE DADOS DO USUÁRIO, POLÍTICA E SALDO
$query = "
    SELECT 
        u.cod_usuario,
        p.nome AS nome_politica,
        IFNULL(qu.quota, p.quota_padrao) AS limite_real,
        p.quota_infinita,
        (IFNULL(qu.quota, p.quota_padrao) - IFNULL((SELECT SUM(paginas) FROM impressoes WHERE usuario = u.usuario), 0)) AS saldo_atual
    FROM usuarios u
    LEFT JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario
    LEFT JOIN grupos g ON gu.cod_grupo = g.cod_grupo
    LEFT JOIN politica_grupo pg ON g.grupo = pg.grupo 
    LEFT JOIN politicas p ON pg.cod_politica = p.cod_politica
    LEFT JOIN quota_usuario qu ON (qu.usuario = u.usuario AND qu.cod_politica = p.cod_politica AND qu.grupo = g.grupo)
    WHERE u.usuario = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('s', $usuario_logado);
$stmt->execute();
$stmt->bind_result($cod_usuario, $nome_politica, $limite_padrao, $quota_infinita, $saldo_atual);
$stmt->fetch();
$stmt->close();

if (empty($nome_politica)) {
    $nome_politica = "Padrão (Pendente)";
    $limite_padrao = 50;
    $saldo_atual = 50;
    $quota_infinita = 0;
}

$percentual = 0;
$cor_barra = "bg-success";

if ($quota_infinita == 1) {
    $percentual = 100;
} elseif ($limite_padrao > 0) {
    $percentual = ($saldo_atual / $limite_padrao) * 100;
    if ($percentual < 0) $percentual = 0;
    if ($percentual > 100) $percentual = 100;

    if ($percentual < 20) $cor_barra = "bg-danger";
    elseif ($percentual < 50) $cor_barra = "bg-warning";
}
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
        
        /* Efeito hover nos nossos novos cartões de impressão */
        .impressao-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .impressao-card:hover {
            background-color: #f8f9fa;
            border-left-color: #32a041;
        }
        
        /* O texto ajusta-se melhor agora que não está esmagado numa tabela */
        .doc-nome-responsivo {
            max-width: 250px; 
        }
        @media (min-width: 768px) {
            .doc-nome-responsivo {
                max-width: 380px; 
            }
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-ifnmg shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="meu_painel.php"><i class="bi bi-printer-fill me-2"></i> Impressões IFNMG</a>
            <div class="d-flex text-white align-items-center">
                <span class="me-3 d-none d-md-inline"><i class="bi bi-person-circle me-1"></i> Olá, <b><?php echo htmlspecialchars($usuario_logado); ?></b></span>
                <a href="../core/auth/logout.php" class="btn btn-sm btn-outline-light px-3"><i class="bi bi-box-arrow-right me-1"></i> Sair</a>
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
                            <p class="text-muted small">Você não possui cota de impressão.</p>
                        <?php } elseif ($quota_infinita == 1) { ?>
                            <div class="display-3 text-primary mb-2"><i class="bi bi-infinity"></i></div>
                            <h4 class="fw-bold text-dark">Impressão Ilimitada</h4>
                            <p class="text-muted small">Política: <?php echo $nome_politica; ?></p>
                        <?php } else { ?>
                            <div class="display-1 fw-bold text-dark mb-0" id="saldo-tela"><?php echo (int)$saldo_atual; ?></div>
                            <p class="text-muted mb-3">páginas restantes de <?php echo $limite_padrao; ?></p>

                            <div class="progress mb-3" style="height: 25px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated <?php echo $cor_barra; ?>" role="progressbar" style="width: <?php echo $percentual; ?>%;">
                                    <span class="fw-bold fs-6"><?php echo round($percentual); ?>%</span>
                                </div>
                            </div>

                            <p class="small text-muted mb-4">Regra aplicada: <b><?php echo $nome_politica; ?></b></p>
                            <a href="solicitar_cota.php" class="btn btn-outline-success w-100 fw-bold shadow-sm" style="border-width: 2px;">
                                <i class="bi bi-plus-circle me-2"></i>Solicitar Páginas Extras
                            </a>
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
                        <p class="text-muted">Envie arquivos PDF diretamente para as impressoras do campus sem cabos.</p>
                        <a href="web_print.php" class="btn btn-primary shadow-sm fw-bold"><i class="bi bi-upload me-2"></i> Enviar Documento</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-5">
            <div class="card-header bg-white fw-bold p-3 border-bottom d-flex justify-content-between align-items-center">
                <span><i class="bi bi-activity text-primary me-2"></i> Últimas 10 Impressões</span>
                <div>
                    <span class="badge bg-light text-secondary border me-2" id="status-conexao"><i class="bi bi-broadcast"></i> Conectando...</span>
                    <a href="meu_historico.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i> Histórico Completo</a>
                </div>
            </div>
            
            <!-- AQUI ESTÁ A MÁGICA: Substituímos a Table por uma List-Group -->
            <div class="list-group list-group-flush" id="lista-impressoes-realtime">
                <div class="list-group-item text-center text-muted py-5 border-0">
                    <div class="spinner-border text-primary mb-2" role="status"></div><br>
                    Sincronizando com as impressoras...
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function buscarStatusTempoReal() {
            fetch('ajax_status.php')
                .then(response => response.json())
                .then(data => {
                    const lista = document.getElementById('lista-impressoes-realtime');
                    const conexao = document.getElementById('status-conexao');

                    conexao.className = "badge bg-success bg-opacity-10 text-success border border-success-subtle me-2";
                    conexao.innerHTML = '<i class="bi bi-broadcast"></i> Ao vivo';

                    if (data.erro) {
                        lista.innerHTML = `<div class="list-group-item text-danger text-center py-4 border-0"><i class="bi bi-x-circle me-1"></i> ${data.erro}</div>`;
                        return;
                    }

                    if (data.length === 0) {
                        lista.innerHTML = `<div class="list-group-item text-muted text-center py-5 border-0"><i class="bi bi-inbox display-6 d-block mb-2"></i>Nenhuma impressão recente encontrada.</div>`;
                        return;
                    }

                    let html = '';
                    data.forEach(item => {
                        // Construímos um Card Responsivo com Flexbox (d-flex)
                        html += `
                        <div class="list-group-item impressao-card py-3">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                                
                                <div class="mb-3 mb-md-0 w-100">
                                    <div class="text-muted small mb-1"><i class="bi bi-calendar-event me-1"></i>${item.data_imp} às ${item.hora_impressao}</div>
                                    <div class="fw-bold text-dark text-truncate doc-nome-responsivo" title="${item.nome_documento}">
                                        <i class="bi bi-file-earmark-pdf text-danger me-1"></i>${item.nome_documento}
                                    </div>
                                    <small class="text-muted"><i class="bi bi-printer me-1"></i>${item.impressora}</small>
                                </div>
                                
                                <div class="d-flex align-items-center justify-content-between w-100 w-md-auto mt-1 mt-md-0">
                                    <div class="me-4 text-center">
                                        <span class="d-block text-muted small" style="line-height: 1;">Páginas</span>
                                        <b class="fs-5">${item.paginas}</b>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge ${item.cor} shadow-sm px-3 py-2 fs-6"><i class="bi ${item.icone} me-1"></i>${item.status_texto}</span>
                                    </div>
                                </div>
                                
                            </div>
                        </div>`;
                    });

                    lista.innerHTML = html;

                    setTimeout(() => {
                        conexao.className = "badge bg-light text-secondary border me-2";
                        conexao.innerHTML = '<i class="bi bi-broadcast"></i> Aguardando...';
                    }, 2000);

                })
                .catch(error => {
                    console.error('Erro na sincronização:', error);
                    const conexao = document.getElementById('status-conexao');
                    conexao.className = "badge bg-danger bg-opacity-10 text-danger border border-danger-subtle me-2";
                    conexao.innerHTML = '<i class="bi bi-wifi-off"></i> Sem Conexão';
                });
        }

        buscarStatusTempoReal();
        setInterval(buscarStatusTempoReal, 3000);
    </script>
</body>
</html>