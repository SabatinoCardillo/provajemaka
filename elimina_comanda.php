<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Dati JSON non validi']);
        exit;
    }
    
    try {
        if ($data['tipo'] === 'singola') {
            // Elimina singola comanda
            $comanda_id = intval($data['comanda_id']);
            
            if (!$comanda_id) {
                echo json_encode(['success' => false, 'message' => 'ID comanda mancante']);
                exit;
            }
            
            // Elimina la comanda
            $stmt = $conn->prepare("DELETE FROM comande WHERE id = ?");
            $stmt->bind_param("i", $comanda_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Comanda eliminata con successo']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Comanda non trovata']);
            }
            $stmt->close();
            
        } elseif ($data['tipo'] === 'categoria') {
            // Elimina tutte le comande di una categoria
            $categoria = $data['categoria'];
            $prenotazione_id = intval($data['prenotazione_id']);
            
            if (!$categoria || !$prenotazione_id) {
                echo json_encode(['success' => false, 'message' => 'Dati mancanti per elimina categoria']);
                exit;
            }
            
            // Elimina tutte le comande salvate (stampata = 6) della categoria
            $stmt = $conn->prepare("DELETE FROM comande WHERE categoria = ? AND prenotazione_id = ? AND stampata = 6");
            $stmt->bind_param("si", $categoria, $prenotazione_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => "Eliminate {$stmt->affected_rows} comande della categoria {$categoria}"
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Nessuna comanda da eliminare in questa categoria']);
            }
            $stmt->close();
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Tipo di operazione non valido']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Errore database: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
}
?>