<?php

/**
 * IBQUOTA 3 - Gestão de Solicitações de Cota Extra
 */
include_once '../core/db.php';
include_once '../core/functions.php';
sec_session_start();

if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 1) {
    header("Location: ../public/login.php");
    exit();
}

$admin_logado = $_SESSION['usuario'];
$msg = "";
$tipo_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $id_solicitacao = (int)$_POST['id_solicitacao'];
    $acao = $_POST['acao'];

    // Pega os dados do pedido
    $stmt_busca = $mysqli->prepare("SELECT usuario, paginas, motivo FROM solicitacoes_cota WHERE id = ? AND status = 'Pendente'");
    $stmt_busca->bind_param('i', $id_solicitacao);
    $stmt_busca->execute();
    $res = $stmt_busca->get_result();

    if ($row = $res->fetch_assoc()) {
        $usuario = $row['usuario'];
        $paginas = $row['paginas'];
        $motivo = "Aprovado via Portal: " . $row['motivo'];

        if ($acao == 'aprovar') {
            // LÓGICA DE INJEÇÃO INTELIGENTE
            // 1. Acha o grupo e política atual do usuário
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

                // Registra no Histórico de Adicionais
                $mysqli->query("INSERT INTO quota_adicional (cod_politica, usuario, quota_adicional, motivo, datahora, useradmin) VALUES ($cod_politica, '$usuario', $paginas, '$motivo', NOW(), '$admin_logado')");

                // VERIFICA SE O USUÁRIO JÁ TEM REGISTRO NA TABELA QUOTA_USUARIO
                $chk_q = $mysqli->query("SELECT quota FROM quota_usuario WHERE cod_politica = $cod_politica AND usuario = '$usuario' AND grupo = '$grupo'");

                if ($chk_q->num_rows > 0) {
                    // Já tem registro, só atualiza somando
                    $mysqli->query("UPDATE quota_usuario SET quota = quota + $paginas WHERE cod_politica = $cod_politica AND usuario = '$usuario' AND grupo = '$grupo'");
                } else {
                    // Não tem registro. Cria um novo somando o Padrão da Política + O que ele pediu agora!
                    $nova_cota = $cota_padrao + $paginas;
                    $mysqli->query("INSERT INTO quota_usuario (cod_politica, grupo, usuario, quota) VALUES ($cod_politica, '$grupo', '$usuario', $nova_cota)");
                }

                // Atualiza o Status do Pedido para Aprovado
                $mysqli->query("UPDATE solicitacoes_cota SET status = 'Aprovado', data_resposta = NOW(), respondido_por = '$admin_logado' WHERE id = $id_solicitacao");

                $msg = "Cota de {$paginas} páginas injetada para {$usuario} com sucesso!";
                $tipo_msg = "success";
            } else {
                $msg = "Falha ao aprovar: O usuário não está vinculado a nenhum grupo com política de impressão.";
                $tipo_msg = "danger";
            }
            $stmt_pol->close();
        } elseif ($acao == 'negar') {
            $mysqli->query("UPDATE solicitacoes_cota SET status = 'Negado', data_resposta = NOW(), respondido_por = '$admin_logado' WHERE id = $id_solicitacao");
            $msg = "Solicitação negada.";
            $tipo_msg = "warning";
        }
    }
    $stmt_busca->close();
}

include '../core/layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-inbox-fill text-primary me-2"></i> Solicitações de Cota Extra</h3>
        <p class="text-muted mb-0 small">Aprove ou negue os pedidos de páginas feitos pelos servidores.</p>
    </div>
</div>

<?php if ($msg != "") {
    echo "<div class='alert alert-{$tipo_msg} shadow-sm'><i class='bi bi-info-circle-fill me-2'></i> {$msg}</div>";
} ?>

<div class="card shadow-sm border-0 border-top border-primary border-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Data/Hora</th>
                    <th>Servidor</th>
                    <th>Justificativa</th>
                    <th class="text-center">Quantidade</th>
                    <th class="text-center pe-4">Decisão</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $pendentes = $mysqli->query("SELECT *, DATE_FORMAT(data_solicitacao, '%d/%m %H:%i') as data_br FROM solicitacoes_cota WHERE status = 'Pendente' ORDER BY data_solicitacao ASC");
                if ($pendentes->num_rows > 0) {
                    while ($p = $pendentes->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td class='ps-4 text-muted small'>{$p['data_br']}</td>";
                        echo "<td class='fw-bold text-dark'><i class='bi bi-person me-1'></i>{$p['usuario']}</td>";
                        echo "<td>{$p['motivo']}</td>";
                        echo "<td class='text-center'><span class='badge bg-dark fs-6'>+{$p['paginas']}</span></td>";
                        echo "<td class='text-center pe-4'>
                                <form method='post' class='d-flex justify-content-center gap-2 m-0'>
                                    <input type='hidden' name='id_solicitacao' value='{$p['id']}'>
                                    <button type='submit' name='acao' value='aprovar' class='btn btn-success btn-sm fw-bold' onclick='return confirm(\"Aprovar injeção de {$p['paginas']} páginas para {$p['usuario']}?\")'><i class='bi bi-check-lg'></i> Aprovar</button>
                                    <button type='submit' name='acao' value='negar' class='btn btn-danger btn-sm' onclick='return confirm(\"Negar esta solicitação?\")'><i class='bi bi-x-lg'></i></button>
                                </form>
                              </td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='5' class='text-center py-5 text-muted'><i class='bi bi-emoji-smile fs-1 d-block mb-3'></i> Não há pedidos pendentes no momento.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../core/layout/footer.php'; ?>