<?php
/**
 * IBQUOTA 3
 * Modelo/template refatorado para Cartões Bootstrap 5 responsivos 
 * e Consultas SQL otimizadas com tratamento nativo de datas.
 */  

function top_usuarios_hoje($mysqli) { 
    $stmt = $mysqli->prepare("SELECT usuario, sum(paginas) AS qte_impre
        FROM impressoes
        WHERE cod_status_impressao = 1 AND data_impressao = CURRENT_DATE() 
        GROUP BY usuario ORDER BY qte_impre DESC LIMIT 10");
    $stmt->execute();    
    $stmt->store_result();
    $stmt->bind_result($usuario, $impressoes );

    echo "<div class=\"col\">";
    echo "<div class=\"card border-success shadow-sm h-100\">";
    echo "<div class=\"card-header bg-ifnmg text-white fw-bold\">🏆 Top 10 Usuários Hoje</div>";
    echo "<div class=\"card-body p-0\">";

    echo "<ul class=\"list-group list-group-flush\">";
    $tem_usuarios = 0;
    while ($stmt->fetch()) {
      $tem_usuarios = 1;
      echo "<li class=\"list-group-item d-flex justify-content-between align-items-center py-2\">";
      echo "<span class=\"text-dark fw-semibold\">" . htmlspecialchars($usuario) . "</span>";
      echo "<span class=\"badge text-bg-success rounded-pill\">". $impressoes ."</span>";
      echo "</li>";
    }
    if ($tem_usuarios == 0) {
      echo "<li class=\"list-group-item text-center text-muted fst-italic py-4\">Nenhum registro encontrado hoje.</li>";
    }
    echo "</ul>";
    echo "</div></div></div>";  
}

function top_usuarios_mes($mysqli) { 
    // LÓGICA SQL CORRIGIDA: Usa as funções MONTH() e YEAR() do banco de dados
    $stmt = $mysqli->prepare("SELECT usuario, sum(paginas) AS qte_impre
        FROM impressoes
        WHERE cod_status_impressao = 1 
        AND MONTH(data_impressao) = MONTH(CURRENT_DATE())
        AND YEAR(data_impressao)  = YEAR(CURRENT_DATE())
        GROUP BY usuario ORDER BY qte_impre DESC LIMIT 10");
    $stmt->execute();    
    $stmt->store_result();
    $stmt->bind_result($usuario, $impressoes);

    echo "<div class=\"col\">";
    echo "<div class=\"card border-success shadow-sm h-100\">";
    echo "<div class=\"card-header bg-ifnmg text-white fw-bold\">📅 Top 10 Usuários no Mês</div>";
    echo "<div class=\"card-body p-0\">";

    echo "<ul class=\"list-group list-group-flush\">";
    $tem_usuarios = 0;
    while ($stmt->fetch()) {
      $tem_usuarios = 1;
      echo "<li class=\"list-group-item d-flex justify-content-between align-items-center py-2\">";
      echo "<span class=\"text-dark fw-semibold\">" . htmlspecialchars($usuario) . "</span>";
      echo "<span class=\"badge text-bg-success rounded-pill\">". $impressoes ."</span>";
      echo "</li>";
    }
    if ($tem_usuarios == 0) {
      echo "<li class=\"list-group-item text-center text-muted fst-italic py-4\">Nenhum registro neste mês.</li>";
    }
    echo "</ul>";
    echo "</div></div></div>";  
}

function qtde_impressoes_hoje($mysqli) { 
    $stmt = $mysqli->prepare("SELECT sum(paginas)
        FROM impressoes
        WHERE cod_status_impressao = 1 AND data_impressao = CURRENT_DATE()");
    $stmt->execute();    
    $stmt->store_result();
    $stmt->bind_result($impressoes);
    $stmt->fetch();

    $e_stmt = $mysqli->prepare("SELECT sum(paginas)
        FROM impressoes
        WHERE cod_status_impressao <> 1 AND data_impressao = CURRENT_DATE()");
    $e_stmt->execute();    
    $e_stmt->store_result();
    $e_stmt->bind_result($impressoes_erro);
    $e_stmt->fetch();

    if ( !isset($impressoes) ) $impressoes = 0;
    if ( !isset($impressoes_erro) ) $impressoes_erro = 0;

    echo "<div class=\"col\">";
    echo "<div class=\"card border-success shadow-sm h-100\">";
    echo "<div class=\"card-header bg-ifnmg text-white fw-bold\">📈 Total de Páginas Hoje</div>";
    echo "<div class=\"card-body p-0\">";

    echo "<ul class=\"list-group list-group-flush\">";
    echo "<li class=\"list-group-item d-flex justify-content-between align-items-center py-3\">";
    echo "<span class=\"text-dark\"><i class=\"text-success fw-bold fs-5 me-2\">✓</i> Impressas c/ Sucesso</span>";
    echo "<span class=\"badge text-bg-success rounded-pill fs-6\">". $impressoes ."</span>";
    echo "</li>";

    echo "<li class=\"list-group-item d-flex justify-content-between align-items-center py-3\">";
    echo "<span class=\"text-dark\"><i class=\"text-danger fw-bold fs-5 me-2\">✕</i> Falhas / Erros</span>";
    echo "<span class=\"badge text-bg-danger rounded-pill fs-6\">". $impressoes_erro ."</span>";
    echo "</li>";

    echo "<li class=\"list-group-item d-flex justify-content-between align-items-center bg-light py-3 border-top border-success mt-1\">";
    echo "<b class=\"text-dark text-uppercase small\">Volume Total Solicitado</b>";
    echo "<span class=\"badge text-bg-primary rounded-pill fs-6\">". ($impressoes + $impressoes_erro) ."</span>";
    echo "</li>";
    echo "</ul>";
    
    echo "</div></div></div>";  
}

function qtde_impressoes_mes($mysqli) { 
    // LÓGICA SQL CORRIGIDA (Sucesso)
    $stmt = $mysqli->prepare("SELECT sum(paginas)
        FROM impressoes
        WHERE cod_status_impressao = 1 
        AND MONTH(data_impressao) = MONTH(CURRENT_DATE())
        AND YEAR(data_impressao)  = YEAR(CURRENT_DATE())");
    $stmt->execute();    
    $stmt->store_result();
    $stmt->bind_result($impressoes);
    $stmt->fetch();

    // LÓGICA SQL CORRIGIDA (Erros)
    $e_stmt = $mysqli->prepare("SELECT sum(paginas)
        FROM impressoes
        WHERE cod_status_impressao <> 1 
        AND MONTH(data_impressao) = MONTH(CURRENT_DATE())
        AND YEAR(data_impressao)  = YEAR(CURRENT_DATE())");
    $e_stmt->execute();    
    $e_stmt->store_result();
    $e_stmt->bind_result($impressoes_erro);
    $e_stmt->fetch();

    if ( !isset($impressoes) ) $impressoes = 0;
    if ( !isset($impressoes_erro) ) $impressoes_erro = 0;

    echo "<div class=\"col\">";
    echo "<div class=\"card border-success shadow-sm h-100\">";
    echo "<div class=\"card-header bg-ifnmg text-white fw-bold\">📊 Total de Páginas no Mês</div>";
    echo "<div class=\"card-body p-0\">";

    echo "<ul class=\"list-group list-group-flush\">";
    echo "<li class=\"list-group-item d-flex justify-content-between align-items-center py-3\">";
    echo "<span class=\"text-dark\"><i class=\"text-success fw-bold fs-5 me-2\">✓</i> Impressas c/ Sucesso</span>";
    echo "<span class=\"badge text-bg-success rounded-pill fs-6\">". $impressoes ."</span>";
    echo "</li>";

    echo "<li class=\"list-group-item d-flex justify-content-between align-items-center py-3\">";
    echo "<span class=\"text-dark\"><i class=\"text-danger fw-bold fs-5 me-2\">✕</i> Falhas / Erros</span>";
    echo "<span class=\"badge text-bg-danger rounded-pill fs-6\">". $impressoes_erro ."</span>";
    echo "</li>";

    echo "<li class=\"list-group-item d-flex justify-content-between align-items-center bg-light py-3 border-top border-success mt-1\">";
    echo "<b class=\"text-dark text-uppercase small\">Volume Total Solicitado</b>";
    echo "<span class=\"badge text-bg-primary rounded-pill fs-6\">". ($impressoes + $impressoes_erro) ."</span>";
    echo "</li>";
    echo "</ul>";

    echo "</div></div></div>";  
}

function erros_log_ibquota($mysqli) { 
    $stmt = $mysqli->prepare("SELECT mensagem, datahora
        FROM log_ibquota 
        ORDER BY datahora DESC LIMIT 5");
    $stmt->execute();    
    $stmt->store_result();
    $stmt->bind_result($mensagem, $datahora );

    echo "<div class=\"col\">";
    echo "<div class=\"card border-warning shadow-sm h-100\">";
    echo "<div class=\"card-header bg-warning text-dark fw-bold\">📝 Últimos Logs do Sistema</div>";
    echo "<div class=\"card-body bg-light\">";

    echo "<ul class=\"list-unstyled mb-0\">";
    $tem_log = 0;
    while ($stmt->fetch()) {
      // Deixamos apenas Horas e Minutos para ficar mais limpo na tela
      $hora = date("H:i", strtotime($datahora)); 
      $dia = date("d/m/Y", strtotime($datahora));
      $tem_log = 1;
      
      echo "<li class=\"mb-2 pb-2 border-bottom border-secondary-subtle\">";
      echo "<span class=\"badge text-bg-dark me-2\" title=\"$dia\">$hora</span>";
      // htmlspecialchars protege contra injeção de código
      echo "<span class=\"small text-muted\">" . htmlspecialchars($mensagem) . "</span>";
      echo "</li>";
    }
    if ($tem_log == 0) {
      echo "<li class=\"text-center text-muted fst-italic py-4\">Nenhum registro de log recente.</li>";
    }
    echo "</ul>";
    echo "</div></div></div>";  
}
?>