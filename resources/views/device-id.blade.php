<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Gerador de Device ID - Mercado Pago</title>
  <script src="https://sdk.mercadopago.com/js/v2"></script>
  <style>
    body { font-family: Arial, sans-serif; padding: 30px; }
    input { width: 100%; padding: 10px; font-size: 18px; margin: 10px 0; }
    button { padding: 10px 20px; font-size: 16px; }
  </style>
</head>
<body>
  <h1>Gerador de Device ID - Mercado Pago</h1>
  <p>Copie o <code>device_id</code> abaixo para usar nos testes com cartão de crédito no Postman:</p>
  <input type="text" id="deviceId" placeholder="Aguardando o device_id..." readonly>
  <button onclick="copiar()">Copiar para área de transferência</button>

  <script>
    const mp = new MercadoPago("{{ config('services.mercadopago.public_key') }}", { locale: 'pt-BR' });

    mp.getDevice().then(device => {
      document.getElementById("deviceId").value = device.deviceId;
    }).catch(error => {
      console.error("Erro ao gerar device_id:", error);
      alert("Erro ao gerar o device_id. Veja o console.");
    });

    function copiar() {
      const input = document.getElementById("deviceId");
      input.select();
      document.execCommand("copy");
      alert("Device ID copiado!");
    }
  </script>
</body>
</html>
