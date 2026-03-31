<?php
include_once '../includes/db.php';
include_once '../includes/functions.php';

$sql_usuario  = "SELECT * FROM usuarios ORDER BY usuario";
$nome =  $mysqli->query($sql_usuario);
$menu_usuario = $nome->fetch_assoc();

sec_session_start();


if (login_check($mysqli) == false) {
  header("Location: ../login.php");
  exit();
}

include '../includes/header.php';
?>
<!DOCTYPE html>
<html>

<head lang="pt-br">
  <meta charset="UTF-8">
  <title>Auditoria Impress&otilde;es</title>
 
  <link rel="stylesheet" href="../css/bootstrap531/dist/css/bootstrap.min.css">

  <script src="../js/jquery371/dist/jquery.min.js"></script>  
  <script src="../css/bootstrap531/dist/js/bootstrap.min.js"></script>
  


 <script type="text/javascript">
    // Exibe a validação dos campos do formulário auditoria impressão			
    $(document).ready(function() {

      $('#criterio_entrada').on('change', function() {
        var criterio_validacao = $('#criterio_entrada option:selected').val();
        switch (parseInt(criterio_validacao)) {

          case 1:
            alert("Por favor preencha somente o campo nome usuario !");

            $('#busca_entrada').val("");
            $('#data_inicial').val("");
            $('#data_final').val("");
            $('#data_inicial').attr("disabled", true);
            $('#data_final').attr("disabled", true);
            document.getElementById("busca_entrada").focus();
            break;

          case 2:
            alert("Por favor preencha todos os campos !");

            $('#busca_entrada').val("");
            $('#data_inicial').val("");
            $('#data_final').val("");
            $('#data_inicial').attr("disabled", false);
            $('#data_final').attr("disabled", false);
            document.getElementById("busca_entrada").focus();
            break;

          case 3:
            alert("Por favor preencha somente os campos datas !");

            $("#busca_entrada").val("");
            $('#data_inicial').val("");
            $('#data_final').val("");
            $('#data_inicial').attr("disabled", false);
            $('#data_final').attr("disabled", false);
            document.getElementById("data_inicial").focus();
            break;

          case 4:
            alert("Por favor click somente em pesquisar !");

            $('#busca_entrada').val("");
            $('#data_inicial').val("");
            $('#data_final').val("")
            document.getElementById("pesquisar").focus();
            break;
        }
      });

      // Realiza a consulta ao clicar no botão pesquisar
      $('#pesquisar').on('click', function() {
        var criterio_entrada = $('#criterio_entrada option:selected').val();
        var busca_entrada = $('#busca_entrada').val();
        var data_inicial = $("#data_inicial").val();
        var data_final = $("#data_final").val();

        if (criterio_entrada == '') {

          alert("Por favor selecione um critério de busca !")
          document.getElementById("criterio_entrada").focus();
          return;
        }

        //Verificar se h� valor na vari�vel "criterio e busca" é = 1.
        if (criterio_entrada == 1 && busca_entrada !== '') {

          $('#aguarde').show();
          $.ajax({
              url: "con_auditoria_impressoes.php",
              type: "post",
              dataType: "html"
            })
            .done(function(retorna) {
              $("#resultado").html(retorna);
              $('#aguarde').hide();
            })
            .fail(function(retorna) {
              console.log(retorna);
            })
        }

        //Verificar se h� valor na vari�vel "criterio e busca" é = 2.
        if (criterio_entrada == 2 && busca_entrada !== '' && data_inicial !== '' && data_final !== '' && data_inicial <= data_final) {

          $('#aguarde').show();
          $.ajax({
              url: "con_auditoria_impressoes.php",
              type: "post",
              dataType: "html"
            })
            .done(function(retorna) {
              $("#resultado").html(retorna);
              $('#aguarde').hide();
            })
            .fail(function(retorna) {
              console.log(retorna);
            })
        }

        //Verificar se h� valor na vari�vel "criterio e busca" é = 3.
        if (criterio_entrada == 3 && data_inicial !== '' && data_final !== '' && data_inicial <= data_final) {

          $('#aguarde').show();
          $.ajax({
              cache: false,
              url: "con_auditoria_impressoes.php",
              type: "post",
              dataType: "html"
            })
            .done(function(retorna) {
              $("#resultado").html(retorna);
              $('#aguarde').hide();
            })
            .fail(function(retorna) {
              console.log(retorna);
            })

        }

        //Verificar se h� valor na vari�vel "criterio e busca" é = 4.
        if (criterio_entrada == 4) {

          $('#aguarde').show();
          $.ajax({
              url: "con_auditoria_impressoes.php",
              type: "post",
              dataType: "html"
            })
            .done(function(retorna) {
              $("#resultado").html(retorna);
              $('#aguarde').hide();
            })
            .fail(function(retorna) {
              console.log(retorna);
            })

        }

      });
    });
  </script>
