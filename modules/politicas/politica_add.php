<?php

/**
 * IFQUOTA - AÇÃO SILENCIOSA: Adicionar Política
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

// MÁGICA DO DEBUG: Obriga o banco a mostrar o erro na tela em vez de dar Erro 500
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (session_status() === PHP_SESSION_NONE) {
        sec_session_start();
    }

    $host_atual = $_SERVER['HTTP_HOST'] ?? '';
    $BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

    // Bloqueia se NÃO for o NTI (Nível 2)
    if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] != 2) {
        header("Location: " . $BASE_URL . "/admin/dashboard?msg=acesso_negado");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome'])) {

        validar_csrf_token($_POST['csrf_token'] ?? '');

        $nome = trim($_POST['nome']);
        $quota_padrao = (int)$_POST['quota_padrao'];
        $quota_infinita = isset($_POST['quota_infinita']) ? 1 : 0;
        
        if (strlen($nome) > 0) {
            
            $chk = $mysqli->prepare("SELECT cod_politica FROM politicas WHERE nome = ?");
            $chk->bind_param('s', $nome);
            $chk->execute();
            $chk->store_result();

            if ($chk->num_rows > 0) {
                $chk->close();
                header("Location: " . $BASE_URL . "/admin/politicas?msg=duplicado");
                exit();
            }
            $chk->close();

            // CORREÇÃO APLICADA AQUI: Adicionámos a coluna 'prioridade' e enviamos o valor '0' por defeito
            $prioridade = 0;
            $ins = $mysqli->prepare("INSERT INTO politicas (nome, quota_padrao, quota_infinita, prioridade) VALUES (?, ?, ?, ?)");
            $ins->bind_param('siii', $nome, $quota_padrao, $quota_infinita, $prioridade);
            $ins->execute();
            $ins->close();
        }
    }
    
    // Deu tudo certo, volta para a tela de listagem
    header("Location: " . $BASE_URL . "/admin/politicas?msg=add");
    exit();

} catch (Exception $e) {
    // INTERCEPTAÇÃO DO ERRO
    die("
        <div style='font-family: sans-serif; padding: 20px; background: #fee; border: 1px solid #f00; border-radius: 5px; margin: 40px auto; max-width: 800px;'>
            <h3 style='color: red;'>Erro 500 Interceptado com Sucesso!</h3>
            <p>O banco de dados rejeitou o comando por conta do seguinte erro estrutural:</p>
            <p><b>" . $e->getMessage() . "</b></p>
            <hr>
            <small><i>Tire um print ou copie essa mensagem de erro para arrumarmos a estrutura do banco.</i></small>
            <br><br><a href='{$BASE_URL}/admin/politicas'>Voltar</a>
        </div>
    ");
}