<?php
require_once 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenotazione_id = intval($_POST['prenotazione_id']);
    $ordine = json_decode($_POST['ordine_json'], true);
    
    if (!$prenotazione_id || empty($ordine)) {
        header("Location: comanda.php?id=$prenotazione_id&error=dati_mancanti");
        exit;
    }
    
    try {
        // Salva le comande con stampata = 6 (salvata ma non stampata)
        $stmt = $conn->prepare("INSERT INTO comande (prenotazione_id, nome, note, quantita, categoria, stampata) VALUES (?, ?, ?, ?, ?, 6)");
        
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
        
        // Redirect con messaggio di successo
        header("Location: comanda.php?id=$prenotazione_id&success=comanda_salvata");
        exit;
        
    } catch (Exception $e) {
        header("Location: comanda.php?id=$prenotazione_id&error=errore_database");
        exit;
    }
}
?>