<?php
$h = "mysql-jemaka.alwaysdata.net";   // host pubblico ngrok
$u = "jemaka";            // l'utente MySQL creato sulla VPS
$p = "Saba270704!";      // la password dell'utente
$db = "jemaka_clienti";             // il nome del database sulla VPS

// Connessione con porta personalizzata
$conn = mysqli_connect($h, $u, $p, $db);

if(!$conn){
    die ("Connessione fallita: " . mysqli_connect_error());
}
?>
