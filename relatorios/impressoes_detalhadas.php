<?php

//ini_set('display_errors',1);
//ini_set('display_startup_erros',1);
//error_reporting(E_ALL);

/**
 * IBQUOTA 3
 * GG - Gerenciador Grafico do IBQUOTA
 * 
 * 12/11/2018 - Valcir C.
 *
 * Lista Impressoes

 */


include_once '../includes/db.php';
include_once '../includes/functions.php';


sec_session_start();


if (login_check($mysqli) == false) {
  header("Location: ../login.php");
  exit();
}

include '../includes/header.php';
?>

<script charset="utf-8" type="text/javascript">
  /******************************************************************************
  function limpar(qualInput)
  {
    document.filtro.qualInput.value = '';
  }

  function isNumber(n) 
  {
    return !isNaN(parseFloat(n)) && isFinite(n);
  }


  function colocarBarrasTxtDataInicial() 
  {
    if (document.filtro.txtDataInicial.value.length == 8)
    {       
      var dataMascarada = document.filtro.txtDataInicial.value.substring(0,2) + '/' + document.filtro.txtDataInicial.value.substring(2,4) + '/' + document.filtro.txtDataInicial.value.substring(4,8);
       document.filtro.txtDataInicial.value = '';

       document.filtro.txtDataInicial.value = dataMascarada;
    }
    else
    {
      document.filtro.txtDataInicial.value = '';
      document.filtro.txtDataInicial.focus();      
    }
  }


  function colocarBarrasTxtDataFinal() 
  {
    if (document.filtro.txtDataFinal.value.length == 8)
    {       
      var dataMascarada = document.filtro.txtDataFinal.value.substring(0,2) + '/' + document.filtro.txtDataFinal.value.substring(2,4) + '/' + document.filtro.txtDataFinal.value.substring(4,8);
       document.filtro.txtDataFinal.value = '';

       document.filtro.txtDataFinal.value = dataMascarada;
    }
    else
    {
      document.filtro.txtDataFinal.value = '';
      document.filtro.txtDataFinal.focus();      
    }
  }



  function validarTxtDataInicial ()
  {
    var txtDataInicial = document.filtro.txtDataInicial.value.trim();
    if (txtDataInicial.length > 0)
    {
      if (isNumber(txtDataInicial))
      {
        var tecla = event.keyCode;
        if (txtDataInicial.length == 1)
        {          
          if (txtDataInicial.substring(0,1) > 3)
          {
            alert('Digite o dia do m�s com dois d�gitos (01 at� 31)');
            txtDataInicial = '';
            document.filtro.txtDataInicial.value = '';
            document.filtro.txtDataInicial.focus();
          }
        }
        if (txtDataInicial.length == 4)
        {
          if (txtDataInicial.substring(2,4) > 12 || txtDataInicial.substring(2,4) < 1)
          {
            alert('Digite o m�s com dois d�gitos (01 a 12) ');            
            document.filtro.txtDataInicial.value = '';
            document.filtro.txtDataInicial.focus();
          }
        }
        if (txtDataInicial.length == 8)
        {                      
          if (txtDataInicial.substring(4,8) < 2019)
          {
            alert('Digite o ano a partir de 2019, com 4 d�gitos');            
            document.filtro.txtDataInicial.value = '';
            document.filtro.txtDataInicial.focus();
          }

        }          
      }
      else
      {
        alert('Digite apenas n�meros na data.');
        document.filtro.txtDataInicial.value = '';
        document.filtro.txtDataInicial.focus();
      }
    }
  }



  function validarTxtDataFinal ()
  {
    var txtDataFinal = document.filtro.txtDataFinal.value.trim();
    if (txtDataFinal.length > 0)
    {
      if (isNumber(txtDataFinal))
      {
        var tecla = event.keyCode;
        if (txtDataFinal.length == 1)
        {          
          if (txtDataFinal.substring(0,1) > 3)
          {
            alert('Digite o dia do m�s com dois d�gitos (01 at� 31)');

            txtDataFinal = '';
            document.filtro.txtDataFinal.value = '';
            document.filtro.txtDataFinal.focus();
          }
        }
        if (txtDataFinal.length == 4)
        {
          if (txtDataFinal.substring(2,4) > 12 || txtDataFinal.substring(2,4) < 1)
          {
            alert('Digite o m�s com dois d�gitos (01 a 12) ');            
            document.filtro.txtDataFinal.value = '';
            document.filtro.txtDataFinal.focus();
          }
        }
        if (txtDataFinal.length == 8)
        {                      
          if (txtDataFinal.substring(4,8) < 2019)
          {
            alert('Digite o ano a partir de 2019, com 4 d�gitos');            
            document.filtro.txtDataFinal.value = '';
            document.filtro.txtDataFinal.focus();
          }

        }          
      }
      else
      {
        alert('Digite apenas n�meros na data.');
        document.filtro.txtDataFinal.value = '';
        document.filtro.txtDataFinal.focus();
      }
    }
  }   ***********************************************************************/
