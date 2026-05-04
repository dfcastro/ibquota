<?php

/**
 * IBQUOTA 3 - PORTAL DO SERVIDOR (VERSÃO FINAL INTEGRADA)
 */
include_once '../core/db.php';
include_once '../core/functions.php';

sec_session_start();

// 1. Validação de Sessão
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario_logado = $_SESSION['usuario'];


// 2. BUSCA DE DADOS DO USUÁRIO, POLÍTICA E SALDO (CORRIGIDO PARA LER COTAS EXTRAS)
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

        /* Efeito de transição suave nas impressões */
        #lista-impressoes-realtime tr {
            transition: background-color 0.5s ease;
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
                            <p class="text-muted small">Você não possui cota de impressão. Procure o NTI.</p>
                        <?php } elseif ($quota_infinita == 1) { ?>
                            <div class="display-3 text-primary mb-2"><i class="bi bi-infinity"></i></div>
                            <h4 class="fw-bold text-dark">Impressão Ilimitada</h4>
                            <p class="text-muted small">Política: <?php echo $nome_politica; ?></p>
                        <?php } else { ?>
                            <div class="display-1 fw-bold text-dark mb-0" id="saldo-tela"><?php echo (int)$saldo_atual; ?></div>
                            <p class="text-muted mb-3">páginas restantes de <?php echo $limite_padrao; ?></p>

                            <div class="progress mb-3" style="height: 25px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated <?php echo $cor_barra; ?>" role="progressbar" style="width: <?php echo $percentual; ?>%;" aria-valuenow="<?php echo $saldo_atual; ?>" aria-valuemin="0" aria-valuemax="<?php echo $limite_padrao; ?>">
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
                        <p class="text-muted">Envie arquivos PDF diretamente do seu celular ou notebook pessoal para as impressoras do campus sem precisar de cabos.</p>
                        <a href="web_print.php" class="btn btn-primary shadow-sm fw-bold"><i class="bi bi-upload me-2"></i> Enviar Documento</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-5">
            <div class="card-header bg-white fw-bold p-3 border-bottom d-flex justify-content-between align-items-center">
                <span><i class="bi bi-activity text-primary me-2"></i> Atividade em Tempo Real</span>
                <span class="badge bg-light text-secondary border" id="status-conexao"><i class="bi bi-broadcast"></i> Conectando...</span>
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
                    <tbody id="lista-impressoes-realtime">
                        <tr>
                            <td colspan="4" class="text-center text-muted py-5">
                                <div class="spinner-border text-primary mb-2" role="status"></div><br>
                                Sincronizando com as impressoras...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function buscarStatusTempoReal() {
            fetch('ajax_status.php')
                .then(response => {
                    if (!response.ok) throw new Error('Erro na rede');
                    return response.json();
                })
                .then(data => {
                    const lista = document.getElementById('lista-impressoes-realtime');
                    const conexao = document.getElementById('status-conexao');

                    // Pisca a bolinha de conexão (feedback)
                    conexao.className = "badge bg-success bg-opacity-10 text-success border border-success-subtle";
                    conexao.innerHTML = '<i class="bi bi-broadcast"></i> Ao vivo';

                    if (data.erro) {
                        lista.innerHTML = `<tr><td colspan="4" class="text-danger text-center py-4"><i class="bi bi-x-circle me-1"></i> ${data.erro}</td></tr>`;
                        return;
                    }

                    if (data.length === 0) {
                        lista.innerHTML = `<tr><td colspan="4" class="text-muted text-center py-5"><i class="bi bi-inbox display-6 d-block mb-2"></i>Nenhuma impressão recente encontrada.</td></tr>`;
                        return;
                    }

                    // Constrói a tabela HTML com os dados novos
                    let html = '';
                    data.forEach(item => {
                        html += `
                    <tr>
                        <td class='ps-4 text-muted small'>${item.data_imp} às ${item.hora_impressao}</td>
                        <td>
                            <span class='d-inline-block text-truncate' style='max-width: 350px;' title='${item.nome_documento}'>
                                <i class='bi bi-file-earmark-pdf me-2 text-danger'></i>${item.nome_documento}
                            </span>
                            <br><small class="text-muted"><i class="bi bi-printer me-1"></i>${item.impressora}</small>
                        </td>
                        <td class='text-center fw-bold'>${item.paginas}</td>
                        <td><span class='badge ${item.cor} shadow-sm px-3 py-2'><i class="bi ${item.icone} me-1"></i>${item.status_texto}</span></td>
                    </tr>`;
                    });

                    // Injeta o HTML novo na tela
                    lista.innerHTML = html;

                    // Volta o badge de conexão para cinza rapidinho para criar o "pulso"
                    setTimeout(() => {
                        conexao.className = "badge bg-light text-secondary border";
                        conexao.innerHTML = '<i class="bi bi-broadcast"></i> Aguardando...';
                    }, 2000);

                })
                .catch(error => {
                    console.error('Erro na sincronização:', error);
                    const conexao = document.getElementById('status-conexao');
                    conexao.className = "badge bg-danger bg-opacity-10 text-danger border border-danger-subtle";
                    conexao.innerHTML = '<i class="bi bi-wifi-off"></i> Sem Conexão';
                });
        }

        // Executa na hora que a tela abre
        buscarStatusTempoReal();

        // Repete a cada 3 segundos!
        setInterval(buscarStatusTempoReal, 3000);
    </script>

</body>

</html>