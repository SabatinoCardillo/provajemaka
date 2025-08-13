<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Stampa Automatica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: sans-serif; text-align: center;">
  <h2>🖨️ Stampa automatica attiva</h2>
  <p>Controllo ogni 5 secondi…</p>
  <div id="log" style="font-size: small; margin-top: 20px;"></div>

<script>
function log(msg) {
  const box = document.getElementById('log');
  box.innerHTML = `[${new Date().toLocaleTimeString()}] ${msg}<br>` + box.innerHTML;
}

async function controllaStampe() {
  try {
    const res = await fetch('controlla_comande.php');
    const comande = await res.json();

    for (let c of comande) {
      const categoria = c.categoria.toLowerCase();
      let tag = '';

      if (categoria.includes('pizza')) tag = '#stampante:pizza';
      else if (['primo piatto', 'secondo', 'antipasto'].some(k => categoria.includes(k))) tag = '#stampante:cucina';
      else tag = '#stampante:default';

      const testo = `${tag}\n${c.nome} x${c.quantita}\n${c.note}\n------------------`;
      const encoded = encodeURIComponent(testo);
      window.open(`rawbt:print?text=${encoded}`, '_blank');

      // segna come stampata
      await fetch(`segna_stampata.php?id=${c.id}`);
      log(`Stampata: ${c.nome} (${c.categoria})`);
    }
  } catch (err) {
    log("Errore: " + err.message);
  }
}

setInterval(controllaStampe, 5000);
</script>
</body>
</html>
