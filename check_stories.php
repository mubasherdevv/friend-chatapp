<?php
require_once 'config/database.php';
$result = $conn->query('DESCRIBE stories');
while($row = $result->fetch_assoc()) {
    print_r($row);
    echo "\n";
}