</head>

<body>
  <div class="card border-dark">
    <div style="text-align: center;" class="card-header bg-dark text-white">CRIT&EacuteRIO DE BUSCA DA SELE&Ccedil;&Atilde;O</div>
    <div class="card-body">
      <div class="table-responsive-sm">
        <form class="row g-3" name="auditoria_impressoes" id="auditoria_impressoes" method="post" action="">
          <table cellpadding="1" cellspacing="1" class="display compact" width="100%">
            <thead>
              <tr>
                <div class="col-md-3">
                  <label for="criterio_entrada"><b>Selecione o crit&eacute;rio de Busca</b></label>
                  <select name="criterio_entrada" id="criterio_entrada" class="form-select" autofocus required>
                    <option value=""></option>
                    <option value="1">Nome Usu&aacute;rio</option>
                    <option value="2">Nome Usu&aacute;rio e Per&iacute;odo</option>
                    <option value="3">Per&iacute;odo Data</option>
                    <option value="4">Tudo</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="busca_entrada"><b>Nome Usu&aacute;rio</b></label>
                  <input type="text" name="busca_entrada" list="datalistOptions" id="busca_entrada" size="60" class="form-control">
                  <datalist id="datalistOptions">
                    <option value="0">--</option>
                    <?php
                    do {
                    ?>
                      <option value="<?php echo $menu_usuario['usuario']; ?>"><?php echo $menu_usuario['usuario']; ?></option>
                    <?php
                    } while ($menu_usuario = $nome->fetch_assoc());
                    $nome->free_result();

                    ?>
                  </datalist>
                </div>
                <div class="col-md-2">
                  <label for="data_inicial"><b>Data Inicial</b></label>
                  <input name="data_inicial" type="date" id="data_inicial" class="form-control" disabled>
                </div>
                <div class="col-md-2">
                  <label for="data_final"><b>Data Final</b></label>
                  <input name="data_final" type="date" id="data_final" class="form-control" disabled>
                </div>
                <div class="col-md-1">
                  <label for="pesquisar"><b></b></label>
                  <button type="button" id="pesquisar" class="btn btn-success form-control">Pesquisar</button>
                </div>
                <div class="col-md-1">
                  <label for="limpar"><b></b></label>
                  <button type="reset" id="limpar" class="btn btn-danger form-control">Limpar</button>
                </div>
              </tr>
            </thead>

            <tbody class="auditoria_impressoes-error">
              <tr>
                <td>
                  <!--AQUI SER� APRESENTADO O RESULTADO DA BUSCA DIN�MICA.. OU SEJA OS NOMES-->
                  <div id="aguarde" style="display:none" class="alert alert-success" role="alert">Aguarde, estamos processando</div>
                  <div id="resultado"></div>
                </td>
              </tr>
            </tbody>
          </table>
        </form>
      </div>
    </div>
  </div>
</body>

</html>
<?php
include '../includes/footer.php';

 // Fecha a conexao
mysqli_close($mysqli);
?>