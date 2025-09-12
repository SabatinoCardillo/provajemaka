<?php
session_start();
if (!isset($_SESSION['cameriere_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';
$res = $conn->query("SELECT p.id, p.data_prenotazione, c.nome, c.cognome, p.numero_persone 
                     FROM prenotazioni p 
                     JOIN clienti c ON p.cliente_id = c.id 
                     WHERE DATE(p.data_prenotazione) = CURDATE()
                     ORDER BY p.data_prenotazione DESC");
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- fondamentale -->
  <title>Prenotazioni Cameriere</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6 px-4">
  <div class="max-w-4xl mx-auto">
    <p class="text-base text-gray-700 mb-4">
      👤 Benvenuto: <strong><?= htmlspecialchars($_SESSION['cameriere_nome']) ?></strong>
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <?php while ($row = $res->fetch_assoc()): ?>
        <div class="bg-white p-5 rounded-xl shadow-md flex flex-col justify-between">
          <div>
            <h2 class="text-lg font-semibold"><?= htmlspecialchars($row['nome'] . ' ' . $row['cognome']) ?></h2>
            <p class="text-sm text-gray-600">🗓 Data: <?= htmlspecialchars($row['data_prenotazione']) ?></p>
            <p class="text-sm text-gray-600">👥 Persone: <?= htmlspecialchars($row['numero_persone']) ?></p>
          </div>
          <a href="comanda.php?id=<?= $row['id'] ?>"
             class="mt-4 bg-blue-600 hover:bg-blue-700 text-white text-center font-semibold py-2 rounded transition">
            Prendi Ordinazione
          </a>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
</body>
</html>
