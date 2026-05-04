<?php
/**
 * IBQUOTA 3 - Leitor de Variáveis de Ambiente (.env)
 * 
 * Este script procura o arquivo .env na raiz do projeto, 
 * lê linha por linha e salva as configurações na variável global $_ENV.
 */

function carregarEnv($caminhoArquivo) {
    // 1. Verifica se o arquivo .env existe
    if (!file_exists($caminhoArquivo)) {
        die("ERRO CRÍTICO: Arquivo de configuração .env não encontrado.");
    }

    // 2. Lê o arquivo ignorando linhas em branco
    $linhas = file($caminhoArquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    // 3. Processa cada linha
    foreach ($linhas as $linha) {
        // Ignora comentários (linhas que começam com #)
        if (strpos(trim($linha), '#') === 0) {
            continue;
        }

        // Separa o NOME da variável do VALOR dela (ex: DB_USER = root)
        list($nome, $valor) = explode('=', $linha, 2);

        $nome = trim($nome);
        $valor = trim($valor);

        // Remove aspas caso você tenha colocado no .env (ex: "Portal")
        $valor = trim($valor, '"\'');

        // Salva na memória do PHP para podermos usar em qualquer lugar!
        if (!array_key_exists($nome, $_SERVER) && !array_key_exists($nome, $_ENV)) {
            putenv(sprintf('%s=%s', $nome, $valor));
            $_ENV[$nome] = $valor;
            $_SERVER[$nome] = $valor;
        }
    }
}

// Lógica inteligente para descobrir onde está a raiz do projeto e achar o .env
$caminho_env = __DIR__ . '/../.env'; // __DIR__ é a pasta 'core', então voltamos uma pasta (../)

carregarEnv($caminho_env);
?>