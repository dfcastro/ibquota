<?php

/**
 * IFQUOTA - DIAGNÓSTICO LDAP / AD
 * Testa a conexão com o servidor de diretório usando as configurações do banco.
 */

// 1. INCLUDES BLINDADOS
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

// Apenas Admin (Nível 2)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 2) {
    header("Location: " . $BASE_URL . "/login");
    exit();
}

include __DIR__ . '/../core/layout/header.php';

// 1. Busca as configurações no banco de dados
$stmt = $mysqli->prepare("SELECT LDAP_server, LDAP_port, LDAP_base, LDAP_user, LDAP_password, LDAP_filter FROM config_geral WHERE id = 1");
$stmt->execute();
$stmt->bind_result($ldap_server, $ldap_porta, $ldap_base, $ldap_usuario, $ldap_senha, $ldap_filtro);
$stmt->fetch();
$stmt->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-diagram-3-fill text-primary me-2"></i> Diagnóstico de Conexão LDAP / AD</h3>
        <p class="text-muted mb-0 small">Testando a comunicação com o servidor: <b class="text-dark"><?php echo htmlspecialchars($ldap_server); ?></b></p>
    </div>
    <!-- Link blindado para a Rota Limpa -->
    <a href="<?php echo $BASE_URL; ?>/admin/configuracao" class="btn btn-outline-secondary btn-sm shadow-sm fw-bold">
        <i class="bi bi-gear me-1"></i> Alterar Configurações
    </a>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm border-0 border-top border-primary border-4">
            <div class="card-body p-4 bg-light">

                <?php
                // Verifica se a extensão LDAP do PHP está instalada no servidor Linux
                if (!function_exists('ldap_connect')) {
                    echo "<div class='alert alert-danger shadow-sm border-0 fw-bold'><i class='bi bi-x-octagon-fill me-2'></i> ERRO CRÍTICO: A extensão PHP-LDAP não está instalada no servidor Linux!</div>";
                    echo "<p class='text-muted'>Rode este comando no terminal do servidor: <code>apt-get install php-ldap</code> e reinicie o Apache (<code>systemctl restart apache2</code>).</p>";
                } else {
                    echo "<ul class='list-group mb-4 shadow-sm font-monospace small border-0'>";

                    // PASSO 1: Conectar
                    $ldapconn = @ldap_connect($ldap_server, $ldap_porta);
                    if ($ldapconn) {
                        echo "<li class='list-group-item list-group-item-success border-0 mb-1 rounded'><i class='bi bi-check-circle-fill me-2'></i> <b>PASSO 1:</b> Conexão com {$ldap_server}:{$ldap_porta} estabelecida.</li>";

                        ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
                        ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0); // Importante para Active Directory do Windows

                        // PASSO 2: Autenticar (Bind)
                        if (empty($ldap_usuario)) {
                            $bind = @ldap_bind($ldapconn); // Anônimo
                            $tipo_bind = "Anônimo";
                        } else {
                            $bind = @ldap_bind($ldapconn, $ldap_usuario, $ldap_senha);
                            $tipo_bind = "Autenticado ($ldap_usuario)";
                        }

                        if ($bind) {
                            echo "<li class='list-group-item list-group-item-success border-0 mb-1 rounded'><i class='bi bi-check-circle-fill me-2'></i> <b>PASSO 2:</b> Bind {$tipo_bind} realizado com sucesso.</li>";

                            // PASSO 3: Pesquisar Usuários
                            // Filtro genérico se o do banco estiver vazio, para testar se lista alguém
                            $filtro_busca = !empty($ldap_filtro) ? $ldap_filtro : "(sAMAccountName=*)";

                            $search = @ldap_search($ldapconn, $ldap_base, $filtro_busca);

                            if ($search) {
                                $info = ldap_get_entries($ldapconn, $search);
                                echo "<li class='list-group-item list-group-item-success border-0 rounded'><i class='bi bi-check-circle-fill me-2'></i> <b>PASSO 3:</b> Busca concluída na base '{$ldap_base}'. Encontrados <b>" . $info["count"] . " registros</b>.</li>";
                                echo "</ul>";

                                // MOSTRAR OS PRIMEIROS 15 RESULTADOS PARA O ADMIN
                                echo "<h5 class='fw-bold mt-4 mb-3 text-dark'><i class='bi bi-people-fill text-primary me-2'></i> Amostra de Usuários Encontrados (Top 15)</h5>";
                                echo "<div class='table-responsive'><table class='table table-bordered table-hover bg-white small shadow-sm'>";
                                echo "<thead class='table-light text-secondary'><tr><th>Nome de Login (sAMAccountName / uid)</th><th>Nome Completo (cn)</th><th>Email (mail)</th></tr></thead><tbody>";

                                $limite = min($info["count"], 15);
                                for ($i = 0; $i < $limite; $i++) {
                                    $login = isset($info[$i]["samaccountname"][0]) ? $info[$i]["samaccountname"][0] : (isset($info[$i]["uid"][0]) ? $info[$i]["uid"][0] : "N/A");
                                    
                                    // A proteção contra depreciação do utf8_encode e blindagem XSS
                                    $nome_bruto = isset($info[$i]["cn"][0]) ? $info[$i]["cn"][0] : "N/A";
                                    $nome = mb_convert_encoding($nome_bruto, 'UTF-8', 'auto'); 

                                    $email = isset($info[$i]["mail"][0]) ? $info[$i]["mail"][0] : "N/A";

                                    echo "<tr>";
                                    echo "<td class='fw-bold text-primary'>" . htmlspecialchars($login) . "</td>";
                                    echo "<td>" . htmlspecialchars($nome) . "</td>";
                                    echo "<td class='text-muted'>" . htmlspecialchars($email) . "</td>";
                                    echo "</tr>";
                                }
                                echo "</tbody></table></div>";
                            } else {
                                echo "<li class='list-group-item list-group-item-danger border-0 rounded'><i class='bi bi-x-circle-fill me-2'></i> <b>PASSO 3:</b> Falha ao buscar na base LDAP. Verifique o <b>Filtro LDAP</b> e a <b>Base LDAP</b>. Erro: " . ldap_error($ldapconn) . "</li>";
                                echo "</ul>";
                            }
                        } else {
                            echo "<li class='list-group-item list-group-item-danger border-0 rounded'><i class='bi bi-x-circle-fill me-2'></i> <b>PASSO 2:</b> Falha na autenticação (Bind). Verifique o Usuário e a Senha Bind. Erro: " . ldap_error($ldapconn) . "</li>";
                            echo "</ul>";
                        }
                        ldap_close($ldapconn);
                    } else {
                        echo "<li class='list-group-item list-group-item-danger border-0 rounded'><i class='bi bi-x-circle-fill me-2'></i> <b>PASSO 1:</b> Não foi possível conectar ao servidor {$ldap_server} na porta {$ldap_porta}. O IP está correto? O firewall está bloqueando?</li>";
                        echo "</ul>";
                    }
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../core/layout/footer.php'; ?>