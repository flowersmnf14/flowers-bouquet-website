<?php
include 'config.php';

echo "<h3>Struktur Tabel Products:</h3>";
$result = $conn->query('DESCRIBE products');
if($result) {
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "<br>";
    }
} else {
    echo "Error: " . $conn->error;
}

echo "<h3>Data Products:</h3>";
$result = $conn->query('SELECT * FROM products LIMIT 1');
if($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Kolom yang ada: " . implode(", ", array_keys($row)) . "<br>";
} else {
    echo "Tidak ada data";
}
?>
