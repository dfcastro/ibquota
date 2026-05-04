<?php

/**
 * IBQUOTA 3 - LOGIN HÍBRIDO (VERSÃO FINAL ESTRUTURADA E AUDITADA)
 */
include_once '../core/db.php';
include_once '../core/functions.php';

sec_session_start();

$erro = "";

if (isset($_POST['login'], $_POST['senha'])) {
  $usuario = trim(strtolower($_POST['login']));
  $senha = $_POST['senha'];
  $admin_logado = false;

  // DADOS DO ESPIÃO (AUDITORIA)
  $ip_acesso = $_SERVER['REMOTE_ADDR'];
  $navegador = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido';

  // =======================================================
  // TENTATIVA 1: LOGIN ADMIN LOCAL (Banco de Dados)
  // =======================================================
  $stmt_adm = $mysqli->prepare("SELECT cod_adm_users, login, senha, permissao FROM adm_users WHERE login = ? LIMIT 1");
  if ($stmt_adm) {
    $stmt_adm->bind_param('s', $usuario);
    $stmt_adm->execute();
    $stmt_adm->store_result();

    if ($stmt_adm->num_rows == 1) {
      $stmt_adm->bind_result($cod_adm, $db_login, $db_senha, $db_permissao);
      $stmt_adm->fetch();

      // Verifica se a senha bate com o Hash SHA-512
      $senha_hash = hash('sha512', $senha);

      if ($senha_hash === $db_senha || password_verify($senha, $db_senha)) {

        // --- GRAVA LOG DE SUCESSO DO ADMIN ---
        $stmt_log = $mysqli->prepare("INSERT INTO logs_acesso (usuario, ip, status, user_agent) VALUES (?, ?, 'Sucesso (Admin)', ?)");
        $stmt_log->bind_param('sss', $usuario, $ip_acesso, $navegador);
        $stmt_log->execute();
        $stmt_log->close();

        // Sucesso! Registra a sessão do Administrador
        $_SESSION['usuario'] = $db_login;
        $_SESSION['permissao'] = $db_permissao;
        $_SESSION['login_string'] = hash('sha512', $db_senha . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

        $admin_logado = true;

        // Vai para o roteador na raiz (que jogará para o painel admin)
        header("Location: ../index.php");
        exit();
      }
    }
    $stmt_adm->close();
  }

  // =======================================================
  // TENTATIVA 2: ACTIVE DIRECTORY (Se não for admin)
  // =======================================================
  if (!$admin_logado) {
    $stmt_ldap = $mysqli->prepare("SELECT LDAP_server, LDAP_port, LDAP_base, LDAP_user, LDAP_password FROM config_geral LIMIT 1");

    if ($stmt_ldap) {
      $stmt_ldap->execute();
      $stmt_ldap->bind_result($ldap_server, $ldap_porta, $ldap_base, $ldap_usuario, $ldap_senha);
      $stmt_ldap->fetch();
      $stmt_ldap->close();
    }

    if (!empty($ldap_server)) {
      $ldapconn = @ldap_connect($ldap_server, $ldap_porta);
      if ($ldapconn) {
        ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);

        if (@ldap_bind($ldapconn, $ldap_usuario, $ldap_senha)) {
          $filtro = "(&(objectCategory=person)(objectClass=user)(sAMAccountName={$usuario}))";
          $search = @ldap_search($ldapconn, $ldap_base, $filtro);
          $info = @ldap_get_entries($ldapconn, $search);

          if ($info["count"] > 0) {
            $dn_usuario = $info[0]["dn"];

            if (@ldap_bind($ldapconn, $dn_usuario, $senha)) {

              $stmt_chk = $mysqli->prepare("SELECT usuario FROM usuarios WHERE usuario = ?");
              $stmt_chk->bind_param('s', $usuario);
              $stmt_chk->execute();
              $stmt_chk->store_result();

              if ($stmt_chk->num_rows > 0) {

                // --- GRAVA LOG DE SUCESSO DO AD ---
                $stmt_log = $mysqli->prepare("INSERT INTO logs_acesso (usuario, ip, status, user_agent) VALUES (?, ?, 'Sucesso (AD)', ?)");
                $stmt_log->bind_param('sss', $usuario, $ip_acesso, $navegador);
                $stmt_log->execute();
                $stmt_log->close();

                // Sucesso! Registra a sessão do Professor/Técnico
                $_SESSION['usuario'] = $usuario;
                $_SESSION['permissao'] = 1;
                $_SESSION['login_string'] = hash('sha512', $senha . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

                header("Location: meu_painel.php");
                exit();
              } else {
                $erro = "Usuário na rede, mas não sincronizado.";
              }
            } else {
              $erro = "Senha de rede incorreta.";
            }
          } else {
            $erro = "Usuário ou senha incorretos.";
          }
        } else {
          $erro = "Falha de conexão com a conta de serviço do AD.";
        }
        ldap_close($ldapconn);
      } else {
        $erro = "Servidor de rede (AD) indisponível.";
      }
    } else {
      $erro = "Usuário ou senha incorretos.";
    }
  }

  // =======================================================
  // AUDITORIA DE FALHAS (Se chegou aqui e tem erro, grava!)
  // =======================================================
  if (!empty($erro)) {
    // Pega apenas os primeiros 50 caracteres do erro para não estourar a coluna do banco
    $motivo_falha = substr("Falha: " . $erro, 0, 50);

    $stmt_log = $mysqli->prepare("INSERT INTO logs_acesso (usuario, ip, status, user_agent) VALUES (?, ?, ?, ?)");
    $stmt_log->bind_param('ssss', $usuario, $ip_acesso, $motivo_falha, $navegador);
    $stmt_log->execute();
    $stmt_log->close();
  }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="../favicon.png" />
  <title>Portal de Impressão - IFNMG</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="../assets/css/ifnmg.css" rel="stylesheet">

  <style>
    body.login-page {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background-color: #f4f6f9;
    }

    .form-signin {
      width: 100%;
      max-width: 420px;
      padding: 15px;
      margin: auto;
    }

    .logo-campus {
      max-width: 220px;
      margin-bottom: 25px;
    }

    .form-control:focus {
      box-shadow: none;
      border-color: #dee2e6;
    }

    .input-group:focus-within {
      box-shadow: 0 0 0 0.25rem rgba(50, 160, 65, 0.25);
      border-radius: 0.375rem;
    }
  </style>
</head>

<body class="login-page text-center">

  <main class="form-signin">
    <div class="card shadow-sm border-0 border-top border-success border-4 rounded-3">

      <div class="card-body p-4">
        <img class="logo-campus img-fluid mb-3" src="../assets/img/logo_almenara.jpg" alt="Logo IFNMG Campus Almenara" onerror="this.style.display='none'">

        <h4 class="mb-1 fw-bold text-dark">Portal de Impressão</h4>
        <p class="text-muted small mb-3">Acesso para Servidores e Equipe NTI</p>

        <div class="alert alert-success bg-opacity-10 text-success border-success py-2 small shadow-sm text-start mb-4" role="alert">
          <i class="bi bi-info-circle-fill me-1"></i> Utilize o seu usuário <b>nome.sobrenome</b> e a senha da rede do campus.
        </div>

        <?php if (!empty($erro)) { ?>
          <div class="alert alert-danger py-2 small fw-bold shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo $erro; ?>
          </div>
        <?php } ?>

        <form action="login.php" method="post" name="login_form">
          

          <div class="input-group mb-3 shadow-sm rounded bg-white">
            <span class="input-group-text bg-transparent border-end-0 text-success">
              <i class="bi bi-person-fill fs-5"></i>
            </span>
            <input type="text" class="form-control border-start-0 ps-0" id="login" name="login" placeholder="Usuário (nome.sobrenome)" required autofocus>
          </div>

          <div class="input-group mb-4 shadow-sm rounded bg-white">
            <span class="input-group-text bg-transparent border-end-0 text-success">
              <i class="bi bi-lock-fill fs-5"></i>
            </span>
            <input type="password" class="form-control border-start-0 border-end-0 ps-0" id="senha" name="senha" placeholder="Senha da Rede" required>
            <button class="btn border border-start-0 bg-transparent text-muted" type="button" id="btn-toggle-senha" title="Mostrar/Ocultar Senha">
              <i class="bi bi-eye-fill" id="icone-senha"></i>
            </button>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="lembrarsenha.php" class="small text-success text-decoration-none fw-semibold">Esqueceu a senha?</a>
          </div>

          <button class="w-100 btn btn-lg btn-success shadow-sm fw-bold" type="submit">
            <i class="bi bi-box-arrow-in-right me-2"></i> Entrar no Sistema
          </button>

        </form>
      </div>
    </div>

    <p class="mt-4 mb-3 text-muted small">&copy; <?php echo date("Y"); ?> NTI - IFNMG Campus Almenara</p>

  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    const togglePassword = document.querySelector('#btn-toggle-senha');
    const password = document.querySelector('#senha');
    const iconeSenha = document.querySelector('#icone-senha');

    togglePassword.addEventListener('click', function(e) {
      const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
      password.setAttribute('type', type);

      if (type === 'text') {
        iconeSenha.classList.remove('bi-eye-fill');
        iconeSenha.classList.add('bi-eye-slash-fill');
      } else {
        iconeSenha.classList.remove('bi-eye-slash-fill');
        iconeSenha.classList.add('bi-eye-fill');
      }
    });
  </script>
</body>

</html>