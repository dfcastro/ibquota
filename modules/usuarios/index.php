<?php

/**
 * IBQUOTA 3
 * GG - Gerenciador Grafico do IBQUOTA
 * Lista Usuarios - Com Painel Raio-X Integrado (Bootstrap 5)
 */
include_once '../../core/db.php';
include_once '../../core/functions.php';

sec_session_start();

if (!isset($_SESSION['usuario']) || (isset($_SESSION['permissao']) && $_SESSION['permissao'] < 1)) {
  header("Location: ../../public/login.php");
  exit();
}

include '../../core/layout/header.php';

// SISTEMA DE BUSCA E PAGINAÇÃO
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$like_q = "%" . $q . "%";

$p = (isset($_GET['p'])) ? (int)$_GET['p'] : 1;
$p = ($p < 1) ? 1 : $p;
$p_inicio = (QTDE_POR_PAGINA * $p) - QTDE_POR_PAGINA;
$p_qtde_por_pagina = (int)QTDE_POR_PAGINA;
$p_num_registros = 0;

if ($q != '') {
  if ($num_stmt = $mysqli->prepare("SELECT count(*) FROM usuarios WHERE usuario LIKE ?")) {
    $num_stmt->bind_param('s', $like_q);
    $num_stmt->execute();
    $num_stmt->bind_result($p_num_registros);
    $num_stmt->fetch();
    $num_stmt->close();
  }
  $stmt = $mysqli->prepare("SELECT cod_usuario, usuario FROM usuarios WHERE usuario LIKE ? ORDER BY usuario LIMIT ?, ?");
  $stmt->bind_param('sii', $like_q, $p_inicio, $p_qtde_por_pagina);
} else {
  if ($num_stmt = $mysqli->prepare("SELECT count(*) FROM usuarios")) {
    $num_stmt->execute();
    $num_stmt->bind_result($p_num_registros);
    $num_stmt->fetch();
    $num_stmt->close();
  }
  $stmt = $mysqli->prepare("SELECT cod_usuario, usuario FROM usuarios ORDER BY usuario LIMIT ?, ?");
  $stmt->bind_param('ii', $p_inicio, $p_qtde_por_pagina);
}

$stmt->execute();
$stmt->store_result();
$stmt->bind_result($cod_usuario, $usuario);
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
  <div>
    <h3 class="fw-bold text-dark mb-0"><i class="bi bi-people text-muted me-2"></i> Usuários do Sistema</h3>
    <p class="text-muted mb-0 small">Gerencie as contas e acompanhe o saldo de impressão em tempo real.</p>
  </div>
  <div>
    <button type="button" class="btn btn-ifnmg shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddUsuario">
      <i class="bi bi-person-plus-fill me-1"></i> Novo Usuário
    </button>
    <a href="sincronizar_ad.php" class="btn btn-primary fw-bold shadow-sm" onclick="return confirm('Isso fará uma varredura no Active Directory e importará todos os usuários ativos que ainda não estão no IBQuota. Deseja continuar?');">
      <i class="bi bi-arrow-repeat me-1"></i> Sincronizar com o AD
    </a>
  </div>
</div>

