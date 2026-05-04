<?php
/**
 * IBQUOTA 3
 * GG - Gerenciador Grafico do IBQUOTA
 * Adiciona Novo Usuario Administrativo (Refatorado Bootstrap 5)
 */  
include_once '../../core/db.php';
include_once '../../core/functions.php';

sec_session_start();

// 1. Proteção de página: Apenas Admins (Nível 2)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] != 2) {
    header("Location: ../../public/login.php"); 
    exit();
}

$msg_erro = "";

// ==========================================
// PROCESSAMENTO DO FORMULÁRIO (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    
    $login = trim($_POST['login']);
    $senha_texto = trim($_POST['senha']);
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $permissao = (int)$_POST['permissao'];

    if (empty($login) || empty($senha_texto)) {
        $msg_erro = "Obrigatório preencher o Login e a Senha!";
    } else {
        // Verifica se o Login já existe
        $select_stmt = $mysqli->prepare("SELECT cod_adm_users FROM adm_users WHERE login = ?");
        $select_stmt->bind_param('s', $login);
        $select_stmt->execute();
        $select_stmt->store_result();
        
        if ($select_stmt->num_rows > 0) {
            $msg_erro = "O login '{$login}' já está cadastrado no sistema.";
        } else {
            // Criptografia atualizada para SHA-512 (Casando com o login.php)
            $senha_hash = hash('sha512', $senha_texto);
            
            // Grava o novo usuário
            if ($insert_stmt = $mysqli->prepare("INSERT INTO adm_users (login, nome, email, senha, permissao) VALUES (?, ?, ?, ?, ?)")) {
                $insert_stmt->bind_param('ssssi', $login, $nome, $email, $senha_hash, $permissao);
                $insert_stmt->execute();
                $insert_stmt->close();
                
                // Redireciona para o painel principal com aviso de sucesso
                header("Location: index.php?msg=add");
                exit();
            } else {
                $msg_erro = "Erro interno no banco de dados ao tentar salvar o usuário.";
            }
        }
        $select_stmt->close();
    }
}

// 2. Caminho do Header corrigido (Estava ../core/layout/header.php)
include '../../core/layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-person-plus-fill text-success me-2"></i> Novo Administrador</h3>
        <p class="text-muted mb-0 small">Cadastre um novo gestor para acessar este painel de controle.</p>
    </div>
    <div>
        <a href="index.php" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
    </div>
</div>

<?php if ($msg_erro != "") { ?>
    <div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $msg_erro; ?></div>
<?php } ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm border-0 border-top border-success border-4">
            <div class="card-body p-4">
                <form action="adm_users_add.php" method="post">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Login do Sistema <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" name="login" required autofocus>
                            </div>
                        </div>
                        <div class="col-md-6 mt-3 mt-md-0">
                            <label class="form-label fw-bold small text-muted">Senha de Acesso <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control" name="senha" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Nome Completo</label>
                        <input type="text" class="form-control" name="nome">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">E-mail de Contato</label>
                        <input type="email" class="form-control" name="email">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">Nível de Acesso</label>
                        <select class="form-select" name="permissao">
                            <option value="1">Nível 1 - Apenas Relatórios</option>
                            <option value="2" selected>Nível 2 - Equipe NTI (Acesso Total)</option>
                            <option value="3">Nível 3 - Direção Geral (Aprova Exceções)</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success fw-bold py-2"><i class="bi bi-person-check-fill me-1"></i> Cadastrar Usuário</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
// 3. Caminho do Footer corrigido
include '../../core/layout/footer.php'; 
?>