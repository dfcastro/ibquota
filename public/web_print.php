<?php

/**
 * IBQUOTA 3 - WEB PRINT (Impressão sem Fios via CUPS)
 * Com Pré-visualização, Filtro Inteligente, UI Responsiva e Seleção Agrupada de Impressoras
 * ATUALIZADO: Rotas Limpas, CSRF e Caminhos Blindados
 */

// 1. INCLUDES BLINDADOS (Corrigido o /../)
include_once __DIR__ . '/../core/db.php';
include_once __DIR__ . '/../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

// ==========================================
// DETEÇÃO INTELIGENTE DE AMBIENTE
// ==========================================
$host_atual = $_SERVER['HTTP_HOST'];
if ($host_atual === 'localhost' || $host_atual === '127.0.0.1') {
    $BASE_URL = '/gg'; // Ambiente Local
} else {
    $BASE_URL = ''; // Ambiente de Produção
}

// Garante que a sessão possui o Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 2. Validação de Sessão Segura (Redireciona para a Rota Limpa)
if (!isset($_SESSION['usuario'])) {
    header("Location: " . $BASE_URL . "/login");
    exit();
}

$usuario_logado = $_SESSION['usuario'];
$msg = "";
$tipo_msg = "";

// ======================================================================
// Dicionário de Locais das Impressoras Agrupado por Categorias
// ======================================================================
$locais_impressoras_agrupado = [
    'Administração e TI' => [
        'imp-adm-01'        => 'Prédio Administrativo',
        'imp-color'         => 'NGTI (Colorida - Requer Aprovação)'
    ],
    'Pedagógico I (Salas e Secretaria)' => [
        'imp-sec-01'        => 'Secretaria Pedagógico I',
        'imp-prof-p1-01'    => 'Sala Professores Ped. I (Imp 01)',
        'imp-prof-p1-02'    => 'Sala Professores Ped. I (Imp 02)',
        'imp-prof-p1-03'    => 'Sala Professores Ped. I (Imp 03)',
        'imp-nped-01'       => 'Núcleo Pedagógico',
        'imp-caec-01'       => 'CAEC',
        'imp-epe-01'       => 'Estágio/Pesquisa/Extensão'
    ],
    'Pedagógico II e Laboratórios' => [
        'imp-prof-p2-01'    => 'Sala Professores Pedagógico II',
        'imp-lab-solos-01'  => 'Prédio Lab Solos',

    ],
    'Apoio ao Aluno' => [
        'imp-biblioteca-01' => 'Biblioteca',
        'imp-napne'         => 'NAPNE'
    ]
];

// Cria uma lista simples (plana) em memória apenas para exibir as mensagens de sucesso mais tarde
$locais_impressoras_plano = [];
foreach ($locais_impressoras_agrupado as $categoria => $impressoras) {
    foreach ($impressoras as $id => $nome) {
        $locais_impressoras_plano[$id] = $nome;
    }
}

// 2. BUSCA AS IMPRESSORAS PERMITIDAS (DISTINCT PARA NÃO DUPLICAR)
$query_impressoras = "
    SELECT DISTINCT pi.impressora
    FROM usuarios u
    JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario
    JOIN grupos g ON gu.cod_grupo = g.cod_grupo
    JOIN politica_grupo pg ON g.grupo = pg.grupo
    JOIN politica_impressora pi ON pg.cod_politica = pi.cod_politica
    WHERE u.usuario = ?
    ORDER BY pi.impressora
";

$stmt_imp = $mysqli->prepare($query_impressoras);
$stmt_imp->bind_param('s', $usuario_logado);
$stmt_imp->execute();
$result_imp = $stmt_imp->get_result();
$impressoras_permitidas = [];

while ($row = $result_imp->fetch_assoc()) {
    $impressoras_permitidas[] = $row['impressora'];
}
$stmt_imp->close();