</script>

<?php
######### Data espec�fica: ontem, hoje ou tudo ################
if (isset($_POST['data_especifica'])) {
  $data_especifica = $_POST['data_especifica'];

  if ($data_especifica == 'data_tudo')
    //$sqlDataEspecifica = "data_tudo"; // todos os resultados
    $sqlDataEspecifica = "excluido_data_tudo"; // todos os resultados
  elseif ($data_especifica == 'data_anteontem')
    $sqlDataEspecifica = date('Y-m-d', time() - (24 * 3600 * 2)); // anteontem: data atual - 2 dias em segundos
  elseif ($data_especifica == 'data_ontem')
    $sqlDataEspecifica =  date('Y-m-d', time() - (24 * 3600)); // ontem: timestamp atual em segundos menos 24h em segundos
  elseif ($data_especifica == 'data_hoje')
    $sqlDataEspecifica = date('Y-m-d');
}

############ Verifica se h� nome de usuário preenchido com pelo menos 2 caracteres ########
if (isset($_POST['nome_usuario'])) {
  $nome_usuario = $_POST['nome_usuario'];
}

######### Intervalo de datas ###########################
if (isset($_POST['txtDataInicial'][9]) && isset($_POST['txtDataFinal'][9])) // cada input de data tem que ter necessariamente 10 caracteres, incluindo a barra.
{
  $txtDataInicial = trim($_POST['txtDataInicial']);
  //$txtDataInicial = explode("/", $txtDataInicial);
  //$txtDataInicial = $txtDataInicial[2] . '-' . $txtDataInicial[1] . '-' . $txtDataInicial[0];
  $txtDataFinal = $_POST['txtDataFinal'];
  //$txtDataFinal = explode("/", $txtDataFinal);
  //$txtDataFinal = $txtDataFinal[2] . '-' . $txtDataFinal[1] . '-' . $txtDataFinal[0];

}

?>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>

