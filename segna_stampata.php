<?php
require_once 'config.php';
$id = intval($_GET['id']);
$conn->query("UPDATE comande SET stampata = 1 WHERE id = $id");
?>