<?php
include 'config.php';

// Check if category column exists
$result = $conn->query("SHOW COLUMNS FROM products LIKE 'category'");

if ($result->num_rows == 0) {
    // Column doesn't exist, add it
    echo "Menambahkan kolom category ke tabel products...<br>";
    
    $sql = "ALTER TABLE products ADD COLUMN category VARCHAR(50) DEFAULT 'Bunga'";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Kolom category berhasil ditambahkan!<br>";
    } else {
        echo "✗ Error: " . $conn->error . "<br>";
    }
} else {
    echo "✓ Kolom category sudah ada di tabel products<br>";
}

// Show all columns
echo "<h3>Struktur Tabel Products saat ini:</h3>";
$result = $conn->query('DESCRIBE products');
if($result) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>✓ Selesai! Silakan kembali ke manage_products.php</h3>";
?>
