<?php
require_once 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Controlla se i dati arrivano come JSON o come POST normale
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        // Dati JSON - stampa diretta (ora deprecata, usa stampa_comanda.php)
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            http_response_code(400);
            echo "Dati JSON non validi";
            exit;
        }
        
        $prenotazione_id = intval($data['prenotazione_id']);
        
        // Inserisce direttamente come stampata (0) per compatibilità legacy
        $ordine = [[
            'nome' => $data['nome'],
            'note' => $data['note'],
            'quantita' => intval($data['quantita']),
            'categoria' => $data['categoria']
        ]];
        $stampata = 0; // Stampa diretta
        
    } else {
        // Dati POST normali dal form - non più utilizzato, ora si usa salva_comanda.php
        $prenotazione_id = intval($_POST['prenotazione_id']);
        $ordine = json_decode($_POST['ordine_json'], true);
        $stampata = 6; // Salva come "da stampare"
    }
    
    if (!$prenotazione_id || empty($ordine)) {
        http_response_code(400);
        echo "Dati mancanti.";
        exit;
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO comande (prenotazione_id, nome, note, quantita, categoria, stampata) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($ordine as $riga) {
            $stmt->bind_param(
                "issisi",
                $prenotazione_id,
                $riga['nome'],
                $riga['note'],
                $riga['quantita'],
                $riga['categoria'],
                $stampata
            );
            $stmt->execute();
        }
        
        $stmt->close();
        echo "Comanda inserita con successo";
        
    } catch (Exception $e) {
        http_response_code(500);
        echo "Errore nell'inserimento: " . $e->getMessage();
    }
}
?>