<?php

/**
 * IBQUOTA 3
 * Cabecalho Refatorado - Navbar Superior (Bootstrap 5 + Rotas Inteligentes)
 */
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// ==========================================
// DETEÇÃO INTELIGENTE DE AMBIENTE (DEV vs PRODUÇÃO)
// ==========================================
$host_atual = $_SERVER['HTTP_HOST'];

if ($host_atual === 'localhost' || $host_atual === '127.0.0.1') {
  // Ambiente Local (XAMPP/WAMP)
  $BASE_URL = '/gg';
} else {
  // Servidor de Produção (impressao.almenara.ifnmg.edu.br)
  $BASE_URL = '';
}

// Segurança das Permissões do Menu
// 0 = Comum/Relatórios | 1 = Admin Impressão | 2 = Admin Geral (NTI) | 3 = Diretor
$nivel = isset($_SESSION['permissao']) ? (int)$_SESSION['permissao'] : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Portal de Impressão - IFNMG</title>
  <link rel="icon" href="<?php echo $BASE_URL; ?>/favicon.png" />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  <link rel="stylesheet" href="<?php echo $BASE_URL; ?>/assets/css/ifnmg.css">
</head>

<body>

  <script>
    // Inicia Datepicker se estiver nas telas de relatório
    $(function() {
      if (typeof $("#txtDataInicial").datepicker === "function") {
        $("#txtDataInicial").datepicker();
      }
    });
  </script>

  <nav class="navbar navbar-expand-lg navbar-dark bg-ifnmg shadow-sm mb-4">
    <div class="container-fluid">
      <!-- Rota Base (O Roteador decide se vai para Painel ou Dashboard) -->
      <a class="navbar-brand fw-bold" href="<?php echo $BASE_URL; ?>/">🖨️ IFQuota</a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="menuPrincipal">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">

          <li class="nav-item">
            <a class="nav-link active" href="<?php echo $BASE_URL; ?>/"><i class="bi bi-house-door"></i> Início</a>
          </li>

          <?php if ($nivel > 0) { ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown"><i class="bi bi-folder2-open"></i> Cadastros</a>
              <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/contas"><i class="bi bi-people text-muted me-2"></i>Usuários</a></li>
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/grupos"><i class="bi bi-diagram-3 text-muted me-2"></i>Grupos</a></li>
              </ul>
            </li>

            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown"><i class="bi bi-bar-chart"></i> Relatórios</a>
              <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/relatorio"><i class="bi bi-clock-history text-muted me-2"></i>Histórico Geral</a></li>
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/erros-cups"><i class="bi bi-exclamation-triangle text-muted me-2"></i>Impressões com Erro</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/logs"><i class="bi bi-terminal text-muted me-2"></i>Logs do CUPS</a></li>
              </ul>
            </li>
          <?php } ?>

          <?php if ($nivel >= 2) { ?>

            <li class="nav-item ms-lg-2 border-start border-light ps-lg-2">
              <a class="nav-link text-warning fw-bold" href="<?php echo $BASE_URL; ?>/admin/coloridas" title="Gerenciar Impressões Retidas">
                <i class="bi bi-palette-fill"></i> Fila Colorida
              </a>
            </li>

            <li class="nav-item ms-lg-2 border-start border-light ps-lg-2">
              <a class="nav-link text-info fw-bold" href="<?php echo $BASE_URL; ?>/admin/solicitacoes" title="Gerenciar Pedidos de Cotas Extras">
                <i class="bi bi-inbox-fill"></i> Pedidos de Cota
              </a>
            </li>

            <li class="nav-item dropdown ms-lg-2 border-start border-light ps-lg-2">
              <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown"><i class="bi bi-gear"></i> Sistema</a>
              <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/configuracao"><i class="bi bi-sliders text-muted me-2"></i>Configurações Gerais</a></li>
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/politicas"><i class="bi bi-shield-check text-muted me-2"></i>Políticas de Impressão</a></li>
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/init-quotas"><i class="bi bi-arrow-clockwise text-muted me-2"></i>Inicializar Quotas</a></li>
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/usuarios"><i class="bi bi-person-badge text-muted me-2"></i>Usuários Administrativos</a></li>

                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/auditoria"><i class="bi bi-shield-lock text-danger me-2"></i>Auditoria de Acessos</a></li>

                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/teste-ldap"><i class="bi bi-hdd-network text-muted me-2"></i>Teste Conexão LDAP</a></li>
              </ul>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo $BASE_URL; ?>/admin/documentacao">
                <i class="bi bi-book text-muted me-2"></i> Manual do Sistema
              </a>
            </li>
          <?php } ?>

        </ul>

        <ul class="navbar-nav ms-auto">

          <li class="nav-item me-3 d-none d-lg-flex align-items-center">
            <a href="<?php echo $BASE_URL; ?>/web-print" class="btn btn-sm btn-outline-light rounded-pill px-3"><i class="bi bi-cloud-arrow-up-fill me-1"></i> Web Print</a>
          </li>

          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-white fw-bold" href="#" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle fs-5 align-middle me-1"></i> <?php echo isset($_SESSION['usuario']) ? $_SESSION['usuario'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'Conta'); ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-2">
              <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/trocar-senha"><i class="bi bi-key text-muted me-2"></i> Trocar Senha</a></li>
              <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/ajuda"><i class="bi bi-question-circle text-muted me-2"></i> Ajuda</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>
              <li><a class="dropdown-item text-danger fw-bold" href="<?php echo $BASE_URL; ?>/logout"><i class="bi bi-box-arrow-right me-2"></i> Sair do Sistema</a></li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="content container">