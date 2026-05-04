<?php

/**
 * IBQUOTA 3 - WEB PRINT (Impressão sem Fios via CUPS)
 * Com Pré-visualização, Filtro Inteligente de Cores e Auto-Aprovação
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
$msg = "";
$tipo_msg = "";

// Dicionário de Locais das Impressoras
$locais_impressoras = [
    'imp-adm-01'        => 'Prédio Administrativo',
    'imp-color'         => 'NGTI (Colorida - Requer Aprovação)',
    'imp-sec-01'        => 'Secretaria Pedagógico I',
    'imp-prof-p1-01'    => 'Sala Professores Pedagógico I',
    'imp-prof-p1-02'    => 'Sala Professores Pedagógico I',
    'imp-prof-p1-03'    => 'Sala Professores Pedagógico I',
    'imp-caec-01'       => 'CAEC',
    'imp-nped-01'       => 'Núcleo Pedagógico',
    'imp-biblioteca-01' => 'Biblioteca',
    'imp-prof-p2-01'    => 'Sala Professores Pedagógico II',
    'imp-lab-solos-01'  => 'Prédio Lab Solos',
    'imp-napne'         => 'NAPNE',
    'imp-epe-01'        => 'Estágio/Pesquisa/Extensão'
];

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

    $impressora_escolhida = trim($_POST['impressora']);
    $copias = (int)$_POST['copias'];
    if ($copias < 1) $copias = 1;

    // Captura as páginas selecionadas no front-end (Ex: "1,2,5")
    $paginas_selecionadas = trim($_POST['paginas_selecionadas']);
    $qtd_paginas_selecionadas = !empty($paginas_selecionadas) ? count(explode(',', $paginas_selecionadas)) : 0;

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
            $msg = "Você desmarcou todas as páginas. Selecione pelo menos uma página para imprimir.";
            $tipo_msg = "warning";
        } else {

            // ==========================================
            // DESVIO 1: IMPRESSÃO COLORIDA (RAIO-X + FILA/AUTO)
            // ==========================================
            if ($impressora_escolhida == 'imp-color') {

                $nome_limpo = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $nome_original);
                $novo_nome = "REQ_" . time() . "_" . $nome_limpo;
                $destino_final = "uploads/coloridas/" . $novo_nome;

                if (!is_dir('uploads/coloridas/')) {
                    mkdir('uploads/coloridas/', 0777, true);
                }

                if (move_uploaded_file($caminho_temporario, $destino_final)) {

                    // 1. Mapeia quais páginas o usuário escolheu clicar
                    $paginas_desejadas = !empty($paginas_selecionadas) ? explode(',', $paginas_selecionadas) : [];

                    // 2. FAZ O RAIO-X DAS CORES COM GHOSTSCRIPT
                    $cmd_gs = "gs -q -o - -sDEVICE=inkcov " . escapeshellarg($destino_final);
                    $output_gs = shell_exec($cmd_gs);
                    $linhas_gs = explode("\n", trim($output_gs));

                    $paginas_com_cor_real = [];
                    $pagina_atual = 1;

                    foreach ($linhas_gs as $linha) {
                        if (preg_match('/^\s*([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s+CMYK OK/', $linha, $matches)) {
                            // Se Ciano, Magenta ou Amarelo forem > 0, tem cor!
                            if ((float)$matches[1] > 0 || (float)$matches[2] > 0 || (float)$matches[3] > 0) {
                                $paginas_com_cor_real[] = (string)$pagina_atual;
                            }
                            $pagina_atual++;
                        }
                    }

                    // Se ele mandou tudo (não selecionou páginas específicas), criamos um array com todas as páginas do doc
                    if (empty($paginas_desejadas)) {
                        for ($i = 1; $i < $pagina_atual; $i++) {
                            $paginas_desejadas[] = (string)$i;
                        }
                    }

                    // 3. A MÁGICA: Cruza o que o usuário quer com o que realmente tem cor
                    $paginas_finais = array_intersect($paginas_desejadas, $paginas_com_cor_real);
                    sort($paginas_finais); // Ordena de forma crescente

                    $qtd_final = count($paginas_finais);
                    $qtd_desejada = count($paginas_desejadas);

                    if ($qtd_final == 0) {
                        // Nenhuma das páginas selecionadas tinha cor
                        @unlink($destino_final);
                        $msg = "<b>Envio Bloqueado:</b> Nenhuma das páginas que você selecionou possui cores. Por favor, envie para uma impressora Preto e Branco.";
                        $tipo_msg = "danger";
                    } else {
                        // ===============================================
                        // NOVA LÓGICA: VERIFICA SE TEM AUTO-APROVAÇÃO NTI
                        // ===============================================
                        $string_paginas_finais = implode(",", $paginas_finais);
                        $total_folhas_novas = $qtd_final * $copias;

                        // 1. Busca a configuração do botão
                        $res_cfg = $mysqli->query("SELECT auto_aprovar_colorida FROM config_geral WHERE id = 1");
                        $auto_aprovar = $res_cfg->fetch_assoc()['auto_aprovar_colorida'] ?? 0;

                        // 2. Busca o saldo mensal atual do campus
                        $res_cota = $mysqli->query("SELECT SUM(paginas * copias) as total FROM pedidos_coloridos WHERE status = 'Aprovado' AND MONTH(data_pedido) = MONTH(CURRENT_DATE()) AND YEAR(data_pedido) = YEAR(CURRENT_DATE())");
                        $total_mes = $res_cota->fetch_assoc()['total'] ?? 0;
                        $consumo_previsto = $total_mes + $total_folhas_novas;

                        // DECISÃO FINAL: Vai pra fila ou Imprime na hora?
                        if ($auto_aprovar == 1 && $consumo_previsto <= 500) {

                            // LIGAÇÃO DIRETA: Grava como Aprovado e imprime
                            $stmt_ped = $mysqli->prepare("INSERT INTO pedidos_coloridos (usuario, arquivo_nome, arquivo_caminho, paginas, paginas_especificas, copias, impressora, status, aprovado_por) VALUES (?, ?, ?, ?, ?, ?, ?, 'Aprovado', 'Sistema Automático')");
                            $stmt_ped->bind_param('sssisis', $usuario_logado, $nome_original, $destino_final, $qtd_final, $string_paginas_finais, $copias, $impressora_escolhida);
                            $stmt_ped->execute();
                            $stmt_ped->close();

                            // Dispara para a CUPS (Aplica as páginas filtradas do Ghostscript!)
                            $comando_ranges = "-o page-ranges=" . escapeshellarg($string_paginas_finais);
                            $cmd_impressora = escapeshellarg($impressora_escolhida);
                            $cmd_usuario = escapeshellarg($usuario_logado);
                            $cmd_titulo = escapeshellarg("AutoColor-" . $nome_original);
                            $cmd_lados = escapeshellarg($lados);
                            $cmd_arquivo = escapeshellarg(realpath($destino_final));

                            $comando = "lp -d {$cmd_impressora} -n {$copias} -o sides={$cmd_lados} {$orientacao} {$ajustar} {$comando_ranges} -t {$cmd_titulo} -U {$cmd_usuario} {$cmd_arquivo} 2>&1";
                            $saida_shell = shell_exec($comando);

                            @unlink($destino_final); // Limpa o arquivo PDF do disco após enviar ao CUPS

                            $msg = "<b>Aprovado Automaticamente!</b><br>As páginas coloridas ({$string_paginas_finais}) foram enviadas diretamente para a impressora. <br><small>(O Campus ainda possui cota colorida este mês).</small>";
                            $tipo_msg = "success";
                        } else {
                            // FILA TRADICIONAL: Fica pendente aguardando NTI ou Diretor
                            $stmt_ped = $mysqli->prepare("INSERT INTO pedidos_coloridos (usuario, arquivo_nome, arquivo_caminho, paginas, paginas_especificas, copias, impressora) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt_ped->bind_param('sssisis', $usuario_logado, $nome_original, $destino_final, $qtd_final, $string_paginas_finais, $copias, $impressora_escolhida);
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

                // Se mandou páginas específicas, passa o parâmetro. Senão, fica em branco (imprime tudo).
                $comando_ranges = !empty($paginas_selecionadas) ? "-o page-ranges=" . escapeshellarg($paginas_selecionadas) : "";

                $cmd_impressora = escapeshellarg($impressora_escolhida);
                $cmd_usuario = escapeshellarg($usuario_logado);
                $cmd_titulo = escapeshellarg("WebPrint-" . $nome_original);
                $cmd_lados = escapeshellarg($lados);
                $cmd_arquivo = escapeshellarg($caminho_temporario);

                $comando = "lp -d {$cmd_impressora} -n {$copias} -o sides={$cmd_lados} {$orientacao} {$ajustar} {$comando_ranges} -t {$cmd_titulo} -U {$cmd_usuario} {$cmd_arquivo} 2>&1";
                $saida_shell = shell_exec($comando);

                if (strpos(strtolower($saida_shell), 'request id is') !== false || strpos(strtolower($saida_shell), 'id da requisição') !== false) {
                    $msg = "Arquivo enviado com sucesso para a impressora <b>{$locais_impressoras[$impressora_escolhida]}</b>!";
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

        /* Estilos do Preview de Páginas */
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

        /* Estado "Selecionado" */
        .page-thumbnail.selected {
            border-color: #32a041;
            box-shadow: 0 0 10px rgba(50, 160, 65, 0.5);
        }

        .page-thumbnail.selected::after {
            content: '\F26A';
            /* Ícone do Bootstrap (bi-check-circle-fill) */
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

    <div class="container" style="max-width: 900px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-cloud-arrow-up text-primary me-2"></i> Web Print</h3>
            <a href="meu_painel.php" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-left me-1"></i> Voltar ao Painel</a>
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

                    <form action="web_print.php" method="post" enctype="multipart/form-data" id="printForm">
                        <input type="hidden" name="acao" value="enviar_impressao">

                        <input type="hidden" name="paginas_selecionadas" id="paginas_selecionadas" value="">

                        <div class="mb-4 text-center">
                            <label for="arquivo_pdf" class="w-100 cursor-pointer">
                                <div class="upload-area cursor-pointer" id="upload-box">
                                    <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 3rem;"></i>
                                    <h5 class="fw-bold mt-2">Clique para selecionar o PDF</h5>
                                    <p class="text-muted small mb-0">Tamanho máximo: 20MB</p>
                                </div>
                            </label>
                            <input class="form-control d-none" type="file" id="arquivo_pdf" name="arquivo_pdf" accept=".pdf" required>
                        </div>

                        <div id="preview-section" class="d-none mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-images text-primary me-2"></i> Selecione as Páginas</h5>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="selecionarTodas(true)">Todas</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selecionarTodas(false)">Nenhuma</button>
                                </div>
                            </div>
                            <p class="text-muted small">Clique nas páginas que deseja imprimir. O padrão é imprimir o documento inteiro.</p>

                            <div id="pdf-preview-container" class="row row-cols-3 row-cols-md-4 row-cols-lg-5 g-3">
                            </div>
                        </div>

                        <div class="row bg-light p-3 rounded mb-3 mx-1 shadow-sm">
                            <div class="col-md-9 mb-3 mb-md-0">
                                <label class="form-label fw-bold"><i class="bi bi-printer me-1"></i> Impressora Destino</label>
                                <select class="form-select border-success" name="impressora" required>
                                    <option value="" disabled selected>Escolha o local...</option>
                                    <?php foreach ($impressoras_permitidas as $imp) {
                                        $nome_amigavel = isset($locais_impressoras[$imp]) ? $locais_impressoras[$imp] : 'Local não especificado';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($imp); ?>">
                                            <?php echo htmlspecialchars($nome_amigavel) . " (" . htmlspecialchars($imp) . ")"; ?>
                                        </option>
                                    <?php } ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let selectedPages = new Set();

        document.getElementById('arquivo_pdf').addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file || file.type !== 'application/pdf') return;

            // Muda o visual do box de upload
            const uploadBox = document.getElementById('upload-box');
            uploadBox.innerHTML = `<i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i><h5 class="fw-bold mt-2 text-success">${file.name}</h5>`;

            // Mostra o container de preview
            document.getElementById('preview-section').classList.remove('d-none');
            const container = document.getElementById('pdf-preview-container');
            container.innerHTML = '<div class="col-12 text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Gerando visualização...</p></div>';

            try {
                const arrayBuffer = await file.arrayBuffer();
                const pdf = await pdfjsLib.getDocument({
                    data: arrayBuffer
                }).promise;

                container.innerHTML = ''; // Limpa o loading
                selectedPages.clear(); // Reseta seleções anteriores

                // Limita a visualização a 50 páginas para não travar o navegador
                const maxPagesToRender = Math.min(pdf.numPages, 50);

                for (let i = 1; i <= maxPagesToRender; i++) {
                    const page = await pdf.getPage(i);
                    const viewport = page.getViewport({
                        scale: 0.3
                    }); // Miniatura

                    // Cria os elementos HTML
                    const col = document.createElement('div');
                    col.className = 'col';

                    const thumbnail = document.createElement('div');
                    thumbnail.className = 'page-thumbnail selected p-1';
                    thumbnail.dataset.page = i;
                    selectedPages.add(i); // Adiciona no Set (Marcadas por padrão)

                    const canvas = document.createElement('canvas');
                    canvas.className = 'img-fluid border';
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    const numLabel = document.createElement('div');
                    numLabel.className = 'page-number small';
                    numLabel.innerText = `Pág. ${i}`;

                    // Função de clique na miniatura
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

                    // Renderiza o PDF no canvas
                    const renderContext = {
                        canvasContext: canvas.getContext('2d'),
                        viewport: viewport
                    };
                    await page.render(renderContext).promise;
                }

                if (pdf.numPages > 50) {
                    container.innerHTML += `<div class="col-12 text-center text-muted mt-3 small"><i class="bi bi-info-circle me-1"></i> A visualização foi limitada às primeiras 50 páginas por performance, mas o documento completo será impresso se as páginas permanecerem selecionadas.</div>`;
                }

                atualizarInputHidden();

            } catch (err) {
                console.error("Erro ao ler o PDF: ", err);
                container.innerHTML = '<div class="col-12 text-danger text-center"><i class="bi bi-x-circle"></i> Erro ao gerar a pré-visualização. Mas você ainda pode enviar o arquivo inteiro.</div>';
            }
        });

        // Funções Auxiliares de Seleção
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
            // Converte o Set para Array, ordena e junta com vírgulas
            const arrayPaginas = Array.from(selectedPages).sort((a, b) => a - b);
            document.getElementById('paginas_selecionadas').value = arrayPaginas.join(',');

            // Trava o botão de envio se desmarcar todas
            document.getElementById('btn-submit').disabled = (arrayPaginas.length === 0);
        }

        // =====================================
        // RASTREADOR DE TEMPO REAL (O "Mini")
        // =====================================
        const msgSuccess = document.querySelector('.alert-success');
        if (msgSuccess) {
            document.getElementById('card-tracking').classList.remove('d-none');

            function trackLatestJob() {
                fetch('ajax_status.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.erro || data.length === 0) return;

                        const latestJob = data[0];
                        const lista = document.getElementById('lista-tracking');

                        lista.innerHTML = `
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 bg-transparent border-0 px-0">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold text-dark text-truncate" style="max-width: 300px;" title="${latestJob.nome_documento}">
                                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i> ${latestJob.nome_documento}
                                </div>
                                <small class="text-muted"><i class="bi bi-printer me-1"></i>${latestJob.impressora}</small>
                            </div>
                            <div class="text-end">
                                <span class="badge ${latestJob.cor} shadow-sm px-3 py-2 fs-6"><i class="bi ${latestJob.icone} me-1"></i>${latestJob.status_texto}</span>
                            </div>
                        </li>`;

                        // Para o rastreio se for SUCESSO(1) ou se tiver DANGER/WARNING (Erro)
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