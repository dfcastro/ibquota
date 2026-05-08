<?php

/**
 * IFQUOTA - Gestão de Contas da Rede
 * Lista Usuários - Filtro "Sem Grupo", "Pendentes de Cota" e Paginação Inteligente
 */
include_once __DIR__ . '/../../core/db.php';
include_once __DIR__ . '/../../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
  sec_session_start();
}

$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

if (!isset($_SESSION['usuario']) || (isset($_SESSION['permissao']) && $_SESSION['permissao'] < 1)) {
  header("Location: " . $BASE_URL . "/login");
  exit();
}

include __DIR__ . '/../../core/layout/header.php';

// ==========================================
// SISTEMA DE BUSCA, FILTRO E PAGINAÇÃO
// ==========================================
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$like_q = "%" . $q . "%";

// 0 = Todos, -1 = Sem Grupo, -2 = Pendente de Cota, > 0 = ID Específico
$filtro_grupo = (isset($_GET['grupo']) && $_GET['grupo'] != '') ? (int)$_GET['grupo'] : 0;

$p = (isset($_GET['p'])) ? (int)$_GET['p'] : 1;
$p = ($p < 1) ? 1 : $p;
$p_inicio = (QTDE_POR_PAGINA * $p) - QTDE_POR_PAGINA;
$p_qtde_por_pagina = (int)QTDE_POR_PAGINA;
$p_num_registros = 0;

