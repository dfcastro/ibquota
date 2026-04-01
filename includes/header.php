<?php

/**
 * IBQUOTA 3
 * Cabecalho Refatorado - Navbar Superior (Bootstrap 5)
 */
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (file_exists("css")) {
  $path_raiz = "";
} else {
  $path_raiz = "../";
}

// Segurança das Permissões do Menu
$nivel = isset($_SESSION['permissao']) ? (int)$_SESSION['permissao'] : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Portal de Impressão - IFNMG</title>
  <link rel="icon" href="/favicon.png" />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <link rel="stylesheet" href="<?php echo $path_raiz; ?>css/ifnmg.css">
</head>

<body>

  <script>
    $(function() {
      if (typeof $("#txtDataInicial").datepicker === "function") {
        $("#txtDataInicial").datepicker();
      }
    });
  </script>

  <nav class="navbar navbar-expand-lg navbar-dark bg-ifnmg shadow-sm mb-4">
    <div class="container-fluid">
      <a class="navbar-brand fw-bold" href="<?php echo $path_raiz; ?>index.php">🖨️ IBQuota</a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="menuPrincipal">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item">
            <a class="nav-link active" href="<?php echo $path_raiz; ?>index.php"><i class="bi bi-house-door"></i> Início</a>
          </li>

          <?php if ($nivel > 0) { ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown"><i class="bi bi-folder2-open"></i> Cadastros</a>
              <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>usuarios/"><i class="bi bi-people text-muted me-2"></i>Usuários</a></li>
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>grupos/"><i class="bi bi-diagram-3 text-muted me-2"></i>Grupos</a></li>
              </ul>
            </li>

            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown"><i class="bi bi-bar-chart"></i> Relatórios</a>
              <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>relatorios/impressoes.php">Histórico Geral</a></li>
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>relatorios/impressoes_com_erro.php">Impressões com Erro</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>relatorios/ibquota_logs.php"><i class="bi bi-card-text text-muted me-2"></i>Logs do CUPS</a></li>
              </ul>
            </li>
          <?php } ?>

          <?php if ($nivel === 2) { ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown"><i class="bi bi-gear"></i> Sistema</a>
              <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>configuracao.php">Configurações</a></li>
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>politicas/">Políticas</a></li>
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>politicas/init_quota_politica.php">Inicializar Quotas</a></li>
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>adm_users/">Usuários Adm</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>test_ldap.php"><i class="bi bi-hdd-network text-muted me-2"></i>Teste LDAP</a></li>
              </ul>
            </li>
          <?php } ?>
        </ul>

        <ul class="navbar-nav ms-auto">
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-white fw-bold" href="#" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle fs-5 align-middle"></i> <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Conta'; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-2">
              <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>trocarsenha.php"><i class="bi bi-key text-muted me-2"></i> Trocar Senha</a></li>
              <li><a class="dropdown-item" href="<?php echo $path_raiz; ?>ajuda.php"><i class="bi bi-question-circle text-muted me-2"></i> Ajuda</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>
              <li><a class="dropdown-item text-danger fw-bold" href="<?php echo $path_raiz; ?>includes/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Sair do Sistema</a></li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="content container">