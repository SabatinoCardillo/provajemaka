<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, nome, password FROM camerieri WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res && password_verify($password, $res['password'])) {
        $_SESSION['cameriere_id'] = $res['id'];
        $_SESSION['cameriere_nome'] = $res['nome'];
        header("Location: index_cameriere.php");
        exit;
    } else {
        $errore = "Credenziali non valide.";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Login Cameriere</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- fondamentale per il mobile -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center px-4">
  <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
    <h1 class="text-2xl font-bold mb-6 text-center">Login Cameriere</h1>
    
    <?php if (isset($errore)): ?>
      <p class="text-red-600 text-center mb-4"><?= $errore ?></p>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <input type="text" name="username" placeholder="Username"
             class="w-full border border-gray-300 px-4 py-3 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>

      <input type="password" name="password" placeholder="Password"
             class="w-full border border-gray-300 px-4 py-3 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>

      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-md transition duration-300">
        Accedi
      </button>
    </form>
  </div>
</body>
</html>
