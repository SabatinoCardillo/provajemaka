<?php
$h = "0.tcp.sa.ngrok.io";   // host pubblico ngrok
$port = 15075;               // porta pubblica ngrok
$u = "sito_user";            // l'utente MySQL creato sulla VPS
$p = "password_sicura";      // la password dell'utente
$db = "jemaka_clienti";             // il nome del database sulla VPS

// Connessione con porta personalizzata
$conn = mysqli_connect($h, $u, $p, $db, $port);

if(!$conn){
    die ("Connessione fallita: " . mysqli_connect_error());
}
?>
