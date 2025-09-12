<?php
require_once 'config.php';

$sql = "SELECT * FROM comande WHERE stampata = 0 ORDER BY id ASC LIMIT 10";
$res = $conn->query($sql);

$comande = [];
while ($row = $res->fetch_assoc()) {
    $comande[] = $row;
}

header('Content-Type: application/json');
echo json_encode($comande);
