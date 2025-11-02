<?php
require __DIR__ . '/backend/config.php';
render_header('Panduan Peralatan', '.');
?>

<!-- PAGE CONTENT -->
<section class="section">
  <h1 class="page-title">Panduan Peralatan</h1>

  <div class="guide-grid">
    <!-- Camera -->
    <div class="guide-card">
      <div class="guide-header">
        <span class="guide-icon red"><i class="fas fa-camera"></i></span>
        <h3>Camera</h3>
      </div>
      <p><strong>Kegunaan:</strong></p>
      <ul>
        <li>Merakam video untuk persembahan projek dan dokumentasi</li>
        <li>Mengambil gambar untuk portfolio dan laporan</li>
        <li>Merakam aktiviti pembelajaran dan demonstrasi</li>
        <li>Dokumentasi acara dan program politeknik</li>
        <li>Projek multimedia dan kreatif pelajar</li>
      </ul>
      <div class="tip blue">Tip: Pastikan bateri penuh dan kad memori kosong sebelum meminjam.</div>
    </div>

    <!-- Microphone -->
    <div class="guide-card">
      <div class="guide-header">
        <span class="guide-icon green"><i class="fas fa-microphone"></i></span>
        <h3>Microphone</h3>
      </div>
      <p><strong>Kegunaan:</strong></p>
      <ul>
        <li>Persembahan dan ucapan awam</li>
        <li>Rakaman audio untuk projek multimedia</li>
        <li>Sesi temuduga dan wawancara</li>
        <li>Podcast dan content creation</li>
        <li>Acara dan majlis rasmi politeknik</li>
      </ul>
      <div class="tip green">Tip: Uji mikrofon sebelum acara untuk memastikan kualiti audio yang baik.</div>
    </div>

    <!-- Wireless Microphone -->
    <div class="guide-card">
      <div class="guide-header">
        <span class="guide-icon purple"><i class="fas fa-broadcast-tower"></i></span>
        <h3>Wireless Microphone</h3>
      </div>
      <p><strong>Kegunaan:</strong></p>
      <ul>
        <li>Persembahan tanpa kabel dengan pergerakan bebas</li>
        <li>Sesi forum, seminar, dan bengkel</li>
        <li>Kelas interaktif dan demonstrasi</li>
      </ul>
      <div class="tip purple">Tip: Pastikan bateri diganti sebelum digunakan.</div>
    </div>

    <!-- LCD Projector -->
    <div class="guide-card">
      <div class="guide-header">
        <span class="guide-icon orange"><i class="fas fa-video"></i></span>
        <h3>LCD Projector</h3>
      </div>
      <p><strong>Kegunaan:</strong></p>
      <ul>
        <li>Pembentangan kelas atau seminar</li>
        <li>Paparan slaid projek</li>
        <li>Tayangan video atau multimedia</li>
      </ul>
      <div class="tip orange">Tip: Gunakan skrin putih untuk kualiti paparan terbaik.</div>
    </div>

    <!-- Walkie Talkie -->
    <div class="guide-card">
      <div class="guide-header">
        <span class="guide-icon blue"><i class="fas fa-walkie-talkie"></i></span>
        <h3>Walkie Talkie</h3>
      </div>
      <p><strong>Kegunaan:</strong></p>
      <ul>
        <li>Komunikasi semasa acara besar</li>
        <li>Penyelarasan aktiviti lapangan</li>
        <li>Kerja berpasukan di kawasan luas</li>
      </ul>
      <div class="tip blue">Tip: Tetapkan saluran yang sama untuk semua unit sebelum digunakan.</div>
    </div>

    <!-- Portable Speaker -->
    <div class="guide-card">
      <div class="guide-header">
        <span class="guide-icon red"><i class="fas fa-volume-up"></i></span>
        <h3>Portable Speaker</h3>
      </div>
      <p><strong>Kegunaan:</strong></p>
      <ul>
        <li>Sistem bunyi mudah alih untuk bilik kuliah atau ruang kecil</li>
        <li>Acara rasmi atau tidak rasmi berskala sederhana</li>
        <li>Pembentangan dengan audiens sederhana</li>
      </ul>
      <div class="tip red">Tip: Jangan letakkan speaker terlalu dekat dengan microphone untuk elakkan feedback.</div>
    </div>

    <!-- PA System DKU -->
    <div class="guide-card">
      <div class="guide-header">
        <span class="guide-icon green"><i class="fas fa-bullhorn"></i></span>
        <h3>PA System DKU</h3>
      </div>
      <p><strong>Kegunaan:</strong></p>
      <ul>
        <li>Sistem audio untuk Dewan Kuliah Utama</li>
        <li>Digunakan untuk acara besar, seminar, dan perhimpunan</li>
      </ul>
      <div class="tip green">Tip: Pastikan semua kabel dipasang dengan betul sebelum acara bermula.</div>
    </div>

    <!-- PA System DSP -->
    <div class="guide-card">
      <div class="guide-header">
        <span class="guide-icon yellow"><i class="fas fa-headset"></i></span>
        <h3>PA System DSP</h3>
      </div>
      <p><strong>Kegunaan:</strong></p>
      <ul>
        <li>Sistem audio untuk Dewan Sri Putra</li>
        <li>Sesuai untuk majlis rasmi, tayangan, dan persembahan</li>
      </ul>
      <div class="tip yellow">Tip: Sentiasa uji sistem sekurang-kurangnya 30 minit sebelum majlis.</div>
    </div>

    <!-- LCD DKU -->
    <div class="guide-card">
      <div class="guide-header">
        <span class="guide-icon blue"><i class="fas fa-tv"></i></span>
        <h3>LCD DKU</h3>
      </div>
      <p><strong>Kegunaan:</strong></p>
      <ul>
        <li>Paparan visual untuk Dewan Kuliah Utama</li>
        <li>Sokongan paparan slaid, video dan visual pengajaran</li>
        <li>Diguna bersama PA System DKU untuk pengalaman lengkap</li>
      </ul>
      <div class="tip blue">Tip: Semak sambungan HDMI/VGA dan pilih input yang betul pada panel.</div>
    </div>

    <!-- LCD DSP -->
    <div class="guide-card">
      <div class="guide-header">
        <span class="guide-icon purple"><i class="fas fa-tv"></i></span>
        <h3>LCD DSP</h3>
      </div>
      <p><strong>Kegunaan:</strong></p>
      <ul>
        <li>Paparan visual untuk Dewan Sri Putra</li>
        <li>Menayangkan slaid, video dan bahan multimedia semasa majlis</li>
        <li>Integrasi dengan PA System DSP untuk persembahan yang jelas</li>
      </ul>
      <div class="tip purple">Tip: Laraskan kecerahan/keystone jika imej tidak rata di skrin.</div>
    </div>

  </div>
