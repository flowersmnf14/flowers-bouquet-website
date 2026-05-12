# 🎠 Troubleshooting Carousel Produk Unggulan

## Jika Carousel Tidak Bisa Digeser

### Kemungkinan Penyebab & Solusi:

#### 1. **Tidak Ada Produk di Database**
- Carousel hanya muncul jika ada minimal 1 produk
- **Solusi:** 
  - Login sebagai admin
  - Klik "Kelola Produk"
  - Tambahkan produk dengan foto

#### 2. **Browser Cache/Cookie**
- Cache lama mungkin masih ter-load
- **Solusi:**
  - Hard refresh: `Ctrl + Shift + R` (Chrome/Firefox)
  - atau `Cmd + Shift + R` (Mac)
  - Clear cache browser

#### 3. **JavaScript Error**
- Console browser mungkin menunjukkan error
- **Solusi:**
  - Buka Developer Tools: `F12`
  - Lihat tab "Console"
  - Report error ke developer

#### 4. **CSS Tidak Ter-load**
- Styling carousel mungkin tidak berfungsi
- **Solusi:**
  - Refresh halaman: `F5`
  - Pastikan file `style.css` ada

### Testing

#### Test Carousel (Lokal):
Buka file test untuk verifikasi carousel bekerja:
- **URL:** `http://localhost/crud/rpl/test_carousel.html`

Jika test carousel berfungsi:
✓ File `script.js` OK
✓ Logika JavaScript OK

Jika test carousel TIDAK berfungsi:
✗ Ada masalah dengan CSS atau JavaScript

#### Cara Menggunakan Carousel:
1. **Tombol Prev/Next:** ❮ untuk slide kiri, ❯ untuk slide kanan
2. **Dot Indicator:** Klik titik di bawah untuk langsung ke produk
3. **Auto-Slide:** Otomatis bergerak setiap 5 detik
4. **Hover Effect:** Gambar membesar saat di-hover

### Debug Checklist

- [ ] Minimal 1 produk sudah ditambahkan
- [ ] Login sudah berhasil
- [ ] Halaman di-refresh dengan hard refresh
- [ ] Test carousel.html berfungsi
- [ ] Browser console tidak ada error
- [ ] File `script.js` ada di folder
- [ ] File `style.css` ada di folder

### Files yang Penting

```
berandaAdmin.php    ← HTML carousel
script.js           ← JavaScript carousel logic
style.css           ← CSS carousel styling
test_carousel.html  ← Test file (buka untuk debug)
```

### Jika Masih Error

Silakan check:
1. Buka `http://localhost/crud/rpl/test_carousel.html`
2. Jika test berfungsi, berarti produk yang perlu ditambahkan
3. Jika test tidak berfungsi, ada masalah dengan kode

---

**Terakhir diupdate:** 30 November 2025
