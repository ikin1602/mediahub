<?php
require __DIR__ . '/backend/config.php';
render_header('Tentang Kami', '.');
?>

<section class="section max-w-4xl mx-auto leading-relaxed text-gray-200 space-y-8">

  <div>
    <h2 class="text-2xl font-semibold text-white tracking-wide mb-2">Tentang MediaHub</h2>
    <p class="text-gray-300">
      <strong>MediaHub</strong> merupakan sistem pengurusan tempahan dan peminjaman peralatan serta fasiliti rasmi yang dibangunkan khusus untuk kegunaan warga Politeknik Nilai. 
      Sistem ini berperanan sebagai platform digital bersepadu bagi menguruskan proses permohonan, kelulusan, serta pemantauan inventori dengan lebih sistematik, telus dan efisien. 
      Melalui MediaHub, segala urusan pinjaman dapat dilakukan secara dalam talian tanpa keperluan penggunaan borang.
    </p>
  </div>

  <div>
    <h3 class="text-xl font-semibold text-white mb-2">Latar Belakang</h3>
    <p class="text-gray-300">
      Sebelum pengenalan sistem ini, proses pinjaman peralatan dijalankan secara manual melalui borang fizikal. 
      Kaedah tersebut sering menimbulkan kekeliruan, pertindihan tempahan, serta kesukaran dalam merekod dan menjejak status pemulangan peralatan. 
      Justeru, <strong>MediaHub</strong> diwujudkan bagi menambah baik pengurusan tempahan dan memastikan segala rekod direkodkan secara automatik serta boleh diakses oleh pihak berkaitan pada bila-bila masa.
    </p>
  </div>

  <div>
    <h3 class="text-xl font-semibold text-white mb-3">Ciri-Ciri Utama Sistem</h3>
    <ul class="list-disc list-inside space-y-2 text-gray-300">
      <li><strong>Pengurusan Tempahan Peralatan</strong> — Membolehkan pengguna memohon peralatan seperti kamera, mikrofon, pembesar suara, walkie talkie dan sebagainya secara dalam talian.</li>
      <li><strong>Tempahan Fasiliti</strong> — Menyediakan kemudahan tempahan ruang seperti Bilik Podcast, Bilik Rakaman, dan TECC dengan fungsi semakan konflik masa secara automatik.</li>
      <li><strong>Kelulusan Digital</strong> — Permohonan boleh diluluskan atau ditolak oleh pentadbir dengan sebab rasmi yang direkodkan dalam sistem.</li>
      <li><strong>Semakan Inventori Masa Nyata</strong> — Sistem akan menyemak jumlah stok yang masih tersedia berdasarkan tarikh pinjaman yang dipohon.</li>
      <li><strong>Rekod Pulangan dan Pemantauan</strong> — Status pinjaman dikategorikan sebagai “Menunggu Pulangan”, “Sudah Dipulangkan” atau “Lewat Pulang” untuk memudahkan pemantauan.</li>
  </div>

  <div>
    <h3 class="text-xl font-semibold text-white mb-2">Objektif Pembangunan MediaHub</h3>
    <ul class="list-disc list-inside space-y-2 text-gray-300">
      <li>Mengenal pasti keperluan dan masalah utama dalam proses tempahan dan pengurusan alatan multimedia di Politeknik Nilai.</li>
      <li>Membangunkan sistem tempahan berasaskan web yang mesra pengguna, teratur dan efisien bagi memudahkan proses pinjaman, inventori dan pemantauan pengguna.</li>
      <li>Menilai keberkesanan dan kepuasan pengguna sistem MEDIAHUB sebagai platform digital di Politeknik Nilai.</li>
    </ul>
  </div>

  <div class="pt-4 border-t border-slate-700 text-sm text-gray-400">
    <p>
      Segala bentuk pinjaman dan tempahan tertakluk kepada kelulusan pihak pengurusan. 
      Penggunaan peralatan dan fasiliti adalah dibenarkan hanya bagi aktiviti rasmi, akademik, kokurikulum, atau program yang mendapat kelulusan institusi.
    </p>
  </div>

</section>

<?php render_footer(); ?>
