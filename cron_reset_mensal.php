<?php

/**
 * IFQUOTA - SCRIPT DE AUTOMAÇÃO (CRON) - VIRADA DE MÊS
 * Executa o reset de todas as políticas de cota limitada.
 * Salvar na RAIZ do sistema (/gg/cron_reset_cotas.php)
 */

// SEGURANÇA MÁXIMA: Impede execução pelo navegador. Só funciona via terminal do Linux!
if (php_sapi_name() !== 'cli') {
    die("Acesso negado! Este script de manutenção só pode ser executado pelo servidor.");
}

// Como está na raiz, o caminho para o banco fica mais simples:
include_once __DIR__ . '/core/db.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando Renovação Mensal de Cotas IFQUOTA...\n";

// Seleciona todas as políticas limitadas (que não são infinitas)
$query_politicas = "SELECT cod_politica, nome FROM politicas WHERE quota_infinita = 0";
$res = $mysqli->query($query_politicas);

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $cod = $row['cod_politica'];
        $nome_pol = $row['nome'];

        // Apaga os saldos. O IFQUOTA vai recriar o saldo padrão quando a pessoa for imprimir.
        $mysqli->query("DELETE FROM quota_usuario WHERE cod_politica = $cod");

        echo " - Política: {$nome_pol} (ID {$cod}) -> Saldo reiniciado.\n";
    }

    // Otimiza a tabela (recupera espaço no HD do servidor)
    $mysqli->query("OPTIMIZE TABLE quota_usuario");
    echo "[" . date('Y-m-d H:i:s') . "] Sucesso: Banco de dados otimizado.\n";
} else {
    echo "Nenhuma política limitada encontrada para reset.\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Processo concluído.\n";