</section>

<section class="section">
  <h2 class="page-title">Cara Membuat Permohonan</h2>
  <p class="subtitle">Ikuti langkah-langkah mudah untuk memohon kemudahan politeknik</p>

  <div class="steps-grid">
    <div class="step">
      <div class="step-number blue">1</div>
      <h4>Mohon 3 Hari Awal</h4>
      <p>Permohonan mesti dibuat sekurang-kurangnya 3 hari sebelum tarikh penggunaan untuk memastikan pemprosesan yang mencukupi</p>
    </div>

    <div class="step">
      <div class="step-number green">2</div>
      <h4>Hantar ke Admin</h4>
      <p>Permohonan akan dihantar terus kepada pentadbir untuk semakan dan kelulusan dalam masa 24 jam</p>
    </div>

    <div class="step">
      <div class="step-number purple">3</div>
      <h4>Periksa Status Permohonan</h4>
      <p>Status kelulusan permohonan akan dipaparkan pada profil</p>
    </div>

    <div class="step">
      <div class="step-number orange">4</div>
      <h4>Pulang Tepat Masa</h4>
      <p>Kembalikan peralatan mengikut masa yang ditetapkan untuk mengelakkan gangguan kepada pengguna lain</p>
    </div>
  </div>

  <div class="important-box">
    <h4>Perkara Penting</h4>
    <ul>
      <li>Pastikan maklumat yang diberikan adalah tepat dan lengkap</li>
      <li>Peralatan yang rosak atau hilang akan dikenakan bayaran gantian</li>
      <li>Permohonan lewat atau tidak lengkap mungkin ditolak</li>
      <li>Hubungi pejabat PIPD untuk sebarang pertanyaan atau bantuan</li>
    </ul>
  </div>
</section>

<?php render_footer(); ?>

<style>
.page-title {
  text-align: left;
  font-size: 1.8rem;
  font-weight: bold;
  margin-bottom: 1.5rem;
  color: var(--primary-color);
}

.subtitle {
  text-align: center;
  margin-bottom: 2rem;
  color: #ffffffff;
}

/* Guide Cards */
.guide-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1.5rem;
  margin-bottom: 3rem;
}

.guide-card {
  background: #1d263fff;
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.guide-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.guide-icon {
  font-size: 1.5rem;
}
.guide-icon.red { color: #e74c3c; }
.guide-icon.green { color: #27ae60; }
.guide-icon.blue { color: #007bff; }
.guide-icon.orange { color: #e67e22; }
.guide-icon.purple { color: #9b59b6; }
.guide-icon.yellow { color: #f1c40f; }

.guide-card h3 {
  margin: 0 auto;      
  text-align: center;  
  flex: 1;             
  color: var(--primary-color);
}

.tip {
  margin-top: 1rem;
  padding: 0.6rem 1rem;
  border-radius: 8px;
  font-size: 0.9rem;
}
.tip.blue { background: #eaf4ff; color: #007bff; }
.tip.green { background: #eafaf0; color: #27ae60; }
.tip.purple { background: #f8ecff; color: #9b59b6; }
.tip.orange { background: #fff5e6; color: #e67e22; }
.tip.red { background: #ffecec; color: #e74c3c; }
.tip.yellow { background: #fffbe6; color: #f1c40f; }

/* Steps */
.steps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
  text-align: center;
}

.step {
  background: #1d263fff;
  border-radius: 12px;
  padding: 1rem;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.step-number {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 0.8rem;
  font-weight: bold;
  color: #fff;
}

.step-number.blue { background: #007bff; }
.step-number.green { background: #27ae60; }
.step-number.purple { background: #9b59b6; }
.step-number.orange { background: #e67e22; }

.step h4 {
  margin-bottom: 0.5rem;
  color: var(--primary-color);
}

/* Important Box */
.important-box {
  background: #0c142a;
  border-left: 5px solid var(--primary-color);
  padding: 1rem 1.5rem;
  border-radius: 8px;
}
.important-box h4 {
  margin-top: 0;
  margin-bottom: 0.5rem;
}
</style>
