<?php
require __DIR__ . '/backend/config.php';
render_header('Rooms', '.');
?>

<!-- PAGE CONTENT (adapt from your old rooms.html body) -->
<section class="section">
  <div class="kicker">Bilik TECC</div>
  <div class="card-grid">
    <a class="card" href="backend/book.php?slug=tecc1">
      <div class="thumb" style="background:linear-gradient(135deg,#0ea5e9,#22d3ee)"></div>
      <div class="body">
        <h3>TECC1</h3>
        <div class="meta">Jabatan Perdagangan</div>
        <p><span class="btn">Mohon</span></p>
      </div>
    </a>
    <a class="card" href="backend/book.php?slug=tecc2">
      <div class="thumb" style="background:linear-gradient(135deg,#818cf8,#a78bfa)"></div>
      <div class="body">
        <h3>TECC2</h3>
        <div class="meta">Jabatan Perdagangan</div>
        <p><span class="btn">Mohon</span></p>
      </div>
    </a>
    <a class="card" href="backend/book.php?slug=tecc3">
      <div class="thumb" style="background:linear-gradient(135deg,#f97316,#fb7185)"></div>
      <div class="body">
        <h3>TECC3</h3>
        <div class="meta">Pusat Sumber</div>
        <p><span class="btn">Mohon</span></p>
      </div>
    </a>
  </div>
</section>

<?php render_footer(); ?>
