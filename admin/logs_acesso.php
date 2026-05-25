<?php

/**
 * IFQUOTA - Auditoria de Acessos
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

// Bloqueia se NÃO for o NTI (Nível 2)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] != 2) {
    header("Location: " . $BASE_URL . "/admin/dashboard?msg=acesso_negado");
    exit();
}

// Filtro rápido para exibir apenas falhas
$mostrar_apenas_falhas = isset($_GET['falhas']) ? true : false;
$filtro_sql = $mostrar_apenas_falhas ? "WHERE status != 'Sucesso'" : "";

include __DIR__ . '/../core/layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-shield-lock-fill text-danger me-2"></i> Auditoria de Acessos</h3>
        <p class="text-muted mb-0 small">Monitoramento de logins (Sucesso e Falhas) no sistema IFQUOTA.</p>
    </div>
    <div>
        <?php if ($mostrar_apenas_falhas) { ?>
            <a href="<?php echo $BASE_URL; ?>/admin/auditoria" class="btn btn-outline-secondary shadow-sm fw-bold"><i class="bi bi-list-check me-1"></i> Ver Todos</a>
        <?php } else { ?>
            <a href="<?php echo $BASE_URL; ?>/admin/auditoria?falhas=1" class="btn btn-outline-danger shadow-sm fw-bold"><i class="bi bi-exclamation-octagon-fill me-1"></i> Apenas Falhas</a>
        <?php } ?>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-danger border-4 mb-5">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 text-sm">
            <thead class="table-light">
                <tr>
                    <th class="ps-4 py-3">Data / Hora</th>
                    <th class="py-3">Usuário Tentado</th>
                    <th class="py-3">Status</th>
                    <th class="py-3">Endereço IP</th>
                    <th class="py-3">Dispositivo (Navegador)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Busca os últimos 500 registros
                $query = "SELECT *, DATE_FORMAT(data_hora, '%d/%m/%Y %H:%i:%s') as data_br FROM logs_acesso $filtro_sql ORDER BY data_hora DESC LIMIT 500";
                $res = $mysqli->query($query);

                if ($res->num_rows > 0) {
                    while ($log = $res->fetch_assoc()) {
                        
                        // Lógica Refinada das Badges
                        $status_txt = htmlspecialchars($log['status']);
                        if ($status_txt == 'Sucesso') {
                            $badge = "<span class='badge bg-success rounded-pill px-3 py-2 shadow-sm'><i class='bi bi-check-circle-fill me-1'></i> Sucesso</span>";
                        } elseif (strpos(strtolower($status_txt), 'falha') !== false || strpos(strtolower($status_txt), 'erro') !== false || strpos(strtolower($status_txt), 'incorreta') !== false) {
                            $badge = "<span class='badge bg-danger rounded-pill px-3 py-2 shadow-sm'><i class='bi bi-x-circle-fill me-1'></i> {$status_txt}</span>";
                        } else {
                            $badge = "<span class='badge bg-warning text-dark rounded-pill px-3 py-2 shadow-sm'><i class='bi bi-exclamation-triangle-fill me-1'></i> {$status_txt}</span>";
                        }

                        // Extrai navegador simplificado com segurança
                        $user_agent_completo = $log['user_agent'] ?? 'Desconhecido';
                        $nav_curto = strlen($user_agent_completo) > 40 ? substr($user_agent_completo, 0, 40) . "..." : $user_agent_completo;

                        echo "<tr>";
                        echo "<td class='ps-4 text-muted font-monospace small'>{$log['data_br']}</td>";
                        echo "<td class='fw-bold text-dark'>" . htmlspecialchars($log['usuario']) . "</td>";
                        echo "<td>{$badge}</td>";
                        echo "<td><span class='badge bg-light text-dark border font-monospace px-2 py-1'>" . htmlspecialchars($log['ip']) . "</span></td>";
                        echo "<td title='" . htmlspecialchars($user_agent_completo) . "' class='text-muted small'>" . htmlspecialchars($nav_curto) . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='5' class='text-center py-5 text-muted'><i class='bi bi-inbox fs-2 d-block mb-2 text-light'></i>Nenhum registro de acesso encontrado.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../core/layout/footer.php'; ?>