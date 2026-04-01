<?php

/**
 * IBQUOTA 3
 * GG - Gerenciador Grafico do IBQUOTA
 * Pagina de login Unificada (Bootstrap 5 + Identidade IFNMG)
 */
include_once 'includes/db.php';
include_once 'includes/functions.php';

if (primeiro_acesso($mysqli) == true) {
  header("Location: primeiro_acesso.php");
  exit();
}

sec_session_start();

if (login_check($mysqli) == true) {
  header("Location: includes/logout.php");
  exit();
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
      /* Levemente mais largo para acomodar o texto explicativo */
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

        <?php if (isset($_GET['error'])) { ?>
          <div class="alert alert-danger py-2 small fw-bold shadow-sm" role="alert">
            ⚠️ Credenciais incorretas. Tente novamente!
          </div>
        <?php } ?>

        <form action="includes/process_login.php" method="post" name="login_form">

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