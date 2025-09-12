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
            // Stampa singola comanda
            $comanda_id = intval($data['comanda_id']);
            
            if (!$comanda_id) {
                echo json_encode(['success' => false, 'message' => 'ID comanda mancante']);
                exit;
            }
            
            // Aggiorna lo stato della comanda a stampata (0)
            $stmt = $conn->prepare("UPDATE comande SET stampata = 0 WHERE id = ? AND stampata = 6");
            $stmt->bind_param("i", $comanda_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Comanda stampata con successo']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Comanda non trovata o già stampata']);
            }
            $stmt->close();
            
        } elseif ($data['tipo'] === 'categoria') {
            // Stampa tutte le comande di una categoria
            $categoria = $data['categoria'];
            $prenotazione_id = intval($data['prenotazione_id']);
            
            if (!$categoria || !$prenotazione_id) {
                echo json_encode(['success' => false, 'message' => 'Dati mancanti per stampa categoria']);
                exit;
            }
            
            // Aggiorna tutte le comande della categoria a stampata (0)
            $stmt = $conn->prepare("UPDATE comande SET stampata = 0 WHERE categoria = ? AND prenotazione_id = ? AND stampata = 6");
            $stmt->bind_param("si", $categoria, $prenotazione_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => "Stampate {$stmt->affected_rows} comande della categoria {$categoria}"
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Nessuna comanda da stampare in questa categoria']);
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