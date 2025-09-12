<?php
require_once 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenotazione_id = intval($_POST['prenotazione_id']);
    $ordine = json_decode($_POST['ordine_json'], true);

    if (!$prenotazione_id || empty($ordine)) {
        exit("Dati mancanti.");
    }

    $stmt = $conn->prepare("INSERT INTO comande (prenotazione_id, nome, note, quantita, categoria, stampata) VALUES (?, ?, ?, ?, ?, 0)");

    foreach ($ordine as $riga) {
        $stmt->bind_param(
            "issis",
            $prenotazione_id,
            $riga['nome'],
            $riga['note'],
            $riga['quantita'],
            $riga['categoria']
        );
        $stmt->execute();
    }

    $stmt->close();

    // Dopo l'invio, redirect per tornare alla prenotazione e vedere il riepilogo aggiornato
    header("Location: comanda.php?id=$prenotazione_id"); // cambia con il tuo file se il nome è diverso
    exit;
}
?>
