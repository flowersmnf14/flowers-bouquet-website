<?php
session_start();
include 'config.php';

/* ================= FILTER ================= */
$range    = $_GET['range'] ?? 7;
$category = $_GET['category'] ?? 'all';
$format   = $_POST['format'] ?? '';

// Ambil tanggal terakhir ada transaksi di DB sebagai end_date
$qMax = $conn->query("SELECT MAX(DATE(order_date)) as max_date FROM orders");
$end_date = $qMax->fetch_assoc()['max_date'] ?? date('Y-m-d');
$start_date = date('Y-m-d', strtotime("-$range days", strtotime($end_date)));

$whereCategory = '';
if ($category !== 'all') {
    $whereCategory = "AND p.category='$category'";
}

/* ================= RINGKASAN ================= */
$summary = ['total_transaksi'=>0,'total_pendapatan'=>0,'produk_terjual'=>0];
$qSummary = $conn->query("
    SELECT 
        COUNT(DISTINCT o.id_orders) total_transaksi,
        SUM(o.total) total_pendapatan,
        SUM(d.jumlah) produk_terjual
    FROM orders o
    JOIN detailproduk d ON o.id_orders=d.id_orders
    JOIN products p ON d.product_id=p.product_id
    WHERE DATE(o.order_date) BETWEEN '$start_date' AND '$end_date'
    $whereCategory
");
if($qSummary) $summary=$qSummary->fetch_assoc();

/* ================= GRAFIK HARIAN ================= */
$labels=[]; $income=[]; $sold=[];

// Buat semua tanggal dalam range
$period = new DatePeriod(
    new DateTime($start_date),
    new DateInterval('P1D'),
    (new DateTime($end_date))->modify('+1 day')
);
foreach ($period as $dt) {
    $labels[] = $dt->format('Y-m-d');
}

// Ambil data pendapatan dan produk terjual per hari
$qDay = $conn->query("
    SELECT DATE(o.order_date) tgl,
           SUM(o.total) pendapatan,
           SUM(d.jumlah) terjual
    FROM orders o
    JOIN detailproduk d ON o.id_orders=d.id_orders
    JOIN products p ON d.product_id=p.product_id
    WHERE DATE(o.order_date) BETWEEN '$start_date' AND '$end_date'
    $whereCategory
    GROUP BY DATE(o.order_date)
    ORDER BY tgl
");

// Simpan data per tanggal
$dataByDay = [];
while($r=$qDay->fetch_assoc()){
    $dataByDay[$r['tgl']] = ['pendapatan'=>(float)$r['pendapatan'],'terjual'=>(int)$r['terjual']];
}

foreach($labels as $day){
    $income[] = $dataByDay[$day]['pendapatan'] ?? 0;
    $sold[]   = $dataByDay[$day]['terjual'] ?? 0;
}

/* ================= GRAFIK PENDAPATAN PER KATEGORI ================= */
$categories=['Bunga','Uang','Boneka','Makanan'];
$incomeByCategory = [];
$categoryTotal = [];

foreach($categories as $cat){
    $stmt = $conn->prepare("
        SELECT DATE(o.order_date) tgl, 
               SUM(d.subtotal) pendapatan, 
               SUM(d.jumlah) terjual
        FROM orders o
        JOIN detailproduk d ON o.id_orders=d.id_orders
        JOIN products p ON d.product_id=p.product_id
        WHERE p.category=? AND DATE(o.order_date) BETWEEN ? AND ?
        GROUP BY DATE(o.order_date)
        ORDER BY tgl
    ");
    $stmt->bind_param("sss",$cat,$start_date,$end_date);
    $stmt->execute();
    $res = $stmt->get_result();

    $tmp = []; $totalCat=0;
    foreach($labels as $day){ $tmp[$day]=0; } 
    while($row=$res->fetch_assoc()){
        $tmp[$row['tgl']] = (float)$row['pendapatan'];
        $totalCat += (int)$row['terjual'];
    }

    $incomeByCategory[$cat] = array_values($tmp);
    $categoryTotal[$cat] = $totalCat;
}

/* ================= PIE KATEGORI ================= */
$pieData=[];
foreach($categories as $cat){
    $stmt=$conn->prepare("
        SELECT SUM(d.jumlah) total
        FROM detailproduk d
        JOIN products p ON d.product_id=p.product_id
        JOIN orders o ON d.id_orders=o.id_orders
        WHERE p.category=? AND DATE(o.order_date) BETWEEN ? AND ?
    ");
    $stmt->bind_param("sss",$cat,$start_date,$end_date);
    $stmt->execute();
    $pieData[] = (int)$stmt->get_result()->fetch_assoc()['total'] ?? 0;
}

/* ================= ANALISIS OTOMATIS ================= */
$nonZeroIncome = array_filter($income, fn($v)=>$v>0);
$trend = (count($nonZeroIncome)>=2 && end($nonZeroIncome) >= reset($nonZeroIncome)) ? 'Naik' : 'Turun';
$bestCategory = array_keys($categoryTotal, max($categoryTotal))[0] ?? '-';
$daysWithTransactions = count($nonZeroIncome);
$avgPendapatan = $daysWithTransactions>0 ? (int)($summary['total_transaksi'] / $daysWithTransactions) : 0;
$avgProduk     = $daysWithTransactions>0 ? (int)($summary['produk_terjual'] / $daysWithTransactions) : 0;

// Jika diminta cetak (PDF / Word), buat laporan sederhana berdasarkan data di atas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['format'])) {
    // Autoload composer (dompdf & phpoffice harus sudah terpasang di vendor/)
    require_once __DIR__ . '/vendor/autoload.php';

    $format = $_POST['format'];
    $filenameBase = 'laporan_' . $start_date . '_' . $end_date;
    $filename = $filenameBase . ($format === 'pdf' ? '.pdf' : '.docx');

    // Build a well-formatted HTML body for both PDF and Word outputs.
    $reportBody = '';
    $reportBody .= '<div style="font-family:Segoe UI,Arial,sans-serif;color:#222">';
    $reportBody .= '<h1 style="color:#333;margin-bottom:4px;">Laporan Admin</h1>';
    $reportBody .= '<p style="margin-top:0;color:#555;">Periode: ' . htmlspecialchars($start_date) . ' s.d. ' . htmlspecialchars($end_date) . '</p>';

    // Ringkasan
    $reportBody .= '<h2 style="border-bottom:1px solid #eee;padding-bottom:6px;">Ringkasan</h2>';
    $reportBody .= '<table style="width:100%;border-collapse:collapse;margin-bottom:12px;">';
    $reportBody .= '<thead><tr>';
    $reportBody .= '<th style="border:1px solid #ddd;padding:8px;background:#f7f7f7;text-align:left;">Total Transaksi</th>';
    $reportBody .= '<th style="border:1px solid #ddd;padding:8px;background:#f7f7f7;text-align:left;">Total Pendapatan (Rp)</th>';
    $reportBody .= '<th style="border:1px solid #ddd;padding:8px;background:#f7f7f7;text-align:left;">Produk Terjual</th>';
    $reportBody .= '</tr></thead><tbody>';
    $reportBody .= '<tr>';
    $reportBody .= '<td style="border:1px solid #ddd;padding:8px;">' . ($summary['total_transaksi'] ?? 0) . '</td>';
    $reportBody .= '<td style="border:1px solid #ddd;padding:8px;">Rp ' . number_format($summary['total_pendapatan'] ?? 0, 0, ',', '.') . '</td>';
    $reportBody .= '<td style="border:1px solid #ddd;padding:8px;">' . ($summary['produk_terjual'] ?? 0) . '</td>';
    $reportBody .= '</tr></tbody></table>';

    // Analisis
    $reportBody .= '<h2 style="border-bottom:1px solid #eee;padding-bottom:6px;">Analisis</h2>';
    $reportBody .= '<table style="width:100%;border-collapse:collapse;margin-bottom:12px;">';
    $reportBody .= '<thead><tr>';
    $reportBody .= '<th style="border:1px solid #ddd;padding:8px;background:#f7f7f7;text-align:left;">Tren Pendapatan</th>';
    $reportBody .= '<th style="border:1px solid #ddd;padding:8px;background:#f7f7f7;text-align:left;">Kategori Paling Laku</th>';
    $reportBody .= '<th style="border:1px solid #ddd;padding:8px;background:#f7f7f7;text-align:left;">Rata-rata Transaksi/Hari</th>';
    $reportBody .= '<th style="border:1px solid #ddd;padding:8px;background:#f7f7f7;text-align:left;">Rata-rata Produk/Hari</th>';
    $reportBody .= '</tr></thead><tbody>';
    $reportBody .= '<tr>';
    $reportBody .= '<td style="border:1px solid #ddd;padding:8px;">' . htmlspecialchars($trend) . '</td>';
    $reportBody .= '<td style="border:1px solid #ddd;padding:8px;">' . htmlspecialchars($bestCategory) . '</td>';
    $reportBody .= '<td style="border:1px solid #ddd;padding:8px;">' . $avgPendapatan . '</td>';
    $reportBody .= '<td style="border:1px solid #ddd;padding:8px;">' . $avgProduk . '</td>';
    $reportBody .= '</tr></tbody></table>';

    // Detail Harian
    $reportBody .= '<h2 style="border-bottom:1px solid #eee;padding-bottom:6px;">Detail Harian</h2>';
    $reportBody .= '<table style="width:100%;border-collapse:collapse;margin-bottom:12px;">';
    $reportBody .= '<thead><tr>';
    $reportBody .= '<th style="border:1px solid #ddd;padding:8px;background:#f7f7f7;text-align:left;width:30%;">Tanggal</th>';
    $reportBody .= '<th style="border:1px solid #ddd;padding:8px;background:#f7f7f7;text-align:right;width:35%;">Pendapatan (Rp)</th>';
    $reportBody .= '<th style="border:1px solid #ddd;padding:8px;background:#f7f7f7;text-align:right;width:35%;">Produk Terjual</th>';
    $reportBody .= '</tr></thead><tbody>';
    foreach ($labels as $i => $day) {
        $valIncome = number_format($income[$i] ?? 0, 0, ',', '.');
        $valSold = (int)($sold[$i] ?? 0);
        $reportBody .= '<tr>';
        $reportBody .= '<td style="border:1px solid #ddd;padding:8px;">' . htmlspecialchars($day) . '</td>';
        $reportBody .= '<td style="border:1px solid #ddd;padding:8px;text-align:right;">Rp ' . $valIncome . '</td>';
        $reportBody .= '<td style="border:1px solid #ddd;padding:8px;text-align:right;">' . $valSold . '</td>';
        $reportBody .= '</tr>';
    }
    $reportBody .= '</tbody></table>';

    // Ringkasan Per Kategori
    $reportBody .= '<h2 style="border-bottom:1px solid #eee;padding-bottom:6px;">Ringkasan Per Kategori</h2>';
    $reportBody .= '<table style="width:100%;border-collapse:collapse;margin-bottom:12px;">';
    $reportBody .= '<thead><tr>';
    $reportBody .= '<th style="border:1px solid #ddd;padding:8px;background:#f7f7f7;text-align:left;">Kategori</th>';
    $reportBody .= '<th style="border:1px solid #ddd;padding:8px;background:#f7f7f7;text-align:right;">Produk Terjual</th>';
    $reportBody .= '</tr></thead><tbody>';
    foreach ($categories as $cat) {
        $reportBody .= '<tr>';
        $reportBody .= '<td style="border:1px solid #ddd;padding:8px;">' . htmlspecialchars($cat) . '</td>';
        $reportBody .= '<td style="border:1px solid #ddd;padding:8px;text-align:right;">' . ($categoryTotal[$cat] ?? 0) . '</td>';
        $reportBody .= '</tr>';
    }
    $reportBody .= '</tbody></table>';

    $reportBody .= '<p style="font-size:12px;color:#666;margin-top:12px;">Generated: ' . date('Y-m-d H:i:s') . '</p>';
    $reportBody .= '</div>';

    // Wrap into a full HTML document for PDF rendering; for Word we will use $reportBody
    $reportHtml = '<!doctype html><html><head><meta charset="utf-8"><title>Laporan Admin</title>';
    $reportHtml .= '<style>body{margin:18px;} table{font-size:12px;} h1,h2{font-family:Segoe UI,Arial,sans-serif;}</style>';
    $reportHtml .= '</head><body>' . $reportBody . '</body></html>';

    if ($format === 'pdf') {
        // Generate PDF using Dompdf
        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml($reportHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        // Stream ke browser sebagai download
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    if ($format === 'word') {
        // Prepare the HTML body content (PhpWord works better with body only)
        if (preg_match('#<body.*?>(.*)</body>#is', $reportHtml, $m)) {
            $reportBody = $m[1];
        } else {
            $reportBody = $reportHtml;
        }

        // If ZipArchive (PHP zip extension) is available, use PhpWord to create a .docx
        if (class_exists('ZipArchive')) {
            // Generate Word (.docx) using PhpWord
            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $section = $phpWord->addSection();
            \PhpOffice\PhpWord\Shared\Html::addHtml($section, $reportBody, false, false);

            $tmpfile = tempnam(sys_get_temp_dir(), 'laporan_') . '.docx';
            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($tmpfile);

            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($tmpfile));
            readfile($tmpfile);
            @unlink($tmpfile);
            exit;
        }

        // Fallback: ZipArchive not available (common on default XAMPP). Send a Word-compatible
        // HTML file (.doc) so the browser/Word will open it. This avoids requiring the PHP zip
        // extension and allows immediate download.
        $filenameDoc = $filenameBase . '.doc';
        header('Content-Description: File Transfer');
        header('Content-Type: application/msword');
        header('Content-Disposition: attachment; filename="' . $filenameDoc . '"');
        echo '<html><head><meta charset="utf-8"></head><body>' . $reportBody . '</body></html>';
        exit;
    }
}



?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Admin</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body{margin:0;font-family:'Segoe UI',sans-serif;background:#fffbe6} /* cream */
.container{max-width:1200px;margin:auto;padding:2rem}
header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem}
header h1{color:#ffcc00;margin:0;font-size:1.8rem} /* kuning cerah */
button.back-btn{background:#ffcc00;color:#fff;border:none;padding:8px 16px;border-radius:6px;font-weight:600;cursor:pointer;transition:.3s}
button.back-btn:hover{background:#ffd633;color:#fff}
.filter-bar{display:flex;justify-content:space-between;align-items:center;background:#fff;padding:1rem 1.5rem;border-radius:16px;box-shadow:0 8px 20px rgba(0,0,0,.1);margin-bottom:2rem}
.filter-left,.filter-right{display:flex;gap:10px}
.filter-bar a{padding:8px 16px;border-radius:999px;background:#ffb6e0;text-decoration:none;font-weight:600;color:#000;font-size:14px}
.filter-bar a.active{background:#ffcc00;color:#fff}
.summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:2rem}
.summary-card{background:linear-gradient(135deg,#ff69b4,#ffcc00);border-radius:16px;padding:1rem;text-align:center;color:#fff;box-shadow:0 8px 20px rgba(0,0,0,.1)}
.summary-card h3{margin-bottom:.5rem}
.summary-card p{font-size:1.1rem;font-weight:bold}
.chart-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;margin-bottom:2rem}
.chart-box{background:#fff;border-radius:16px;padding:1rem;box-shadow:0 8px 20px rgba(0,0,0,.1)}
.chart-box h3{text-align:center;color:#ffcc00;margin-bottom:.5rem;font-size:1rem}
.chart-box ul{list-style:none;padding:0;margin:0;font-size:.9rem;color:#333}
.chart-box ul li{margin-bottom:.3rem}
footer{text-align:center;margin-top:2rem;color:#888;font-size:.9rem}
canvas{max-height:300px}

.gradient-card {
    background: linear-gradient(135deg, #ff69b4, #ffcc00);
    color: #ffffff;
    font-size: 16px;
    padding: 1.2rem;
    border-radius: 16px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.gradient-card h3 {
    color: #ffffff;
    font-size: 1.3rem;
    margin-bottom: 0.6rem;
}

.gradient-card ul li {
    margin-bottom: 0.4rem;
    color: #ffffff;
    font-size: 1.1rem;
}

header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.9rem 2rem;
    border-radius: 16px;
    background: linear-gradient(135deg, #ff69b4, #ffcc00);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

header h1 {
    color: #ffffff;
    font-size: 1.8rem;
    margin: 0;
}

header .back-btn {
    background: #ff82c0ff;       /* warna tombol */
    color: #ffffff;              /* font putih */
    border: 2px solid #ff5aacff;      /* garis hitam tegas */
    font-size: 0.9rem;
    padding: 15px 30px;
    border-radius: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.3s;            /* animasi lebih smooth */
}

header .back-btn:hover {
    background: rgba(255, 251, 15, 1);  /* lebih terang saat hover */
    color: #ff69b4;
    border-color: #ff82c0ff;                  /* tetap garis hitam saat hover */
}

</style>
</head>
<body>

<header style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <h1>Laporan Admin</h1>

    <div style="display:flex; gap:10px; align-items:center;">
        <!-- Tombol Cetak -->
        <form method="post" action="laporan.php" target="_blank" style="display:flex; gap:10px;">
            <input type="hidden" name="range" value="<?= $range ?>">
            <input type="hidden" name="category" value="<?= $category ?>">
            <button type="submit" name="format" value="pdf" style="padding:8px 16px;border-radius:8px;background:#ffcc00;color:#fff;font-weight:600;cursor:pointer;">Cetak PDF</button>
            <button type="submit" name="format" value="word" style="padding:8px 16px;border-radius:8px;background:#ff69b4;color:#fff;font-weight:600;cursor:pointer;">Cetak Word</button>
        </form>
        

        <!-- Tombol Kembali -->
        <button class="back-btn" onclick="window.location.href='berandaAdmin.php'">Kembali</button>
    </div>
</header>

<div class="container">

<!-- FILTER -->
<div class="filter-bar">
    <div class="filter-left">
        <?php foreach(['all'=>'Semua','Bunga'=>'Bunga','Uang'=>'Uang','Boneka'=>'Boneka','Makanan'=>'Makanan'] as $k=>$v): ?>
            <a href="?category=<?= $k ?>&range=<?= $range ?>" class="<?= $category==$k?'active':'' ?>"><?= $v ?></a>
        <?php endforeach ?>
    </div>
    <div class="filter-right">
        <?php foreach([7,28,60,365] as $r): ?>
            <a href="?range=<?= $r ?>&category=<?= $category ?>" class="<?= $range==$r?'active':'' ?>"><?= $r ?> Hari</a>
        <?php endforeach ?>
    </div>
</div>

<!-- SUMMARY -->
<div class="summary-grid">
    <div class="summary-card"><h3>Total Transaksi</h3><p><?= $summary['total_transaksi'] ?></p></div>
    <div class="summary-card"><h3>Total Pendapatan</h3><p>Rp <?= number_format($summary['total_pendapatan']) ?></p></div>
    <div class="summary-card"><h3>Produk Terjual</h3><p><?= $summary['produk_terjual'] ?></p></div>
</div>

<!-- GRAFIK 2x2 -->
<div class="chart-grid">
    <div class="chart-box">
        <h3>📈 Pendapatan Total</h3>
        <canvas id="incomeChart"></canvas>
    </div>
    <div class="chart-box">
        <h3>📈 Pendapatan Per Kategori</h3>
        <canvas id="incomeCategoryChart"></canvas>
    </div>
    <div class="chart-box">
        <h3>📊 Produk Terjual</h3>
        <canvas id="soldChart"></canvas>
    </div>
    <div class="chart-box">
        <h3>🥧 Kategori Terlaris</h3>
        <canvas id="categoryChart"></canvas>
    </div>
</div> <br> 

<!-- ANALISIS OTOMATIS -->
<div class="chart-box gradient-card">
    <h3>📋 Analisis Penjualan</h3>
    <ul>
        <li> * Tren Pendapatan: <strong><?= $trend ?></strong></li>
        <li> * Kategori Paling Laku: <strong><?= $bestCategory ?></strong></li>
        <li> * Rata-rata Transaksi per Hari: <strong><?= $avgPendapatan ?> transaksi</strong></li>
        <li> * Rata-rata Produk Terjual per Hari: <strong><?= $avgProduk ?> produk</strong></li>
    </ul>
</div> <br> 

<!-- REKOMENDASI OTOMATIS -->
<div class="chart-box gradient-card">
    <h3>💡 Rekomendasi Penjualan</h3><br>
    <ul>
        <?php
      if($trend == 'Naik'){
            echo "<li> * Tren pendapatan naik, pertahankan strategi saat ini.</li>";
        } else {
            echo "<li> * Tren pendapatan turun, pertimbangkan diskon khusus atau promosi flash sale.</li>";
        }
        echo "<li> * Kategori paling laku: <strong>$bestCategory</strong>. Tingkatkan stok atau buat paket bundling.</li>";
        if($avgProduk < 5){
            echo "<li> * Rata-rata produk terjual per hari rendah ($avgProduk produk). Bisa menambahkan promosi beli 1 gratis 1 atau hadiah kecil untuk meningkatkan penjualan.</li>";
        } else {
            echo "<li> * Rata-rata produk terjual per hari cukup baik ($avgProduk produk), pertahankan strategi upselling.</li>";
        }
        ?>
    </ul>
</div>

<footer>
    <small>Sistem Admin • <?= date('d/m/Y') ?></small>
</footer>
</div>

<script>
const colors = {
    bunga:'#ff69b4',
    uang:'#ffcc00',
    boneka:'#fdb2e0ff',
    makanan:'#fff6a6ff'
};

/* ===== Chart Pendapatan Total ===== */
new Chart(document.getElementById('incomeChart'),{
    type:'line',
    data:{
        labels: <?= json_encode($labels) ?>,
        datasets:[{
            label:'Pendapatan Total',
            data: <?= json_encode($income) ?>,
            borderColor:colors.bunga,
            backgroundColor:'rgba(255,105,180,0.2)',
            fill:true,
            tension:0.4,
            pointHoverRadius:6,
            pointHoverBorderWidth:2
        }]
    },
    options:{responsive:true, plugins:{legend:{display:true,position:'top'}}, interaction:{mode:'index',intersect:false}, scales:{y:{beginAtZero:true}}}
});

/* ===== Chart Pendapatan Per Kategori ===== */
new Chart(document.getElementById('incomeCategoryChart'),{
    type:'line',
    data:{
        labels: <?= json_encode($labels) ?>,
        datasets:[
            {label:'Bunga', data: <?= json_encode($incomeByCategory['Bunga']) ?>, borderColor:colors.bunga, backgroundColor:'rgba(255,105,180,0.2)', fill:true, tension:0.4},
            {label:'Uang', data: <?= json_encode($incomeByCategory['Uang']) ?>, borderColor:colors.uang, backgroundColor:'rgba(255,204,0,0.2)', fill:true, tension:0.4},
            {label:'Boneka', data: <?= json_encode($incomeByCategory['Boneka']) ?>, borderColor:colors.boneka, backgroundColor:'rgba(255,133,208,0.2)', fill:true, tension:0.4},
            {label:'Makanan', data: <?= json_encode($incomeByCategory['Makanan']) ?>, borderColor:colors.makanan, backgroundColor:'rgba(255,240,102,0.2)', fill:true, tension:0.4}
        ]
    },
    options:{responsive:true, plugins:{legend:{position:'top'}}, interaction:{mode:'index',intersect:false}, scales:{y:{beginAtZero:true}}}
});

/* ===== Bar Chart Produk Terjual ===== */
new Chart(document.getElementById('soldChart'),{
    type:'bar',
    data:{
        labels: <?= json_encode($labels) ?>,
        datasets:[{label:'Produk Terjual', data: <?= json_encode($sold) ?>, backgroundColor:'#ffcc00'}]
    },
    options:{responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}
});

/* ===== Pie Chart Kategori Terlaris ===== */
new Chart(document.getElementById('categoryChart'),{
    type:'doughnut',
    data:{
        labels: <?= json_encode($categories) ?>,
        datasets:[{data: <?= json_encode($pieData) ?>, backgroundColor:['#ff69b4','#ffcc00','#fdb2e0ff','#fff6a6ff']}]
    },
    options:{responsive:true, plugins:{legend:{position:'top'}}}
});
</script>
</body>
</html>


