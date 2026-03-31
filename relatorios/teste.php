<?php 
$ontem = date('Y-m-d', time() - (24*3600));
$anteontem = date('Y-m-d', time() - (24*3600*545));
echo $ontem;
echo '<br />';
echo $anteontem;
?>