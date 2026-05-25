<?php

/**
 * IBQUOTA 3
 * GG - Gerenciador Grafico do IBQUOTA
 * * Funções definidas para utilização no GG
 * Refatoradas para Bootstrap 5, Performance SQL e Estabilidade de Sessões.
 */

include_once 'db.php';

/** Quantidades de registro por pagina - PAGINACAO **/
define('QTDE_POR_PAGINA', 20);


// Abre o ficheiro core/functions.php e substitui a tua função sec_session_start por esta:

function sec_session_start()
{
    $session_name = 'sec_session_id';   // Atribui um nome de sessão personalizado
    $secure = false; // Em localhost (HTTP) deve ser false. Em Produção (HTTPS) muda para true.
    $httponly = true; // Impede que o JavaScript aceda ao id da sessão (Proteção contra XSS)

    // 1. Verifica se a sessão já foi iniciada em algum outro lugar (ex: header.php ou db.php)
    if (session_status() === PHP_SESSION_NONE) {

        // 2. Tenta forçar o uso exclusivo de cookies para as sessões
        if (ini_set('session.use_only_cookies', 1) === FALSE) {
            // Em vez de redirecionar para um 'error.php' cego, mostramos o erro diretamente de forma elegante
            http_response_code(500);
            die("<div style='font-family: sans-serif; padding: 20px; color: #dc3545;'><b>Erro Crítico de Segurança:</b> Não foi possível iniciar uma sessão segura. Verifique as configurações (ini_set) do servidor PHP.</div>");
        }

        // 3. Configura os parâmetros do cookie de sessão
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $cookieParams["lifetime"],
            'path' => $cookieParams["path"],
            'domain' => $cookieParams["domain"],
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax' // Proteção extra moderna
        ]);

        // 4. Define o nome da sessão e inicia-a
        session_name($session_name);
        session_start();
        session_regenerate_id(true); // Previne ataques de "Session Fixation"
    }
}

function login($login, $password, $mysqli)
{
    if ($stmt = $mysqli->prepare("SELECT cod_adm_users, login, senha, permissao
        FROM adm_users
        WHERE login = ? LIMIT 1")) {
        $stmt->bind_param('s', $login);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($user_id, $nome, $db_password, $permissao);
        $stmt->fetch();

        if ($stmt->num_rows == 0) {
            return false;
        }

        // faz o hash da senha.
        $password = hash('sha256', $password, FALSE);

        if ($db_password == $password) {
            // A senha está correta!
            $user_browser = $_SERVER['HTTP_USER_AGENT'];

            // XSS protect
            $user_id = preg_replace("/[^0-9]+/", "", $user_id);
            $_SESSION['user_id'] = (int)$user_id;

            $nome = preg_replace("/[^a-zA-Z0-9_\-]+/", "", $nome);
            $_SESSION['username'] = $nome;
            $_SESSION['usuario'] = $nome; // Alias para compatibilidade visual

            $_SESSION['login_string'] = hash('sha256', $password . $user_browser);
            $_SESSION['permissao'] = (int)$permissao; // Garante que é número para os IFs do menu

            // Segurança: Regenera o ID da sessão apenas no momento de sucesso do login
            session_regenerate_id(true);

            return true;
        }
    }
    return false;
}


function login_check($mysqli)
{
    // Verifica se todas as variáveis das sessões foram definidas 
    if (isset(
        $_SESSION['user_id'],
        $_SESSION['username'],
        $_SESSION['login_string'],
        $_SESSION['permissao']
    )) {

        $user_id = $_SESSION['user_id'];
        $login_string = $_SESSION['login_string'];
        $username = $_SESSION['username'];
        $user_browser = $_SERVER['HTTP_USER_AGENT'];

        if ($stmt = $mysqli->prepare("SELECT senha FROM adm_users WHERE cod_adm_users = ? LIMIT 1")) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($password);
                $stmt->fetch();
                $login_check = hash('sha256', $password . $user_browser);

                if ($login_check == $login_string) {
                    return true; // Logado!!!
                }
            }
        }
    }
    return false; // Não foi logado 
}

function esc_url($url)
{
    if ('' == $url) {
        return $url;
    }

    $url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\\x80-\\xff]|i', '', $url);
    $strip = array('%0d', '%0a', '%0D', '%0A');
    $url = (string) $url;

    $count = 1;
    while ($count) {
        $url = str_replace($strip, '', $url, $count);
    }

    $url = str_replace(';//', '://', $url);
    $url = htmlentities($url);
    $url = str_replace('&amp;', '&#038;', $url);
    $url = str_replace("'", '&#039;', $url);

    if ($url[0] !== '/') {
        return '';
    } else {
        return $url;
    }
}


