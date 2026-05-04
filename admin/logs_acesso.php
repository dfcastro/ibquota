<?php
/**
 * IBQUOTA 3 - Auditoria de Acessos
 */
include_once '../core/db.php';
include_once '../core/functions.php';
sec_session_start();

if (!isset($_SESSION['usuario']) || !isset($_SESSION['permissao']) || $_SESSION['permissao'] < 2) {
    header("Location: ../public/login.php"); exit();
}

// Filtro rápido para exibir apenas falhas
$mostrar_apenas_falhas = isset($_GET['falhas']) ? true : false;
$filtro_sql = $mostrar_apenas_falhas ? "WHERE status != 'Sucesso'" : "";

include '../core/layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-shield-lock-fill text-danger me-2"></i> Auditoria de Acessos</h3>
        <p class="text-muted mb-0 small">Monitoramento de logins (Sucesso e Falhas) no sistema IBQuota.</p>
    </div>
    <div>
        <?php if ($mostrar_apenas_falhas) { ?>
            <a href="logs_acesso.php" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-list-check me-1"></i> Ver Todos</a>
        <?php } else { ?>
            <a href="logs_acesso.php?falhas=1" class="btn btn-outline-danger shadow-sm"><i class="bi bi-exclamation-octagon me-1"></i> Apenas Falhas</a>
        <?php } ?>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-danger border-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 text-sm">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Data / Hora</th>
                    <th>Usuário Tentado</th>
                    <th>Status</th>
                    <th>Endereço IP</th>
                    <th>Dispositivo (Navegador)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Busca os últimos 500 registros
                $query = "SELECT *, DATE_FORMAT(data_hora, '%d/%m/%Y %H:%i:%s') as data_br FROM logs_acesso $filtro_sql ORDER BY data_hora DESC LIMIT 500";
                $res = $mysqli->query($query);

                if ($res->num_rows > 0) {
                    while ($log = $res->fetch_assoc()) {
                        $is_sucesso = ($log['status'] == 'Sucesso');
                        $badge_cor = $is_sucesso ? 'success' : 'danger';
                        $icone = $is_sucesso ? 'bi-check-circle' : 'bi-x-octagon';
                        
                        // Extrai navegador simplificado para não quebrar a tela
                        $nav_curto = substr($log['user_agent'], 0, 40) . "...";

                        echo "<tr>";
                        echo "<td class='ps-4 text-muted font-monospace small'>{$log['data_br']}</td>";
                        echo "<td class='fw-bold'>".htmlspecialchars($log['usuario'])."</td>";
                        echo "<td><span class='badge bg-{$badge_cor} bg-opacity-10 text-{$badge_cor} border border-{$badge_cor} rounded-pill'><i class='bi {$icone} me-1'></i>{$log['status']}</span></td>";
                        echo "<td class='font-monospace text-muted'>{$log['ip']}</td>";
                        echo "<td title='".htmlspecialchars($log['user_agent'])."' class='text-muted small'>".htmlspecialchars($nav_curto)."</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='5' class='text-center py-5 text-muted'>Nenhum registro encontrado.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../core/layout/footer.php'; ?>