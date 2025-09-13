<?php
// Abilitare error reporting per debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    session_start();

    // Verifica che config.php esista
    if (!file_exists('config.php')) {
        die("File config.php non trovato.");
    }
    
    require_once 'config.php';

    // Verifica connessione database
    if (!isset($conn) || !$conn) {
        die("Errore: connessione al database non disponibile.");
    }

    $id = $_GET['id'] ?? null;
    if (!$id || !is_numeric($id)) {
        die("ID prenotazione non valido.");
    }

    // Query per prenotazione
    $stmt = $conn->prepare("SELECT p.*, c.nome AS cliente_nome, c.cognome FROM prenotazioni p JOIN clienti c ON p.cliente_id = c.id WHERE p.id = ?");
    if (!$stmt) {
        die("Errore preparazione query prenotazione: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        die("Errore esecuzione query prenotazione: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $prenotazione = $result->fetch_assoc();
    $stmt->close();

    if (!$prenotazione) {
        die("Prenotazione non trovata.");
    }

    // Query per menu
    $menu_query = "SELECT * FROM menu";
    $menu = $conn->query($menu_query);
    if (!$menu) {
        die("Errore query menu: " . $conn->error);
    }

    // PAGINAZIONE PIETANZE - NUOVO SISTEMA
    $categoria_selezionata = $_GET['categoria'] ?? null;
    $pietanze_page = isset($_GET['pietanze_page']) ? max(1, (int)$_GET['pietanze_page']) : 1;
    $pietanze_per_page = 10;
    $pietanze_offset = ($pietanze_page - 1) * $pietanze_per_page;

    // Query per ottenere tutte le categorie
    $categorie_query = "SELECT DISTINCT c.nome FROM categorie c INNER JOIN pietanze p ON c.id = p.id_categoria ORDER BY c.nome";
    $categorie_result = $conn->query($categorie_query);
    if (!$categorie_result) {
        die("Errore query categorie: " . $conn->error);
    }
    
    $categorie = [];
    while ($cat = $categorie_result->fetch_assoc()) {
        $categorie[] = $cat['nome'];
    }

    // Se non c'è categoria selezionata, usa la prima
    if (!$categoria_selezionata && !empty($categorie)) {
        $categoria_selezionata = $categorie[0];
    }

    $pietanze_paginata = [];
    $total_pietanze = 0;
    $total_pietanze_pages = 1;

    if ($categoria_selezionata) {
        // Conta il totale delle pietanze per la categoria selezionata
        $count_pietanze_stmt = $conn->prepare("SELECT COUNT(*) as total FROM pietanze p JOIN categorie c ON p.id_categoria = c.id WHERE c.nome = ?");
        if (!$count_pietanze_stmt) {
            die("Errore preparazione query count pietanze: " . $conn->error);
        }
        
        $count_pietanze_stmt->bind_param("s", $categoria_selezionata);
        if (!$count_pietanze_stmt->execute()) {
            die("Errore esecuzione query count pietanze: " . $count_pietanze_stmt->error);
        }
        
        $total_result = $count_pietanze_stmt->get_result();
        $total_pietanze = $total_result->fetch_assoc()['total'];
        $count_pietanze_stmt->close();

        $total_pietanze_pages = ceil($total_pietanze / $pietanze_per_page);

        // Query pietanze con paginazione per categoria specifica
        $pietanze_stmt = $conn->prepare("SELECT p.id, p.nome, c.nome AS categoria FROM pietanze p JOIN categorie c ON p.id_categoria = c.id WHERE c.nome = ? ORDER BY p.nome LIMIT ? OFFSET ?");
        if (!$pietanze_stmt) {
            die("Errore preparazione query pietanze: " . $conn->error);
        }
        
        $pietanze_stmt->bind_param("sii", $categoria_selezionata, $pietanze_per_page, $pietanze_offset);
        if (!$pietanze_stmt->execute()) {
            die("Errore esecuzione query pietanze: " . $pietanze_stmt->error);
        }
        
        $pietanze_result = $pietanze_stmt->get_result();
        while ($p = $pietanze_result->fetch_assoc()) {
            $pietanze_paginata[] = $p;
        }
        $pietanze_stmt->close();
    }

    // Carica comande salvate
    $stmt = $conn->prepare("SELECT id, nome, note, quantita, categoria FROM comande WHERE prenotazione_id = ? AND stampata = 6 ORDER BY categoria, nome");
    if (!$stmt) {
        die("Errore preparazione query comande: " . $conn->error);
    }
    
    $stmt->bind_param("i", $prenotazione['id']);
    if (!$stmt->execute()) {
        die("Errore esecuzione query comande: " . $stmt->error);
    }
    
    $comande_salvate = $stmt->get_result();
    $comande_per_categoria = [];
    
    while ($comanda = $comande_salvate->fetch_assoc()) {
        $comande_per_categoria[$comanda['categoria']][] = $comanda;
    }
    $stmt->close();

    // Paginazione ordini (sistema esistente)
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $items_per_page = 5;
    $offset = ($page - 1) * $items_per_page;

    // Conteggio totale ordini
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM comande WHERE prenotazione_id = ?");
    if (!$count_stmt) {
        die("Errore preparazione query count: " . $conn->error);
    }
    
    $count_stmt->bind_param("i", $prenotazione['id']);
    if (!$count_stmt->execute()) {
        die("Errore esecuzione query count: " . $count_stmt->error);
    }
    
    $total_result = $count_stmt->get_result();
    $total_orders = $total_result->fetch_assoc()['total'];
    $count_stmt->close();

    $total_pages = ceil($total_orders / $items_per_page);

    // Query ordini con paginazione
    $orders_stmt = $conn->prepare("SELECT c.id, c.nome, c.note, c.quantita, c.categoria, c.stampata, c.created_at FROM comande c WHERE c.prenotazione_id = ? ORDER BY c.created_at DESC LIMIT ? OFFSET ?");
    if (!$orders_stmt) {
        die("Errore preparazione query ordini: " . $conn->error);
    }
    
    $orders_stmt->bind_param("iii", $prenotazione['id'], $items_per_page, $offset);
    if (!$orders_stmt->execute()) {
        die("Errore esecuzione query ordini: " . $orders_stmt->error);
    }
    
    $orders_result = $orders_stmt->get_result();

} catch (Exception $e) {
    die("Errore generale: " . $e->getMessage());
}

// Funzione per badge stato
function getStatusBadge($stampata) {
    $stati = [
        0 => '<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">Nuovo</span>',
        1 => '<span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">In Preparazione</span>',
        2 => '<span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Pronto</span>',
        3 => '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Servito</span>',
        4 => '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Annullato</span>',
        5 => '<span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">In Attesa</span>',
        6 => '<span class="px-2 py-1 text-xs rounded-full bg-orange-100 text-orange-800">Da Stampare</span>'
    ];
    
    return $stati[$stampata] ?? '<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">Sconosciuto</span>';
}

// Funzione per creare URL con parametri esistenti
function createUrl($newParams = []) {
    $currentParams = $_GET;
    $params = array_merge($currentParams, $newParams);
    return '?' . http_build_query($params);
}

// Carica menu assegnati dal database
$menu = $conn->query("SELECT * FROM menu");
$menu_base_id = $prenotazione['menu_id'];
$numero_persone = $prenotazione['numero_persone'];

// Carica il prezzo del menu base
$stmt_prezzo_base = $conn->prepare("SELECT prezzo FROM menu WHERE id = ?");
$stmt_prezzo_base->bind_param("i", $menu_base_id);
$stmt_prezzo_base->execute();
$result_prezzo_base = $stmt_prezzo_base->get_result();
$prenotazione_menu_base_prezzo = $result_prezzo_base->fetch_assoc()['prezzo'];
$stmt_prezzo_base->close();

// Carica menu assegnati dal database
$menu_assegnati_query = "SELECT menu_id, quantita FROM prenotazione_menu_sostituzioni WHERE prenotazione_id = ?";
$stmt_assegnati = $conn->prepare($menu_assegnati_query);
if (!$stmt_assegnati) {
    die("Errore preparazione query menu assegnati: " . $conn->error);
}

$stmt_assegnati->bind_param("i", $prenotazione['id']);
if (!$stmt_assegnati->execute()) {
    die("Errore esecuzione query menu assegnati: " . $stmt_assegnati->error);
}

$menu_assegnati_result = $stmt_assegnati->get_result();
$menu_assegnati = [];
while ($row = $menu_assegnati_result->fetch_assoc()) {
    $menu_assegnati[$row['menu_id']] = $row['quantita'];
}
$stmt_assegnati->close();

// Calcola quanti menu base sono rimasti
$menu_base_utilizzati = !empty($menu_assegnati) ? array_sum($menu_assegnati) : 0;
$menu_base_disponibili = $numero_persone - $menu_base_utilizzati;

// Gestisci messaggi di successo/errore
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'menu_aggiornato':
            $success_message = 'Menu aggiornato con successo!';
            break;
    }
}

