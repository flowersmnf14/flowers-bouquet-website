# 🔧 Panduan Perbaikan Error Kategori di Kelola Produk

## Masalah
Error terjadi saat menambah/edit produk di halaman Kelola Produk (manage_products.php) karena kolom `category` tidak ada di tabel `products`.

## Solusi

### Langkah 1: Cek Status Database
Buka halaman ini untuk melihat status tabel database:
- **URL:** `http://localhost/crud/rpl/status_db.php`
- Lihat apakah kolom `category` sudah ada atau tidak

### Langkah 2: Tambahkan Kolom Category (Jika Belum Ada)
Jika kolom `category` belum ada, jalankan salah satu:

**Opsi A: Otomatis (Recommended)**
- Buka: `http://localhost/crud/rpl/add_category_column.php`
- Script akan otomatis menambahkan kolom

**Opsi B: Manual via Setup**
- Buka: `http://localhost/crud/rpl/setup.php`
- Ini akan recreate semua tabel dengan struktur terbaru

### Langkah 3: Verifikasi
1. Buka `http://localhost/crud/rpl/status_db.php` lagi
2. Pastikan ada ✓ pada "Kolom 'category' sudah ada"
3. Sekarang kelola produk bisa digunakan dengan normal

## File Helper yang Dibuat
- `status_db.php` - Cek status database
- `check_db.php` - Lihat struktur tabel products
- `add_category_column.php` - Tambah kolom category

## Cara Mengguna Kelola Produk
1. Login sebagai admin
2. Klik "Kelola Produk" di panel manajemen
3. Isi form:
   - **Nama Produk**: Nama produk
   - **Deskripsi**: Deskripsi produk
   - **Harga**: Harga produk (dalam Rupiah)
   - **Kategori**: Pilih dari dropdown (Bunga/Uang/Boneka/Makanan) ← INI YANG DIPERBAIKI
   - **Foto Produk**: Upload gambar (JPG, PNG, GIF, WEBP, max 2MB)
4. Klik "Tambah Produk"

## Catatan
- Kolom `category` sudah ditambahkan ke `setup.php`
- Default kategori adalah "Bunga"
- Kategori otomatis tersimpan ke database
- Statistik penjualan akan menghitung berdasarkan kategori ini
