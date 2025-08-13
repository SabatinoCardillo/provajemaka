<?
$h="mysql-jemaka.alwaysdata.net";
$u="jemaka";
$p="Saba270704!";
$db="jemaka_clienti";

$conn=mysqli_connect($h,$u,$p,$db);
if(!$conn){
    die ("Connessione fallita" . mysqli_connect_error());
}

?>
