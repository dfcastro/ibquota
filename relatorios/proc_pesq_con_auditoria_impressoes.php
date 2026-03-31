<?php
#Arquivo de funções ********************************** 
include("../includes/functions.php");
include("../includes/db.php");

#********************************************************

//Receber a requisão da pesquisa 
$requestData = $_REQUEST;

//Indice da coluna na tabela visualizar resultado => nome da coluna no banco de dados
$columns = array(
	0=> 'job_id', 
	1=> 'data_impressao',
	2=> 'usuario',
	3=> 'impressora',
	4=> 'estacao',
	5=> 'nome_documento',
	6=> 'paginas',
	7=> 'cod_status_impressao'

);

# Func��o para gravar date
function formatarData($data)
{
	$rData = implode("-", array_reverse(explode("/", trim($data))));
	return $rData;
}
# fim da fun��o

// Pegar a p�gina atual por POST
$criterio_entrada = isset($_POST['criterio_entrada']) ? $_POST['criterio_entrada'] : '';
$busca_entrada = isset($_POST['busca_entrada']) ? $_POST['busca_entrada'] : '';
$data_inicial = isset($_POST['data_inicial']) ? $_POST['data_inicial'] : '';
$data_final = isset($_POST['data_final']) ? $_POST['data_final'] : '';


/*
//Obtendo registros de número total sem qualquer pesquisa
$sql_entrada = "SELECT * FROM impressoes ORDER BY cod_impressoes DESC";
$resultado_total = mysqli_query($conn, $sql_entrada);
$qnt_linhas = mysqli_num_rows($resultado_total);
$totalFiltered = $qnt_linhas; // Quando não há parâmetro de pesquisa, o número total de linhas = número total de linhas filtradas
*/

$sql_impressao = "SELECT * FROM impressoes ORDER BY cod_impressoes DESC";
$resultado_total = $mysqli->query($sql_impressao);
$qnt_linhas = $resultado_total->num_rows;
$totalFiltered = $qnt_linhas;

// Pega o ano do dia da consulta
$agora = new DateTime(); // Pega o momento atual
$ano  = date_format($agora, 'Y'); // Exibe no formato desejado

$sql1 = "SELECT data_impressao, hora_impressao, job_id, impressora, usuario, estacao, nome_documento, paginas, cod_politica, cod_status_impressao
FROM impressoes WHERE usuario  LIKE '".$busca_entrada."'";

$sql2 = "SELECT data_impressao, hora_impressao, job_id, impressora, usuario, estacao, nome_documento, paginas, cod_politica, cod_status_impressao
FROM impressoes WHERE usuario LIKE '".$busca_entrada."' and data_impressao BETWEEN '" . $data_inicial . "' AND '" . $data_final . "'";

$sql3 = "SELECT data_impressao, hora_impressao, job_id, impressora, usuario, estacao, nome_documento, paginas, cod_politica, cod_status_impressao
FROM impressoes WHERE data_impressao BETWEEN '" . $data_inicial . "' AND '" . $data_final . "'";

$sql4 = "SELECT data_impressao, hora_impressao, job_id, impressora, usuario, estacao, nome_documento, paginas, cod_politica, cod_status_impressao
FROM impressoes";

/*
$sql5 = "SELECT data_impressao, hora_impressao, job_id, impressora, usuario, estacao, nome_documento, sum(paginas) as paginas, cod_politica, cod_status_impressao
FROM impressoesxxx 
WHERE usuario LIKE '" . $busca_entrada . "'
GROUP BY data_impressao, hora_impressao, job_id, impressora, usuario, estacao, nome_documento, paginas, cod_politica, cod_status_impressao";
*/

// Seleciona no banco de dados com o LIMIT indicado pelos n�meros acima
switch ($criterio_entrada) {
	case 1:
		$criterio_entrada = $sql1;
		break;
	case 2:
		$criterio_entrada = $sql2;
		break;
	case 3:
		$criterio_entrada = $sql3;
		break;
	case 4:
		$criterio_entrada = $sql4;
		break;
	case 5:
		$criterio_entrada = $sql5;
		break;
	default;
		$criterio_entrada = $sql4;
		break;
}

//Obter os dados a serem apresentados
$sql_impressoes = "$criterio_entrada";

/*
if (!empty($requestData['search']['value'])) {   // se houver um parâmetro de pesquisa, $requestData['search']['value'] contém o parâmetro de pesquisa
	$sql_entradas .= " AND (cod_produto LIKE '" . $requestData['search']['value'] . "' ";
	$sql_entradas .= " OR cod_fornecedor LIKE '" . $requestData['search']['value'] . "' ";
	$sql_entradas .= " OR descricao LIKE '" . $requestData['search']['value'] . "%' ";
	$sql_entradas .= " OR n_entrada LIKE '" . $requestData['search']['value'] . "' ";
	$sql_entradas .= " OR data_entrada LIKE '" . $requestData['search']['value'] . "')";
}
*/

$resultado_impressao = $mysqli->query($sql_impressoes);
$totalFiltered = $resultado_impressao->num_rows;

//Ordenar o resultado
$sql_impressoes .= " ORDER BY " . $columns[$requestData['order'][0]['column']] . "   " . $requestData['order'][0]['dir'] . "  LIMIT " . $requestData['start'] . " ," . $requestData['length'] . "   ";
$resultado_impressoes = $mysqli->query($sql_impressoes);

// Ler e criar o array de dados
$dados = array();
$i = 1 + $requestData['start'];
while ($row_impressao = $resultado_impressoes->fetch_assoc()) {
	$dado = array();

	$dado[] = $row_impressao["job_id"];
	$dado[] = date('d/m/Y',strtotime($row_impressao["data_impressao"])) . '-' .$row_impressao["hora_impressao"];
	$dado[] = $row_impressao["usuario"];
	$dado[] = $row_impressao["impressora"];
	$dado[] = $row_impressao["estacao"];	
	$dado[] = $row_impressao["nome_documento"];	
	$dado[] = $row_impressao["paginas"];
	$dado[] = status_impressao($row_impressao["cod_status_impressao"]);
	
	$dados[] = $dado;
	$i++;
}

//Cria o array de informações a serem retornadas para o Javascript
$json_data = array(
	"draw" => intval($requestData['draw']), //para cada requisição é enviado um número como parâmetro
	"recordsTotal" => intval($qnt_linhas),  //Quantidade de registros que há no banco de dados
	"recordsFiltered" => intval($totalFiltered), //Total de registros quando houver pesquisa
	"data" => $dados   //Array de dados completo dos dados retornados da tabela 
);

echo json_encode($json_data);