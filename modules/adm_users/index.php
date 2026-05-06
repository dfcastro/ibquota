<?php

/**
 * IBQUOTA 3 - GG (Gerenciador Gráfico)
 * Lista Usuários Administrativos (Atualizado)
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
  sec_session_start();
}

// Proteção: Apenas Admin (Nível 2) acessa a gestão de acessos
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] != 2) {
  header("Location: /gg/login");
  exit();
}

include __DIR__ . '/../../core/layout/header.php';

// PAGINAÇÃO
if (!defined('QTDE_POR_PAGINA')) define('QTDE_POR_PAGINA', 20);

$p = (isset($_GET['p'])) ? (int)$_GET['p'] : 1;
$p = ($p < 1) ? 1 : $p;
$p_inicio = (QTDE_POR_PAGINA * $p) - QTDE_POR_PAGINA;
$p_qtde_por_pagina = (int)QTDE_POR_PAGINA;
$p_num_registros = 0;

if ($num_stmt = $mysqli->prepare("SELECT count(*) FROM adm_users")) {
  $num_stmt->execute();
  $num_stmt->bind_result($p_num_registros);
  $num_stmt->fetch();
  $num_stmt->close();
}

// Busca usuarios
if ($stmt = $mysqli->prepare("SELECT cod_adm_users, login, nome, email, permissao FROM adm_users ORDER BY permissao DESC, login LIMIT ?, ?")) {
  $stmt->bind_param('ii', $p_inicio, $p_qtde_por_pagina);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($cod_adm_users, $login, $nome, $email, $permissao);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
  <div>
    <h3 class="fw-bold text-dark mb-0"><i class="bi bi-people-fill text-primary me-2"></i> Gestão de Administradores</h3>
    <p class="text-muted mb-0 small">Controle quem tem acesso ao painel do IBQuota</p>
  </div>
  <a href="../../index.php" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-left me-1"></i> Voltar ao Dashboard</a>
</div>

<?php
// ===== SISTEMA DE ALERTAS =====
if (isset($_GET['msg'])) {
  $mensagens = [
    'add' => 'Novo administrador cadastrado com sucesso!',
    'edit' => 'Privilégios e dados atualizados com sucesso!',
    'del' => 'Administrador excluído do sistema.',
    'erro_404' => 'Erro: Administrador não encontrado.'
  ];

  $tipo = 'success';
  $icone = 'bi-check-circle-fill';

  if ($_GET['msg'] == 'del') {
    $tipo = 'warning text-dark';
    $icone = 'bi-exclamation-circle-fill';
  } elseif ($_GET['msg'] == 'erro_404') {
    $tipo = 'danger';
    $icone = 'bi-exclamation-triangle-fill';
  }

  if (array_key_exists($_GET['msg'], $mensagens)) {
    echo "<div class='alert alert-{$tipo} alert-dismissible fade show shadow-sm mb-4' role='alert'>
            <i class='bi {$icone} me-2'></i> <strong>{$mensagens[$_GET['msg']]}</strong>
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
          </div>";
  }
}
?>

<div class="row">
  <div class="col-lg-8 mb-4">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white fw-bold py-3"><i class="bi bi-list-ul me-2"></i>Usuários Cadastrados</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Login</th>
                <th>Nome e Contato</th>
                <th class="text-end pe-4">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($stmt->fetch()) { ?>
                <tr>
                  <td class="ps-4">
                    <b class="d-block text-dark"><?php echo htmlspecialchars($login); ?></b>
                    <?php
                    // Etiquetas de Permissão dinâmicas
                    if ($permissao == 3) echo '<span class="badge bg-danger rounded-pill"><i class="bi bi-star-fill me-1"></i>Diretor(a)</span>';
                    elseif ($permissao == 2) echo '<span class="badge bg-primary rounded-pill">Admin Geral</span>';
                    elseif ($permissao == 1) echo '<span class="badge bg-success rounded-pill">Admin Impressão</span>';
                    else echo '<span class="badge bg-secondary rounded-pill">Visualiza Relatórios</span>';
                    ?>
                  </td>
                  <td>
                    <span class="d-block text-dark fw-semibold"><?php echo htmlspecialchars($nome); ?></span>
                    <small class="text-muted"><?php echo htmlspecialchars($email); ?></small>
                  </td>
                  <td class="text-end pe-4">
                    <!-- CÓDIGO NOVO (CORRETO E BLINDADO) -->
                    <!-- CÓDIGO NOVO (CORRETO E BLINDADO) -->
                    <a href="<?php echo $BASE_URL; ?>/admin/usuarios/editar?cod_adm_users=<?php echo $cod_adm_users; ?>" class="btn btn-outline-primary btn-sm shadow-sm" title="Editar"><i class="bi bi-pencil-square"></i></a>

                    <a href="<?php echo $BASE_URL; ?>/admin/usuarios/excluir?cod_adm_users=<?php echo $cod_adm_users; ?>" class="btn btn-outline-danger btn-sm ms-1 shadow-sm" title="Excluir" onclick="return confirm('ATENÇÃO: Deseja realmente excluir este administrador? Ele perderá acesso ao painel imediatamente.');"><i class="bi bi-trash3"></i></a>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer bg-white py-3">
        <?php barra_de_paginas($p, $p_num_registros); ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm border-0 border-top border-success border-3">
      <div class="card-header bg-white fw-bold py-3"><i class="bi bi-person-plus-fill text-success me-2"></i>Novo Acesso</div>
      <div class="card-body bg-light">
        <form action="<?php echo $BASE_URL; ?>/admin/usuarios/add" method="post">
          <input type="hidden" name="csrf_token" value="<?php echo gerar_csrf_token(); ?>">
          <div class="mb-3">
            <label class="form-label fw-bold small text-muted">Login de Acesso</label>
            <input type="text" class="form-control" name="login" placeholder="Ex: diretor.geral" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold small text-muted">Senha</label>
            <input type="password" class="form-control" name="senha" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold small text-muted">Nome Completo</label>
            <input type="text" class="form-control" name="nome" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold small text-muted">E-mail</label>
            <input type="email" class="form-control" name="email">
          </div>
          <div class="mb-4">
            <label class="form-label fw-bold small text-muted">Nível de Permissão</label>
            <select class="form-select border-success" name="permissao">
              <option value="3" class="text-danger fw-bold">Nível 3: Diretor Geral (Aprova Exceções)</option>
              <option value="2">Nível 2: Administrador do NTI</option>
              <option value="1" selected>Nível 1: Admin. de Impressão</option>
              <option value="0">Nível 0: Visualiza Relatórios</option>
            </select>
          </div>
          <button type="submit" class="btn btn-success w-100 fw-bold"><i class="bi bi-check-circle me-2"></i>Cadastrar Usuário</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../core/layout/footer.php'; ?>