if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Comanda Veloce</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .btn-categoria { min-height: 36px; }
        .btn-action { 
            min-height: 28px; 
            min-width: 28px; 
            font-size: 14px;
            font-weight: bold;
        }
        .counter-display { 
            min-width: 30px; 
            font-size: 12px;
            font-weight: bold;
        }
        .pietanza-item {
            transition: background-color 0.2s;
        }
        .pietanza-item:hover {
            background-color: #f9fafb;
        }
        
        .section-header {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .section-header:hover {
            background-color: #f3f4f6;
        }
        
        .section-content {
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .section-content.collapsed {
            max-height: 0;
            opacity: 0;
        }
        
        .section-content.expanded {
            max-height: 2000px;
            opacity: 1;
        }

        /* Loader per i pulsanti del menu */
        .loading {
            pointer-events: none;
            opacity: 0.6;
        }
        
        .loading::after {
            content: '⏳';
            margin-left: 5px;
        }
        
        .messaggio-temporaneo { opacity: 0; }
        
        @media (max-width: 640px) {
            .btn-categoria { min-height: 32px; font-size: 0.75rem; }
            .btn-action { min-height: 24px; min-width: 24px; font-size: 12px; }
            .counter-display { min-width: 24px; font-size: 11px; }
        }
    </style>
</head>
<body class="bg-gray-100 p-2">
    <div class="max-w-4xl mx-auto bg-white p-3 rounded shadow">
        
        <!-- Messaggi di successo/errore -->
        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Header compatto -->
        <div class="flex justify-between items-center mb-4 bg-blue-50 p-3 rounded">
            <div>
                <h1 class="text-lg font-bold">🍽️ <?php echo htmlspecialchars($prenotazione['cliente_nome']); ?> <?php echo htmlspecialchars($prenotazione['cognome']); ?></h1>
                <div class="text-sm text-gray-600">Persone: <?php echo $prenotazione['numero_persone']; ?> (Adulti: <?php echo $prenotazione['numero_adulti']; ?>, Bambini: <?php echo $prenotazione['numero_bambini']; ?>)</div>
            </div>
            <a href="index_cameriere.php" class="bg-gray-500 text-white px-3 py-2 rounded text-sm hover:bg-gray-600">⬅️ Indietro</a>
        </div>

        <!-- SEZIONE 1: MENU -->
        <div class="mb-4 bg-white border rounded-lg shadow-sm">
            <div class="section-header px-4 py-3 bg-purple-50 border-b flex justify-between items-center" onclick="toggleSection('menu')">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">🍽️</span>
                    <h2 class="text-lg font-semibold text-gray-800">Menu</h2>
                </div>
                <span id="menu-icon" class="text-lg transform transition-transform">▼</span>
            </div>
            <div id="menu-content" class="section-content collapsed p-4">
                <!-- Menu Base -->
                <div class="bg-blue-50 p-4 rounded border mb-4">
                    <h3 class="text-md font-semibold mb-3 flex items-center">
                        🍽️ Menu Base
                        <span id="menu-base-badge" class="ml-2 bg-blue-500 text-white px-2 py-1 rounded-full text-xs">
                            <?= $menu_base_disponibili ?> persone
                        </span>
                    </h3>
                    <div class="bg-white p-3 rounded border">
                        <span class="font-medium">
                            <?php
                            $menu->data_seek(0);
                            while ($m = $menu->fetch_assoc()) {
                                if ($m['id'] == $prenotazione['menu_id']) {
                                    echo htmlspecialchars($m['nome']) . ' - €' . $m['prezzo'];
                                    break;
                                }
                            }
                            ?>
                        </span>
                    </div>
                </div>

                <!-- Menu Alternativi con Pulsanti + e - (Ajax) -->
                <div class="bg-yellow-50 p-4 rounded border">
                    <h3 class="text-md font-semibold mb-3">🔄 Menu Alternativi</h3>
                    <div class="space-y-3">
                        <?php 
                        $menu->data_seek(0);
                        while ($m = $menu->fetch_assoc()): 
                            if ($m['id'] != $prenotazione['menu_id']): 
                                $quantita_assegnata = isset($menu_assegnati[$m['id']]) ? $menu_assegnati[$m['id']] : 0;
                                $differenza_prezzo = $m['prezzo'] - $prenotazione_menu_base_prezzo;
                                $segno_prezzo = $differenza_prezzo >= 0 ? '+' : '';
                        ?>
                            <div class="bg-white p-3 rounded border flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="font-medium text-sm">
                                        <?= htmlspecialchars($m['nome']) ?> - €<?= $m['prezzo'] ?>
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        Differenza: <?= $segno_prezzo ?>€<?= number_format($differenza_prezzo, 2) ?> per persona
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    <!-- Quantità attuale -->
                                    <div class="text-center">
                                        <div class="text-xs text-gray-500">Assegnati</div>
                                        <span id="quantita-menu-<?= $m['id'] ?>" class="bg-gray-100 px-2 py-1 rounded text-sm font-bold min-w-[30px] inline-block">
                                            <?= $quantita_assegnata ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Pulsanti + e - con Ajax -->
                                    <div class="flex items-center gap-1">
                                        <!-- Pulsante - (Rimuovi) -->
                                        <button type="button" 
                                                id="btn-rimuovi-<?= $m['id'] ?>"
                                                onclick="aggiornaMenuAjax(<?= $prenotazione['id'] ?>, <?= $m['id'] ?>, 'rimuovi')"
                                                class="bg-red-500 text-white w-8 h-8 rounded hover:bg-red-600 text-sm font-bold <?= $quantita_assegnata == 0 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                                <?= $quantita_assegnata == 0 ? 'disabled' : '' ?>>
                                            -
                                        </button>
                                        
                                        <!-- Pulsante + (Aggiungi) -->
                                        <button type="button" 
                                                id="btn-aggiungi-<?= $m['id'] ?>"
                                                onclick="aggiornaMenuAjax(<?= $prenotazione['id'] ?>, <?= $m['id'] ?>, 'aggiungi')"
                                                class="bg-green-500 text-white w-8 h-8 rounded hover:bg-green-600 text-sm font-bold <?= $menu_base_disponibili == 0 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                                <?= $menu_base_disponibili == 0 ? 'disabled' : '' ?>>
                                            +
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endif; 
                        endwhile; 
                        ?>
                    </div>
                    
                    <!-- Riepilogo -->
                    <div class="mt-4 bg-blue-100 p-3 rounded border">
                        <div class="text-sm font-medium text-center">
                            Menu Base Rimanenti: <span id="menu-base-rimanenti" class="font-bold"><?= $menu_base_disponibili ?></span>
                        </div>
                        <?php if (!empty($menu_assegnati)): ?>
                            <div class="text-xs text-gray-600 text-center mt-2">
                                Totale sostituzioni: <span id="totale-sostituzioni"><?= array_sum($menu_assegnati) ?></span>
                            </div>
                        <?php else: ?>
                            <div id="sostituzioni-info" class="text-xs text-gray-600 text-center mt-2" style="display: none;">
                                Totale sostituzioni: <span id="totale-sostituzioni">0</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEZIONE 2: ORDINI CON PAGINAZIONE -->
        <div class="mb-4 bg-white border rounded-lg shadow-sm">
            <div class="section-header px-4 py-3 bg-green-50 border-b flex justify-between items-center" onclick="toggleSection('ordini')">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">📋</span>
                    <h2 class="text-lg font-semibold text-gray-800">Ordini</h2>
                </div>
                <span id="ordini-icon" class="text-lg transform transition-transform">▼</span>
            </div>
            <div id="ordini-content" class="section-content expanded p-4">
                <!-- Selezione Categoria -->
                <div class="mb-4">
                    <h3 class="font-bold text-md mb-3">📋 Categorie</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                        <?php foreach ($categorie as $cat): ?>
                            <button type="button"
                                    onclick="cambiaCategoria('<?php echo htmlspecialchars($cat, ENT_QUOTES); ?>')"
                                    class="btn-categoria <?php echo $cat === $categoria_selezionata ? 'bg-blue-700' : 'bg-blue-500 hover:bg-blue-600'; ?> text-white px-3 py-2 rounded font-medium text-sm text-center">
                                <?php echo htmlspecialchars($cat); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Form per la comanda -->
                <form method="POST" action="salva_comanda.php" onsubmit="return salvaOrdine()">
                    <input type="hidden" name="prenotazione_id" value="<?php echo $prenotazione['id']; ?>">
                    <input type="hidden" name="ordine_json" id="ordine_json">
                    <input type="hidden" name="cameriere_id" value="<?php echo isset($_SESSION['cameriere_id']) ? $_SESSION['cameriere_id'] : ''; ?>">

                    <!-- Categoria Selezionata con Paginazione -->
                    <?php if ($categoria_selezionata && !empty($pietanze_paginata)): ?>
                        <div class="categoria mb-4">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-md font-bold bg-gray-100 px-3 py-2 rounded">
                                    <?php echo htmlspecialchars($categoria_selezionata); ?> 
                                    <span class="text-sm font-normal text-gray-600">
                                        (<?php echo $total_pietanze; ?> pietanze)
                                    </span>
                                </h3>
                                
                                <!-- Info paginazione -->
                                <?php if ($total_pietanze_pages > 1): ?>
                                    <div class="text-sm text-gray-600">
                                        Pagina <?php echo $pietanze_page; ?> di <?php echo $total_pietanze_pages; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php foreach ($pietanze_paginata as $p): ?>
                                    <div class="pietanza-item bg-gray-50 rounded border p-3">
                                        <!-- Nome pietanza con controlli inline -->
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="font-medium text-sm flex-1 pr-2"><?php echo htmlspecialchars($p['nome']); ?></div>
                                            <div class="flex items-center gap-1">
                                                <button type="button" onclick="decrementaPietanza(<?php echo $p['id']; ?>, '<?php echo str_replace("'", "\\'", $p['nome']); ?>', '<?php echo str_replace("'", "\\'", $p['categoria']); ?>')" 
                                                        class="btn-action bg-red-500 text-white rounded hover:bg-red-600">−</button>
                                                <span id="counter-<?php echo $p['id']; ?>" class="counter-display bg-white px-2 py-1 rounded border text-center">0</span>
                                                <button type="button" onclick="incrementaPietanza(<?php echo $p['id']; ?>, '<?php echo str_replace("'", "\\'", $p['nome']); ?>', '<?php echo str_replace("'", "\\'", $p['categoria']); ?>')" 
                                                        class="btn-action bg-green-500 text-white rounded hover:bg-green-600">+</button>
                                            </div>
                                        </div>
                                        
                                        <!-- Input note -->
                                        <input type="text" id="note-<?php echo $p['id']; ?>" placeholder="Note..." 
                                               class="w-full text-sm border px-3 py-2 rounded bg-white"
                                               onkeypress="if(event.key==='Enter') { incrementaPietanza(<?php echo $p['id']; ?>, '<?php echo str_replace("'", "\\'", $p['nome']); ?>', '<?php echo str_replace("'", "\\'", $p['categoria']); ?>'); }">
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Paginazione Pietanze -->
                            <?php if ($total_pietanze_pages > 1): ?>
                                <div class="mt-4 flex justify-between items-center">
                                    <div class="text-sm text-gray-700">
                                        Mostrando <?php echo count($pietanze_paginata); ?> di <?php echo $total_pietanze; ?> pietanze
                                    </div>
                                    <div class="flex gap-2">
                                        <!-- I pulsanti saranno sostituiti da JavaScript -->
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($categoria_selezionata): ?>
                        <div class="text-center py-8 text-gray-500">
                            <p>Nessuna pietanza trovata per la categoria "<?php echo htmlspecialchars($categoria_selezionata); ?>"</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <p>Seleziona una categoria per visualizzare le pietanze</p>
                        </div>
                    <?php endif; ?>

                    <!-- Riepilogo Ordine -->
                    <div class="mt-6 bg-blue-50 p-4 rounded border-2 border-blue-200">
                        <h3 class="font-bold text-md mb-3">🧾 Riepilogo Ordine</h3>
                        <div id="riepilogo" class="space-y-2 min-h-[60px] max-h-48 overflow-y-auto">
                            <div class="text-gray-500 text-center text-sm">Nessun elemento aggiunto</div>
                        </div>
                        
                        <div class="mt-4 flex gap-3">
                            <button type="button" onclick="svuotaOrdine()" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 flex-1 text-sm">
                                🗑️ Svuota
                            </button>
                            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 flex-1 font-bold text-sm">
                                💾 Salva Comanda
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- SEZIONE 3: STAMPA -->
        <div class="mb-4 bg-white border rounded-lg shadow-sm">
            <div class="section-header px-4 py-3 bg-yellow-50 border-b flex justify-between items-center" onclick="toggleSection('stampa')">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">🖨️</span>
                    <h2 class="text-lg font-semibold text-gray-800">Stampa</h2>
                    <?php if (!empty($comande_per_categoria)): ?>
                        <span class="bg-red-500 text-white px-2 py-1 rounded-full text-xs font-bold">
                            <?php echo array_sum(array_map('count', $comande_per_categoria)); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <span id="stampa-icon" class="text-lg transform transition-transform">▼</span>
            </div>
            <div id="stampa-content" class="section-content collapsed p-4">
                <!-- Comande da stampare -->
                <?php if (!empty($comande_per_categoria)): ?>
                <div class="bg-yellow-50 border-2 border-yellow-300 p-3 rounded mb-6">
                    <h3 class="text-md font-bold mb-3 text-center">🖨️ COMANDE DA STAMPARE</h3>
                    
                    <div class="space-y-2">
                        <?php foreach ($comande_per_categoria as $categoria => $comande): ?>
                            <div class="bg-white rounded border text-sm">
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-t cursor-pointer hover:bg-gray-100" 
                                     onclick="toggleCategoriaStampa('<?php echo str_replace("'", "\\'", $categoria); ?>')">
                                    <div class="flex items-center gap-2">
                                        <span id="freccia-stampa-<?php echo str_replace("'", "\\'", $categoria); ?>" class="text-sm">▶️</span>
                                        <h4 class="font-bold"><?php echo htmlspecialchars($categoria); ?></h4>
                                        <span class="bg-red-500 text-white px-2 py-1 rounded-full text-xs font-bold">
                                            <?php echo count($comande); ?>
                                        </span>
                                    </div>
                                    <div class="flex gap-2" onclick="event.stopPropagation()">
                                        <button onclick="stampaCategoria('<?php echo str_replace("'", "\\'", $categoria); ?>')" 
                                                class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 text-xs">
                                            🖨️ Stampa
                                        </button>
                                        <button onclick="eliminaCategoria('<?php echo str_replace("'", "\\'", $categoria); ?>')" 
                                                class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 text-xs">
                                            🗑️ Elimina
                                        </button>
                                    </div>
                                </div>
                                
                                <div id="contenuto-stampa-<?php echo str_replace("'", "\\'", $categoria); ?>" class="hidden border-t">
                                    <?php foreach ($comande as $comanda): ?>
                                        <div class="p-3 border-b border-gray-100 last:border-b-0">
                                            <span class="font-medium text-sm"><?php echo $comanda['quantita']; ?> × <?php echo htmlspecialchars($comanda['nome']); ?></span>
                                            <?php if ($comanda['note']): ?>
                                                <div class="text-sm text-gray-600 italic mt-1"><?php echo htmlspecialchars($comanda['note']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <button onclick="stampaTutto()" class="bg-blue-600 text-white px-4 py-3 rounded hover:bg-blue-700 font-bold text-sm w-full">
                            🖨️ STAMPA TUTTO
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Storico Ordini -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-md font-medium text-gray-900">📊 Storico Ordini (<?php echo $total_orders; ?>)</h3>
                    </div>

                    <?php if ($total_orders > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pietanza</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Categoria</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qtà</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stato</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">Data/Ora</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($order = $orders_result->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($order['nome']); ?>
                                                <?php if ($order['note']): ?>
                                                    <div class="text-xs text-gray-500 italic"><?php echo htmlspecialchars($order['note']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-500 hidden sm:table-cell">
                                                <?php echo htmlspecialchars($order['categoria']); ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                                <?php echo $order['quantita']; ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <?php echo getStatusBadge($order['stampata']); ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-500 hidden lg:table-cell">
                                                <?php 
                                                if ($order['created_at']) {
                                                    echo date('d/m H:i', strtotime($order['created_at']));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginazione Storico Ordini -->
                        <?php if ($total_pages > 1): ?>
                            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
                                <div class="text-sm text-gray-700">
                                    Pag. <?php echo $page; ?> di <?php echo $total_pages; ?>
                                </div>
                                <div class="flex gap-2">
                                    <?php if ($page > 1): ?>
                                        <a href="<?php echo createUrl(['page' => $page - 1]); ?>" class="bg-gray-200 text-gray-700 px-3 py-2 rounded text-sm hover:bg-gray-300">← Precedente</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="<?php echo createUrl(['page' => $page + 1]); ?>" class="bg-blue-500 text-white px-3 py-2 rounded text-sm hover:bg-blue-600">Successivo →</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="px-4 py-8 text-center">
                            <div class="text-gray-500 text-sm">
                                <p>Nessun ordine presente</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

<script>
let ordine = [];
let contatori = {};

// *** FUNZIONI AJAX PER MENU ***
function aggiornaMenuAjax(prenotazioneId, menuId, azione) {
    const btnAggiungi = document.getElementById(`btn-aggiungi-${menuId}`);
    const btnRimuovi = document.getElementById(`btn-rimuovi-${menuId}`);
    
    if (btnAggiungi) btnAggiungi.classList.add('loading');
    if (btnRimuovi) btnRimuovi.classList.add('loading');

    fetch('aggiorna_menu.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            prenotazione_id: prenotazioneId,
            menu_id: menuId,
            azione: azione
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            aggiornaInterfacciaMenu(data.data);
            mostraMessaggioTemporaneo(data.message, 'success');
        } else {
            mostraMessaggioTemporaneo(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        mostraMessaggioTemporaneo('Errore di connessione', 'error');
    })
    .finally(() => {
        if (btnAggiungi) btnAggiungi.classList.remove('loading');
        if (btnRimuovi) btnRimuovi.classList.remove('loading');
    });
}

function aggiornaInterfacciaMenu(data) {
    const menuBaseBadge = document.getElementById('menu-base-badge');
    if (menuBaseBadge) {
        menuBaseBadge.textContent = `${data.menu_base_disponibili} persone`;
    }

    const menuBaseRimanenti = document.getElementById('menu-base-rimanenti');
    if (menuBaseRimanenti) {
        menuBaseRimanenti.textContent = data.menu_base_disponibili;
    }

    const totaleSostituzioni = document.getElementById('totale-sostituzioni');
    const sostituzioniInfo = document.getElementById('sostituzioni-info');
    if (totaleSostituzioni) {
        const totale = Object.values(data.menu_assegnati).reduce((sum, qty) => sum + qty, 0);
        totaleSostituzioni.textContent = totale;
        
        if (sostituzioniInfo) {
            if (totale > 0) {
                sostituzioniInfo.style.display = 'block';
            } else {
                sostituzioniInfo.style.display = 'none';
            }
        }
    }

    Object.keys(data.menu_assegnati).forEach(menuId => {
        const quantitaSpan = document.getElementById(`quantita-menu-${menuId}`);
        const btnAggiungi = document.getElementById(`btn-aggiungi-${menuId}`);
        const btnRimuovi = document.getElementById(`btn-rimuovi-${menuId}`);
        
        if (quantitaSpan) {
            quantitaSpan.textContent = data.menu_assegnati[menuId];
        }
        
        if (btnRimuovi) {
            if (data.menu_assegnati[menuId] <= 0) {
                btnRimuovi.classList.add('opacity-50', 'cursor-not-allowed');
                btnRimuovi.disabled = true;
            } else {
                btnRimuovi.classList.remove('opacity-50', 'cursor-not-allowed');
                btnRimuovi.disabled = false;
            }
        }
        
        if (btnAggiungi) {
            if (data.menu_base_disponibili <= 0) {
                btnAggiungi.classList.add('opacity-50', 'cursor-not-allowed');
                btnAggiungi.disabled = true;
            } else {
                btnAggiungi.classList.remove('opacity-50', 'cursor-not-allowed');
                btnAggiungi.disabled = false;
            }
        }
    });

    document.querySelectorAll('[id^="quantita-menu-"]').forEach(element => {
        const menuId = element.id.replace('quantita-menu-', '');
        if (!data.menu_assegnati[menuId]) {
            element.textContent = '0';
            
            const btnRimuovi = document.getElementById(`btn-rimuovi-${menuId}`);
            if (btnRimuovi) {
                btnRimuovi.classList.add('opacity-50', 'cursor-not-allowed');
                btnRimuovi.disabled = true;
            }
        }
    });
}

function mostraMessaggioTemporaneo(messaggio, tipo) {
    const messaggiEsistenti = document.querySelectorAll('.messaggio-temporaneo');
    messaggiEsistenti.forEach(msg => msg.remove());

    const div = document.createElement('div');
    div.className = `messaggio-temporaneo fixed top-4 right-4 px-4 py-3 rounded-lg shadow-lg z-50 transition-all duration-300 ${
        tipo === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'
    }`;
    div.textContent = messaggio;

    document.body.appendChild(div);

    setTimeout(() => div.style.opacity = '1', 10);

    setTimeout(() => {
        div.style.opacity = '0';
        setTimeout(() => div.remove(), 300);
    }, 3000);
}

// *** FUNZIONI PER PAGINAZIONE AJAX PIETANZE ***
function caricaPietanzeAjax(categoria, pagina = 1) {
    const contenitorePietanze = document.querySelector('.categoria .grid');
    if (contenitorePietanze) {
        contenitorePietanze.innerHTML = '<div class="col-span-full text-center py-8"><div class="text-gray-500">Caricamento...</div></div>';
    }

    const params = new URLSearchParams({
        categoria: categoria,
        pietanze_page: pagina,
        ajax: '1',
        id: document.querySelector('input[name="prenotazione_id"]').value
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('page')) params.append('page', urlParams.get('page'));

    fetch('carica_pietanze.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                aggiornaInterfacciaPietanze(data.data);
            } else {
                console.error('Errore nel caricamento:', data.message);
                mostraMessaggioTemporaneo('Errore nel caricamento delle pietanze', 'error');
            }
        })
        .catch(error => {
            console.error('Errore AJAX:', error);
            mostraMessaggioTemporaneo('Errore di connessione', 'error');
        });
}

function aggiornaInterfacciaPietanze(data) {
    const contenitorePietanze = document.querySelector('.categoria .grid');
    const headerCategoria = document.querySelector('.categoria h3');
    const infoPaginazione = document.querySelector('.categoria .flex.justify-between .text-sm');
    const paginazioneContainer = document.querySelector('.categoria .mt-4.flex.justify-between');

    if (!contenitorePietanze || !data.pietanze) return;

    if (headerCategoria && data.categoria_selezionata) {
        headerCategoria.innerHTML = `
            ${escapeHtml(data.categoria_selezionata)} 
            <span class="text-sm font-normal text-gray-600">
                (${data.total_pietanze} pietanze)
            </span>
        `;
    }

    if (infoPaginazione && data.total_pietanze_pages > 1) {
        infoPaginazione.textContent = `Pagina ${data.pietanze_page} di ${data.total_pietanze_pages}`;
    }

    contenitorePietanze.innerHTML = '';
    data.pietanze.forEach(pietanza => {
        const div = document.createElement('div');
        div.className = 'pietanza-item bg-gray-50 rounded border p-3';
        div.innerHTML = `
            <div class="flex items-center justify-between mb-2">
                <div class="font-medium text-sm flex-1 pr-2">${escapeHtml(pietanza.nome)}</div>
                <div class="flex items-center gap-1">
                    <button type="button" onclick="decrementaPietanza(${pietanza.id}, '${pietanza.nome.replace(/'/g, "\\'")}', '${pietanza.categoria.replace(/'/g, "\\'")}'); event.preventDefault();" 
                            class="btn-action bg-red-500 text-white rounded hover:bg-red-600">−</button>
                    <span id="counter-${pietanza.id}" class="counter-display bg-white px-2 py-1 rounded border text-center">0</span>
                    <button type="button" onclick="incrementaPietanza(${pietanza.id}, '${pietanza.nome.replace(/'/g, "\\'")}', '${pietanza.categoria.replace(/'/g, "\\'")}'); event.preventDefault();" 
                            class="btn-action bg-green-500 text-white rounded hover:bg-green-600">+</button>
                </div>
            </div>
            <input type="text" id="note-${pietanza.id}" placeholder="Note..." 
                   class="w-full text-sm border px-3 py-2 rounded bg-white"
                   onkeypress="if(event.key==='Enter') { incrementaPietanza(${pietanza.id}, '${pietanza.nome.replace(/'/g, "\\'")}', '${pietanza.categoria.replace(/'/g, "\\'")}'); event.preventDefault(); }">
        `;
        contenitorePietanze.appendChild(div);

        const contatore = contatori[pietanza.id] || 0;
        const counterElement = document.getElementById(`counter-${pietanza.id}`);
        if (counterElement) {
            counterElement.textContent = contatore;
        }
    });

    if (paginazioneContainer) {
        const conteggioDiv = paginazioneContainer.querySelector('.text-sm.text-gray-700');
        const pulsantiDiv = paginazioneContainer.querySelector('.flex.gap-2');

        if (conteggioDiv) {
            conteggioDiv.textContent = `Mostrando ${data.pietanze.length} di ${data.total_pietanze} pietanze`;
        }

        if (pulsantiDiv) {
            pulsantiDiv.innerHTML = '';

            if (data.pietanze_page > 1) {
                const btnPrecedente = document.createElement('button');
                btnPrecedente.className = 'bg-gray-200 text-gray-700 px-3 py-2 rounded text-sm hover:bg-gray-300';
                btnPrecedente.textContent = '← Precedente';
                btnPrecedente.onclick = () => caricaPietanzeAjax(data.categoria_selezionata, data.pietanze_page - 1);
                pulsantiDiv.appendChild(btnPrecedente);
            }

            if (data.pietanze_page < data.total_pietanze_pages) {
                const btnSuccessivo = document.createElement('button');
                btnSuccessivo.className = 'bg-blue-500 text-white px-3 py-2 rounded text-sm hover:bg-blue-600';
                btnSuccessivo.textContent = 'Successivo →';
                btnSuccessivo.onclick = () => caricaPietanzeAjax(data.categoria_selezionata, data.pietanze_page + 1);
                pulsantiDiv.appendChild(btnSuccessivo);
            }
        }
    }
}

function cambiaCategoria(categoria) {
    document.querySelectorAll('.btn-categoria').forEach(btn => {
        btn.classList.remove('bg-blue-700');
        btn.classList.add('bg-blue-500', 'hover:bg-blue-600');
    });
    
    const btnCategoria = Array.from(document.querySelectorAll('.btn-categoria')).find(btn => 
        btn.textContent.trim() === categoria
    );
    if (btnCategoria) {
        btnCategoria.classList.remove('bg-blue-500', 'hover:bg-blue-600');
        btnCategoria.classList.add('bg-blue-700');
    }

    caricaPietanzeAjax(categoria, 1);
}

// *** FUNZIONI PER GESTIRE LE SEZIONI ***
function toggleSection(sectionName) {
    const content = document.getElementById(sectionName + '-content');
    const icon = document.getElementById(sectionName + '-icon');
    
    if (content.classList.contains('collapsed')) {
        content.classList.remove('collapsed');
        content.classList.add('expanded');
        icon.style.transform = 'rotate(180deg)';
    } else {
        content.classList.remove('expanded');
        content.classList.add('collapsed');
        icon.style.transform = 'rotate(0deg)';
    }
}

// *** FUNZIONI PER ORDINI ***
function incrementaPietanza(id, nome, categoria) {
    const noteInput = document.getElementById('note-' + id);
    const note = noteInput ? noteInput.value.trim() : '';
    
    const esistente = ordine.find(p => p.id === id && p.note === note);
    
    if (esistente) {
        esistente.quantita++;
    } else {
        ordine.push({ id, nome, note, quantita: 1, categoria });
    }
    
    contatori[id] = (contatori[id] || 0) + 1;
    aggiornaContatore(id);
    aggiornaRiepilogo();
}

function decrementaPietanza(id, nome, categoria) {
    const noteInput = document.getElementById('note-' + id);
    const note = noteInput ? noteInput.value.trim() : '';
    
    let elementoDaRimuovere;
    
    if (note) {
        elementoDaRimuovere = ordine.find(p => p.id === id && p.note === note);
    }
    
    if (!elementoDaRimuovere) {
        elementoDaRimuovere = ordine.find(p => p.id === id && p.note === '');
    }
    
    if (!elementoDaRimuovere) {
        for (let i = ordine.length - 1; i >= 0; i--) {
            if (ordine[i].id === id) {
                elementoDaRimuovere = ordine[i];
                break;
            }
        }
    }
    
    if (elementoDaRimuovere) {
        if (elementoDaRimuovere.quantita > 1) {
            elementoDaRimuovere.quantita--;
        } else {
            const index = ordine.indexOf(elementoDaRimuovere);
            ordine.splice(index, 1);
        }
        
        contatori[id] = Math.max(0, (contatori[id] || 0) - 1);
        aggiornaContatore(id);
        aggiornaRiepilogo();
    }
}

function aggiornaContatore(pietanzaId) {
    const counter = document.getElementById(`counter-${pietanzaId}`);
    if (counter) {
        counter.textContent = contatori[pietanzaId] || 0;
    }
}

function aggiornaRiepilogo() {
    const container = document.getElementById("riepilogo");
    
    if (ordine.length === 0) {
        container.innerHTML = '<div class="text-gray-500 text-center text-sm">Nessun elemento aggiunto</div>';
    } else {
        container.innerHTML = '';
        ordine.forEach((p, i) => {
            const div = document.createElement("div");
            div.className = "flex justify-between items-center bg-white p-3 rounded border text-sm";
            div.innerHTML = `
                <div class="flex-1">
                    <span class="font-medium">${p.quantita} × ${escapeHtml(p.nome)}</span>
                    ${p.note ? `<div class="text-xs text-gray-600 italic mt-1">${escapeHtml(p.note)}</div>` : ''}
                </div>
                <div class="text-xs text-gray-500 ml-2">${escapeHtml(p.categoria)}</div>
            `;
            container.appendChild(div);
        });
    }

    document.getElementById("ordine_json").value = JSON.stringify(ordine);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function svuotaOrdine() {
    if (ordine.length === 0) return;
    
    if (confirm('Confermi di voler svuotare tutto l\'ordine?')) {
        ordine = [];
        contatori = {};
        
        document.querySelectorAll('[id^="counter-"]').forEach(counter => {
            counter.textContent = '0';
        });
        
        aggiornaRiepilogo();
    }
}

function salvaOrdine() {
    if (ordine.length === 0) {
        alert("Aggiungi almeno una pietanza per salvare.");
        return false;
    }
    return true;
}

// *** FUNZIONI PER STAMPA ***
function toggleCategoriaStampa(categoria) {
    const contenuto = document.getElementById('contenuto-stampa-' + categoria);
    const freccia = document.getElementById('freccia-stampa-' + categoria);
    
    if (contenuto && freccia) {
        if (contenuto.classList.contains('hidden')) {
            contenuto.classList.remove('hidden');
            freccia.textContent = '▼';
        } else {
            contenuto.classList.add('hidden');
            freccia.textContent = '▶️';
        }
    }
}

function stampaCategoria(categoria) {
    if (!confirm(`Stampa tutte le comande di "${categoria}"?`)) return;
    
    const prenotazioneId = document.querySelector('input[name="prenotazione_id"]').value;
    
    fetch('stampa_comanda.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            tipo: 'categoria',
            categoria: categoria,
            prenotazione_id: prenotazioneId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(`Comande di "${categoria}" inviate alla stampante!`);
            location.reload();
        } else {
            alert('Errore: ' + data.message);
        }
    })
    .catch(err => {
        alert('Errore: ' + err.message);
    });
}

function eliminaCategoria(categoria) {
    if (!confirm(`Elimina tutte le comande di "${categoria}"?`)) return;
    
    const prenotazioneId = document.querySelector('input[name="prenotazione_id"]').value;
    
    fetch('elimina_comanda.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            tipo: 'categoria',
            categoria: categoria,
            prenotazione_id: prenotazioneId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(`Comande di "${categoria}" eliminate!`);
            location.reload();
        } else {
            alert('Errore: ' + data.message);
        }
    })
    .catch(err => {
        alert('Errore: ' + err.message);
    });
}

function stampaTutto() {
    if (!confirm('Stampa TUTTE le comande salvate?')) return;
    
    const prenotazioneId = document.querySelector('input[name="prenotazione_id"]').value;
    
    fetch('stampa_comanda.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            tipo: 'tutto',
            prenotazione_id: prenotazioneId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Tutte le comande inviate alla stampante!');
            location.reload();
        } else {
            alert('Errore: ' + data.message);
        }
    })
    .catch(err => {
        alert('Errore: ' + err.message);
    });
}

// *** INIZIALIZZAZIONE ***
document.addEventListener('DOMContentLoaded', () => {
    // Inizializza contatori per le pietanze visibili nella pagina corrente
    document.querySelectorAll('[id^="counter-"]').forEach(counter => {
        const pietanzaId = counter.id.replace('counter-', '');
        if (!contatori[pietanzaId]) {
            contatori[pietanzaId] = 0;
        }
        counter.textContent = contatori[pietanzaId];
    });
    
    // Imposta la sezione "Ordini" come aperta di default
    const ordiniContent = document.getElementById('ordini-content');
    const ordiniIcon = document.getElementById('ordini-icon');
    if (ordiniContent && ordiniIcon) {
        ordiniContent.classList.remove('collapsed');
        ordiniContent.classList.add('expanded');
        ordiniIcon.style.transform = 'rotate(180deg)';
    }
});
</script>
</body>
</html>