// Lógica SQL Inteligente para os filtros
if ($filtro_grupo == -1 && $q != '') {
  // Busca por NOME e SEM GRUPO
  $sql_count = "SELECT count(DISTINCT u.cod_usuario) FROM usuarios u LEFT JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario WHERE gu.cod_grupo IS NULL AND u.usuario LIKE ?";
  $num_stmt = $mysqli->prepare($sql_count);
  $num_stmt->bind_param('s', $like_q);

  $sql_data = "SELECT DISTINCT u.cod_usuario, u.usuario FROM usuarios u LEFT JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario WHERE gu.cod_grupo IS NULL AND u.usuario LIKE ? ORDER BY u.usuario LIMIT ?, ?";
  $stmt = $mysqli->prepare($sql_data);
  $stmt->bind_param('sii', $like_q, $p_inicio, $p_qtde_por_pagina);
} elseif ($filtro_grupo == -1) {
  // Busca APENAS SEM GRUPO
  $sql_count = "SELECT count(DISTINCT u.cod_usuario) FROM usuarios u LEFT JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario WHERE gu.cod_grupo IS NULL";
  $num_stmt = $mysqli->prepare($sql_count);

  $sql_data = "SELECT DISTINCT u.cod_usuario, u.usuario FROM usuarios u LEFT JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario WHERE gu.cod_grupo IS NULL ORDER BY u.usuario LIMIT ?, ?";
  $stmt = $mysqli->prepare($sql_data);
  $stmt->bind_param('ii', $p_inicio, $p_qtde_por_pagina);
} elseif ($filtro_grupo == -2 && $q != '') {
  // Busca APENAS "Pendentes de Cota" (Têm grupo, mas não têm cota) com busca por nome
  $sql_count = "SELECT count(DISTINCT u.cod_usuario) FROM usuarios u JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario LEFT JOIN quota_usuario qu ON u.usuario = qu.usuario WHERE qu.usuario IS NULL AND u.usuario LIKE ?";
  $num_stmt = $mysqli->prepare($sql_count);
  $num_stmt->bind_param('s', $like_q);

  $sql_data = "SELECT DISTINCT u.cod_usuario, u.usuario FROM usuarios u JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario LEFT JOIN quota_usuario qu ON u.usuario = qu.usuario WHERE qu.usuario IS NULL AND u.usuario LIKE ? ORDER BY u.usuario LIMIT ?, ?";
  $stmt = $mysqli->prepare($sql_data);
  $stmt->bind_param('sii', $like_q, $p_inicio, $p_qtde_por_pagina);
} elseif ($filtro_grupo == -2) {
  // Busca APENAS "Pendentes de Cota"
  $sql_count = "SELECT count(DISTINCT u.cod_usuario) FROM usuarios u JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario LEFT JOIN quota_usuario qu ON u.usuario = qu.usuario WHERE qu.usuario IS NULL";
  $num_stmt = $mysqli->prepare($sql_count);

  $sql_data = "SELECT DISTINCT u.cod_usuario, u.usuario FROM usuarios u JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario LEFT JOIN quota_usuario qu ON u.usuario = qu.usuario WHERE qu.usuario IS NULL ORDER BY u.usuario LIMIT ?, ?";
  $stmt = $mysqli->prepare($sql_data);
  $stmt->bind_param('ii', $p_inicio, $p_qtde_por_pagina);
} elseif ($filtro_grupo > 0 && $q != '') {
  // Busca por NOME e GRUPO ESPECÍFICO
  $sql_count = "SELECT count(DISTINCT u.cod_usuario) FROM usuarios u JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario WHERE gu.cod_grupo = ? AND u.usuario LIKE ?";
  $num_stmt = $mysqli->prepare($sql_count);
  $num_stmt->bind_param('is', $filtro_grupo, $like_q);

  $sql_data = "SELECT DISTINCT u.cod_usuario, u.usuario FROM usuarios u JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario WHERE gu.cod_grupo = ? AND u.usuario LIKE ? ORDER BY u.usuario LIMIT ?, ?";
  $stmt = $mysqli->prepare($sql_data);
  $stmt->bind_param('isii', $filtro_grupo, $like_q, $p_inicio, $p_qtde_por_pagina);
} elseif ($filtro_grupo > 0) {
  // Busca APENAS por GRUPO ESPECÍFICO
  $sql_count = "SELECT count(DISTINCT u.cod_usuario) FROM usuarios u JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario WHERE gu.cod_grupo = ?";
  $num_stmt = $mysqli->prepare($sql_count);
  $num_stmt->bind_param('i', $filtro_grupo);

  $sql_data = "SELECT DISTINCT u.cod_usuario, u.usuario FROM usuarios u JOIN grupo_usuario gu ON u.cod_usuario = gu.cod_usuario WHERE gu.cod_grupo = ? ORDER BY u.usuario LIMIT ?, ?";
  $stmt = $mysqli->prepare($sql_data);
  $stmt->bind_param('iii', $filtro_grupo, $p_inicio, $p_qtde_por_pagina);
} elseif ($q != '') {
  // Busca APENAS por NOME (Qualquer grupo ou sem grupo)
  $sql_count = "SELECT count(*) FROM usuarios WHERE usuario LIKE ?";
  $num_stmt = $mysqli->prepare($sql_count);
  $num_stmt->bind_param('s', $like_q);

  $sql_data = "SELECT cod_usuario, usuario FROM usuarios WHERE usuario LIKE ? ORDER BY usuario LIMIT ?, ?";
  $stmt = $mysqli->prepare($sql_data);
  $stmt->bind_param('sii', $like_q, $p_inicio, $p_qtde_por_pagina);
} else {
  // SEM FILTROS (Mostra todos)
  $sql_count = "SELECT count(*) FROM usuarios";
  $num_stmt = $mysqli->prepare($sql_count);

  $sql_data = "SELECT cod_usuario, usuario FROM usuarios ORDER BY usuario LIMIT ?, ?";
  $stmt = $mysqli->prepare($sql_data);
  $stmt->bind_param('ii', $p_inicio, $p_qtde_por_pagina);
}

$num_stmt->execute();
$num_stmt->bind_result($p_num_registros);
$num_stmt->fetch();
$num_stmt->close();

$stmt->execute();
$stmt->store_result();
$stmt->bind_result($cod_usuario, $usuario);

// Busca a lista de grupos para preencher os menus
$todos_os_grupos = [];
$res_grupos_lista = $mysqli->query("SELECT cod_grupo, grupo FROM grupos ORDER BY grupo");
while ($g = $res_grupos_lista->fetch_assoc()) {
  $todos_os_grupos[] = $g;
}

