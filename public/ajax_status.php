<?php

/**
 * IBQUOTA 3 - API de Status em Tempo Real
 * Retorna as últimas 10 impressões do usuário (Atualizado com verificação SNMP).
 */
include_once __DIR__ . '/../core/db.php';
include_once __DIR__ . '/../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

// 2. CABEÇALHO OBRIGATÓRIO PARA AJAX: Força o PHP a falar em JSON
header('Content-Type: application/json; charset=utf-8');

// 3. Barreira de Segurança
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['erro' => 'Sessão expirada ou não autenticada.']);
    exit();
}

$usuario_logado = $_SESSION['usuario'];

// Busca as 10 impressões mais recentes do utilizador
$query = "SELECT job_id, impressora, nome_documento, paginas, cod_status_impressao, DATE_FORMAT(data_impressao, '%d/%m/%Y') as data_imp, hora_impressao 
          FROM impressoes 
          WHERE usuario = ? 
          ORDER BY cod_impressoes DESC LIMIT 10";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('s', $usuario_logado);
$stmt->execute();
$res = $stmt->get_result();

$impressoes = [];

while ($row = $res->fetch_assoc()) {
    $nome_status_original = status_impressao($row['cod_status_impressao']);

    // ==========================================
    // TRADUTOR DE CORES E ÍCONES DO PAINEL
    // ==========================================

    if ($row['cod_status_impressao'] == 1) {
        $row['cor'] = 'text-bg-success';
        $row['icone'] = 'bi-check-circle-fill';
        $row['status_texto'] = 'Impresso com Sucesso';
    } elseif ($row['cod_status_impressao'] == 10) {
        // NOVIDADE: Identificação do erro capturado pelo script Perl via SNMP
        $row['cor'] = 'text-bg-dark';
        $row['icone'] = 'bi-printer-fill';
        $row['status_texto'] = 'Erro Físico / Offline';
    } elseif ($row['cod_status_impressao'] == 3 || stripos($nome_status_original, 'cadastrado') !== false) {
        $row['cor'] = 'text-bg-warning text-dark'; // Adicionado text-dark para melhor legibilidade
        $row['icone'] = 'bi-exclamation-triangle-fill';
        $row['status_texto'] = 'Bloqueado (Sem Cota)';
    } elseif (stripos($nome_status_original, 'excedida') !== false) {
        $row['cor'] = 'text-bg-danger';
        $row['icone'] = 'bi-slash-circle';
        $row['status_texto'] = 'Cota Excedida';
    } else {
        // Status intermédios (enquanto o Perl processa o ficheiro)
        $row['cor'] = 'text-bg-secondary';
        $row['icone'] = 'bi-arrow-repeat spinner-border spinner-border-sm';
        $row['status_texto'] = 'Processando...';
    }

    $impressoes[] = $row;
}

$stmt->close();

// Devolve o resultado em formato JSON para o JavaScript do painel
header('Content-Type: application/json');
echo json_encode($impressoes);
exit();
