<?php

/**
 * IFQUOTA 3
 * Cabecalho Refatorado - Navbar Superior (Bootstrap 5 + Rotas Inteligentes)
 */
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// ==========================================
// DETEÇÃO INTELIGENTE DE AMBIENTE
// ==========================================
$host_atual = $_SERVER['HTTP_HOST'];
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

// Segurança das Permissões do Menu
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

  <nav class="navbar navbar-expand-lg navbar-dark bg-ifnmg shadow-sm mb-4">
    <div class="container-fluid">
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
            <!-- MENU: GESTÃO E CADASTROS -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown"><i class="bi bi-folder2-open"></i> Cadastros</a>
              <ul class="dropdown-menu shadow-sm">
                <li class="dropdown-header small text-uppercase fw-bold">Utilizadores</li>
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/contas"><i class="bi bi-people text-muted me-2"></i>Contas da Rede</a></li>
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/grupos"><i class="bi bi-diagram-3 text-muted me-2"></i>Grupos</a></li>

                <?php if ($nivel >= 2) { ?>
                  <li>
                    <hr class="dropdown-divider">
                  </li>
                  <li class="dropdown-header small text-uppercase fw-bold">Infraestrutura</li>
                  <!-- NOVAS OPÇÕES ADICIONADAS AQUI -->
                  <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/locais"><i class="bi bi-geo-alt text-muted me-2"></i>Locais/Setores</a></li>
                  <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/impressoras"><i class="bi bi-printer-fill text-muted me-2"></i>Setup de Impressoras</a></li>
                <?php } ?>
              </ul>
            </li>

            <!-- MENU: RELATÓRIOS -->
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
            <!-- FILA COLORIDA E SOLICITAÇÕES (DESTAQUE) -->
            <li class="nav-item ms-lg-2 border-start border-light ps-lg-2">
              <a class="nav-link text-warning fw-bold" href="<?php echo $BASE_URL; ?>/admin/coloridas"><i class="bi bi-palette-fill"></i> Fila Colorida</a>
            </li>

            <li class="nav-item">
              <a class="nav-link text-info fw-bold" href="<?php echo $BASE_URL; ?>/admin/solicitacoes"><i class="bi bi-inbox-fill"></i> Pedidos</a>
            </li>

            <!-- MENU: CONFIGURAÇÕES DE SISTEMA -->
            <li class="nav-item dropdown ms-lg-2 border-start border-light ps-lg-2">
              <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown"><i class="bi bi-gear"></i> Sistema</a>
              <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/configuracao"><i class="bi bi-sliders text-muted me-2"></i>Configurações Gerais</a></li>
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/politicas"><i class="bi bi-shield-check text-muted me-2"></i>Políticas de Impressão</a></li>
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/init-quotas"><i class="bi bi-arrow-clockwise text-muted me-2"></i>Inicializar Quotas</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/usuarios"><i class="bi bi-person-badge text-muted me-2"></i>Admins do Painel</a></li>
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/auditoria"><i class="bi bi-shield-lock text-danger me-2"></i>Auditoria de Acessos</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/admin/teste-ldap"><i class="bi bi-hdd-network text-muted me-2"></i>Teste LDAP</a></li>
              </ul>
            </li>

            <li class="nav-item">
              <a class="nav-link" href="<?php echo $BASE_URL; ?>/admin/documentacao"><i class="bi bi-book text-muted me-2"></i> Manual</a>
            </li>
          <?php } ?>

        </ul>

        <ul class="navbar-nav ms-auto">
          <li class="nav-item me-3 d-none d-lg-flex align-items-center">
            <a href="<?php echo $BASE_URL; ?>/web-print" class="btn btn-sm btn-outline-light rounded-pill px-3"><i class="bi bi-cloud-arrow-up-fill me-1"></i> Web Print</a>
          </li>

          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-white fw-bold" href="#" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle fs-5 align-middle me-1"></i> <?php echo isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Conta'; ?>
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