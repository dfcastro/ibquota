<?php

/**
 * DIAGNÓSTICO LDAP / AD
 * Testa a conexão com o servidor de diretório usando as configurações do banco.
 */
include_once '../core/db.php';
include_once '../core/functions.php';
sec_session_start();

// Apenas Admin (Nível 2)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] != 2) {
    header("Location: ../public/login.php");
    exit();
}

include '../core/layout/header.php';

// 1. Busca as configurações no banco de dados
$stmt = $mysqli->prepare("SELECT LDAP_server, LDAP_port, LDAP_base, LDAP_user, LDAP_password, LDAP_filter FROM config_geral WHERE id = 1");
$stmt->execute();
$stmt->bind_result($ldap_server, $ldap_porta, $ldap_base, $ldap_usuario, $ldap_senha, $ldap_filtro);
$stmt->fetch();
$stmt->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
  <div>
    <h3 class="fw-bold text-dark mb-0"><i class="bi bi-diagram-3 text-primary me-2"></i> Diagnóstico de Conexão LDAP / AD</h3>
    <p class="text-muted mb-0 small">Testando a comunicação com o servidor: <b><?php echo $ldap_server; ?></b></p>
  </div>
  <a href="configuracao.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear me-1"></i> Alterar Configurações</a>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="card shadow-sm border-0">
      <div class="card-body p-4 bg-light">

        <?php
        // Verifica se a extensão LDAP do PHP está instalada no servidor Linux
        if (!function_exists('ldap_connect')) {
          echo "<div class='alert alert-danger fw-bold'><i class='bi bi-x-octagon-fill me-2'></i> ERRO CRÍTICO: A extensão PHP-LDAP não está instalada no servidor Linux!</div>";
          echo "<p>Rode este comando no terminal do servidor: <code>apt-get install php-ldap</code> e reinicie o Apache (<code>systemctl restart apache2</code>).</p>";
        } else {
          echo "<ul class='list-group mb-4 shadow-sm font-monospace small'>";

          // PASSO 1: Conectar
          $ldapconn = @ldap_connect($ldap_server, $ldap_porta);
          if ($ldapconn) {
            echo "<li class='list-group-item list-group-item-success'><i class='bi bi-check-circle-fill me-2'></i> <b>PASSO 1:</b> Conexão com {$ldap_server}:{$ldap_porta} estabelecida.</li>";

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
              echo "<li class='list-group-item list-group-item-success'><i class='bi bi-check-circle-fill me-2'></i> <b>PASSO 2:</b> Bind {$tipo_bind} realizado com sucesso.</li>";

              // PASSO 3: Pesquisar Usuários
              // Vamos usar um filtro genérico se o do banco estiver vazio, só para testar se lista alguém
              $filtro_busca = !empty($ldap_filtro) ? $ldap_filtro : "(sAMAccountName=*)";

              $search = @ldap_search($ldapconn, $ldap_base, $filtro_busca);

              if ($search) {
                $info = ldap_get_entries($ldapconn, $search);
                echo "<li class='list-group-item list-group-item-success'><i class='bi bi-check-circle-fill me-2'></i> <b>PASSO 3:</b> Busca concluída na base '{$ldap_base}'. Encontrados <b>" . $info["count"] . " registros</b>.</li>";
                echo "</ul>";

                // MOSTRAR OS PRIMEIROS 15 RESULTADOS PARA O ADMIN VER COMO VEM O NOME
                echo "<h5 class='fw-bold mt-4'><i class='bi bi-people me-2'></i> Amostra de Usuários Encontrados (Top 15)</h5>";
                echo "<div class='table-responsive'><table class='table table-bordered table-striped bg-white small'>";
                echo "<thead class='table-dark'><tr><th>Nome de Login (sAMAccountName / uid)</th><th>Nome Completo (cn)</th><th>Email (mail)</th></tr></thead><tbody>";

                $limite = min($info["count"], 15);
                for ($i = 0; $i < $limite; $i++) {
                  $login = isset($info[$i]["samaccountname"][0]) ? $info[$i]["samaccountname"][0] : (isset($info[$i]["uid"][0]) ? $info[$i]["uid"][0] : "N/A");
                  $nome = isset($info[$i]["cn"][0]) ? $info[$i]["cn"][0] : "N/A";
                  $email = isset($info[$i]["mail"][0]) ? $info[$i]["mail"][0] : "N/A";

                  echo "<tr>";
                  echo "<td class='fw-bold text-primary'>{$login}</td>";
                  echo "<td>" . utf8_encode($nome) . "</td>";
                  echo "<td>{$email}</td>";
                  echo "</tr>";
                }
                echo "</tbody></table></div>";
              } else {
                echo "<li class='list-group-item list-group-item-danger'><i class='bi bi-x-circle-fill me-2'></i> <b>PASSO 3:</b> Falha ao buscar na base LDAP. Verifique o <b>Filtro LDAP</b> e a <b>Base LDAP</b>. Erro: " . ldap_error($ldapconn) . "</li>";
                echo "</ul>";
              }
            } else {
              echo "<li class='list-group-item list-group-item-danger'><i class='bi bi-x-circle-fill me-2'></i> <b>PASSO 2:</b> Falha na autenticação (Bind). Verifique o Usuário e a Senha Bind. Erro: " . ldap_error($ldapconn) . "</li>";
              echo "</ul>";
            }
            ldap_close($ldapconn);
          } else {
            echo "<li class='list-group-item list-group-item-danger'><i class='bi bi-x-circle-fill me-2'></i> <b>PASSO 1:</b> Não foi possível conectar ao servidor {$ldap_server} na porta {$ldap_porta}. O IP está correto? O firewall está bloqueando?</li>";
            echo "</ul>";
          }
        }
        ?>
      </div>
    </div>
  </div>
</div>

<?php include '../core/layout/footer.php'; ?>