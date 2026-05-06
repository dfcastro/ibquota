<?php

/**
 * IFQUOTA - Página de Documentação Técnica
 * Manual do programador e infraestrutura.
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

// Proteção: Apenas Administradores (Nível 2 ou superior) podem ver o manual
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 2) {
    header("Location: " . $BASE_URL . "/login");
    exit();
}

include __DIR__ . '/../core/layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-journal-code text-primary me-2"></i> Documentação do Sistema</h3>
        <p class="text-muted mb-0 small">Manual técnico de infraestrutura, portas de rede e arquitetura do IFQUOTA.</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">

        <!-- SECÇÃO 1: VISÃO GERAL -->
        <div class="card shadow-sm border-0 border-top border-primary border-4 mb-4">
            <div class="card-header bg-white fw-bold py-3 fs-5">
                <i class="bi bi-info-circle text-primary me-2"></i> 1. Visão Geral do Sistema
            </div>
            <div class="card-body">
                <p class="text-muted">O IFQUOTA é um sistema integrado de bilhetagem e gestão de impressão. Ele atua como um intermediário (proxy) entre os utilizadores e as impressoras, garantindo que as políticas de cotas institucionais sejam respeitadas, prevenindo desperdícios e gerando relatórios precisos.</p>
                <h6 class="fw-bold mt-4">Tecnologias Utilizadas:</h6>
                <ul class="text-muted">
                    <li><b>Interface Web:</b> PHP 8.x, Bootstrap 5, JavaScript.</li>
                    <li><b>Motor de Impressão:</b> Servidor CUPS (Linux), Scripts em Perl.</li>
                    <li><b>Contador de Páginas:</b> Python (<code>pkpgcounter</code>).</li>
                    <li><b>Base de Dados:</b> MySQL / MariaDB.</li>
                    <li><b>Autenticação:</b> Integração via protocolo LDAP/Active Directory.</li>
                </ul>
            </div>
        </div>

        <!-- SECÇÃO 2: REDE E FIREWALL -->
        <div class="card shadow-sm border-0 border-top border-danger border-4 mb-4">
            <div class="card-header bg-white fw-bold py-3 fs-5">
                <i class="bi bi-shield-lock text-danger me-2"></i> 2. Requisitos de Rede e Firewall
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">Para que o sistema funcione corretamente, os firewalls devem permitir o tráfego nas seguintes portas:</p>

                <h6 class="fw-bold text-dark"><i class="bi bi-printer me-1"></i> Comunicação Servidor ➔ Impressoras (LAN)</h6>
                <ul class="text-muted mb-4">
                    <li><b>TCP 9100 (AppSocket / JetDirect):</b> Usada pelo motor do CUPS para enviar o arquivo para a memória da impressora.</li>
                    <li><b>UDP 161 (SNMP v1/v2c):</b> Usada para consultar o estado físico da impressora (falta de papel, encravamento) <i>antes</i> de imprimir. Vital para não perder cotas indevidamente.</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-person-badge me-1"></i> Comunicação Servidor ➔ Servidor LDAP / AD</h6>
                <ul class="text-muted mb-4">
                    <li><b>TCP 389 (LDAP Padrão):</b> Autenticação sem criptografia.</li>
                    <li><b>TCP 636 (LDAPS):</b> Autenticação segura com SSL/TLS (Recomendado).</li>
                </ul>

                <h6 class="fw-bold text-dark"><i class="bi bi-globe me-1"></i> Comunicação Utilizadores ➔ Servidor Web</h6>
                <ul class="text-muted">
                    <li><b>TCP 80 (HTTP) e TCP 443 (HTTPS):</b> Acesso ao portal do servidor, solicitação de cotas e Web Print.</li>
                </ul>
            </div>
        </div>

        <!-- SECÇÃO 3: MOTOR DE IMPRESSÃO (CUPS) -->
        <div class="card shadow-sm border-0 border-top border-dark border-4 mb-4">
            <div class="card-header bg-white fw-bold py-3 fs-5">
                <i class="bi bi-terminal-fill text-dark me-2"></i> 3. O Motor de Impressão (Backend CUPS)
            </div>
            <div class="card-body">
                <p class="text-muted">O "cérebro" do bloqueio reside no Linux, dentro da diretoria <code>/usr/lib/cups/backend/</code>.</p>

                <h6 class="fw-bold mt-4">Arquivo <code>ibquota3</code> (Perl)</h6>
                <p class="text-muted">Este script atua disfarçado de impressora para o CUPS. O fluxo de execução é:</p>
                <ol class="text-muted">
                    <li><b>Interceção:</b> Recebe o trabalho (Job ID, Utilizador, Título, Spoolfile).</li>
                    <li><b>Monitorização Física (SNMP):</b> Dispara um comando <code>snmpget</code>. Se retornar erro físico, congela a impressão e regista o Status 10.</li>
                    <li><b>Contagem (Python):</b> Aciona o <code>pkpgcounter</code> para ler o número exato de páginas.</li>
                    <li><b>Validação (MySQL):</b> Verifica se o utilizador tem saldo na base de dados.</li>
                    <li><b>Envio Final:</b> Entrega o arquivo à impressora via Socket. Apenas debita a cota se o envio for bem-sucedido.</li>
                </ol>

                <div class="alert alert-warning border-0 shadow-sm mt-3">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <b>Nota Técnica:</b> Qualquer alteração no ficheiro Perl exige que o serviço CUPS seja reiniciado no Linux (<code>systemctl restart cups</code>).
                </div>
            </div>
        </div>

        <!-- SECÇÃO 4: MÓDULOS WEB -->
        <div class="card shadow-sm border-0 border-top border-success border-4 mb-5">
            <div class="card-header bg-white fw-bold py-3 fs-5">
                <i class="bi bi-window-sidebar text-success me-2"></i> 4. Módulos e Funcionalidades Web (PHP)
            </div>
            <div class="card-body text-muted">
                <h6 class="fw-bold text-dark">Módulo do Utilizador (Servidor / Aluno)</h6>
                <ul>
                    <li><b>Meu Painel:</b> Dashboard responsivo mostrando o saldo atual, regra aplicada e impressões em tempo real.</li>
                    <li><b>Web Print:</b> Permite o upload de ficheiros PDF sem instalação de drivers. Suporta OptGroups (impressoras por departamento).</li>
                    <li><b>Solicitar Cota:</b> Formulário protegido por CSRF para pedir páginas extras.</li>
                </ul>

                <h6 class="fw-bold text-dark mt-4">Módulo Administrativo</h6>
                <ul>
                    <li><b>Dashboard Admin:</b> Resumo gerencial de volume, filas pendentes e alertas de hardware (Offline/Sem Papel).</li>
                    <li><b>Aprovação de Cotas:</b> Injeta saldo automaticamente na base de dados.</li>
                    <li><b>Relatórios:</b> Tabelas geradas com DataTables, com pesquisa instantânea e exportação para PDF/Excel.</li>
                </ul>
            </div>
        </div>

        <!-- SECÇÃO 5: GUIA DE MANUTENÇÃO E EXPANSÃO (IFQUOTA) -->
        <div class="card shadow-sm border-0 border-top border-warning border-4 mb-5">
            <div class="card-header bg-white fw-bold py-3 fs-5">
                <i class="bi bi-code-square text-warning me-2"></i> 5. Guia do Programador: Adicionar Novas Funcionalidades
            </div>
            <div class="card-body">
                <p class="text-muted">O IFQUOTA utiliza um sistema de <b>Rotas Limpas</b>. Isso significa que as URLs não apontam diretamente para arquivos .php físicos por motivos de segurança.</p>

                <h6 class="fw-bold text-dark mt-4">Passo-a-passo para criar uma nova página:</h6>
                <ol class="text-muted">
                    <li><b>Criar o Arquivo:</b> Crie o ficheiro <code>.php</code> dentro da pasta <code>public/</code> (se for interface) ou <code>admin/</code> (se for gerencial).</li>
                    <li><b>Registar a Rota:</b> Abra o ficheiro <code>/index.php</code> (na raiz do projeto) e adicione o nome da URL e o caminho do arquivo no array <code>$rotas</code>.
                        <pre class="bg-light p-2 mt-2 border rounded"><code>'nome-da-url' => 'public/seu_arquivo.php',</code></pre>
                    </li>
                    <li><b>Links Internos:</b> Nunca use links diretos como <code>&lt;a href="pagina.php"&gt;</code>. Use sempre a variável global <code>$BASE_URL</code> para garantir portabilidade:
                        <pre class="bg-light p-2 mt-2 border rounded"><code>&lt;a href="&lt;?php echo $BASE_URL; ?&gt;/nome-da-url"&gt;</code></pre>
                    </li>
                </ol>

                <h6 class="fw-bold text-dark mt-4">Padrões de Segurança Obrigatórios:</h6>
                <ul class="text-muted">
                    <li><b>CSRF:</b> Todo o formulário <code>POST</code> deve conter o campo oculto do token:
                        <br><code>&lt;input type="hidden" name="csrf_token" value="&lt;?php echo $_SESSION['csrf_token']; ?&gt;"&gt;</code>
                    </li>
                    <li><b>Caminhos de Inclusão:</b> Use sempre <code>__DIR__</code> para evitar erros de diretoria:
                        <br><code>include_once __DIR__ . '/../core/db.php';</code>
                    </li>
                </ul>

                <div class="alert alert-info border-0 shadow-sm mt-3 mb-0">
                    <i class="bi bi-lightbulb-fill me-2"></i> <b>Dica de Ouro:</b> Se a página nova for para o Admin, verifique sempre o nível de permissão (<code>$_SESSION['permissao'] >= 2</code>) logo no topo do arquivo.
                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../core/layout/footer.php'; ?>