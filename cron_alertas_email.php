<?php

/**
 * IFQUOTA - SCRIPT DE AUTOMAÇÃO (CRON) - AVISO DE COTA ESTOURADA
 * Refatorado para usar a função global de e-mails do sistema.
 */

if (php_sapi_name() !== 'cli') {
    die("Acesso negado! Este script só pode ser executado pelo terminal do servidor.");
}

include_once __DIR__ . '/core/db.php';
include_once __DIR__ . '/core/functions.php'; // Puxa a nossa função global de e-mail

$DOMINIO_PADRAO = "ifnmg.edu.br";

echo "[" . date('Y-m-d H:i:s') . "] Iniciando verificacao de bloqueios do IFQUOTA...\n";
$query = "
    SELECT i.job_id, i.usuario, i.nome_documento, i.impressora, u.email
    FROM impressoes i
    LEFT JOIN usuarios u ON i.usuario = u.usuario
    WHERE i.cod_status_impressao = 3
    AND CONCAT(i.data_impressao, ' ', i.hora_impressao) >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    GROUP BY i.usuario
";

$res = $mysqli->query($query);

if ($res && $res->num_rows > 0) {

    $link_painel = "https://ifquota.almenara.ifnmg.edu.br/meu-painel";
    $assunto = '⚠️ Impressão Bloqueada - Saldo Insuficiente';

    while ($row = $res->fetch_assoc()) {
        $user = $row['usuario'];
        $doc = $row['nome_documento'];
        $imp = $row['impressora'];

        // Fallback do e-mail
        $email_destino = !empty($row['email']) ? $row['email'] : $user . "@" . $DOMINIO_PADRAO;

        // Conteúdo HTML do E-mail
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
        </head>
        <body style='font-family: Arial, Helvetica, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid #e0e0e0;'>
                
                <div style='background-color: #ffffff; padding: 20px; text-align: center; border-bottom: 3px solid #32a852;'>
                  <img src='cid:logo_campus' alt='IFNMG' style='height: 150px; max-width: 100%; display: block; margin: 0 auto;'>
                </div>
                
                <div style='padding: 30px;'>
                    <h2 style='color: #2c3e50; margin-top: 0; font-size: 22px;'>Olá, <strong>{$user}</strong>!</h2>
                    <p style='color: #555555; font-size: 16px; line-height: 1.6;'>Uma tentativa de impressão sua foi <strong style='color: #e74c3c;'>bloqueada</strong> pelo servidor do campus.</p>
                    
                    <div style='background-color: #fdf3f2; border-left: 5px solid #e74c3c; padding: 18px; margin: 25px 0; border-radius: 0 4px 4px 0;'>
                        <p style='margin: 0 0 10px 0; color: #2c3e50; font-size: 15px;'><b>📄 Documento:</b> <span style='color: #555;'>{$doc}</span></p>
                        <p style='margin: 0 0 10px 0; color: #2c3e50; font-size: 15px;'><b>🖨️ Impressora:</b> <span style='color: #555;'>{$imp}</span></p>
                        <p style='margin: 0; color: #e74c3c; font-size: 15px;'><b>❌ Motivo:</b> Você não possui saldo de páginas suficiente.</p>
                    </div>
                    
                    <p style='color: #555555; font-size: 16px; line-height: 1.6;'>Por favor, acesse o painel de impressões da rede para verificar seu saldo atual ou entre em contato com o NTI para solicitar mais páginas.</p>
                    
                    <div style='text-align: center; margin-top: 35px; margin-bottom: 10px;'>
                        <a href='{$link_painel}' style='background-color: #32a852; color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 5px; font-weight: bold; font-size: 16px; display: inline-block;'>Acessar Meu Painel</a>
                    </div>
                </div>
                
                <div style='background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #eeeeee;'>
                    <p style='margin: 0; color: #95a5a6; font-size: 12px; line-height: 1.5;'>Este é um e-mail automático do sistema IFQUOTA.<br>Por favor, não responda diretamente a esta mensagem.</p>
                    <p style='margin: 10px 0 0 0; color: #7f8c8d; font-size: 13px;'><strong>Núcleo de Tecnologia da Informação (NTI)</strong><br>Campus Almenara</p>
                </div>
                
            </div>
        </body>
        </html>
        ";

        // Versão em texto puro (Plano B)
        $alt_body = "Olá, {$user}! A impressão do documento '{$doc}' na impressora '{$imp}' foi bloqueada por falta de saldo. Acesse o painel para mais informações.";

        // CHAMA A NOSSA FUNÇÃO GLOBAL MÁGICA
        if (enviar_email_sistema($email_destino, $assunto, $html, $alt_body)) {
            echo " - ALERTA: E-mail enviado para {$email_destino} (Doc: {$doc})\n";
        } else {
            echo " - ERRO: Falha ao enviar para {$email_destino} (Consulte os logs do PHP).\n";
        }
    }
} else {
    echo " - Nenhum bloqueio de impressao recente encontrado.\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Verificacao concluida.\n";