<center>
  <h2>
    <font color=#428bca>Relatório detalhado de impress&otilde;es</font>
  </h2><br>

  <form name="filtro" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm alert">
          <div class="btn-group" data-toggle="buttons">
            <label class="btn btn-outline-info active">
              <input type="radio" name="data_especifica" value="data_tudo" autocomplete="off"> Tudo
            </label>

            <label class="btn btn-outline-info">
              <input type="radio" name="data_especifica" value="data_anteontem" autocomplete="off"> Anteontem
            </label>

            <label class="btn btn-outline-info">
              <input type="radio" name="data_especifica" value="data_ontem" autocomplete="off"> Ontem
            </label>

            <label class="btn btn-outline-info">
              <input type="radio" name="data_especifica" value="data_hoje" autocomplete="off" checked> Hoje
            </label>
          </div>
        </div>

        <div class="col-sm alert">
          <div class="input-group mb-3">
            <input type="text" name="nome_usuario" class="form-control" placeholder="Usuario institucional">
            <div class="input-group-append">
              <span class="input-group-text">@ifnmg.edu.br</span>
            </div>
          </div>
        </div>
        <div class="col-sm alert">
          <input type="date" name="txtDataInicial" id="txtDataInicial" style="border-radius: 5px; border: solid thin #c0c0c0; width: 130px; padding-left: 4px" onkeydown="validarTxtDataInicial();" onblur="colocarBarrasTxtDataInicial();" onfocus="limpar('txtDataInicial');"> -

          <input type="date" name="txtDataFinal" id="txtDataFinal" style="border-radius: 5px; border: solid thin #c0c0c0; width: 130px; padding-left: 4px" onkeydown="validarTxtDataFinal();" onblur="colocarBarrasTxtDataFinal();" onfocus="limpar('txtDataFinal');">
        </div>



        <div class="col-sm alert">
          <input type="submit" name="buscar" class="btn btn-success" value="Gerar Relatório">
        </div>
      </div>
    </div>

  </form>

  <div class="table-responsive-sm">
    <table class="table table-hover table-sm">
      <thead>
        <tr>
          <th scope="col">Job ID</th>
          <th scope="col">Data/hora</th>
          <th scope="col">Usu&aacute;rio</th>
          <th scope="col">Impressora</th>
          <th scope="col">Esta&ccedil;&atilde;o</th>
          <th scope="col">Documento</th>
          <th scope="col">P&aacute;gina</th>
          <th scope="col">Status</th>
        </tr>

        <?php
        // faz conex�o PDO em vez de mysqli
        $conexao = new PDO("mysql:host=localhost;dbname=ibquota3", "ibquota", "D9xjcHza");

        $sql = "SELECT
            DATE_FORMAT(data_impressao, '%d/%m/%Y') as data_impressao, 
            hora_impressao,
            job_id,
            impressora, 
            usuario,
            estacao,
            nome_documento,
            paginas,
            cod_politica,
            cod_status_impressao
            FROM impressoes ";



        if (
          (isset($_POST['data_especifica']) && ($sqlDataEspecifica != "data_tudo")) || (
            (isset($_POST['nome_usuario'][1])) ||
            (isset($_POST['txtDataInicial'][9]) && isset($_POST['txtDataFinal'][9])))
        ) {


          $sql .= " WHERE cod_status_impressao = '1'";


          if (($sqlDataEspecifica != 'data_tudo') && (!isset($_POST['txtDataInicial'][9]) && !isset($_POST['txtDataFinal'][9]))) // anteontem ou ontem ou hoje APENAS se n�o tiver escolhido o per�odo
          {
            // filtra por data espec�fica: anteontem ou ontem ou hoje.
            $sql .= "AND data_impressao = '" . $sqlDataEspecifica . "'";

            // � data espec�fica (n�o quer filtro por per�odo), ent�o verifica se filtrou por usu�rio tamb�m
            if (isset($_POST['nome_usuario'][1]))
              $sql .= " AND usuario = " . "'" . $nome_usuario . "'";
          } else {
            // Filtra por nome nome de usuario com pelo menos 2 caracteres
            if (isset($_POST['nome_usuario'][1])) {
              $sql .= "AND usuario = " . "'" . $nome_usuario . "'";

              if (isset($_POST['txtDataInicial'][9]) && isset($_POST['txtDataFinal'][9])) {
                $sql .= " AND data_impressao BETWEEN '" . $txtDataInicial . "' AND '" . $txtDataFinal . "'";
              }
            }
            // filtra por per�odo            
            else if (isset($_POST['txtDataInicial'][9]) && isset($_POST['txtDataFinal'][9]))
              $sql .= "AND data_impressao BETWEEN '" . $txtDataInicial . "' AND '" . $txtDataFinal . "'";
          }
        }

        $sql .= " ORDER BY cod_impressoes DESC";

        // echo $sql;
        $resultado = $conexao->query($sql);
        $totalImpressoes = 0;
        while ($linha = $resultado->fetch(PDO::FETCH_OBJ)) {

        ?>
          <tr>
            <td> <?php echo $linha->job_id; ?></td>
            <td> <?php echo $linha->data_impressao . ' ' . $linha->hora_impressao; ?></td>
            <td> <?php echo $linha->usuario; ?></td>
            <td> <?php echo $linha->impressora; ?></td>
            <td> <?php echo $linha->estacao; ?></td>
            <td> <?php echo utf8_decode($linha->nome_documento); ?></td>
            <td> <?php echo $linha->paginas; ?></td>
            <td> <?php echo status_impressao($linha->cod_status_impressao); ?></td>
          </tr>
        <?php
          $totalImpressoes += $linha->paginas;
        }
        ?>

      </thead>
    </table>
  </div>
  <?php
  if ($totalImpressoes > 0) {
  ?>
    <div class="card">
      <div class="card-body">
        <strong>
          <?php echo "Total: " . $totalImpressoes; ?>
        </strong>
      </div>
    </div>
  <?php
  }

  ?>
</center>

<?php

   include '../includes/footer.php';

?>