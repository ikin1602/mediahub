<?php
require __DIR__ . '/backend/config.php';
render_header('Home', '.');
?>
    
<!-- PAGE CONTENT -->
<section class="hero">
  <div>
    <div class="kicker">SELAMAT DATANG</div>
    <h1>MEDIAHUB</h1>
    <h3>POWERED BY PIPD</h3>
  </div>
</section>

<section class="section">
  <div class="kicker">SERVIS MEDIAHUB</div>
  <div class="card-grid">

    <a class="card" href="backend/book.php?slug=kamera">
      <div class="thumb" style="background:url('asset/borangpinjam.png') center/cover no-repeat;"></div>
      <div class="body">
        <h3>Peralatan dan Fasiliti</h3>
        <div class="meta">Borang permohonan peminjaman peralatan dan fasiliti.</div>
      </div>
    </a>

    <a class="card" href="backend/my_bookings.php">
      <div class="thumb" style="background:url('asset/tempahan.png') center/cover no-repeat;"></div>
      <div class="body">
        <h3>Tempahan Saya</h3>
        <div class="meta">Lihat atau batal permohonan</div>
      </div>
    </a>

    <!-- New Panduan card -->
    <a class="card" href="panduan.php">
      <div class="thumb" style="background:url('asset/panduan.png') center/cover no-repeat;"></div>
      <div class="body">
        <h3>Panduan</h3>
        <div class="meta">Cara memohon peralatan, peraturan & garis panduan</div>
      </div>
    </a>

  </div>
</section>
<?php render_footer(); ?>