function barra_de_paginas($p, $p_registros)
{
    if ($p < 1) $p = 1;
    $p_total = ceil($p_registros / QTDE_POR_PAGINA);
    if ($p > $p_total) $p = $p_total;
    if ($p_total <= 1) return; // Não mostra barra se houver apenas 1 página

    $p_anterior = (($p - 1) <= 0) ? 1 : $p - 1;
    $p_posterior = (($p + 1) >= $p_total) ? $p_total : $p + 1;
    if ($p_posterior == 0) $p_posterior = 1;

    // Layout moderno Bootstrap 5
    echo "<nav aria-label=\"Navegação de páginas\" class=\"mt-4\">";
    echo " <ul class=\"pagination pagination-sm justify-content-center shadow-sm\">";

    $inicio_desabilitado = ($p <= 1) ? "disabled" : "";
    $fim_desabilitado = ($p_total <= $p) ? "disabled" : "";

    $urlbarra = $_SERVER["PHP_SELF"] . "?";
    if ($urlbarra <> $_SERVER["REQUEST_URI"]) {
        if (isset($_GET['cod_usuario'])) $urlbarra .= "cod_usuario=" . $_GET['cod_usuario'] . "&";
        if (isset($_GET['cod_grupo'])) $urlbarra .= "cod_grupo=" . $_GET['cod_grupo'] . "&";
        if (isset($_GET['q'])) $urlbarra .= "q=" . urlencode($_GET['q']) . "&";
    }

    echo "<li class=\"page-item $inicio_desabilitado\"><a class=\"page-link\" href=\"" . $urlbarra . "p=1\" aria-label=\"Primeira\">&laquo;</a></li>\n";
    echo "<li class=\"page-item $inicio_desabilitado\"><a class=\"page-link\" href=\"" . $urlbarra . "p=" . $p_anterior . "\" aria-label=\"Anterior\">&lsaquo; Anterior</a></li>\n";

    // Botao "..." inicial
    if ($p > 3) {
        echo "<li class=\"page-item disabled\"><a class=\"page-link\" href=\"#\">...</a></li>\n";
    }

    // botoes numerados
    if ($p < 4) {
        $p_botao_inicial = 1;
    } elseif (($p > 3) and ($p == $p_total)) {
        $p_botao_inicial = $p - 4;
    } elseif (($p > 3) and ($p == ($p_total - 1))) {
        $p_botao_inicial = $p - 3;
    } elseif (($p > 3) and ($p < ($p_total - 1))) {
        $p_botao_inicial = $p - 2;
    }

    $p_botao_inicial = max(1, $p_botao_inicial);

    for ($i = $p_botao_inicial; $i < ($p_botao_inicial + 5); $i++) {
        if ($i <= $p_total) {
            $active = ($p == $i) ? "active" : "";
            $bg_class = ($p == $i) ? "bg-ifnmg border-ifnmg text-white" : "text-dark";
            echo "<li class=\"page-item $active\"><a class=\"page-link fw-bold $bg_class\" href=\"" . $urlbarra . "p=" . $i . "\">$i</a></li>\n";
        }
    }

    // Botao "..." final
    if (($p_total > 5) and ($p_total - 2) > $p) {
        echo "<li class=\"page-item disabled\"><a class=\"page-link\" href=\"#\">...</a></li>\n";
    }

    echo " <li class=\"page-item $fim_desabilitado\"><a class=\"page-link\" href=\"" . $urlbarra . "p=" . $p_posterior . "\">Próxima &rsaquo;</a></li>\n";
    echo " <li class=\"page-item $fim_desabilitado\"><a class=\"page-link\" href=\"" . $urlbarra . "p=" . $p_total . "\">&raquo;</a></li>\n";
    echo "  </ul>\n</nav>";
}

function primeiro_acesso($mysqli)
{
    if ($stmt = $mysqli->prepare("SELECT senha FROM adm_users WHERE login = 'admin' LIMIT 1")) {
        $stmt->execute();
        $stmt->bind_result($password);
        $stmt->fetch();

        if (strlen($password) < 1) {
            return true; // Senha em branco
        }
        return false;
    }
    return false;
}

function status_impressao($cod_status_impressao)
{
    global $mysqli; // Otimização: Usa a ligação já existente em vez de criar uma nova!
    if ($stmt = $mysqli->prepare("SELECT nome_status FROM status_impressao WHERE cod_status_impressao = ? LIMIT 1")) {
        $stmt->bind_param('i', $cod_status_impressao);
        $stmt->execute();
        $stmt->bind_result($nome_status);
        $stmt->fetch();
        if (strlen($nome_status) < 1) {
            return "NONE";
        }
    }
    return $nome_status;
}