// 3. PROCESSA O ENVIO DO ARQUIVO
if (isset($_POST['acao']) && $_POST['acao'] == 'enviar_impressao') {

    // A FECHADURA DE SEGURANÇA: Valida o Token CSRF antes de processar o PDF
    $token_recebido = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!hash_equals($_SESSION['csrf_token'], $token_recebido)) {
        die("Tentativa inválida de submissão do formulário (Erro CSRF).");
    }

    $impressora_escolhida = trim($_POST['impressora']);
    $copias = (int)$_POST['copias'];
    if ($copias < 1) $copias = 1;

    // Captura as páginas e traduz intervalos (Ex: "1, 3-5" vira "1,3,4,5")
    $paginas_selecionadas_raw = trim($_POST['paginas_selecionadas']);
    $paginas_desejadas = [];

    if (!empty($paginas_selecionadas_raw)) {
        $partes = explode(',', $paginas_selecionadas_raw);
        foreach ($partes as $parte) {
            $parte = trim($parte);
            if (strpos($parte, '-') !== false) {
                list($inicio, $fim) = explode('-', $parte);
                for ($i = (int)$inicio; $i <= (int)$fim; $i++) {
                    $paginas_desejadas[] = (string)$i;
                }
            } elseif (!empty($parte)) {
                $paginas_desejadas[] = (string)$parte;
            }
        }
        $paginas_desejadas = array_unique($paginas_desejadas);
        sort($paginas_desejadas);
    }

    $qtd_paginas_selecionadas = count($paginas_desejadas);
    $paginas_selecionadas = implode(',', $paginas_desejadas);

    $lados = isset($_POST['lados']) ? $_POST['lados'] : 'one-sided';
    if (!in_array($lados, ['one-sided', 'two-sided-long-edge'])) {
        $lados = 'one-sided';
    }

    $orientacao = '';
    if (isset($_POST['orientacao']) && in_array($_POST['orientacao'], ['3', '4'])) {
        $orientacao = "-o orientation-requested=" . escapeshellarg($_POST['orientacao']);
    }

    $ajustar = isset($_POST['ajustar_pagina']) ? "-o fit-to-page" : "";

    // Validações
    if (!in_array($impressora_escolhida, $impressoras_permitidas)) {
        $msg = "Acesso negado a esta impressora.";
        $tipo_msg = "danger";
    } elseif (isset($_FILES['arquivo_pdf']) && $_FILES['arquivo_pdf']['error'] == UPLOAD_ERR_OK) {

        $nome_original = $_FILES['arquivo_pdf']['name'];
        $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
        $tamanho_mb = $_FILES['arquivo_pdf']['size'] / 1048576;
        $caminho_temporario = $_FILES['arquivo_pdf']['tmp_name'];

        if ($extensao != "pdf") {
            $msg = "Apenas arquivos PDF são permitidos.";
            $tipo_msg = "warning";
        } elseif ($tamanho_mb > 20) {
            $msg = "O arquivo é muito grande. O limite máximo é de 20MB.";
            $tipo_msg = "warning";
        } elseif ($qtd_paginas_selecionadas === 0 && !empty($_POST['paginas_selecionadas'])) {
            $msg = "Você digitou um formato inválido ou desmarcou todas as páginas. Selecione pelo menos uma.";
            $tipo_msg = "warning";
        } else {

            // ==========================================
            // DESVIO 1: IMPRESSÃO COLORIDA (RAIO-X + FILA/AUTO)
            // ==========================================
            if ($impressora_escolhida == 'imp-color') {

                $nome_limpo = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $nome_original);
                $novo_nome = "REQ_" . time() . "_" . $nome_limpo;
                $destino_final = __DIR__ . "/../uploads/coloridas/" . $novo_nome;

                if (!is_dir(__DIR__ . '/../uploads/coloridas/')) {
                    mkdir(__DIR__ . '/../uploads/coloridas/', 0777, true);
                }

                if (move_uploaded_file($caminho_temporario, $destino_final)) {

                    $cmd_gs = "gs -q -o - -sDEVICE=inkcov " . escapeshellarg($destino_final);
                    $output_gs = shell_exec($cmd_gs);
                    $linhas_gs = explode("\n", trim($output_gs));

                    $paginas_com_cor_real = [];
                    $pagina_atual = 1;

                    foreach ($linhas_gs as $linha) {
                        if (preg_match('/^\s*([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s+CMYK OK/', $linha, $matches)) {
                            if ((float)$matches[1] > 0 || (float)$matches[2] > 0 || (float)$matches[3] > 0) {
                                $paginas_com_cor_real[] = (string)$pagina_atual;
                            }
                            $pagina_atual++;
                        }
                    }

                    if (empty($paginas_desejadas)) {
                        for ($i = 1; $i < $pagina_atual; $i++) {
                            $paginas_desejadas[] = (string)$i;
                        }
                    }

                    $paginas_finais = array_intersect($paginas_desejadas, $paginas_com_cor_real);
                    sort($paginas_finais);

                    $qtd_final = count($paginas_finais);
                    $qtd_desejada = count($paginas_desejadas);

                    if ($qtd_final == 0) {
                        @unlink($destino_final);
                        $msg = "<b>Envio Bloqueado:</b> Nenhuma das páginas que você selecionou possui cores. Por favor, envie para uma impressora Preto e Branco.";
                        $tipo_msg = "danger";
                    } else {
                        $string_paginas_finais = implode(",", $paginas_finais);
                        $total_folhas_novas = $qtd_final * $copias;

                        $res_cfg = $mysqli->query("SELECT auto_aprovar_colorida FROM config_geral WHERE id = 1");
                        $auto_aprovar = $res_cfg->fetch_assoc()['auto_aprovar_colorida'] ?? 0;

                        $res_cota = $mysqli->query("SELECT SUM(paginas * copias) as total FROM pedidos_coloridos WHERE status = 'Aprovado' AND MONTH(data_pedido) = MONTH(CURRENT_DATE()) AND YEAR(data_pedido) = YEAR(CURRENT_DATE())");
                        $total_mes = $res_cota->fetch_assoc()['total'] ?? 0;
                        $consumo_previsto = $total_mes + $total_folhas_novas;

                        // Caminho relativo para a Base de Dados (public/uploads)
                        $caminho_bd = "uploads/coloridas/" . $novo_nome;

                        if ($auto_aprovar == 1 && $consumo_previsto <= 500) {

                            $stmt_ped = $mysqli->prepare("INSERT INTO pedidos_coloridos (usuario, arquivo_nome, arquivo_caminho, paginas, paginas_especificas, copias, impressora, status, aprovado_por) VALUES (?, ?, ?, ?, ?, ?, ?, 'Aprovado', 'Sistema Automático')");
                            $stmt_ped->bind_param('sssisis', $usuario_logado, $nome_original, $caminho_bd, $qtd_final, $string_paginas_finais, $copias, $impressora_escolhida);
                            $stmt_ped->execute();
                            $stmt_ped->close();

                            $comando_ranges = "-o page-ranges=" . escapeshellarg($string_paginas_finais);
                            $cmd_impressora = escapeshellarg($impressora_escolhida);
                            $cmd_usuario = escapeshellarg($usuario_logado);
                            $cmd_titulo = escapeshellarg("AutoColor-" . $nome_original);
                            $cmd_lados = escapeshellarg($lados);
                            $cmd_arquivo = escapeshellarg(realpath($destino_final));

                            $comando = "lp -d {$cmd_impressora} -n {$copias} -o sides={$cmd_lados} {$orientacao} {$ajustar} {$comando_ranges} -t {$cmd_titulo} -U {$cmd_usuario} {$cmd_arquivo} 2>&1";
                            shell_exec($comando);

                            @unlink($destino_final);

                            $msg = "<b>Aprovado Automaticamente!</b><br>As páginas coloridas ({$string_paginas_finais}) foram enviadas diretamente para a impressora. <br><small>(O Campus ainda possui cota colorida este mês).</small>";
                            $tipo_msg = "success";
                        } else {
                            $stmt_ped = $mysqli->prepare("INSERT INTO pedidos_coloridos (usuario, arquivo_nome, arquivo_caminho, paginas, paginas_especificas, copias, impressora) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt_ped->bind_param('sssisis', $usuario_logado, $nome_original, $caminho_bd, $qtd_final, $string_paginas_finais, $copias, $impressora_escolhida);
                            $stmt_ped->execute();
                            $stmt_ped->close();

                            if ($consumo_previsto > 500) {
                                $msg = "O Campus atingiu o limite mensal de 500 páginas coloridas. Seu documento foi para a fila da <b>Direção Geral</b>.";
                                $tipo_msg = "warning";
                            } elseif ($qtd_final == $qtd_desejada) {
                                $msg = "Seu documento colorido foi para a fila de aprovação do NTI. <br><small>Páginas solicitadas: <b>{$string_paginas_finais}</b></small>";
                                $tipo_msg = "success";
                            } else {
                                $msg = "<b>Economia Inteligente!</b> Selecionadas {$qtd_desejada} página(s), descartadas as P&B. <b>Apenas {$qtd_final} página(s)</b> ({$string_paginas_finais}) foram para a fila de aprovação do NTI!";
                                $tipo_msg = "success";
                            }
                        }
                    }
                } else {
                    $msg = "Erro ao salvar o arquivo.";
                    $tipo_msg = "danger";
                }

                // ==========================================
                // DESVIO 2: IMPRESSÃO MONOCROMÁTICA (DIRETO PARA CUPS)
                // ==========================================
            } else {
                $comando_ranges = !empty($paginas_selecionadas) ? "-o page-ranges=" . escapeshellarg($paginas_selecionadas) : "";

                $cmd_impressora = escapeshellarg($impressora_escolhida);
                $cmd_usuario = escapeshellarg($usuario_logado);
                $cmd_titulo = escapeshellarg("WebPrint-" . $nome_original);
                $cmd_lados = escapeshellarg($lados);
                $cmd_arquivo = escapeshellarg($caminho_temporario);

                $comando = "lp -d {$cmd_impressora} -n {$copias} -o sides={$cmd_lados} {$orientacao} {$ajustar} {$comando_ranges} -t {$cmd_titulo} -U {$cmd_usuario} {$cmd_arquivo} 2>&1";
                $saida_shell = shell_exec($comando);

                if (strpos(strtolower($saida_shell), 'request id is') !== false || strpos(strtolower($saida_shell), 'id da requisição') !== false) {
                    $nome_amigavel_sucesso = isset($locais_impressoras_plano[$impressora_escolhida]) ? $locais_impressoras_plano[$impressora_escolhida] : $impressora_escolhida;
                    $msg = "Arquivo enviado com sucesso para a impressora <b>{$nome_amigavel_sucesso}</b>!";
                    $tipo_msg = "success";
                } else {
                    $msg = "Erro ao processar no servidor CUPS: <br><small>" . htmlspecialchars($saida_shell) . "</small>";
                    $tipo_msg = "danger";
                }
            }
        }
    } else {
        $msg = "Erro no upload do arquivo. Tente novamente.";
        $tipo_msg = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Print - IFNMG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>

    <style>
        body {
            background-color: #f4f6f9;
        }

        .bg-ifnmg {
            background-color: #32a041;
            color: white;
        }

        .upload-area {
            border: 2px dashed #32a041;
            border-radius: 10px;
            padding: 30px;
            background-color: #eaf5eb;
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            background-color: #d1ebd3;
        }

        #pdf-preview-container {
            max-height: 400px;
            overflow-y: auto;
            background: #e9ecef;
            border-radius: 8px;
            padding: 15px;
        }

        .page-thumbnail {
            cursor: pointer;
            border: 3px solid transparent;
            border-radius: 5px;
            transition: transform 0.2s, border-color 0.2s;
            position: relative;
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .page-thumbnail:hover {
            transform: scale(1.05);
        }

        .page-thumbnail.selected {
            border-color: #32a041;
            box-shadow: 0 0 10px rgba(50, 160, 65, 0.5);
        }

        .page-thumbnail.selected::after {
            content: '\F26A';
            font-family: "bootstrap-icons";
            position: absolute;
            top: 5px;
            right: 5px;
            color: #32a041;
            font-size: 1.5rem;
            background: white;
            border-radius: 50%;
            line-height: 1;
        }

        .page-number {
            text-align: center;
            font-weight: bold;
            margin-top: 5px;
            color: #6c757d;
        }

        .doc-nome-responsivo {
            max-width: 120px;
        }

        @media (min-width: 768px) {
            .doc-nome-responsivo {
                max-width: 300px;
            }
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-ifnmg shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo $BASE_URL; ?>/meu-painel"><i class="bi bi-printer-fill me-2"></i> Impressões IFNMG</a>
            <div class="d-flex text-white align-items-center">
                <span class="me-3 d-none d-md-inline"><i class="bi bi-person-circle me-1"></i> Olá, <b><?php echo htmlspecialchars($usuario_logado); ?></b></span>
                <a href="<?php echo $BASE_URL; ?>/logout" class="btn btn-sm btn-outline-light px-3"><i class="bi bi-box-arrow-right me-1"></i> Sair</a>
            </div>
        </div>
    </nav>

    <div class="container" style="max-width: 900px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-cloud-arrow-up text-primary me-2"></i> Web Print</h3>
            <a href="<?php echo $BASE_URL; ?>/meu-painel" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-left me-1"></i> Voltar ao Painel</a>
        </div>

        <?php if ($msg != "") { ?>
            <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible shadow-sm">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php } ?>

        <div class="card shadow-sm border-0 mb-5">
            <div class="card-body p-4">

                <?php if (count($impressoras_permitidas) == 0) { ?>
                    <div class="text-center py-5">
                        <i class="bi bi-x-octagon text-danger display-1 mb-3"></i>
                        <h4 class="fw-bold">Nenhuma Impressora Liberada</h4>
                        <p class="text-muted">Seu usuário não possui permissão para utilizar nenhuma impressora no momento.</p>
                    </div>
                <?php } else { ?>

                    <form action="<?php echo $BASE_URL; ?>/web-print" method="post" enctype="multipart/form-data" id="printForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="acao" value="enviar_impressao">

                        <!-- ÁREA DE UPLOAD MELHORADA -->
                        <div class="mb-4 text-center" id="upload-wrapper">
                            <!-- Estado 1: Aguardando Ficheiro -->
                            <label for="arquivo_pdf" class="w-100 cursor-pointer" id="label-upload">
                                <div class="upload-area cursor-pointer">
                                    <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 3rem;"></i>
                                    <h5 class="fw-bold mt-2">Clique para selecionar o PDF</h5>
                                    <p class="text-muted small mb-0">Tamanho máximo: 20MB</p>
                                </div>
                            </label>
                            <input class="form-control d-none" type="file" id="arquivo_pdf" name="arquivo_pdf" accept=".pdf" required>

                            <!-- Estado 2: Ficheiro Selecionado -->
                            <div id="file-info-box" class="d-none upload-area bg-white border-success">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                                <h5 class="fw-bold mt-2 text-success doc-nome-responsivo mx-auto d-block text-truncate" id="nome-arquivo-selecionado">arquivo.pdf</h5>
                                <button type="button" class="btn btn-outline-danger mt-3 fw-bold shadow-sm" onclick="removerArquivo()">
                                    <i class="bi bi-trash me-1"></i> Remover ou Escolher Outro
                                </button>
                            </div>
                        </div>

                        <!-- SECÇÃO DE PRÉ-VISUALIZAÇÃO COM GRELHA RESPONSIVA -->
                        <div id="preview-section" class="d-none mb-4 p-3 bg-white border rounded shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-images text-primary me-2"></i> Selecione as Páginas</h5>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="selecionarTodas(true)">Todas</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selecionarTodas(false)">Nenhuma</button>
                                </div>
                            </div>

                            <!-- Grelha responsiva -->
                            <div id="pdf-preview-container" class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-3 mb-4">
                            </div>

                            <hr>

                            <label class="form-label fw-bold text-dark"><i class="bi bi-input-cursor-text me-1"></i> Digitar Páginas Específicas</label>
                            <input type="text" class="form-control border-success form-control-lg" name="paginas_selecionadas" id="paginas_selecionadas" placeholder="Ex: 1, 3, 5-10 (Deixe em branco para imprimir tudo)">
                            <div class="form-text text-muted mt-1"><i class="bi bi-info-circle me-1"></i>Pode clicar nas miniaturas acima ou digitar manualmente os intervalos. Ex: 1, 3, 5-10</div>
                        </div>

                        <!-- CONFIGURAÇÕES DE IMPRESSÃO (NOVO OPTGROUP) -->
                        <div class="row bg-light p-3 rounded mb-3 mx-1 shadow-sm">
                            <div class="col-md-9 mb-3 mb-md-0">
                                <label class="form-label fw-bold"><i class="bi bi-printer me-1"></i> Impressora Destino</label>
                                <select class="form-select border-success" name="impressora" required>
                                    <option value="" disabled selected>Escolha o local...</option>
                                    <?php
                                    foreach ($locais_impressoras_agrupado as $categoria => $impressoras) {
                                        $imprimir_categoria = false;
                                        $opcoes_html = "";

                                        foreach ($impressoras as $imp_id => $nome_amigavel) {
                                            if (in_array($imp_id, $impressoras_permitidas)) {
                                                $imprimir_categoria = true;
                                                $opcoes_html .= "<option value=\"" . htmlspecialchars($imp_id) . "\">" . htmlspecialchars($nome_amigavel) . " (" . htmlspecialchars($imp_id) . ")</option>";
                                            }
                                        }

                                        if ($imprimir_categoria) {
                                            echo "<optgroup label=\"" . htmlspecialchars($categoria) . "\">";
                                            echo $opcoes_html;
                                            echo "</optgroup>";
                                        }
                                    }

                                    foreach ($impressoras_permitidas as $imp_id) {
                                        if (!array_key_exists($imp_id, $locais_impressoras_plano)) {
                                            echo "<option value=\"" . htmlspecialchars($imp_id) . "\">Impressora Desconhecida (" . htmlspecialchars($imp_id) . ")</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold"><i class="bi bi-files me-1"></i> Cópias</label>
                                <input type="number" class="form-control border-success" name="copias" value="1" min="1" max="50" required>
                            </div>
                        </div>

                        <div class="row mx-1">
                            <div class="col-md-4 mb-3 ps-0">
                                <label class="form-label fw-bold small text-muted"><i class="bi bi-book me-1"></i> Formato</label>
                                <select class="form-select form-select-sm" name="lados">
                                    <option value="one-sided">Apenas Frente</option>
                                    <option value="two-sided-long-edge">Frente e Verso</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold small text-muted"><i class="bi bi-file-earmark-richtext me-1"></i> Orientação</label>
                                <select class="form-select form-select-sm" name="orientacao">
                                    <option value="">Automática</option>
                                    <option value="3">Retrato (Em pé)</option>
                                    <option value="4">Paisagem (Deitada)</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3 pe-0 mt-4">
                                <div class="form-check form-switch p-1 border rounded bg-white shadow-sm">
                                    <input class="form-check-input ms-1 mt-1" type="checkbox" name="ajustar_pagina" id="fit" value="1" checked>
                                    <label class="form-check-label fw-bold ms-2 small" for="fit">Ajustar à Página</label>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning small py-2 mt-2">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Documentos enviados para a <b>Colorida</b> serão analisados. Apenas as páginas que contém cores vão para aprovação.
                        </div>

                        <div class="card shadow-sm border-0 mb-3 d-none" id="card-tracking">
                            <div class="card-header bg-white fw-bold p-3 border-bottom text-primary">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                Acompanhando envio...
                            </div>
                            <div class="card-body p-3 bg-light">
                                <ul class="list-group list-group-flush" id="lista-tracking"></ul>
                            </div>
                        </div>

                        <button type="submit" id="btn-submit" class="btn btn-primary w-100 btn-lg fw-bold mt-3 shadow-sm" onclick="this.innerHTML='<i class=\'bi bi-hourglass-split\'></i> Analisando e Enviando...';">
                            <i class="bi bi-send-fill me-2"></i> Confirmar Impressão
                        </button>
                    </form>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../core/layout/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let selectedPages = new Set();

        // 1. CARREGAMENTO DO FICHEIRO PDF
        document.getElementById('arquivo_pdf').addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file || file.type !== 'application/pdf') return;

            document.getElementById('label-upload').classList.add('d-none');
            const fileInfoBox = document.getElementById('file-info-box');
            fileInfoBox.classList.remove('d-none');
            document.getElementById('nome-arquivo-selecionado').innerText = file.name;
            document.getElementById('nome-arquivo-selecionado').title = file.name;

            document.getElementById('preview-section').classList.remove('d-none');

            const container = document.getElementById('pdf-preview-container');
            container.innerHTML = '<div class="col-12 text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Gerando visualização...</p></div>';

            try {
                const arrayBuffer = await file.arrayBuffer();
                const pdf = await pdfjsLib.getDocument({
                    data: arrayBuffer
                }).promise;

                container.innerHTML = '';
                selectedPages.clear();

                const maxPagesToRender = Math.min(pdf.numPages, 50);

                for (let i = 1; i <= maxPagesToRender; i++) {
                    const page = await pdf.getPage(i);
                    const viewport = page.getViewport({
                        scale: 0.3
                    });

                    const col = document.createElement('div');
                    col.className = 'col';

                    const thumbnail = document.createElement('div');
                    thumbnail.className = 'page-thumbnail selected p-1';
                    thumbnail.dataset.page = i;
                    selectedPages.add(i);

                    const canvas = document.createElement('canvas');
                    canvas.className = 'img-fluid border';
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    const numLabel = document.createElement('div');
                    numLabel.className = 'page-number small';
                    numLabel.innerText = `Pág. ${i}`;

                    thumbnail.onclick = function() {
                        this.classList.toggle('selected');
                        if (this.classList.contains('selected')) {
                            selectedPages.add(i);
                        } else {
                            selectedPages.delete(i);
                        }
                        atualizarInputHidden();
                    };

                    thumbnail.appendChild(canvas);
                    thumbnail.appendChild(numLabel);
                    col.appendChild(thumbnail);
                    container.appendChild(col);

                    const renderContext = {
                        canvasContext: canvas.getContext('2d'),
                        viewport: viewport
                    };
                    await page.render(renderContext).promise;
                }

                if (pdf.numPages > 50) {
                    container.innerHTML += `<div class="col-12 text-center text-muted mt-3 small"><i class="bi bi-info-circle me-1"></i> A visualização foi limitada às primeiras 50 páginas por performance.</div>`;
                }

                atualizarInputHidden();

            } catch (err) {
                console.error("Erro ao ler o PDF: ", err);
                container.innerHTML = '<div class="col-12 text-danger text-center"><i class="bi bi-x-circle"></i> Erro ao gerar a pré-visualização. Mas você ainda pode digitar as páginas na caixa abaixo.</div>';
            }
        });

        // 2. FUNÇÃO PARA REMOVER O FICHEIRO
        function removerArquivo() {
            document.getElementById('arquivo_pdf').value = '';
            document.getElementById('label-upload').classList.remove('d-none');
            document.getElementById('file-info-box').classList.add('d-none');
            document.getElementById('preview-section').classList.add('d-none');
            document.getElementById('pdf-preview-container').innerHTML = '';
            selectedPages.clear();
            document.getElementById('paginas_selecionadas').value = '';
            document.getElementById('btn-submit').disabled = false;
        }

        // 3. FUNÇÕES DE SELEÇÃO
        function selecionarTodas(selecionar) {
            const thumbnails = document.querySelectorAll('.page-thumbnail');
            thumbnails.forEach(thumb => {
                const pageNum = parseInt(thumb.dataset.page);
                if (selecionar) {
                    thumb.classList.add('selected');
                    selectedPages.add(pageNum);
                } else {
                    thumb.classList.remove('selected');
                    selectedPages.delete(pageNum);
                }
            });
            atualizarInputHidden();
        }

        function atualizarInputHidden() {
            const arrayPaginas = Array.from(selectedPages).sort((a, b) => a - b);
            document.getElementById('paginas_selecionadas').value = arrayPaginas.join(', ');
            document.getElementById('btn-submit').disabled = (arrayPaginas.length === 0);
        }

        // 4. SINCRONIZA TEXTO ESCRITO COM AS MINIATURAS
        document.getElementById('paginas_selecionadas').addEventListener('input', function() {
            selectedPages.clear();
            const partes = this.value.split(',');

            partes.forEach(parte => {
                parte = parte.trim();
                if (parte.includes('-')) {
                    let [inicio, fim] = parte.split('-');
                    inicio = parseInt(inicio);
                    fim = parseInt(fim);
                    if (inicio && fim && inicio <= fim) {
                        for (let i = inicio; i <= fim; i++) selectedPages.add(i);
                    }
                } else {
                    const p = parseInt(parte);
                    if (p) selectedPages.add(p);
                }
            });

            document.querySelectorAll('.page-thumbnail').forEach(thumb => {
                const p = parseInt(thumb.dataset.page);
                if (selectedPages.has(p)) {
                    thumb.classList.add('selected');
                } else {
                    thumb.classList.remove('selected');
                }
            });

            document.getElementById('btn-submit').disabled = (this.value.trim() !== '' && selectedPages.size === 0);
        });

        // =====================================
        // RASTREADOR DE TEMPO REAL (AJAX CORRIGIDO)
        // =====================================
        const msgSuccess = document.querySelector('.alert-success');
        if (msgSuccess) {
            document.getElementById('card-tracking').classList.remove('d-none');

            function trackLatestJob() {
                // Rota corrigida para a URL Base dinâmica
                fetch('<?php echo $BASE_URL; ?>/public/ajax_status.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.erro || data.length === 0) return;

                        const latestJob = data[0];
                        const lista = document.getElementById('lista-tracking');

                        lista.innerHTML = `
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 bg-transparent border-0 px-0">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold text-dark text-truncate doc-nome-responsivo" title="${latestJob.nome_documento}">
                                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i> ${latestJob.nome_documento}
                                </div>
                                <small class="text-muted"><i class="bi bi-printer me-1"></i>${latestJob.impressora}</small>
                            </div>
                            <div class="text-end">
                                <span class="badge ${latestJob.cor} shadow-sm px-3 py-2 fs-6"><i class="bi ${latestJob.icone} me-1"></i>${latestJob.status_texto}</span>
                            </div>
                        </li>`;

                        if (latestJob.cod_status_impressao == 1 || latestJob.cor.includes('danger') || latestJob.cor.includes('warning')) {
                            clearInterval(trackingInterval);
                            document.querySelector('#card-tracking .card-header').innerHTML = '<i class="bi bi-check2-all me-2"></i>Status Finalizado';
                            document.querySelector('#card-tracking .card-header').classList.replace('text-primary', 'text-success');
                        }
                    });
            }
            trackLatestJob();
            const trackingInterval = setInterval(trackLatestJob, 2000);
        }
    </script>
</body>

</html>