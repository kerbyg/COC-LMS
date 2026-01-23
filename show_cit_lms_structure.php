<?php
$host = 'localhost';
$dbname = 'cit_lms';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== COC-LMS Database Structure ===\n\n";
    echo "Database: $dbname\n";
    echo "Host: $host\n\n";
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Total Tables: " . count($tables) . "\n\n";
    echo str_repeat('=', 80) . "\n\n";
    
    foreach ($tables as $table) {
        echo "TABLE: $table\n";
        echo str_repeat('-', 80) . "\n";
        
        // Get column information
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        printf("%-25s %-20s %-10s %-10s %-15s\n", 'Column', 'Type', 'Null', 'Key', 'Extra');
        echo str_repeat('-', 80) . "\n";
        
        foreach ($columns as $column) {
            printf("%-25s %-20s %-10s %-10s %-15s\n",
                $column['Field'],
                $column['Type'],
                $column['Null'],
                $column['Key'],
                $column['Extra']
            );
        }
        
        // Get row count
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $rowCount = $stmt->fetchColumn();
        echo "\nRow Count: $rowCount\n";
        
        echo "\n" . str_repeat('=', 80) . "\n\n";
    }
    
    echo "Database structure retrieved successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
