<?php
require_once 'config.php';
session_start();

// Imposta l'header per JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo non supportato']);
    exit;
}

// Leggi i dati JSON dalla richiesta
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dati non validi']);
    exit;
}

$prenotazione_id = intval($input['prenotazione_id'] ?? 0);
$menu_id = intval($input['menu_id'] ?? 0);
$azione = $input['azione'] ?? '';

if (!$prenotazione_id || !$menu_id || !in_array($azione, ['aggiungi', 'rimuovi'])) {
    echo json_encode(['success' => false, 'message' => 'Parametri mancanti o non validi']);
    exit;
}

try {
    $conn->begin_transaction();

    // Recupera la prenotazione originale
    $stmt = $conn->prepare("SELECT numero_persone, menu_id, tot_pagare FROM prenotazioni WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Errore preparazione query prenotazione: " . $conn->error);
    }
    
    $stmt->bind_param("i", $prenotazione_id);
    if (!$stmt->execute()) {
        throw new Exception("Errore esecuzione query prenotazione: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $prenotazione = $result->fetch_assoc();
    $stmt->close();
    
    if (!$prenotazione) {
        throw new Exception("Prenotazione non trovata");
    }

    // Verifica se esiste già una voce per questo menu
    $stmt_check = $conn->prepare("SELECT id, quantita FROM prenotazione_menu_sostituzioni WHERE prenotazione_id = ? AND menu_id = ?");
    if (!$stmt_check) {
        throw new Exception("Errore preparazione query check: " . $conn->error);
    }
    
    $stmt_check->bind_param("ii", $prenotazione_id, $menu_id);
    if (!$stmt_check->execute()) {
        throw new Exception("Errore esecuzione query check: " . $stmt_check->error);
    }
    
    $result_check = $stmt_check->get_result();
    $sostituzione_esistente = $result_check->fetch_assoc();
    $stmt_check->close();

    if ($azione === 'aggiungi') {
        // Verifica che ci siano menu base disponibili
        $stmt_count = $conn->prepare("SELECT COALESCE(SUM(quantita), 0) as totale_sostituzioni FROM prenotazione_menu_sostituzioni WHERE prenotazione_id = ?");
        $stmt_count->bind_param("i", $prenotazione_id);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        $totale_sostituzioni = $result_count->fetch_assoc()['totale_sostituzioni'];
        $stmt_count->close();
        
        if ($totale_sostituzioni >= $prenotazione['numero_persone']) {
            throw new Exception("Non ci sono più menu base disponibili da sostituire");
        }

        if ($sostituzione_esistente) {
            // Aumenta la quantità esistente
            $nuova_quantita = $sostituzione_esistente['quantita'] + 1;
            $stmt_update = $conn->prepare("UPDATE prenotazione_menu_sostituzioni SET quantita = ? WHERE id = ?");
            $stmt_update->bind_param("ii", $nuova_quantita, $sostituzione_esistente['id']);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            // Crea una nuova voce
            $stmt_insert = $conn->prepare("INSERT INTO prenotazione_menu_sostituzioni (prenotazione_id, menu_id, quantita) VALUES (?, ?, 1)");
            $stmt_insert->bind_param("ii", $prenotazione_id, $menu_id);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        
    } else if ($azione === 'rimuovi') {
        if (!$sostituzione_esistente || $sostituzione_esistente['quantita'] <= 0) {
            throw new Exception("Non ci sono menu di questo tipo da rimuovere");
        }

        if ($sostituzione_esistente['quantita'] > 1) {
            // Diminuisce la quantità
            $nuova_quantita = $sostituzione_esistente['quantita'] - 1;
            $stmt_update = $conn->prepare("UPDATE prenotazione_menu_sostituzioni SET quantita = ? WHERE id = ?");
            $stmt_update->bind_param("ii", $nuova_quantita, $sostituzione_esistente['id']);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            // Rimuove completamente la voce
            $stmt_delete = $conn->prepare("DELETE FROM prenotazione_menu_sostituzioni WHERE id = ?");
            $stmt_delete->bind_param("i", $sostituzione_esistente['id']);
            $stmt_delete->execute();
            $stmt_delete->close();
        }
    }

    // Ricalcola il totale
    $stmt_totale = $conn->prepare("
        SELECT 
            p.numero_persone,
            mb.prezzo as prezzo_base,
            COALESCE(SUM((ma.prezzo - mb.prezzo) * pms.quantita), 0) as differenza_totale
        FROM prenotazioni p
        LEFT JOIN menu mb ON p.menu_id = mb.id
        LEFT JOIN prenotazione_menu_sostituzioni pms ON p.id = pms.prenotazione_id
        LEFT JOIN menu ma ON pms.menu_id = ma.id
        WHERE p.id = ?
        GROUP BY p.id
    ");
    
    if (!$stmt_totale) {
        throw new Exception("Errore preparazione query totale: " . $conn->error);
    }
    
    $stmt_totale->bind_param("i", $prenotazione_id);
    if (!$stmt_totale->execute()) {
        throw new Exception("Errore esecuzione query totale: " . $stmt_totale->error);
    }
    
    $result_totale = $stmt_totale->get_result();
    $dati_totale = $result_totale->fetch_assoc();
    $stmt_totale->close();
    
    // Calcola il nuovo totale
    $totale_base = $dati_totale['numero_persone'] * $dati_totale['prezzo_base'];
    $nuovo_totale = $totale_base + $dati_totale['differenza_totale'];
    
    // Aggiorna il totale nella prenotazione
    $stmt_update_totale = $conn->prepare("UPDATE prenotazioni SET tot_pagare = ? WHERE id = ?");
    if (!$stmt_update_totale) {
        throw new Exception("Errore preparazione query update totale: " . $conn->error);
    }
    
    $stmt_update_totale->bind_param("di", $nuovo_totale, $prenotazione_id);
    if (!$stmt_update_totale->execute()) {
        throw new Exception("Errore aggiornamento totale: " . $stmt_update_totale->error);
    }
    $stmt_update_totale->close();

    // Recupera i dati aggiornati per la risposta
    $stmt_menu_aggiornati = $conn->prepare("SELECT menu_id, quantita FROM prenotazione_menu_sostituzioni WHERE prenotazione_id = ?");
    $stmt_menu_aggiornati->bind_param("i", $prenotazione_id);
    $stmt_menu_aggiornati->execute();
    $result_menu_aggiornati = $stmt_menu_aggiornati->get_result();
    
    $menu_assegnati_aggiornati = [];
    while ($row = $result_menu_aggiornati->fetch_assoc()) {
        $menu_assegnati_aggiornati[$row['menu_id']] = $row['quantita'];
    }
    $stmt_menu_aggiornati->close();

    // Calcola il nuovo numero di menu base disponibili
    $menu_base_utilizzati = !empty($menu_assegnati_aggiornati) ? array_sum($menu_assegnati_aggiornati) : 0;
    $menu_base_disponibili = $prenotazione['numero_persone'] - $menu_base_utilizzati;

    $conn->commit();

    // Risposta di successo con i dati aggiornati
    echo json_encode([
        'success' => true,
        'message' => 'Menu aggiornato con successo',
        'data' => [
            'menu_assegnati' => $menu_assegnati_aggiornati,
            'menu_base_disponibili' => $menu_base_disponibili,
            'nuovo_totale' => number_format($nuovo_totale, 2),
            'quantita_aggiornata' => [
                'menu_id' => $menu_id,
                'quantita' => isset($menu_assegnati_aggiornati[$menu_id]) ? $menu_assegnati_aggiornati[$menu_id] : 0
            ]
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Errore Ajax aggiornamento menu: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>