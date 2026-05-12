<?php
include 'config.php';

// Check if created_at column exists
$result = $conn->query("SHOW COLUMNS FROM customers LIKE 'created_at'");

if ($result->num_rows == 0) {
    // Column doesn't exist, add it
    echo "Menambahkan kolom created_at ke tabel customers...<br>";

    $sql = "ALTER TABLE customers ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Kolom created_at berhasil ditambahkan!<br>";
    } else {
        echo "✗ Error: " . $conn->error . "<br>";
    }
} else {
    echo "✓ Kolom created_at sudah ada di tabel customers<br>";
}

// Show all columns
echo "<h3>Struktur Tabel Customers saat ini:</h3>";
$result = $conn->query('DESCRIBE customers');
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

echo "<h3>✓ Selesai! Silakan kembali ke berandaAdmin.php</h3>";
?>
