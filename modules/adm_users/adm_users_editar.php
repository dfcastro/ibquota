<?php

/**
 * IFQUOTA - Gestão de Administradores
 * Editar Usuário Administrativo
 */

include_once __DIR__ . '/../core/db.php';
include_once __DIR__ . '/../core/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    sec_session_start();
}

// ==========================================
// DETEÇÃO INTELIGENTE DE AMBIENTE
// ==========================================
$host_atual = $_SERVER['HTTP_HOST'] ?? '';
$BASE_URL = ($host_atual === 'localhost' || $host_atual === '127.0.0.1') ? '/gg' : '';

// 1. Proteção de página: Apenas Admins (Nível 2)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 2) {
    header("Location: " . $BASE_URL . "/login");
    exit();
}

$msg_erro = "";

// ==========================================
// PROCESSAMENTO DO FORMULÁRIO (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cod_adm_users'], $_POST['login'])) {
    $token_recebido = $_POST['csrf_token'] ?? '';
    validar_csrf_token($token_recebido);

    $cod_adm_users = (int)$_POST['cod_adm_users'];
    $login = trim($_POST['login']);
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $permissao = (int)$_POST['permissao'];
    $senha_nova = trim($_POST['senha']);

    // Verifica se já existe outro admin com esse mesmo login
    $chk = $mysqli->prepare("SELECT cod_adm_users FROM adm_users WHERE login = ? AND cod_adm_users != ? LIMIT 1");
    $chk->bind_param('si', $login, $cod_adm_users);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows > 0) {
        $msg_erro = "Já existe outro administrador cadastrado com o login '{$login}'.";
    } else {
        // Se a senha foi preenchida, atualiza TUDO
        if (!empty($senha_nova)) {
            $senha_hash = hash('sha512', $senha_nova);
            $upd = $mysqli->prepare("UPDATE adm_users SET login = ?, nome = ?, email = ?, senha = ?, permissao = ? WHERE cod_adm_users = ?");
            $upd->bind_param('ssssii', $login, $nome, $email, $senha_hash, $permissao, $cod_adm_users);
        }
        // Se a senha ficou em branco, mantém a antiga
        else {
            $upd = $mysqli->prepare("UPDATE adm_users SET login = ?, nome = ?, email = ?, permissao = ? WHERE cod_adm_users = ?");
            $upd->bind_param('sssii', $login, $nome, $email, $permissao, $cod_adm_users);
        }

        $upd->execute();
        $upd->close();

        // Redireciona com sucesso para a Rota Limpa
        header("Location: " . $BASE_URL . "/admin/usuarios?msg=edit");
        exit();
    }
    $chk->close();
}

// ==========================================
// CARREGAMENTO DOS DADOS (GET)
// ==========================================
if (!isset($_GET['cod_adm_users']) && !isset($_POST['cod_adm_users'])) {
    header("Location: " . $BASE_URL . "/admin/usuarios");
    exit();
}

$cod_adm_users = isset($_GET['cod_adm_users']) ? (int)$_GET['cod_adm_users'] : (int)$_POST['cod_adm_users'];

$stmt = $mysqli->prepare("SELECT nome, login, email, permissao FROM adm_users WHERE cod_adm_users = ? LIMIT 1");
$stmt->bind_param('i', $cod_adm_users);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows < 1) {
    header("Location: " . $BASE_URL . "/admin/usuarios?msg=erro_404");
    exit();
}

$stmt->bind_result($nome, $login, $email, $permissao_atual);
$stmt->fetch();
$stmt->close();

include __DIR__ . '/../core/layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-person-lines-fill text-primary me-2"></i> Editar Administrador</h3>
        <p class="text-muted mb-0 small">Altere as credenciais e permissões de acesso ao painel do IFQUOTA.</p>
    </div>
    <div>
        <a href="<?php echo $BASE_URL; ?>/admin/usuarios" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
    </div>
</div>

<?php if ($msg_erro != "") { ?>
    <div class="alert alert-danger shadow-sm border-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $msg_erro; ?></div>
<?php } ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm border-0 border-top border-primary border-4">
            <div class="card-body p-4">
                <!-- Action apontado para a Rota Limpa -->
                <form action="<?php echo $BASE_URL; ?>/admin/usuarios/editar" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <input type="hidden" name="cod_adm_users" value="<?php echo $cod_adm_users; ?>">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Login do Sistema</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" name="login" value="<?php echo htmlspecialchars($login); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6 mt-3 mt-md-0">
                            <label class="form-label fw-bold small text-muted">Nova Senha</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control" name="senha" placeholder="Deixe em branco para manter">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Nome Completo</label>
                        <input type="text" class="form-control" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">E-mail de Contato</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">Nível de Acesso</label>
                        <select class="form-select" name="permissao">
                            <option value="1" <?php if ($permissao_atual == 1) echo "selected"; ?>>Nível 1 - Apenas Relatórios</option>
                            <option value="2" <?php if ($permissao_atual == 2) echo "selected"; ?>>Nível 2 - Equipe NTI (Acesso Total)</option>
                            <option value="3" <?php if ($permissao_atual == 3) echo "selected"; ?>>Nível 3 - Direção Geral (Aprova Exceções)</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary fw-bold py-2 shadow-sm"><i class="bi bi-save me-1"></i> Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../core/layout/footer.php'; ?>