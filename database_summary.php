<?php
$host = 'localhost';
$dbname = 'cit_lms';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== COC-LMS Database Summary ===\n\n";
    echo "Database: $dbname\n\n";
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Total Tables: " . count($tables) . "\n\n";
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $rowCount = $stmt->fetchColumn();
        
        echo $table . " (" . count($columns) . " columns, " . $rowCount . " rows)\n";
        foreach ($columns as $column) {
            echo "  - " . $column['Field'] . " (" . $column['Type'] . ")" . 
                 ($column['Key'] == 'PRI' ? ' [PRIMARY KEY]' : '') . 
                 ($column['Extra'] == 'auto_increment' ? ' [AUTO_INCREMENT]' : '') . "\n";
        }
        echo "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
