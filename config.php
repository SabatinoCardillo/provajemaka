<?php
$servername="31.11.39.234";
$username="Sql1877401";
$password="Carmine.1234.q";
$dbname="Sql1877401_1";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connessione al database fallita: " . $conn->connect_error);
}
?>