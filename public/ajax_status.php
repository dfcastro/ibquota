<?php

/**
 * IBQUOTA 3 - API de Status em Tempo Real
 * Retorna as últimas impressões do usuário logado em formato JSON.
 */
include_once '../core/db.php';
include_once '../core/functions.php';

sec_session_start();

if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'Sessão expirada.']);
    exit();
}

$usuario_logado = $_SESSION['usuario'];

// Busca as 5 impressões mais recentes deste usuário
$query = "SELECT job_id, impressora, nome_documento, paginas, cod_status_impressao, DATE_FORMAT(data_impressao, '%d/%m/%Y') as data_imp, hora_impressao 
          FROM impressoes 
          WHERE usuario = ? 
          ORDER BY cod_impressoes DESC LIMIT 5";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('s', $usuario_logado);
$stmt->execute();
$res = $stmt->get_result();

$impressoes = [];

while ($row = $res->fetch_assoc()) {
    // Busca o nome real do status (do mesmo jeito que os relatórios do Admin)
    $nome_status_original = status_impressao($row['cod_status_impressao']);

    // Regras de Cores e Ícones
    if ($row['cod_status_impressao'] == 1) {
        $row['cor'] = 'success';
        $row['icone'] = 'bi-check-circle-fill';
        $row['status_texto'] = 'Impresso';
    } elseif ($row['cod_status_impressao'] == 3 || stripos($nome_status_original, 'cadastrado') !== false) {
        $row['cor'] = 'warning text-dark';
        $row['icone'] = 'bi-exclamation-triangle-fill';
        $row['status_texto'] = 'Bloqueado (Sem Cota)';
    } elseif (stripos($nome_status_original, 'excedida') !== false) {
        $row['cor'] = 'danger';
        $row['icone'] = 'bi-slash-circle';
        $row['status_texto'] = 'Cota Excedida';
    } else {
        $row['cor'] = 'primary';
        $row['icone'] = 'bi-arrow-repeat spinner-border spinner-border-sm';
        $row['status_texto'] = 'Processando...';
    }

    $impressoes[] = $row;
}

$stmt->close();
header('Content-Type: application/json');
echo json_encode($impressoes);
exit();
