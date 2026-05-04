<?php

/**
 * IBQUOTA 3 - Gestão de Solicitações de Cota Extra (Corrigido com CSRF funcional)
 */
include_once '../core/db.php';
include_once '../core/functions.php';
sec_session_start();

// Validação de acesso do Administrador
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 1) {
    header("Location: ../public/login.php");
    exit();
}

$admin_logado = $_SESSION['usuario'];
$msg = "";
$tipo_msg = "";

// PROCESSAMENTO DAS AÇÕES (APROVAR / NEGAR)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {

    // 1. VALIDAÇÃO DO TOKEN CSRF (A Fechadura)
    $token_recebido = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    validar_csrf_token($token_recebido);

    $id_solicitacao = (int)$_POST['id_solicitacao'];
    $acao = $_POST['acao'];

    // Busca os dados do pedido pendente
    $stmt_busca = $mysqli->prepare("SELECT usuario, paginas, motivo FROM solicitacoes_cota WHERE id = ? AND status = 'Pendente'");
    $stmt_busca->bind_param('i', $id_solicitacao);
    $stmt_busca->execute();
    $res = $stmt_busca->get_result();

    if ($row = $res->fetch_assoc()) {
        $usuario = $row['usuario'];
        $paginas = $row['paginas'];
        $motivo_full = "Aprovado via Portal: " . $row['motivo'];

        if ($acao == 'aprovar') {
            // LÓGICA DE INJEÇÃO DE COTAS
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

                // Inserção no histórico de quotas adicionais
                $stmt_add = $mysqli->prepare("INSERT INTO quota_adicional (cod_politica, usuario, quota_adicional, motivo, datahora, useradmin) VALUES (?, ?, ?, ?, NOW(), ?)");
                $stmt_add->bind_param('isiss', $cod_politica, $usuario, $paginas, $motivo_full, $admin_logado);
                $stmt_add->execute();

                // Verifica se já existe saldo personalizado para este utilizador
                $chk_q = $mysqli->prepare("SELECT quota FROM quota_usuario WHERE cod_politica = ? AND usuario = ? AND grupo = ?");
                $chk_q->bind_param('iss', $cod_politica, $usuario, $grupo);
                $chk_q->execute();
                $res_q = $chk_q->get_result();

                if ($res_q->num_rows > 0) {
                    // Atualiza somando ao saldo existente
                    $stmt_upd = $mysqli->prepare("UPDATE quota_usuario SET quota = quota + ? WHERE cod_politica = ? AND usuario = ? AND grupo = ?");
                    $stmt_upd->bind_param('iiss', $paginas, $cod_politica, $usuario, $grupo);
                    $stmt_upd->execute();
                } else {
                    // Cria novo saldo (Cota Padrão + Pedido Extra)
                    $nova_cota = $cota_padrao + $paginas;
                    $stmt_ins = $mysqli->prepare("INSERT INTO quota_usuario (cod_politica, grupo, usuario, quota) VALUES (?, ?, ?, ?)");
                    $stmt_ins->bind_param('issi', $cod_politica, $grupo, $usuario, $nova_cota);
                    $stmt_ins->execute();
                }

                // Finaliza a solicitação como Aprovada
                $stmt_final = $mysqli->prepare("UPDATE solicitacoes_cota SET status = 'Aprovado', data_resposta = NOW(), respondido_por = ? WHERE id = ?");
                $stmt_final->bind_param('si', $admin_logado, $id_solicitacao);
                $stmt_final->execute();

                $msg = "Cota de {$paginas} páginas injetada para {$usuario} com sucesso!";
                $tipo_msg = "success";
            } else {
                $msg = "Erro: Servidor não vinculado a um grupo/política.";
                $tipo_msg = "danger";
            }
        } elseif ($acao == 'negar') {
            $stmt_neg = $mysqli->prepare("UPDATE solicitacoes_cota SET status = 'Negado', data_resposta = NOW(), respondido_por = ? WHERE id = ?");
            $stmt_neg->bind_param('si', $admin_logado, $id_solicitacao);
            $stmt_neg->execute();
            $msg = "Solicitação negada.";
            $tipo_msg = "warning";
        }
    }
}

include '../core/layout/header.php';
?>

<div class="mb-4 mt-2 border-bottom pb-3">
    <h3 class="fw-bold text-dark"><i class="bi bi-inbox-fill text-primary me-2"></i> Pedidos de Cota Extra</h3>
</div>

<?php if ($msg != ""): ?>
    <div class='alert alert-<?php echo $tipo_msg; ?> shadow-sm animate__animated animate__fadeIn'>
        <i class='bi bi-info-circle-fill me-2'></i> <?php echo $msg; ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 border-top border-primary border-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Data/Hora</th>
                    <th>Servidor</th>
                    <th>Justificativa</th>
                    <th class="text-center">Qtd</th>
                    <th class="text-center pe-4">Decisão</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $pendentes = $mysqli->query("SELECT *, DATE_FORMAT(data_solicitacao, '%d/%m %H:%i') as data_br FROM solicitacoes_cota WHERE status = 'Pendente' ORDER BY data_solicitacao ASC");
                if ($pendentes->num_rows > 0):
                    while ($p = $pendentes->fetch_assoc()):
                ?>
                        <tr>
                            <td class='ps-4 text-muted small'><?php echo $p['data_br']; ?></td>
                            <td class='fw-bold text-dark'><i class='bi bi-person me-1'></i><?php echo $p['usuario']; ?></td>
                            <td><?php echo htmlspecialchars($p['motivo']); ?></td>
                            <td class='text-center'><span class='badge bg-dark'>+<?php echo $p['paginas']; ?></span></td>
                            <td class='text-center pe-4'>
                                <form method='post' class='d-flex justify-content-center gap-2 m-0'>

                                    <!-- AQUI ESTÁ A CORREÇÃO: Token CSRF gerado corretamente pelo PHP -->
                                    <input type='hidden' name='csrf_token' value='<?php echo gerar_csrf_token(); ?>'>

                                    <input type='hidden' name='id_solicitacao' value='<?php echo $p['id']; ?>'>

                                    <button type='submit' name='acao' value='aprovar' class='btn btn-success btn-sm fw-bold'
                                        onclick='return confirm("Aprovar estas páginas?")'>
                                        <i class='bi bi-check-lg'></i> Aprovar
                                    </button>

                                    <button type='submit' name='acao' value='negar' class='btn btn-danger btn-sm'
                                        onclick='return confirm("Negar pedido?")'>
                                        <i class='bi bi-x-lg'></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php
                    endwhile;
                else:
                    ?>
                    <tr>
                        <td colspan='5' class='text-center py-5 text-muted'>Não há pedidos pendentes.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../core/layout/footer.php'; ?>