// === BUSCA AS REGRAS DE MAPEAMENTO PARA O MODAL ===
$mapeamentos_cadastrados = [];
$res_map = $mysqli->query("SELECT m.id, m.ou_ad, m.cargo_ad, g.grupo FROM mapeamento_ad m JOIN grupos g ON m.cod_grupo = g.cod_grupo ORDER BY m.ou_ad, m.cargo_ad");
if ($res_map) {
  while ($m = $res_map->fetch_assoc()) {
    $mapeamentos_cadastrados[] = $m;
  }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
  <div>
    <h3 class="fw-bold text-dark mb-0"><i class="bi bi-people text-muted me-2"></i> Usuários da Rede</h3>
    <p class="text-muted mb-0 small">Gerencie as contas e acompanhe o saldo de impressão do campus em tempo real.</p>
  </div>
  <div>
    <button type="button" class="btn btn-success shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddUsuario">
      <i class="bi bi-person-plus-fill me-1"></i> Novo Usuário
    </button>
    <button type="button" class="btn btn-info shadow-sm fw-bold text-white" data-bs-toggle="modal" data-bs-target="#modalMapeamentoAD">
      <i class="bi bi-diagram-3-fill me-1"></i> Regras do AD
    </button>
    <button type="button" class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalSyncAD">
      <i class="bi bi-arrow-repeat me-1"></i> Sincronizar com o AD
    </button>
  </div>
</div>

<?php
if (isset($_GET['msg'])) {
  $msg_key = $_GET['msg'];
  $mensagens = [
    'add' => 'Usuário cadastrado e vinculado com sucesso!',
    'erro_existe' => 'Atenção: Este login de rede já está cadastrado no sistema!',
    'edit' => 'Dados do usuário atualizados com sucesso!',
    'del' => 'Usuário e suas cotas foram excluídos do sistema.',
    'lote_ok' => 'Contas movidas e saldos atualizados com sucesso!',
    'lote_vazio' => 'Atenção: Nenhum utilizador ou grupo foi selecionado.'
  ];

  if (array_key_exists($msg_key, $mensagens)) {
    $tipo = 'success';
    $icone = 'bi-check-circle-fill';

    if (in_array($msg_key, ['erro_existe', 'lote_vazio'])) {
      $tipo = 'warning text-dark';
      $icone = 'bi-exclamation-triangle-fill';
    } elseif ($msg_key == 'del') {
      $tipo = 'danger';
      $icone = 'bi-trash-fill';
    }

    echo "<div class='alert alert-{$tipo} border-0 shadow-sm mb-4' role='alert'>
            <i class='bi {$icone} me-2'></i> <strong>{$mensagens[$msg_key]}</strong>
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
          </div>";
  } else {
    $tipo_custom = isset($_GET['tipo']) ? htmlspecialchars($_GET['tipo']) : 'info';
    $icone_custom = ($tipo_custom == 'success') ? 'bi-check-circle-fill' : (($tipo_custom == 'danger') ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');

    echo "<div class='alert alert-{$tipo_custom} border-0 shadow-sm mb-4' role='alert'>
            <i class='bi {$icone_custom} me-2'></i> " . urldecode($msg_key) . "
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
          </div>";
  }
}
?>

<div class="card shadow-sm border-0 mb-4 border-top border-primary border-4">
  <div class="card-body p-3 bg-light rounded">
    <form action="<?php echo $BASE_URL; ?>/admin/contas" method="GET" class="row gx-2 gy-2 align-items-center">
      <div class="col-md-5">
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" class="form-control border-start-0" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Buscar por login...">
        </div>
      </div>
      <div class="col-md-4">
        <select class="form-select border-primary" name="grupo">
          <option value="0">-- Todos os Grupos --</option>
          <option value="-1" <?php echo ($filtro_grupo == -1) ? 'selected' : ''; ?>>❌ Sem Grupo (Aguardando Ação)</option>
          <option value="-2" <?php echo ($filtro_grupo == -2) ? 'selected' : ''; ?>>⚠️ Pendentes de Cota (No Grupo)</option>
          <?php
          foreach ($todos_os_grupos as $g) {
            $selecionado = ($filtro_grupo == $g['cod_grupo']) ? 'selected' : '';
            echo "<option value='{$g['cod_grupo']}' {$selecionado}>{$g['grupo']}</option>";
          }
          ?>
        </select>
      </div>
      <div class="col-md-3 d-grid gap-2 d-md-flex justify-content-md-end">
        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="bi bi-filter me-1"></i> Filtrar</button>
        <?php if ($q != '' || $filtro_grupo != 0) { ?>
          <a href="<?php echo $BASE_URL; ?>/admin/contas" class="btn btn-outline-secondary">Limpar</a>
        <?php } ?>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm border-0">

  <form action="<?php echo $BASE_URL; ?>/admin/contas/lote" method="post" id="form-lote">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    <input type="hidden" name="acao" value="mover_grupo">

    <div class="bg-light p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap">
      <div class="form-check mb-2 mb-md-0 ms-2">
        <input class="form-check-input" type="checkbox" id="checkAll" style="transform: scale(1.2);">
        <label class="form-check-label fw-bold text-dark ms-1" for="checkAll">Selecionar Todos da Página</label>
      </div>
      <div class="d-flex align-items-center">
        <select class="form-select border-primary fw-semibold me-2" name="novo_cod_grupo" style="min-width: 250px;">
          <option value="" disabled selected>Mover marcados para...</option>
          <?php
          foreach ($todos_os_grupos as $g) {
            echo "<option value='{$g['cod_grupo']}'>{$g['grupo']}</option>";
          }
          ?>
        </select>
        <button type="submit" class="btn btn-primary fw-bold shadow-sm text-nowrap" onclick="return confirm('ATENÇÃO: Deseja mover todos os utilizadores selecionados para o novo grupo? (Eles receberão a cota na hora)');">
          <i class="bi bi-arrow-right-circle me-1"></i> Aplicar
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-4" style="width: 50px;"></th>
            <th>Nome (Rede)</th>
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
              echo "<td class='ps-4'><input class='form-check-input check-item border-secondary' type='checkbox' name='usuarios_selecionados[]' value='{$cod_usuario}' style='transform: scale(1.2);'></td>";
              echo "<td class='fw-semibold text-dark'><i class='bi bi-person-circle text-muted me-2'></i>{$usuario_safe}</td>";

              echo "<td>";
              $res_grupos = $mysqli->query("SELECT g.grupo FROM grupo_usuario gu JOIN grupos g ON g.cod_grupo = gu.cod_grupo WHERE gu.cod_usuario = $cod_usuario");
              $tem_algum_grupo = false;
              if ($res_grupos->num_rows > 0) {
                $tem_algum_grupo = true;
                while ($g = $res_grupos->fetch_assoc()) {
                  echo "<span class='badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle me-1 fw-normal'><i class='bi bi-diagram-3 me-1'></i>{$g['grupo']}</span>";
                }
              } else {
                echo "<span class='text-danger small fw-bold'><i class='bi bi-exclamation-circle-fill'></i> Sem grupo</span>";
              }
              echo "</td>";

              echo "<td>";
              // Busca as quotas do usuário
              $res_quota = $mysqli->query("SELECT qu.quota, p.quota_infinita, p.nome FROM quota_usuario qu JOIN politicas p ON p.cod_politica = qu.cod_politica WHERE qu.usuario = '$usuario_safe'");

              if ($res_quota->num_rows > 0) {
                // Tem cota oficial registada
                while ($q_data = $res_quota->fetch_assoc()) {
                  if ($q_data['quota_infinita'] == 1) {
                    echo "<span class='badge text-bg-success shadow-sm' title='Política: {$q_data['nome']}'><i class='bi bi-infinity'></i> Ilimitada</span><br>";
                  } else {
                    $saldo = $q_data['quota'];
                    $cor_badge = ($saldo > 20) ? 'primary' : (($saldo > 0) ? 'warning text-dark' : 'danger');
                    echo "<span class='badge text-bg-{$cor_badge} shadow-sm' title='Política: {$q_data['nome']}'><i class='bi bi-files'></i> {$saldo} págs</span><br>";
                  }
                }
              } else {
                // NÃO tem cota. Porquê?
                if ($tem_algum_grupo) {
                  echo "<span class='text-warning small fw-bold'><i class='bi bi-hourglass-split'></i> Pendente de Saldo</span><br>";
                  echo "<small class='text-muted' style='font-size:0.7rem'>Aguardando Injeção ou Impressão</small>";
                } else {
                  echo "<span class='text-danger small fw-bold'><i class='bi bi-slash-circle'></i> Sem Acesso</span><br>";
                  echo "<small class='text-muted' style='font-size:0.7rem'>Vincule a um grupo</small>";
                }
              }
              echo "</td>";

              echo "<td class='text-end pe-4'>";
              echo "<a href='{$BASE_URL}/admin/contas/gerenciar?cod_usuario={$cod_usuario}' class='btn btn-sm btn-outline-primary shadow-sm me-1' title='Gerenciar Grupos e Quotas'><i class='bi bi-gear-fill me-1'></i> Gerenciar</a>";
              echo "<a href='{$BASE_URL}/admin/contas/excluir?cod_usuario={$cod_usuario}' class='btn btn-sm btn-outline-danger shadow-sm' title='Excluir Usuário' onclick=\"return confirm('Tem certeza que deseja excluir a conta {$usuario_safe}?');\"><i class='bi bi-trash'></i></a>";
              echo "</td>";
              echo "</tr>";
            }
          } else {
            echo "<tr><td colspan='5' class='text-center text-muted py-5'><i class='bi bi-inbox fs-3 d-block mb-2'></i>Nenhum usuário encontrado.</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </form>
</div>

<div class="d-flex justify-content-center mt-4 mb-3">
  <?php
  $total_paginas = ceil($p_num_registros / $p_qtde_por_pagina);

  if ($total_paginas > 1) {
    echo '<nav><ul class="pagination shadow-sm">';

    // Constrói os parâmetros GET para manter os filtros ativos ao mudar de página
    $qs = [];
    if ($q != '') $qs['q'] = $q;
    if ($filtro_grupo != 0) $qs['grupo'] = $filtro_grupo;

    $qs_str = http_build_query($qs);
    $qs_str = $qs_str ? '&' . $qs_str : '';

    // Botão Anterior
    $prev_disabled = ($p == 1) ? 'disabled' : '';
    $prev_link = ($p > 1) ? "{$BASE_URL}/admin/contas?p=" . ($p - 1) . $qs_str : '#';
    echo "<li class='page-item {$prev_disabled}'><a class='page-link text-success' href='{$prev_link}'>Anterior</a></li>";

    // Números das Páginas
    $inicio_pag = max(1, $p - 2);
    $fim_pag = min($total_paginas, $p + 2);

    if ($inicio_pag > 1) {
      echo "<li class='page-item'><a class='page-link text-success' href='{$BASE_URL}/admin/contas?p=1{$qs_str}'>1</a></li>";
      if ($inicio_pag > 2) echo "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    for ($i = $inicio_pag; $i <= $fim_pag; $i++) {
      $active = ($i == $p) ? 'active' : '';
      $bg = ($i == $p) ? 'bg-success border-success text-white' : 'text-success';
      echo "<li class='page-item {$active}'><a class='page-link {$bg}' href='{$BASE_URL}/admin/contas?p={$i}{$qs_str}'>{$i}</a></li>";
    }

    if ($fim_pag < $total_paginas) {
      if ($fim_pag < $total_paginas - 1) echo "<li class='page-item disabled'><span class='page-link'>...</span></li>";
      echo "<li class='page-item'><a class='page-link text-success' href='{$BASE_URL}/admin/contas?p={$total_paginas}{$qs_str}'>{$total_paginas}</a></li>";
    }

    // Botão Próximo
    $next_disabled = ($p == $total_paginas) ? 'disabled' : '';
    $next_link = ($p < $total_paginas) ? "{$BASE_URL}/admin/contas?p=" . ($p + 1) . $qs_str : '#';
    echo "<li class='page-item {$next_disabled}'><a class='page-link text-success' href='{$next_link}'>Próxima</a></li>";

    echo '</ul></nav>';
  }
  ?>
</div>

<div class="modal fade" id="modalAddUsuario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Cadastrar Usuário Manual</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="<?php echo $BASE_URL; ?>/admin/contas/add" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        <div class="modal-body p-4">
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
                foreach ($todos_os_grupos as $g) {
                  echo "<option value='{$g['cod_grupo']}'>{$g['grupo']}</option>";
                }
                ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light border-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success fw-bold shadow-sm"><i class="bi bi-save me-1"></i> Salvar Cadastro</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalMapeamentoAD" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-diagram-3-fill me-2"></i>Regras de Sincronização do AD</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body p-4 bg-light">
        <div class="alert alert-primary small py-2 shadow-sm border-0">
          <i class="bi bi-info-circle-fill me-1"></i> Defina para qual grupo do IFQUOTA os usuários de uma UO devem ir automaticamente na sincronização.
        </div>

        <form action="<?php echo $BASE_URL; ?>/admin/contas/mapeamento_add" method="post" class="row gx-2 gy-2 align-items-end mb-4 bg-white p-3 rounded shadow-sm border border-info border-opacity-25">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
          <div class="col-md-4">
            <label class="form-label fw-bold small text-muted mb-1">Unidade (OU)</label>
            <input type="text" class="form-control form-control-sm border-info" name="ou_ad" placeholder="Ex: UO_Docentes" required>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-bold small text-muted mb-1">Cargo (Opcional)</label>
            <input type="text" class="form-control form-control-sm border-info" name="cargo_ad" placeholder="Ex: Substituto">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-bold small text-muted mb-1">Destino</label>
            <select class="form-select form-select-sm border-info" name="cod_grupo" required>
              <option value="" disabled selected>Grupo...</option>
              <?php foreach ($todos_os_grupos as $g): ?>
                <option value="<?php echo $g['cod_grupo']; ?>"><?php echo htmlspecialchars($g['grupo']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-sm btn-info text-white fw-bold shadow-sm"><i class="bi bi-plus-lg"></i> Criar</button>
          </div>
        </form>

        <h6 class="fw-bold text-dark mb-3">Regras Ativas:</h6>
        <div class="table-responsive bg-white rounded shadow-sm">
          <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="small text-muted">OU do Windows</th>
                <th class="small text-muted">Condição de Cargo</th>
                <th class="small text-muted">Grupo no Sistema</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($mapeamentos_cadastrados) > 0): ?>
                <?php foreach ($mapeamentos_cadastrados as $map): ?>
                  <tr>
                    <td class="fw-semibold text-dark small"><i class="bi bi-folder-fill text-warning me-1"></i> <?php echo htmlspecialchars($map['ou_ad']); ?></td>
                    <td class="small"><?php echo !empty($map['cargo_ad']) ? htmlspecialchars($map['cargo_ad']) : "<span class='text-muted fst-italic'>Qualquer Cargo</span>"; ?></td>
                    <td><span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle fw-normal"><i class="bi bi-diagram-3 me-1"></i><?php echo htmlspecialchars($map['grupo']); ?></span></td>
                    <td class="text-end">
                      <button type="button" class="btn btn-sm btn-outline-danger border-0" title="Remover Regra" onclick="confirmarExclusaoRegra(<?php echo $map['id']; ?>)">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" class="text-center text-muted small py-3">Nenhuma regra de mapeamento configurada.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer bg-light border-0">
        <button type="button" class="btn btn-secondary shadow-sm fw-bold" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalSyncAD" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-arrow-repeat me-2"></i>Sincronizar Active Directory</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4 bg-light text-center">
        <i class="bi bi-cloud-download text-primary mb-3" style="font-size: 4rem;"></i>
        <h4 class="fw-bold text-dark">Iniciar Varredura?</h4>
        <p class="text-muted mb-4">O sistema irá importar as contas ativas do AD que ainda não existem no IFQUOTA.</p>
        <div class="alert alert-warning shadow-sm border-0 text-start small mb-0">
          <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>DICA IMPORTANTE:</strong><br>
          Já configurou as regras em <b>Regras do AD</b>? Se não, feche esta janela e crie as regras primeiro para que os usuários entrem nos grupos corretos de forma automática!
        </div>
      </div>
      <div class="modal-footer bg-light border-0 justify-content-center pb-4">
        <button type="button" class="btn btn-outline-secondary fw-bold px-4 shadow-sm" data-bs-dismiss="modal">Cancelar</button>
        <a href="<?php echo $BASE_URL; ?>/admin/contas/sincronizar" class="btn btn-primary fw-bold px-4 shadow-sm" onclick="this.innerHTML='<i class=\'bi bi-hourglass-split me-1\'></i> Sincronizando...'; this.classList.add('disabled');">
          <i class="bi bi-check-circle-fill me-1"></i> Sim, Sincronizar Agora
        </a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalExcluirRegra" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-body p-4 text-center bg-light rounded">
        <i class="bi bi-exclamation-octagon-fill text-danger mb-3" style="font-size: 3.5rem;"></i>
        <h5 class="fw-bold text-dark mb-3">Excluir Regra?</h5>
        <p class="text-muted small mb-4">Esta ação apagará a regra do sistema. Os utilizadores que já foram movidos não serão afetados.</p>
        <div class="d-flex justify-content-center gap-2">
          <button type="button" class="btn btn-secondary btn-sm fw-bold px-3 shadow-sm" onclick="voltarModalRegras()">Cancelar</button>
          <a href="#" id="btnConfirmarExclusaoRegra" class="btn btn-danger btn-sm fw-bold px-3 shadow-sm">
            <i class="bi bi-trash-fill me-1"></i> Sim, Excluir
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // LÓGICA DO CHECKBOX "SELECIONAR TODOS"
  document.getElementById('checkAll').addEventListener('change', function() {
    var checkboxes = document.querySelectorAll('.check-item');
    for (var checkbox of checkboxes) {
      checkbox.checked = this.checked;
    }
  });

  // 1. Abre a confirmação de exclusão
  function confirmarExclusaoRegra(idRegra) {
    var modalRegrasEl = document.getElementById('modalMapeamentoAD');
    var modalRegras = bootstrap.Modal.getInstance(modalRegrasEl);
    if (modalRegras) modalRegras.hide();

    var btnConfirmar = document.getElementById('btnConfirmarExclusaoRegra');
    btnConfirmar.href = '<?php echo $BASE_URL; ?>/admin/contas/mapeamento_excluir?id=' + idRegra;

    var modalExcluir = new bootstrap.Modal(document.getElementById('modalExcluirRegra'));
    modalExcluir.show();
  }

  // 2. Cancela a exclusão e volta para as regras
  function voltarModalRegras() {
    var modalExcluirEl = document.getElementById('modalExcluirRegra');
    var modalExcluir = bootstrap.Modal.getInstance(modalExcluirEl);
    if (modalExcluir) modalExcluir.hide();

    var modalRegras = new bootstrap.Modal(document.getElementById('modalMapeamentoAD'));
    modalRegras.show();
  }

  // === AUTO-ABRIR O MODAL DE REGRAS APÓS AÇÃO ===
  document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('modal') === 'mapeamento') {
      setTimeout(function() {
        var modalRegras = new bootstrap.Modal(document.getElementById('modalMapeamentoAD'));
        modalRegras.show();
      }, 300);

      const urlSemModal = window.location.href.replace('&modal=mapeamento', '').replace('?modal=mapeamento', '');
      window.history.replaceState({}, document.title, urlSemModal);
    }
  });
</script>

<?php include __DIR__ . '/../../core/layout/footer.php'; ?>