<?php
//error_reporting(E_ALL);
//ini_set('error_reporting', E_ALL);
//ini_set('display_errors', 1);

session_start();


require_once 'auth/ldap_helper.php';
require_once 'auth/websercice_help.php';
require_once 'auth/config.inc';

# verificando se estamos recebendo um POST. Não aceitamos GET
if( $_SERVER['REQUEST_METHOD'] !== "POST" )
    __output_header__( false, "Método de requisição não aceito.", null );


$r = authValidateUser($_POST['email'], $_POST['senha']);

$_SESSION['usuario'] = $_POST['email'];
$usuario = $_SESSION['usuario'];

# se erro
if( $r === false ){
    //__output_header__( false, 'Usuário não encontrado.', null); 
	header('Location: login_consulta.php');
				  }		 
else {
    //$user_details = authLdapGetDetails($r['user']);
    //__output_header__( true, "Sucesso durante autenticação!", $user_details);
	header('Location: relatorios/quota_usu.php');
}
