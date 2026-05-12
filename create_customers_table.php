<?php
// Small helper to create or migrate the `customers` table expected by register.php
// Run this once from the browser or CLI while your XAMPP server is running.

include 'config.php';

try {
    // Create table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        email VARCHAR(191) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        phone VARCHAR(32) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql) === TRUE) {
        echo "customers table created or already exists.<br>";
    } else {
        echo "Error creating table: " . htmlspecialchars($conn->error) . "<br>";
    }

    // Ensure phone column exists (safe to run even if table just created)
    $res = $conn->query("SHOW COLUMNS FROM customers LIKE 'phone'");
    if ($res && $res->num_rows === 0) {
        if ($conn->query("ALTER TABLE customers ADD COLUMN phone VARCHAR(32) DEFAULT NULL") === TRUE) {
            echo "Added phone column to customers table.<br>";
        } else {
            echo "Error adding phone column: " . htmlspecialchars($conn->error) . "<br>";
        }
    } else {
        echo "phone column already exists.<br>";
    }

    // Ensure status column exists (values: 'aktif' or 'blocked')
    $res2 = $conn->query("SHOW COLUMNS FROM customers LIKE 'status'");
    if ($res2 && $res2->num_rows === 0) {
        if ($conn->query("ALTER TABLE customers ADD COLUMN `status` VARCHAR(16) NOT NULL DEFAULT 'aktif'") === TRUE) {
            echo "Added status column to customers table (default 'aktif').<br>";
        } else {
            echo "Error adding status column: " . htmlspecialchars($conn->error) . "<br>";
        }
    } else {
        echo "status column already exists.<br>";
    }

    // Normalize existing values: if someone used 'active' earlier, convert to 'aktif'
    $conn->query("UPDATE customers SET `status` = 'aktif' WHERE `status` = 'active'");
    if ($conn->affected_rows > 0) {
        echo "Migrated " . (int)$conn->affected_rows . " baris status 'active' -> 'aktif'.<br>";
    }

    echo "Done. You can now register users with register.php and view them at manage_customers.php.";
} catch (Exception $e) {
    echo "Exception: " . htmlspecialchars($e->getMessage());
}

?>