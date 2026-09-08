<?php
#Arquivo principal de apresentação do projeto
require_once __DIR__ . '/../config/database.php';

echo "<h1>Curso de Inglês está funcionando!</h1>";
echo "<p>Conexão com MySQL funcionando!</p>";

$stmt = $pdo->query("SELECT * FROM courses");

$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h1>PEMSLO English</h1>";

echo "<pre>";
print_r($courses);
echo "</pre>";