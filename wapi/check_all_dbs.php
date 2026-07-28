<?php
try {
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $stmt = $pdo->query("SHOW DATABASES");
    $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($dbs, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
