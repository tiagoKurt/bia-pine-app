<?php
$host = "127.0.0.1";
$port = 3306;
$user = "root";
$pass = "";
$db   = "app_controladoria";

// Criar conexão
$conn = new mysqli($host, $user, $pass, $db, $port);

// Verificar conexão
if ($conn->connect_error) {
    die("❌ Falha na conexão: " . $conn->connect_error . PHP_EOL);
}

echo "✅ Conexão bem-sucedida ao banco '{$db}'!" . PHP_EOL;

$conn->close();
?>
