<?php

/**
 * AÇÃO SILENCIOSA: Adicionar Grupo
 */
include_once '../includes/db.php';
include_once '../includes/functions.php';
sec_session_start();

if (login_check($mysqli) == false || $_SESSION['permissao'] < 1) {
  header("Location: ../login.php");
  exit();
}

if (isset($_POST['grupo'])) {
  $grupo = trim($_POST['grupo']);
  if (strlen($grupo) > 0) {
    $chk = $mysqli->prepare("SELECT cod_grupo FROM grupos WHERE grupo = ?");
    $chk->bind_param('s', $grupo);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows == 0) {
      $ins = $mysqli->prepare("INSERT INTO grupos (grupo) VALUES (?)");
      $ins->bind_param('s', $grupo);
      $ins->execute();
    }
  }
}
header("Location: index.php?msg=add");
exit();
