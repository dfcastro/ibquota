<?php
#Arquivo de fun��es *************************************
include("../includes/db.php");

#********************************************************
?>

<!DOCTYPE html>
<html>

<head lang="pt-br">
    <meta charset="UTF-8">
    <title>test</title>
    <link rel="stylesheet" href="../datatables/DataTables-1.13.8/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../datatables/Responsive-2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="../datatables/Select-1.7.0/css/select.dataTables.min.css">
    <script src="../datatables/DataTables-1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="../datatables/Responsive-2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="../datatables/Select-1.7.0/js/dataTables.select.min.js"></script>

    <script type="text/javascript">
        $(document).ready(function() {

            var criterio_entrada = $('#criterio_entrada option:selected').val();
            var busca_entrada = $("#busca_entrada").val();
            var data_inicial  = $("#data_inicial").val();
            var data_final    = $("#data_final").val();

            var dados = {
                criterio_entrada: criterio_entrada,
                busca_entrada: busca_entrada,
                data_inicial: data_inicial,
                data_final: data_final
               
            };

           
            $('#con_auditoria_impressoes').DataTable({

                responsive: {
                    details: {
                        display: $.fn.dataTable.Responsive.display.modal({
                            header: function(row) {
                                var data = row.data();
                                return 'Usuário: ' + data[2];
                            }
                        }),
                        renderer: $.fn.dataTable.Responsive.renderer.tableAll()
                    }
                },

                "filter": false,

                "columnDefs": [{
                    "targets": [0],
                    "targets": [7],
                    //"className": 'dt-body-right',
                    "orderable": false,
                }, ],               

                order: [
        [1, 'desc']
       
    ],
                "processing": true,
                "serverSide": true,

                "ajax": {

                    url: 'proc_pesq_con_auditoria_impressoes.php',
                    type: 'post',
                    //data: { criterio_entrada:criterio_entrada, busca_entrada:busca_entrada },                       
                    data: dados,
                    error: function() {
                        // error handling
                        $(".con_auditoria_impressoes-error").html("");
                        $("#con_auditoria_impressoes").append('<tbody class="rel_pedido-error"><tr><th colspan="3">No data found in the server</th></tr></tbody>');
                        $("#con_auditoria_impressoes_processing").css("display", "none");

                    }
                },

                "lengthMenu": [10, 25, 50, 100, 500],

                select: true,

                "pagingType": "full_numbers",
                "language": {
                    "sEmptyTable": "Nenhum registro encontrado",
                    "sInfo": "Mostrando de _START_ at&eacute; _END_ de _TOTAL_ registros",
                    "sInfoEmpty": "Mostrando 0 at&eacute; 0 de 0 registros",
                    "sInfoFiltered": "(Filtrados de _MAX_ registros)",
                    "sInfoPostFix": "",
                    "sInfoThousands": ".",
                    "sLengthMenu": "Resultados por p&aacute;gina: _MENU_ ",
                    "sLoadingRecords": "<div class='bg-success'>Carregando...</div>",
                    "sProcessing": "<div style='color:white' class='bg-info'>Processando...</div>",
                    "sZeroRecords": "Nenhum registro encontrado",
                    "sSearch": "Pesquisar: _INPUT_",
                    "autoWidth": true,
                    "oPaginate": {
                        "sNext": "Pr&oacute;ximo",
                        "sPrevious": "Anterior",
                        "sFirst": "Primeiro",
                        "sLast": "&Uacute;ltimo"
                    },
                    "oAria": {
                        "sSortAscending": ": Ordenar colunas de forma ascendente",
                        "sSortDescending": ": Ordenar colunas de forma descendente"
                    },
                    select: {
                        rows: {
                            _: "Seleção de %d linhas",
                            1: "Seleção de 1 linha"
                        }
                    }
                },

                "footerCallback": function(row, data, start, end, display) {
                    var api = this.api(),
                        data;

                    // Remove the formatting to get integer data for summation
                    var intVal = function(i) {
                        return typeof i === 'string' ? i.replace(/[\.]/g, '').replace(/[\,]/g, '.') * 1 : typeof i === 'number' ? i : 0;
                    };

                    // total_darf over all pages
                    total_entrada = api
                        .column(6)
                        .data()
                        .reduce(function(a, b) {
                            return intVal(a) + intVal(b);
                        }, 0);

                    // total_page_darf over this page
                    total_page_entrada = api
                        .column(6, {
                            page: 'current'
                        })
                        .data()
                        .reduce(function(a, b) {
                            return intVal(a) + intVal(b);
                        }, 0);

                    //sem R$
                    var vr_entrada = total_entrada.toLocaleString('pt-br', {
                        minimumFractionDigits: 0
                    });
                    var vr_entrada_pag = total_page_entrada.toLocaleString('pt-br', {
                        minimumFractionDigits: 0
                    });

                    // Update footer
                    $('#totalentrada').html(vr_entrada);
                }
            });
        });
    </script>
</head>

<body>
    <div class="card border-dark">
        <div style="text-align: center;" class="card-header bg-dark text-white">RELAT&Oacute;RIO DAS IMPRESS&Otilde;ES</div>
        <div class="card-body">
            <div class="table-responsive-sm">
                <table cellpadding="1" cellspacing="1" id="con_auditoria_impressoes" class="display compact" width="100%">
                    <thead>
                        <tr>
                            <th data-orderable="false">Job ID</th>
                            <th data-orderable="false">Data/hora</th>                                                        
                            <th data-orderable="false">Usu&aacute;rio</th>
                            <th data-orderable="false">Impressora</th>
                            <th data-orderable="false">Esta&ccedil;&atilde;o</th>
                            <th data-orderable="false">Documento</th>
                            <th data-orderable="false">P&aacute;gina</th>
                            <th data-orderable="false">Status</th>                            
                        </tr>
                    </thead>

                    <tbody>

                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="6" style="text-align:right; white-space: nowrap"><b>Total por P&aacute;gina:</b></th>
                            <th colspan="2" style="white-space: nowrap;"><span style="float:left;" id='totalentrada'></span></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</body>

</html>
<?php
// Fecha a conexao
mysqli_close($mysqli);
?>