<?php
/**
 * IBQUOTA 3
 * GG - Gerenciador Grafico do IBQUOTA
 * Index.php - Pagina Principal Unificada e Refatorada (Bootstrap 5)
 */  

// Em caso de problemas locais, descomente as 3 linhas abaixo para ver os erros
// ini_set('display_errors',1);
// ini_set('display_startup_erros',1);
// error_reporting(E_ALL);

include_once 'includes/db.php';
include_once 'includes/functions.php';

sec_session_start();

// Proteção da página
if (login_check($mysqli) == false) {
  header("Location: login.php");
  exit();
}

// Carrega as funções que geram os dados estatísticos
include_once 'includes/status_dash.php';

// O header já traz o menu e abre a <div class="content container">
include 'includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 mt-2 border-bottom border-light pb-3">
    <div>
        <h2 class="fw-bold text-dark mb-0">Painel de Controle</h2>
        <p class="text-muted mb-md-0">Resumo das atividades de impressão do campus.</p>
    </div>
    <div>
        <a href="relatorios/impressoes.php" class="btn btn-ifnmg shadow-sm fw-bold">
            📊 Relatório Completo
        </a>
    </div>
</div>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
    
    <?php
    // Estas funções vão "cuspir" o código HTML dos cartões aqui dentro.
    top_usuarios_hoje($mysqli);
    top_usuarios_mes($mysqli);
    qtde_impressoes_hoje($mysqli);
    qtde_impressoes_mes($mysqli);
    erros_log_ibquota($mysqli);
    ?>

</div>

<?php include 'includes/footer.php'; ?>