<?php
// ===== SISTEMA DE ALERTAS =====
if (isset($_GET['msg'])) {
  $msg_key = $_GET['msg'];
  $mensagens = [
    'add' => 'Usuário cadastrado e vinculado com sucesso!',
    'erro_existe' => 'Atenção: Este login de rede já está cadastrado no sistema!',
    'edit' => 'Dados do usuário atualizados com sucesso!',
    'del' => 'Usuário e suas cotas foram excluídos do sistema.'
  ];

  // Se for uma das mensagens curtas padronizadas
  if (array_key_exists($msg_key, $mensagens)) {
    $tipo = 'success';
    $icone = 'bi-check-circle-fill';

    if ($msg_key == 'erro_existe') {
      $tipo = 'danger';
      $icone = 'bi-exclamation-triangle-fill';
    } elseif ($msg_key == 'del') {
      $tipo = 'warning text-dark';
      $icone = 'bi-exclamation-circle-fill';
    }

    echo "<div class='alert alert-{$tipo} alert-dismissible fade show shadow-sm mb-4' role='alert'>
                <i class='bi {$icone} me-2'></i> <strong>{$mensagens[$msg_key]}</strong>
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
  }
  // Se for uma mensagem dinâmica gigante (como a do Sincronizar AD)
  else {
    $tipo_custom = isset($_GET['tipo']) ? htmlspecialchars($_GET['tipo']) : 'info';
    $icone_custom = ($tipo_custom == 'success') ? 'bi-check-circle-fill' : (($tipo_custom == 'danger') ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');

    // Exibe o texto exato que o script do AD mandou
    echo "<div class='alert alert-{$tipo_custom} alert-dismissible fade show shadow-sm mb-4' role='alert'>
                <i class='bi {$icone_custom} me-2'></i> " . urldecode($msg_key) . "
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
  }
}
?>

<div class="card shadow-sm border-0 mb-4">
  <div class="card-body p-3 bg-light rounded">
    <form action="" method="GET" class="row gx-2 gy-2 align-items-center">
      <div class="col-md-9">
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" class="form-control border-start-0" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Buscar usuário por nome (ex: joao.silva)...">
        </div>
      </div>
      <div class="col-md-3 d-grid gap-2 d-md-flex justify-content-md-end">
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-search me-1"></i> Buscar</button>
        <?php if ($q != '') { ?>
          <a href="index.php" class="btn btn-outline-secondary">Limpar</a>
        <?php } ?>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="table-responsive">
    <table class="table table-hover table-striped align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="ps-4">Nome (Rede)</th>
          <th>Grupos Vinculados</th>
          <th>Saldo de Cota</th>
          <th class="text-end pe-4">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($stmt->num_rows > 0) {
          while ($stmt->fetch()) {
            $usuario_safe = htmlspecialchars($usuario);
            echo "<tr>";
            echo "<td class='ps-4 fw-semibold text-dark'><i class='bi bi-person-circle text-muted me-2'></i>{$usuario_safe}</td>";

            // ===== COLUNA 2: BUSCA OS GRUPOS =====
            echo "<td>";
            $res_grupos = $mysqli->query("SELECT g.grupo FROM grupo_usuario gu JOIN grupos g ON g.cod_grupo = gu.cod_grupo WHERE gu.cod_usuario = $cod_usuario");
            if ($res_grupos->num_rows > 0) {
              while ($g = $res_grupos->fetch_assoc()) {
                echo "<span class='badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle me-1 fw-normal'><i class='bi bi-diagram-3 me-1'></i>{$g['grupo']}</span>";
              }
            } else {
              echo "<span class='text-danger small fst-italic'><i class='bi bi-exclamation-circle'></i> Sem grupo</span>";
            }
            echo "</td>";

            // ===== COLUNA 3: BUSCA O SALDO DE COTA =====
            echo "<td>";
            // A cota ativa fica na tabela quota_usuario, cruzada com politicas para ver se é infinita
            $res_quota = $mysqli->query("SELECT qu.quota, p.quota_infinita, p.nome FROM quota_usuario qu JOIN politicas p ON p.cod_politica = qu.cod_politica WHERE qu.usuario = '$usuario_safe'");

            if ($res_quota->num_rows > 0) {
              while ($q_data = $res_quota->fetch_assoc()) {
                if ($q_data['quota_infinita'] == 1) {
                  echo "<span class='badge text-bg-success shadow-sm' title='Política: {$q_data['nome']}'><i class='bi bi-infinity'></i> Ilimitada</span><br>";
                } else {
                  $saldo = $q_data['quota'];
                  // Cores dinâmicas para o saldo
                  $cor_badge = ($saldo > 20) ? 'primary' : (($saldo > 0) ? 'warning text-dark' : 'danger');
                  echo "<span class='badge text-bg-{$cor_badge} shadow-sm' title='Política: {$q_data['nome']}'><i class='bi bi-files'></i> {$saldo} págs</span><br>";
                }
              }
            } else {
              // Se o usuário ainda não enviou impressão ou não gerou quota ativa no banco
              echo "<span class='text-muted small'><i class='bi bi-hourglass-split'></i> Aguardando Uso</span>";
            }
            echo "</td>";

            // ===== COLUNA 4: AÇÕES =====
            echo "<td class='text-end pe-4'>";
            echo "<a href='usuario_gerenciar.php?cod_usuario={$cod_usuario}' class='btn btn-sm btn-outline-primary me-1' title='Gerenciar Grupos e Quotas'><i class='bi bi-gear-fill me-1'></i> Gerenciar</a>";
            echo "<a href='usuario_excluir.php?cod_usuario={$cod_usuario}' class='btn btn-sm btn-outline-danger' title='Excluir Usuário' onclick=\"return confirm('Tem certeza que deseja excluir o servidor {$usuario_safe}?');\"><i class='bi bi-trash'></i></a>";
            echo "</td>";
            echo "</tr>";
          }
        } else {
          echo "<tr><td colspan='4' class='text-center text-muted py-4'><i class='bi bi-inbox fs-3 d-block mb-2'></i>Nenhum usuário encontrado.</td></tr>";
        }
        ?>
      </tbody>
    </table>
  </div>
</div>

<div class="d-flex justify-content-center mt-3">
  <?php barra_de_paginas($p, $p_num_registros); ?>
</div>

<div class="modal fade" id="modalAddUsuario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-ifnmg text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Cadastrar Usuário</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="usuario_add.php" method="post">
        <div class="modal-body p-4">
          <div class="alert alert-info small shadow-sm border-0 mb-4">
            <i class="bi bi-info-circle-fill me-1"></i> Digite exatamente o login de rede (ex: <b>nome.sobrenome</b>).
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Login do Usuário</label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
              <input type="text" class="form-control" name="usuario" placeholder="Ex: joao.silva" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Vincular a um Grupo</label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-diagram-3"></i></span>
              <select class="form-select" name="cod_grupo">
                <option value="0">-- Apenas cadastrar --</option>
                <?php
                $g_res = $mysqli->query("SELECT cod_grupo, grupo FROM grupos ORDER BY grupo");
                while ($g_row = $g_res->fetch_assoc()) {
                  echo "<option value='{$g_row['cod_grupo']}'>{$g_row['grupo']}</option>";
                }
                ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-ifnmg fw-bold"><i class="bi bi-save me-1"></i> Salvar Cadastro</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../../core/layout/footer.php'; ?>