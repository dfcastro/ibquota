<?php

/**
 * IFQUOTA - Gestão de Solicitações de Cota Extra
 * Permite ao NTI/Direção aprovar ou negar páginas e consultar o histórico com paginação.
 */

include_once __DIR__ . '/../core/db.php';
include_once __DIR__ . '/../core/functions.php';

// 🕵️‍♂️ ARMADILHA PARA PEGAR O ERRO 500
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (session_status() === PHP_SESSION_NONE) {
        sec_session_start();
    }

    // ==========================================
    // DETEÇÃO INTELIGENTE DE AMBIENTE
    // ==========================================
    $host_atual = $_SERVER['HTTP_HOST'] ?? '';
    $BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

    // Permite NTI (2) e Diretor (3)
    if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || ($_SESSION['permissao'] != 2 && $_SESSION['permissao'] != 3)) {
        header("Location: " . $BASE_URL . "/meu-painel");
        exit();
    }

    $admin_logado = $_SESSION['usuario'];
    $msg = "";
    $tipo_msg = "";

    // ==========================================
    // PROCESSAMENTO DAS AÇÕES (APROVAR / NEGAR)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {

        $token_recebido = $_POST['csrf_token'] ?? '';
        validar_csrf_token($token_recebido);

        $id_solicitacao = (int)$_POST['id_solicitacao'];
        $acao = $_POST['acao'];

        $stmt_busca = $mysqli->prepare("SELECT usuario, paginas, motivo FROM solicitacoes_cota WHERE id = ? AND status = 'Pendente'");
        $stmt_busca->bind_param('i', $id_solicitacao);
        $stmt_busca->execute();
        $res = $stmt_busca->get_result();

        if ($row = $res->fetch_assoc()) {
            $usuario = $row['usuario'];
            $paginas = $row['paginas'];
            $motivo_full = "Aprovado via Portal IFQUOTA: " . $row['motivo'];

            // ---------------------------------------------------------
            // BUSCA O E-MAIL DO USUÁRIO PARA NOTIFICAR
            // ---------------------------------------------------------
            $email_alvo = $usuario . "@ifnmg.edu.br"; // Fallback padrão
            $stmt_mail = $mysqli->prepare("SELECT email FROM usuarios WHERE usuario = ?");
            $stmt_mail->bind_param('s', $usuario);
            $stmt_mail->execute();
            $stmt_mail->bind_result($db_email);
            if ($stmt_mail->fetch() && !empty($db_email)) {
                $email_alvo = $db_email;
            }
            $stmt_mail->close();

            $link_painel = "https://ifquota.almenara.ifnmg.edu.br/meu-painel";

            if ($acao == 'aprovar') {
                $query_pol = "SELECT pg.cod_politica, g.grupo, p.quota_padrao 
                              FROM politica_grupo pg 
                              JOIN grupos g ON pg.grupo = g.grupo 
                              JOIN grupo_usuario gu ON g.cod_grupo = gu.cod_grupo 
                              JOIN usuarios u ON u.cod_usuario = gu.cod_usuario 
                              JOIN politicas p ON p.cod_politica = pg.cod_politica
                              WHERE u.usuario = ? LIMIT 1";

                $stmt_pol = $mysqli->prepare($query_pol);
                $stmt_pol->bind_param('s', $usuario);
                $stmt_pol->execute();
                $res_pol = $stmt_pol->get_result();

                if ($pol_data = $res_pol->fetch_assoc()) {
                    $cod_politica = $pol_data['cod_politica'];
                    $grupo = $pol_data['grupo'];
                    $cota_padrao = $pol_data['quota_padrao'];

                    $stmt_add = $mysqli->prepare("INSERT INTO quota_adicional (cod_politica, grupo, usuario, quota_adicional, motivo, datahora, useradmin) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
                    $stmt_add->bind_param('ississ', $cod_politica, $grupo, $usuario, $paginas, $motivo_full, $admin_logado);
                    $stmt_add->execute();

                    $chk_q = $mysqli->prepare("SELECT quota FROM quota_usuario WHERE cod_politica = ? AND usuario = ? AND grupo = ?");
                    $chk_q->bind_param('iss', $cod_politica, $usuario, $grupo);
                    $chk_q->execute();
                    $res_q = $chk_q->get_result();

                    if ($res_q->num_rows > 0) {
                        $stmt_upd = $mysqli->prepare("UPDATE quota_usuario SET quota = quota + ? WHERE cod_politica = ? AND usuario = ? AND grupo = ?");
                        $stmt_upd->bind_param('iiss', $paginas, $cod_politica, $usuario, $grupo);
                        $stmt_upd->execute();
                    } else {
                        $nova_cota = $cota_padrao + $paginas;
                        $stmt_ins = $mysqli->prepare("INSERT INTO quota_usuario (cod_politica, grupo, usuario, quota) VALUES (?, ?, ?, ?)");
                        $stmt_ins->bind_param('issi', $cod_politica, $grupo, $usuario, $nova_cota);
                        $stmt_ins->execute();
                    }

                    $stmt_final = $mysqli->prepare("UPDATE solicitacoes_cota SET status = 'Aprovado', data_resposta = NOW(), respondido_por = ? WHERE id = ?");
                    $stmt_final->bind_param('si', $admin_logado, $id_solicitacao);
                    $stmt_final->execute();

                    // ENVIA E-MAIL DE APROVAÇÃO (Design Premium)
                    $html_email = "
                    <!DOCTYPE html>
                    <html>
                    <body style='font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px;'>
                        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid #e0e0e0;'>
                            <div style='background-color: #ffffff; padding: 20px; text-align: center; border-bottom: 3px solid #27ae60;'>
                                <img src='cid:logo_campus' alt='IFNMG' style='height: 80px; max-width: 100%; display: block; margin: 0 auto;'>
                            </div>
                            <div style='padding: 30px;'>
                                <h2 style='color: #2c3e50; margin-top: 0; font-size: 20px;'>Atualização do seu Pedido</h2>
                                <p style='color: #555555; font-size: 16px; line-height: 1.6;'>Olá, <strong>{$usuario}</strong>. O seu pedido de cota extra de impressão foi avaliado.</p>
                                
                                <div style='background-color: #eafaf1; border-left: 5px solid #27ae60; padding: 18px; margin: 25px 0; border-radius: 0 4px 4px 0;'>
                                    <h3 style='margin-top: 0; font-size: 16px; color: #27ae60;'>✅ PEDIDO APROVADO</h3>
                                    <p style='margin: 0 0 10px 0; color: #2c3e50; font-size: 14px;'><b>Páginas Adicionadas:</b> <span style='color: #555;'>+{$paginas} páginas</span></p>
                                    <p style='margin: 0; color: #2c3e50; font-size: 14px;'>O seu saldo já foi atualizado e as suas impressões encontram-se libertadas no sistema.</p>
                                </div>
                                
                                <div style='text-align: center; margin-top: 35px; margin-bottom: 10px;'>
                                    <a href='{$link_painel}' style='background-color: #27ae60; color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 5px; font-weight: bold; font-size: 16px; display: inline-block;'>Acessar ao Meu Painel</a>
                                </div>
                            </div>
                            <div style='background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #eeeeee;'>
                                <p style='margin: 0; color: #95a5a6; font-size: 12px; line-height: 1.5;'>Este é um e-mail automático do sistema IFQUOTA.<br>Por favor, não responda diretamente a esta mensagem.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    enviar_email_sistema($email_alvo, "✅ Cota de Impressão Aprovada", $html_email);

                    $msg = "Cota de {$paginas} páginas injetada para {$usuario} e e-mail enviado com sucesso!";
                    $tipo_msg = "success";
                } else {
                    $msg = "Erro: O servidor {$usuario} não possui um grupo ou política de impressão vinculada ativa.";
                    $tipo_msg = "danger";
                }
            } elseif ($acao == 'negar') {
                $stmt_neg = $mysqli->prepare("UPDATE solicitacoes_cota SET status = 'Negado', data_resposta = NOW(), respondido_por = ? WHERE id = ?");
                $stmt_neg->bind_param('si', $admin_logado, $id_solicitacao);
                $stmt_neg->execute();

                // ENVIA E-MAIL DE RECUSA (Design Premium)
                $html_email = "
                <!DOCTYPE html>
                <html>
                <body style='font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px;'>
                    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid #e0e0e0;'>
                        <div style='background-color: #ffffff; padding: 20px; text-align: center; border-bottom: 3px solid #e74c3c;'>
                            <img src='cid:logo_campus' alt='IFNMG' style='height: 80px; max-width: 100%; display: block; margin: 0 auto;'>
                        </div>
                        <div style='padding: 30px;'>
                            <h2 style='color: #2c3e50; margin-top: 0; font-size: 20px;'>Atualização do seu Pedido</h2>
                            <p style='color: #555555; font-size: 16px; line-height: 1.6;'>Olá, <strong>{$usuario}</strong>. O seu pedido de cota extra de impressão foi avaliado.</p>
                            
                            <div style='background-color: #fdf3f2; border-left: 5px solid #e74c3c; padding: 18px; margin: 25px 0; border-radius: 0 4px 4px 0;'>
                                <h3 style='margin-top: 0; font-size: 16px; color: #e74c3c;'>❌ PEDIDO RECUSADO</h3>
                                <p style='margin: 0 0 10px 0; color: #2c3e50; font-size: 14px;'><b>Páginas Solicitadas:</b> <span style='color: #555;'>{$paginas} páginas</span></p>
                                <p style='margin: 0; color: #2c3e50; font-size: 14px;'>Infelizmente, o seu pedido não pôde ser aprovado neste momento pela equipa do NTI.</p>
                            </div>
                            
                            <p style='color: #555555; font-size: 14px; line-height: 1.6;'>Para obter mais informações ou detalhar a sua necessidade, por favor, entre em contacto com o setor responsável.</p>
                            
                            <div style='text-align: center; margin-top: 35px; margin-bottom: 10px;'>
                                <a href='{$link_painel}' style='background-color: #7f8c8d; color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 5px; font-weight: bold; font-size: 16px; display: inline-block;'>Acessar ao Meu Painel</a>
                            </div>
                        </div>
                        <div style='background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #eeeeee;'>
                            <p style='margin: 0; color: #95a5a6; font-size: 12px; line-height: 1.5;'>Este é um e-mail automático do sistema IFQUOTA.<br>Por favor, não responda diretamente a esta mensagem.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                enviar_email_sistema($email_alvo, "❌ Pedido de Cota Não Aprovado", $html_email);

                $msg = "Solicitação de {$usuario} negada e e-mail de aviso disparado.";
                $tipo_msg = "warning";
            }
        }
    }

    // ==========================================
    // LÓGICA DE BUSCA E PAGINAÇÃO DO HISTÓRICO
    // ==========================================
    if (!defined('QTDE_POR_PAGINA')) define('QTDE_POR_PAGINA', 20);

    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $p = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
    $qtde_por_pagina = (int)QTDE_POR_PAGINA;
    $p_inicio = ($p - 1) * $qtde_por_pagina;
    $p_num_registros = 0;

    if ($busca !== '') {
        $busca_sql = "%{$busca}%";
        $stmt_count = $mysqli->prepare("SELECT COUNT(*) FROM solicitacoes_cota WHERE status != 'Pendente' AND (usuario LIKE ? OR respondido_por LIKE ? OR status LIKE ?)");
        $stmt_count->bind_param('sss', $busca_sql, $busca_sql, $busca_sql);
    } else {
        $stmt_count = $mysqli->prepare("SELECT COUNT(*) FROM solicitacoes_cota WHERE status != 'Pendente'");
    }
    $stmt_count->execute();
    $stmt_count->bind_result($p_num_registros);
    $stmt_count->fetch();
    $stmt_count->close();

    if ($busca !== '') {
        $stmt_hist = $mysqli->prepare("SELECT *, DATE_FORMAT(data_resposta, '%d/%m/%Y %H:%i') as data_res FROM solicitacoes_cota WHERE status != 'Pendente' AND (usuario LIKE ? OR respondido_por LIKE ? OR status LIKE ?) ORDER BY data_resposta DESC LIMIT ?, ?");
        $stmt_hist->bind_param('sssii', $busca_sql, $busca_sql, $busca_sql, $p_inicio, $qtde_por_pagina);
    } else {
        $stmt_hist = $mysqli->prepare("SELECT *, DATE_FORMAT(data_resposta, '%d/%m/%Y %H:%i') as data_res FROM solicitacoes_cota WHERE status != 'Pendente' ORDER BY data_resposta DESC LIMIT ?, ?");
        $stmt_hist->bind_param('ii', $p_inicio, $qtde_por_pagina);
    }
    $stmt_hist->execute();
    $historico = $stmt_hist->get_result();
    $stmt_hist->close();
} catch (Throwable $e) {
    // A ARMADILHA - Exibe o erro claramente se algo falhar no banco!
    die("
        <div style='font-family: sans-serif; padding: 20px; background: #fee; border: 1px solid #f00; border-radius: 5px; margin: 40px auto; max-width: 800px;'>
            <h3 style='color: red;'>Erro 500 Interceptado (Aprovação de Cota)!</h3>
            <p>Detalhes do Erro no Banco de Dados:</p>
            <p><b>" . $e->getMessage() . "</b></p>
            <p><small>Arquivo: " . basename($e->getFile()) . " | Linha: " . $e->getLine() . "</small></p>
            <br><a href='javascript:history.back()'>Voltar</a>
        </div>
    ");
}

include __DIR__ . '/../core/layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-inbox-fill text-primary me-2"></i> Gestão de Solicitações</h3>
        <p class="text-muted mb-0 small">Analise pedidos de cota extra e consulte o histórico de decisões.</p>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'acesso_negado'): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-shield-lock-fill me-2 fs-5"></i>
        <strong>Acesso Restrito:</strong> Esta página é exclusiva para a equipe técnica do NTI.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($msg != ""): ?>
    <div class='alert alert-<?php echo $tipo_msg; ?> shadow-sm border-0 animate__animated animate__fadeIn mb-4'>
        <i class='bi bi-info-circle-fill me-2'></i> <?php echo $msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 border-top border-primary border-4 mb-5">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-clock-history me-2"></i>Pedidos Pendentes</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Data Pedido</th>
                    <th>Servidor</th>
                    <th>Justificativa</th>
                    <th class="text-center">Qtd</th>
                    <th class="text-center pe-4">Decisão</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $pendentes = $mysqli->query("SELECT *, DATE_FORMAT(data_solicitacao, '%d/%m/%Y %H:%i') as data_br FROM solicitacoes_cota WHERE status = 'Pendente' ORDER BY data_solicitacao ASC");
                if ($pendentes->num_rows > 0):
                    while ($p = $pendentes->fetch_assoc()):
                ?>
                        <tr>
                            <td class='ps-4 text-muted small'><?php echo $p['data_br']; ?></td>
                            <td class='fw-bold text-dark'><?php echo htmlspecialchars($p['usuario']); ?></td>
                            <td class="small"><?php echo htmlspecialchars($p['motivo']); ?></td>
                            <td class='text-center'><span class='badge bg-dark px-3 rounded-pill'>+<?php echo $p['paginas']; ?></span></td>
                            <td class='text-center pe-4'>
                                <form action="<?php echo $BASE_URL; ?>/admin/solicitacoes" method='post' class='d-flex justify-content-center gap-2 m-0'>
                                    <input type='hidden' name='csrf_token' value='<?php echo $_SESSION['csrf_token']; ?>'>
                                    <input type='hidden' name='id_solicitacao' value='<?php echo $p['id']; ?>'>
                                    <button type='submit' name='acao' value='aprovar' class='btn btn-success btn-sm fw-bold shadow-sm' onclick='return confirm("Aprovar este pedido?")'><i class='bi bi-check-lg'></i></button>
                                    <button type='submit' name='acao' value='negar' class='btn btn-danger btn-sm shadow-sm' onclick='return confirm("Negar este pedido?")'><i class='bi bi-x-lg'></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr>
                        <td colspan='5' class='text-center py-4 text-muted'>Nenhum pedido pendente.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-secondary border-4">

    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
        <h5 class="mb-0 fw-bold text-secondary"><i class="bi bi-archive-fill me-2"></i>Histórico de Solicitações</h5>
        <form action="<?php echo $BASE_URL; ?>/admin/solicitacoes" method="get" class="d-flex w-100" style="max-width: 380px;">
            <div class="input-group input-group-sm shadow-sm">
                <span class="input-group-text bg-white border-end-0 text-secondary"><i class="bi bi-search"></i></span>
                <input type="text" name="busca" class="form-control border-start-0 ps-0" placeholder="Buscar usuário ou status..." value="<?php echo htmlspecialchars($busca); ?>">
                <button type="submit" class="btn btn-secondary fw-bold px-3">Buscar</button>
                <?php if ($busca != ''): ?>
                    <a href="<?php echo $BASE_URL; ?>/admin/solicitacoes" class="btn btn-danger" title="Limpar Busca"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Data Resposta</th>
                    <th>Servidor</th>
                    <th class="text-center">Qtd</th>
                    <th class="text-center">Status</th>
                    <th class="pe-4">Respondido por</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($historico->num_rows > 0):
                    while ($h = $historico->fetch_assoc()):
                        $badge_class = ($h['status'] == 'Aprovado') ? 'bg-success' : 'bg-danger';
                ?>
                        <tr>
                            <td class='ps-4 text-muted small'><?php echo $h['data_res']; ?></td>
                            <td>
                                <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($h['usuario']); ?></span>
                                <small class="text-muted d-block" style="font-size: 0.75rem;"><?php echo htmlspecialchars($h['motivo']); ?></small>
                            </td>
                            <td class='text-center small fw-bold'>+<?php echo $h['paginas']; ?></td>
                            <td class='text-center'>
                                <span class='badge <?php echo $badge_class; ?> rounded-pill' style="font-size: 0.7rem;">
                                    <?php echo $h['status']; ?>
                                </span>
                            </td
                            <td class='pe-4 small text-muted'><i class="bi bi-person-check me-1"></i><?php echo htmlspecialchars($h['respondido_por']); ?></td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr>
                        <td colspan='5' class='text-center py-4 text-muted'>
                            <?php echo ($busca != '') ? "Nenhum resultado encontrado para '<b>" . htmlspecialchars($busca) . "</b>'." : "O histórico está vazio."; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($p_num_registros > $qtde_por_pagina): ?>
        <div class="card-footer bg-white py-3 border-0">
            <?php barra_de_paginas($p, $p_num_registros); ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../core/layout/footer.php'; ?>

<?php if ($busca != ''): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const buscaTerm = encodeURIComponent('<?php echo $busca; ?>');
            const linksPaginacao = document.querySelectorAll('.pagination a');

            linksPaginacao.forEach(link => {
                let href = link.getAttribute('href');
                if (href && !href.includes('busca=')) {
                    let separator = href.includes('?') ? '&' : '?';
                    link.setAttribute('href', href + separator + 'busca=' + buscaTerm);
                }
            });
        });
    </script>
<?php endif; ?>