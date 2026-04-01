<?php

/**
 * IBQUOTA 3 - LOGIN HÍBRIDO (CHAMANDO FUNÇÕES NATIVAS)
 */
include_once 'includes/db.php';
include_once 'includes/functions.php';

sec_session_start();

$erro = "";

if (isset($_POST['login'], $_POST['senha'])) {
  $usuario = trim(strtolower($_POST['login']));
  $senha = $_POST['senha'];

  // --- TENTATIVA 1: LOGIN ADMIN (Usando a função nativa do sistema) ---
  // A função login($usuario, $senha, $mysqli) já faz todo o SHA512 + Salt corretamente
  if (login($usuario, $senha, $mysqli) == true) {
    // Sucesso como Admin! A função login() já criou todas as $_SESSION corretamente.
    header("Location: index.php");
    exit();
  }

  // --- TENTATIVA 2: ACTIVE DIRECTORY (Se não for admin) ---
  $stmt_ldap = $mysqli->prepare("SELECT LDAP_server, LDAP_port, LDAP_base, LDAP_user, LDAP_password FROM config_geral WHERE id = 1");
  $stmt_ldap->execute();
  $stmt_ldap->bind_result($ldap_server, $ldap_porta, $ldap_base, $ldap_usuario, $ldap_senha);
  $stmt_ldap->fetch();
  $stmt_ldap->close();

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

            // Senha do AD OK! Verifica se está sincronizado
            $stmt_chk = $mysqli->prepare("SELECT usuario FROM usuarios WHERE usuario = ?");
            $stmt_chk->bind_param('s', $usuario);
            $stmt_chk->execute();
            $stmt_chk->store_result();

            if ($stmt_chk->num_rows > 0) {
              // Criamos a sessão MANUAL para o usuário comum (Portal do Servidor)
              // Importante: Usamos as mesmas variáveis que o login_check() espera
              $_SESSION['usuario'] = $usuario;
              $_SESSION['permissao'] = 1;

              // Esta linha é vital para o functions.php aceitar o login:
              $_SESSION['login_string'] = hash('sha512', $senha . $_SERVER['HTTP_USER_AGENT']);

              header("Location: meu_painel.php");
              exit();
            } else {
              $erro = "Usuário autenticado no AD, mas não encontrado no IBQuota. Sincronize no painel Admin.";
            }
          } else {
            $erro = "Senha de rede incorreta.";
          }
        } else {
          $erro = "Usuário ou senha incorretos.";
        }
      }
      ldap_close($ldapconn);
    }
  } else {
    $erro = "Usuário ou senha incorretos.";
  }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="favicon.png" />
  <title>Portal de Impressão - IFNMG</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="css/ifnmg.css" rel="stylesheet">

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
  </style>
</head>

<body class="login-page text-center">

  <main class="form-signin">
    <div class="card shadow-sm border-0 border-top border-success border-4 rounded-3">

      <div class="card-body p-4">
        <img class="logo-campus img-fluid mb-3" src="png/logo_almenara.jpg" alt="Logo IFNMG Campus Almenara" onerror="this.style.display='none'">

        <h4 class="mb-1 fw-bold text-dark">Portal de Impressão</h4>
        <p class="text-muted small mb-3">Acesso para Servidores e Equipe NTI</p>

        <div class="alert alert-info py-2 small shadow-sm text-start mb-4" role="alert">
          💡 <b>Atenção:</b> Utilize o seu usuário no formato <b>nome.sobrenome</b> e a mesma senha usada para acessar os computadores do campus.
        </div>

        <?php if (!empty($erro)) { ?>
          <div class="alert alert-danger py-2 small fw-bold shadow-sm" role="alert">
            ⚠️ <?php echo $erro; ?>
          </div>
        <?php } ?>

        <form action="login.php" method="post" name="login_form">

          <div class="input-group mb-3 shadow-sm rounded">
            <span class="input-group-text bg-light border-end-0">
              <img src="png/icon-username.png" width="18" alt="Usuário">
            </span>
            <input type="text" class="form-control border-start-0 ps-0 bg-light" id="login" name="login" placeholder="Usuário (nome.sobrenome)" required autofocus>
          </div>

          <div class="input-group mb-3 shadow-sm rounded">
            <span class="input-group-text bg-light border-end-0">
              <img src="png/icon-password.png" width="18" alt="Senha">
            </span>
            <input type="password" class="form-control border-start-0 ps-0 bg-light" id="senha" name="senha" placeholder="Senha da Rede" required>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="lembrarsenha.php" class="small link-ifnmg">Problemas com a senha?</a>
          </div>

          <button class="w-100 btn btn-lg btn-ifnmg shadow-sm fw-bold" type="submit">Entrar</button>

        </form>
      </div>
    </div>

    <p class="mt-4 mb-3 text-muted small">&copy; <?php echo date("Y"); ?> NTI - IFNMG Campus Almenara</p>

  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>