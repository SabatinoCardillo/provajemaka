<?php
// Prende tutti i file di comanda nella cartella
$files = glob("comande/*.txt");

foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true);
    $categoria = $data["categoria"];
    $tavolo = $data["tavolo"];
    $prodotti = $data["prodotti"];

    // CONFIGURA QUI GLI IP DELLE STAMPANTI PER OGNI CATEGORIA
    $stampanti = [
        "Pizza" => "192.168.1.100",
        "Antipasto" => "192.168.1.101",
        "Primo" => "192.168.1.101",
        "Secondo" => "192.168.1.101",
        "Bar" => "192.168.1.102"
    ];

    // Recupera l’IP della stampante per questa categoria
    $ip = $stampanti[$categoria] ?? "192.168.1.150"; // fallback

    // Crea il contenuto della stampa (RAW)
    $contenuto = chr(27) . chr(64); // ESC @ (inizializza)
    $contenuto .= "Tavolo: $tavolo\\n";
    $contenuto .= "REPARTO: $categoria\\n";
    $contenuto .= "----------------------\\n";
    foreach ($prodotti as $p) {
        $contenuto .= "- $p\\n";
    }
    $contenuto .= "\\n\\n\\n";

    // Codifica in base64 per RawBT
    $base64 = base64_encode($contenuto);

    // Invia comando a RawBT (apre nuova scheda)
    echo "<script>window.open('rawbt:base64,' + '$base64' + '&ip=$ip', '_blank');</script>";

    // Elimina il file dopo la stampa
    unlink($file);
}
?>
