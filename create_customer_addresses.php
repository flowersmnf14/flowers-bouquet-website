<?php
// Migration helper to create customer_addresses table
include 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS customer_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    label VARCHAR(100) DEFAULT 'Alamat',
    province VARCHAR(191) NOT NULL,
    regency VARCHAR(191) NOT NULL,
    district VARCHAR(191) NOT NULL,
    village VARCHAR(191) NOT NULL,
    rtrw VARCHAR(32) DEFAULT NULL,
    postal_code VARCHAR(16) DEFAULT NULL,
    detail TEXT DEFAULT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "customer_addresses table created or already exists.<br>";
} else {
    echo "Error creating table: " . htmlspecialchars($conn->error) . "<br>";
}

?>
