<?php
/**
 * * GG - Gerenciador Grafico do IBQUOTA
 * 
 *
 * Pagina de login
 */ 
//include_once 'auth.php';



?>
<!DOCTYPE html>
<html>
    <head>
        <link rel="icon" href="favicon.png" />
        <title>Consulta Impress&atilde;o</title>
        <link href="css/bootstrap.min.css" rel="stylesheet">
    </head>



    <body class="bg-light">
       

<br>



   
<center>
        <div class="card mb-4 shadow-sm" style="width: 18rem;">
          <div class="card-header bg-success">
            <h4 class="my-0 font-weight-normal"><b>Consulta Impress&atilde;o</b></h4>
          </div>
          <div class="card-body">
            <h1 class="card-title pricing-card-title"><small><small>Gerenciador Grafico</small></small></h1>

           <form action="auth.php" method="post" name="login_form">

            <div class="form-group">
               
                <div class="input-group">

                  <div class="input-group-prepend">
                    <div class="input-group-text"> <img src="png/icon-username.png" class="img-rounded"></div>
                  </div>
				  <input type="text" class="form-control form-control-lg" id="login" name="email" placeholder="Usu&aacute;rio" style="text-transform: lowercase;"required, autofocus>
                </div>
            </div>


            <div class="form-group">

            
                <div class="input-group">
                  <div class="input-group-prepend">
                    <div class="input-group-text"><img src="png/icon-password.png" class="img-rounded"></div>
                  </div>
                  <input type="password" class="form-control" id="senha" name="senha" placeholder="Senha">
                </div>
            </div>
                                          
            <button type="submit" class="btn btn-primary btn-lg">&nbsp;&nbsp;&nbsp;Entrar&nbsp;&nbsp;&nbsp;</button><br><br>

          </form>
          </div>
        </div>
</center>





  <script type="text/JavaScript" src="js/jquery-3.3.1.min.js"></script>
  <script type="text/JavaScript" src="js/bootstrap.min.js"></script>

  </body>
</html>
