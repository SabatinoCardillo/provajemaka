<?php
require_once 'config.php';
session_start();

$successo = false;
$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if ($nome && $username && $password) {
        // Verifica che l'username non esista già
        $stmt = $conn->prepare("SELECT id FROM camerieri WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errore = "Username già esistente.";
        } else {
            $stmt->close();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO camerieri (nome, username, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nome, $username, $hash);
            if ($stmt->execute()) {
                $successo = true;
            } else {
                $errore = "Errore durante la registrazione.";
            }
            $stmt->close();
        }
    } else {
        $errore = "Tutti i campi sono obbligatori.";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Registrazione Cameriere</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
  <div class="bg-white p-6 rounded shadow max-w-sm w-full">
    <h1 class="text-xl font-bold mb-4">Registrazione Cameriere</h1>

    <?php if ($successo): ?>
      <p class="text-green-600 mb-4">✅ Registrazione completata! <a href="login.php" class="underline text-blue-600">Vai al login</a></p>
    <?php elseif ($errore): ?>
      <p class="text-red-600 mb-4">⚠️ <?= $errore ?></p>
    <?php endif; ?>

    <form method="POST">
      <input type="text" name="nome" placeholder="Nome completo" class="w-full mb-3 border px-3 py-2 rounded" required>
      <input type="text" name="username" placeholder="Username" class="w-full mb-3 border px-3 py-2 rounded" required>
      <input type="password" name="password" placeholder="Password" class="w-full mb-4 border px-3 py-2 rounded" required>
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded w-full hover:bg-blue-700">Registrati</button>
    </form>
  </div>
</body>
</html>