function is_base_local($mysqli)
{
    $stmt_base_local = $mysqli->prepare("SELECT base_local FROM config_geral LIMIT 1");
    $stmt_base_local->execute();
    $stmt_base_local->bind_result($base_local);
    $stmt_base_local->fetch();
    if ($base_local) {
        $stmt_base_local->close();
        return true;
    }
    return false;
}

function quota_padrao($cod_politica)
{
    global $mysqli; // Otimização: Usa a ligação já existente!
    $stmt_quota = $mysqli->prepare("SELECT quota_padrao FROM politicas WHERE cod_politica = ?");
    $stmt_quota->bind_param('i', $cod_politica);
    $stmt_quota->execute();
    $stmt_quota->bind_result($quota_padrao);
    $stmt_quota->fetch();
    $stmt_quota->close();
    return $quota_padrao;
}

function grupo_usuario_politica($cod_politica, $usuario)
{
    global $mysqli; // Otimização: Usa a ligação já existente!
    $base_local = is_base_local($mysqli);

    $stmt = $mysqli->prepare("SELECT grupo FROM politica_grupo WHERE cod_politica = ?");
    $stmt->bind_param('i', $cod_politica);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    while ($row = $result->fetch_assoc()) {
        $grupo = $row['grupo'];
        if ($base_local) {
            $stmt_grupo = $mysqli->prepare("SELECT usuarios.usuario
                                FROM grupos,grupo_usuario,usuarios
                                WHERE grupos.grupo = ? AND 
                                 grupos.cod_grupo = grupo_usuario.cod_grupo AND 
                                 usuarios.usuario = ? AND 
                                 usuarios.cod_usuario = grupo_usuario.cod_usuario");
            $stmt_grupo->bind_param('ss', $grupo, $usuario);
            $stmt_grupo->execute();
            $stmt_grupo->store_result();
            if ($stmt_grupo->num_rows > 0) {
                $stmt_grupo->close();
                return $grupo;
            }
        } else {
            // LDAP (A implementar futuramente)
        }
    }
    return "";
}

function quota_usuario($cod_politica, $usuario)
{
    global $mysqli; // Otimização: Usa a ligação já existente!
    $stmt = $mysqli->prepare("SELECT quota FROM quota_usuario WHERE cod_politica = ? AND usuario = ? LIMIT 1");
    $stmt->bind_param('is', $cod_politica, $usuario);
    $stmt->execute();
    $stmt->bind_result($quota);
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->fetch();
        return $quota;
    } else {
        return quota_padrao($cod_politica);
    }
}


/**
 * ==========================================
 * SISTEMA DE SEGURANÇA CSRF
 * ==========================================
 */

/**
 * Gera e retorna um Token CSRF único para a sessão atual.
 * 
 * @return string O código de segurança gerado.
 */
function gerar_csrf_token()
{
    // Só gera um novo token se a sessão ainda não tiver um
    if (empty($_SESSION['csrf_token'])) {
        // random_bytes(32) cria um código altamente seguro e bin2hex converte para texto legível
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida se o Token CSRF recebido pelo formulário é igual ao da sessão.
 * 
 * @param string $token_recebido O token que veio do $_POST.
 * @return boolean Retorna true se for válido, ou encerra a página se for inválido.
 */
function validar_csrf_token($token_recebido)
{
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token_recebido)) {
        // hash_equals compara as strings de forma segura contra ataques de timing
        die("ERRO CRÍTICO DE SEGURANÇA: Token CSRF inválido ou ausente. Ação bloqueada para proteger o sistema.");
    }
    return true;
}

