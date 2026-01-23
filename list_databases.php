<?php
$mysqli = new mysqli('localhost', 'root', '');
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}
echo "Available Databases:\n";
echo str_repeat('=', 50) . "\n";
$result = $mysqli->query('SHOW DATABASES');
while ($row = $result->fetch_row()) {
    echo $row[0] . "\n";
}
?>
