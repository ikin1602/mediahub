<?php
require __DIR__ . '/backend/config.php';
render_header('Equipment', '.');
?>

<!-- PAGE CONTENT (adapt from your old equipment.html body) -->
<section class="section">
  <div class="kicker">Peralatan</div>
  <div class="card-grid">
    <a class="card" href="backend/book.php?slug=kamera">
      <div class="thumb" style="background:linear-gradient(135deg,#16a34a,#065f46)"></div>
      <div class="body">
        <h3>Borang Permohonan Peminjaman Alatan</h3>
        <div class="meta">Camera, Loud Speaker, Microphone, Walkie Talkie dan lain-lain</div>
        <p><span class="btn">Mohon</span></p>
      </div>
    </a>
    </a>
  </div>
</section>

<?php render_footer(); ?>
