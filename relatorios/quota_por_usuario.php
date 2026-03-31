<?php 
/**
 * IBQUOTA 3
 * GG - Gerenciador Grafico do IBQUOTA
 * 
 * 23/12/2018 - Valcir C.
 *
 * Lista Quotas de um usuario especifico
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
  <h2 class="text-center"><font color=#428bca>Quota por usuário</font></h2><br>
    <center>
       <table border="0" width="98%" align="center">
        <tr><td>
        <div class="panel panel-default">
          <div class="container-fluid">
           
            <table class="table table-hover table-sm">
              <thead>
                <tr>
                  <th scope="col">Usuário</th>
                  <th scope="col">Quota</th>
                </tr>
              </thead>
              <tbody>
                <?php
                    $con = $mysqli;
                    
                    // Check connection
                    if (mysqli_connect_errno())
                    {
                      echo "Falha ao conectar ao banco de dados: " . mysqli_connect_error();
                    }

                    $sql = "SELECT usuario, quota FROM quota_usuario ORDER BY usuario ASC;";

                    if ($result=mysqli_query($con, $sql))
                    {
                        // Fetch one and one row
                        while ($row=mysqli_fetch_row($result))
                        {
                            echo "<tr>";
                              echo "<td>$row[0]</td>";                
                              echo "<td>$row[1]</td>";
                            echo "</tr>";                        
                        }
                         // Free result set
                         mysqli_free_result($result);
                    }                    
                    mysqli_close($con);
                  ?>
              </tbody>
        </table>
      </div>
     </div>
    </td></tr>
   </table>
   <a class="btn btn-primary" href="..\index.php" role="button" aria-expanded="false">Voltar</a>
   </center>

<?php

   include '../includes/footer.php';
?>
