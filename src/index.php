<?php
echo "<h1>Plataforma SGA — Docker funcionando</h1>";
echo "PHP " . phpversion();
// Prueba de conexión a la base
try {
    $pdo = new PDO("mysql:host=db;dbname=sga;charset=utf8mb4", "sga_user", "sga_pass");
    echo "<p>✅ Conexión a MariaDB exitosa</p>";
} catch (PDOException $e) {
    echo "<p>❌ Error de conexión: " . $e->getMessage() . "</p>";
}