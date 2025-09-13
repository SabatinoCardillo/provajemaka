<?php

// Abilitare error reporting per debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Imposta header JSON
header('Content-Type: application/json');

try {
    session_start();

    // Verifica che sia una richiesta AJAX
    if (!isset($_GET['ajax']) || $_GET['ajax'] !== '1') {
        throw new Exception('Richiesta non valida');
    }

    // Verifica che config.php esista
    if (!file_exists('config.php')) {
        throw new Exception("File config.php non trovato.");
    }
    
    require_once 'config.php';

    // Verifica connessione database
    if (!isset($conn) || !$conn) {
        throw new Exception("Errore: connessione al database non disponibile.");
    }

    // Recupera e valida parametri
    $prenotazione_id = $_GET['id'] ?? null;
    $categoria_selezionata = $_GET['categoria'] ?? null;
    $pietanze_page = isset($_GET['pietanze_page']) ? max(1, (int)$_GET['pietanze_page']) : 1;
    
    if (!$prenotazione_id || !is_numeric($prenotazione_id)) {
        throw new Exception("ID prenotazione non valido.");
    }

    if (!$categoria_selezionata) {
        throw new Exception("Categoria non specificata.");
    }

    // Verifica che la prenotazione esista
    $stmt_verifica = $conn->prepare("SELECT id FROM prenotazioni WHERE id = ?");
    if (!$stmt_verifica) {
        throw new Exception("Errore preparazione query verifica prenotazione: " . $conn->error);
    }
    
    $stmt_verifica->bind_param("i", $prenotazione_id);
    if (!$stmt_verifica->execute()) {
        throw new Exception("Errore esecuzione query verifica prenotazione: " . $stmt_verifica->error);
    }
    
    $result_verifica = $stmt_verifica->get_result();
    if ($result_verifica->num_rows === 0) {
        throw new Exception("Prenotazione non trovata.");
    }
    $stmt_verifica->close();

    // Parametri paginazione
    $pietanze_per_page = 10;
    $pietanze_offset = ($pietanze_page - 1) * $pietanze_per_page;

    // Verifica che la categoria esista
    $stmt_categoria = $conn->prepare("SELECT COUNT(*) as count FROM categorie WHERE nome = ?");
    if (!$stmt_categoria) {
        throw new Exception("Errore preparazione query categoria: " . $conn->error);
    }
    
    $stmt_categoria->bind_param("s", $categoria_selezionata);
    if (!$stmt_categoria->execute()) {
        throw new Exception("Errore esecuzione query categoria: " . $stmt_categoria->error);
    }
    
    $result_categoria = $stmt_categoria->get_result();
    $categoria_count = $result_categoria->fetch_assoc()['count'];
    $stmt_categoria->close();

    if ($categoria_count === 0) {
        throw new Exception("Categoria non trovata.");
    }

    // Conta il totale delle pietanze per la categoria selezionata
    $count_pietanze_stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM pietanze p 
        JOIN categorie c ON p.id_categoria = c.id 
        WHERE c.nome = ?
    ");
    
    if (!$count_pietanze_stmt) {
        throw new Exception("Errore preparazione query count pietanze: " . $conn->error);
    }
    
    $count_pietanze_stmt->bind_param("s", $categoria_selezionata);
    if (!$count_pietanze_stmt->execute()) {
        throw new Exception("Errore esecuzione query count pietanze: " . $count_pietanze_stmt->error);
    }
    
    $total_result = $count_pietanze_stmt->get_result();
    $total_pietanze = $total_result->fetch_assoc()['total'];
    $count_pietanze_stmt->close();

    $total_pietanze_pages = ceil($total_pietanze / $pietanze_per_page);

    // Assicurati che la pagina richiesta sia valida
    if ($pietanze_page > $total_pietanze_pages && $total_pietanze_pages > 0) {
        $pietanze_page = $total_pietanze_pages;
        $pietanze_offset = ($pietanze_page - 1) * $pietanze_per_page;
    }

    // Query pietanze con paginazione per categoria specifica
    $pietanze_stmt = $conn->prepare("
        SELECT p.id, p.nome, c.nome AS categoria 
        FROM pietanze p 
        JOIN categorie c ON p.id_categoria = c.id 
        WHERE c.nome = ? 
        ORDER BY p.nome 
        LIMIT ? OFFSET ?
    ");
    
    if (!$pietanze_stmt) {
        throw new Exception("Errore preparazione query pietanze: " . $conn->error);
    }
    
    $pietanze_stmt->bind_param("sii", $categoria_selezionata, $pietanze_per_page, $pietanze_offset);
    if (!$pietanze_stmt->execute()) {
        throw new Exception("Errore esecuzione query pietanze: " . $pietanze_stmt->error);
    }
    
    $pietanze_result = $pietanze_stmt->get_result();
    $pietanze_paginata = [];
    while ($p = $pietanze_result->fetch_assoc()) {
        $pietanze_paginata[] = [
            'id' => (int)$p['id'],
            'nome' => $p['nome'],
            'categoria' => $p['categoria']
        ];
    }
    $pietanze_stmt->close();

    // Chiudi la connessione
    $conn->close();

    // Prepara la risposta JSON
    $response = [
        'success' => true,
        'data' => [
            'categoria_selezionata' => $categoria_selezionata,
            'pietanze_page' => $pietanze_page,
            'pietanze_per_page' => $pietanze_per_page,
            'total_pietanze' => $total_pietanze,
            'total_pietanze_pages' => $total_pietanze_pages,
            'pietanze' => $pietanze_paginata
        ],
        'message' => 'Pietanze caricate con successo'
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // In caso di errore, restituisci un JSON di errore
    $error_response = [
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null
    ];
    
    echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
}
?>