// ==========================================
// FUNÇÃO GLOBAL DE ENVIO DE E-MAIL (PHPMailer)
// ==========================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviar_email_sistema($destinatarios, $assunto, $mensagem_html, $mensagem_texto = "")
{

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        require_once __DIR__ . '/PHPMailer/Exception.php';
        require_once __DIR__ . '/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer/SMTP.php';
    }

    $env_path = __DIR__ . '/../.env';
    if (!file_exists($env_path)) return false;

    $env = parse_ini_file($env_path);
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $env['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $env['SMTP_USER'];
        $mail->Password   = $env['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $env['SMTP_PORT'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($env['SMTP_FROM'], 'Sistema IFQUOTA');
        $mail->addReplyTo($env['SMTP_REPLYTO'], 'TI Campus Almenara');

        // MÁGICA DOS MÚLTIPLOS DESTINATÁRIOS
        if (is_array($destinatarios)) {
            foreach ($destinatarios as $email) {
                $mail->addAddress($email);
            }
        } else {
            $mail->addAddress($destinatarios);
        }

        $caminhos_possiveis = [
            __DIR__ . '/../assets/img/logo_almenara.jpg',
            __DIR__ . '/../public/assets/img/logo_almenara.jpg',
            $_SERVER['DOCUMENT_ROOT'] . '/gg/assets/img/logo_almenara.jpg',
            $_SERVER['DOCUMENT_ROOT'] . '/assets/img/logo_almenara.jpg'
        ];

        $logo_encontrada = false;
        foreach ($caminhos_possiveis as $caminho) {
            if (file_exists($caminho)) {
                // Pega a imagem física e joga para dentro do e-mail (Inline Attachment)
                $mail->addEmbeddedImage($caminho, 'logo_campus');
                $logo_encontrada = true;
                break; // Achou a imagem? Para de procurar e continua o código!
            }
        }

        // Apenas para registrar no log do servidor caso a imagem seja apagada no futuro
        if (!$logo_encontrada) {
            error_log("IFQUOTA Mailer Aviso: A logo não foi encontrada em nenhum diretorio.");
        }

        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $mensagem_html;
        $mail->AltBody = empty($mensagem_texto) ? strip_tags($mensagem_html) : $mensagem_texto;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("IFQUOTA Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function disparar_alerta_gestor($tipo_alerta, $usuario, $detalhe1, $detalhe2)
{
    global $mysqli; // Puxa a conexão com o banco de dados

    $link_painel_admin = "http://ifquota.almenara.ifnmg.edu.br/admin/dashboard";

    if ($tipo_alerta == 'colorida') {
        $nivel_alvo = 3; // 3 = Direção
        $assunto = "🖨️ Nova Fila: Pedido de Impressão Colorida";
        $titulo_box = "Documento Aguardando Aprovação";
        $lbl1 = "Arquivo";
        $lbl2 = "Total de Páginas";
        $cor_tema = "#3498db"; // Azul
    } else {
        $nivel_alvo = 2; // 2 = NTI
        $assunto = "⚠️ Solicitação de Cota Adicional";
        $titulo_box = "Pedido de Páginas Extras";
        $lbl1 = "Quantidade Solicitada";
        $lbl2 = "Motivo do Pedido";
        $cor_tema = "#f39c12"; // Laranja
    }

    // BUSCA DINÂMICA DE E-MAILS NO BANCO
    // Procura usuários com o nível correto e que tenham preenchido a coluna 'email'
    $stmt = $mysqli->prepare("SELECT email FROM adm_users WHERE permissao = ? AND email IS NOT NULL AND email != ''");
    $stmt->bind_param('i', $nivel_alvo);
    $stmt->execute();
    $res = $stmt->get_result();

    $lista_emails = [];
    while ($row = $res->fetch_assoc()) {
        // Valida se o que foi digitado no cadastro é realmente um formato de e-mail
        if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $lista_emails[] = $row['email'];
        }
    }
    $stmt->close();

    // Se não houver ninguém cadastrado com e-mail para receber esse alerta, ele cancela silenciosamente
    if (empty($lista_emails)) {
        return false;
    }

    // MONTA O HTML DO E-MAIL
    $html = "
    <!DOCTYPE html>
    <html>
    <body style='font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid #e0e0e0;'>
            
            <div style='background-color: #ffffff; padding: 20px; text-align: center; border-bottom: 3px solid {$cor_tema};'>
                <img src='cid:logo_campus' alt='IFNMG' style='height: 100px; max-width: 100%; display: block; margin: 0 auto;'>
            </div>
            
            <div style='padding: 30px;'>
                <h2 style='color: #2c3e50; margin-top: 0; font-size: 20px;'>Notificação do Sistema IFQUOTA</h2>
                <p style='color: #555555; font-size: 16px; line-height: 1.6;'>O usuário <strong>{$usuario}</strong> acabou de realizar um novo pedido no portal.</p>
                
                <div style='background-color: #f8f9fa; border-left: 5px solid {$cor_tema}; padding: 18px; margin: 25px 0; border-radius: 0 4px 4px 0;'>
                    <h3 style='margin-top: 0; font-size: 16px; color: {$cor_tema};'>{$titulo_box}</h3>
                    <p style='margin: 0 0 10px 0; color: #2c3e50; font-size: 14px;'><b>{$lbl1}:</b> <span style='color: #555;'>{$detalhe1}</span></p>
                    <p style='margin: 0; color: #2c3e50; font-size: 14px;'><b>{$lbl2}:</b> <span style='color: #555;'>{$detalhe2}</span></p>
                </div>
                
                <p style='color: #555555; font-size: 16px; line-height: 1.6;'>Acesse o painel administrativo para analisar e aprovar/negar a solicitação.</p>
                
                <div style='text-align: center; margin-top: 35px; margin-bottom: 10px;'>
                    <a href='{$link_painel_admin}' style='background-color: {$cor_tema}; color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 5px; font-weight: bold; font-size: 16px; display: inline-block;'>Acessar Painel Admin</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";

    // Envia o e-mail passando a lista de destinatários dinâmicos!
    return enviar_email_sistema($lista_emails, $assunto, $html);
}
