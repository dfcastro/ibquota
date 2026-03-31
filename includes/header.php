<?php

/**
 * IBQUOTA 3
 * GG - Gerenciador Grafico do IBQUOTA
 * Cabecalho das paginas com Identidade IFNMG.
 */

if (file_exists("css")) {
  $path_raiz = "";
} else {
  $path_raiz = "../";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quota de Impressão (IBQUOTA 3)</title>
  <meta name="description" content="Controle de Quota de Impressão">
  <link rel="icon" href="/favicon.png" />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <link rel="stylesheet" href="<?php echo $path_raiz; ?>css/ifnmg.css">
</head>

<body>

  <script>
    // Se o calendário não abrir, teremos que trazer o jQuery do footer para cima desta linha.
    $(function() {
      $("#txtDataInicial").datepicker();
    });
  </script>

  <nav class="navbar navbar-expand-lg navbar-dark bg-ifnmg shadow-sm mb-4">
    <div class="container-fluid">
      <a class="navbar-brand fw-bold" href="#">IBQuota 3</a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item">
            <a class="nav-link active" aria-current="page" href="<?php echo $path_raiz; ?>index.php">Home</a>
          </li>

          <?php if (isset($_SESSION['permissao']) && $_SESSION['permissao'] > 0) { ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle text-white" href="#" id="dropCadastros" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                Cadastros
              </a>
              <ul class="dropdown-menu" aria-labelledby="dropCadastros">
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>usuarios/">Usuários</a></li>
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>grupos/">Grupos</a></li>
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>usuarios/usuario_quota_add.php">Quota adicional</a></li>
              </ul>
            </li>
          <?php } ?>

          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-white" href="#" id="dropRelatorios" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              Relatórios
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropRelatorios">
              <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>relatorios/impressoes.php">Impressões</a></li>
              <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>relatorios/impressoes_detalhadas.php">Impressões detalhadas</a></li>
              <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>relatorios/auditoria_impressoes.php">Auditoria Impressões</a></li>
              <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>relatorios/impressoes_com_erro.php">Impressões com erro</a></li>
              <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>relatorios/usuarios.php">Pesquisar quota de usuário</a></li>
              <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>relatorios/quota_por_usuario.php">Quota por usuário</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>
              <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>relatorios/ibquota_logs.php">Logs do CUPS</a></li>
            </ul>
          </li>

          <?php if (isset($_SESSION['permissao']) && $_SESSION['permissao'] == 2) { ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle text-white" href="#" id="dropAvancado" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                Avançado
              </a>
              <ul class="dropdown-menu" aria-labelledby="dropAvancado">
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>configuracao.php">Configuração Geral</a></li>
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>politicas/">Políticas de Impressão</a></li>
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>politicas/init_quota_politica.php">Inicializa Quota de Impressão</a></li>
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>adm_users/">Usuários Administrativos</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>test_ldap.php">Teste de conexão LDAP</a></li>
              </ul>
            </li>
          <?php } ?>
        </ul>

        <ul class="navbar-nav ms-auto">
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-white" href="#" id="dropConta" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              ⚙️ Conta
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropConta">
              <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>trocarsenha.php">Trocar Senha</a></li>
              <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>ajuda.php">Ajuda</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>
              <li><a class="dropdown-item text-danger" href="<?php echo $path_raiz; ?>includes/logout.php">Sair</a></li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="content container">