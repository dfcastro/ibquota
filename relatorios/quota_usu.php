<?php 
session_start();
/**
* IBQUOTA 3
* GG - Gerenciador Grafico do IBQUOTA
* 
* 23/12/2018 - Valcir C.
*
* Lista Quotas de um usuario especifico
*/ 

// Abre conexao com o banco de dados
include_once '../includes/cons_db.php';
include_once '../includes/functions.php';

// Comando para pegar o usuario logado no windows
// exec('wmic COMPUTERSYSTEM Get UserName', $user);


// Pega o usuário logado no windows
//$usuario = trim($test1[1]);

//$logado = substr($usuario,15);
//require_once '../auth/ldap_helper.php';

$usuario = $_SESSION['usuario'];
$logado = trim($usuario);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="iso-8859-1">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>

<!-- CSS only -->
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous"> 
</head>

<body>

<h2 class="text-center"><font color=#428bca>Quota por usu&aacute;rio</font></h2>

<br>

<div class="panel panel-default">
<div class="container-fluid">

<table class="table table-hover table-responsive-sm ">
<thead class="thead-dark">
<tr>
<th scope="col">Usu&aacute;rio</th>
<th scope="col">Quota</th>
</tr>
</thead>

<?php

$stmt = $mysqli->prepare("SELECT cod_politica, nome, quota_infinita FROM politicas");
//$stmt->bind_param('ii', $p_inicio,$p_qtde_por_pagina);
$stmt->execute(); 
$stmt->store_result();
$stmt->bind_result($cod_politica,$nome_politica,$quota_infinita);
 echo $cod_politica;
// Pega o usuario aluno
$aluno = "aluno";

if ($logado <> $aluno) {  
  	  
?>		  
<tbody>

<?php
   
   // Lista Politicas deste usuario
   while ($stmt->fetch()) {
      $grupo = grupo_usuario_politica($cod_politica,$logado);
      if ( $grupo != "") {
?>
		<tr class="table-info">
		
<?php
        echo "<td>$logado</td>";
        if ($quota_infinita == 1) {
           echo "<td>Quota Infinita</td>";
        } else {
           echo "<td>" . quota_usuario($cod_politica,$logado) ."</td>";
        }
        echo "</tr>";
      }
   }

?>
</tbody>		
</table>
</div>
</div>
<hr>
</body>
</html> 

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="iso-8859-1">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>

<!-- CSS only -->
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">


</head>

<body>

<center><h2><font color=#428bca>Relatório detalhado de impress&otilde;es</font></h2><br>

<div class="panel panel-default">
<div class="container-fluid">

<div class="table-responsive-sm">
<table class="table table-hover table-sm table-striped">
<thead class="thead-dark">
<tr>

<th scope="col">Data/hora</th>
<th scope="col">Usu&aacute;rio</th>
<th scope="col">Impressora</th>
<th scope="col">Esta&ccedil;&atilde;o</th>
<th scope="col">Documento</th>
<th scope="col">P&aacute;gina</th>

</tr>
<tbody>      




<?php

  $con = $mysqli;
  
  // Check connection
  if (mysqli_connect_errno())
  {
    echo "Falha ao conectar ao banco de dados: " . mysqli_connect_error();
  }
  //captura data de hoje do servidor
  $hoje = date('m');
  $ano = date('y');
  
  
  
  $sql = "SELECT
  DATE_FORMAT(data_impressao, '%d/%m/%y') as data_impressao, 
  hora_impressao,
  job_id,
  impressora, 
  usuario,
  estacao,
  nome_documento,
  paginas,
  cod_politica,
  cod_status_impressao
  FROM impressoes
  WHERE usuario = '$logado' AND date_format(data_impressao ,'%m')= $hoje AND date_format(data_impressao ,'%y')= $ano";
  
  
  $sql .= " ORDER BY cod_impressoes DESC";
  
  
  if ($result=mysqli_query($con, $sql))
  {
    
    //VERIFICA A QUANTIDADE DE REGISTROS RETORNADOS
    $registros = mysqli_num_rows($result);
    
    if($registros > 0){	
      // Fetch one and one row
      
      // Variavel total de impressoes
      $totalImpressoes = 0;
      while ($row=mysqli_fetch_row($result))
      {
        echo "<tr>";
        
        echo "<td>$row[0] - $row[1]</td>";                                       
        echo "<td>$row[4]</td>";
        echo "<td>$row[3]</td>";                
        echo "<td>$row[5]</td>";
        echo "<td>$row[6]</td>";
        echo "<td>$row[7]</td>";
        
        echo "</tr>";  
        $totalImpressoes += $row[7];                      
      }
      // Free result set
      mysqli_free_result($result);
      
      
    }// Fecha if da consulta no mysql
    
      //fecha conexão mysql      
      mysqli_close($con);
      
    } //Fecha if registro da quota usuario  
  
  

?>
</tbody>
</table>
</div>
</div>
</td></tr>
</table>
</div>
<?php 
if ($totalImpressoes > 0)
{
  ?>
  <div class="card">
  <div class="card-body">
  <div colspan="4" class="d-flex justify-content-center">         
  <b>Total de Impress&otilde;es - <b><?php echo "<b>" . $totalImpressoes; ?>
  </div>         
  </div>
  </div>
  <?php
}
} // Fecha if da consulta no mysql
else 
{
    echo "<script language=JavaScript> window.alert('O aluno não tem permissao para impressão!'); </script>";
    echo "<script>window.location = 'https://www.ifnmg.edu.br'</script>";
  }
  
   
session_destroy(); 
?>
</div>
</div>
</center>
<br>
<div class="text-center">
<a href="http://192.168.4.4/gg/login_consulta.php" class="btn btn-outline-primary" role="button" aria-pressed="true">Nova Consulta</a>
<hr>
</div>
</body>
</html>      
