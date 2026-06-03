<?php function artikelFooter(object|array|null $session, string $artikelId, string $toast=''): void { ?>
<footer>
  <div class="container">
    <div class="footer-subscribe">
      <h3>Ulasan Pengguna</h3>
      <p>Berikan Ulasan Terbaik Anda Terhadap Artikel Ini.</p>
      <?= reviewForm($session, $artikelId) ?>
    </div>
    <div class="footer-main">
      <div class="footer-col"><h4>Alamat</h4><p>JL. Tanimbar No.22, Kasin, Kec. Klojen, Kota Malang, Jawa Timur 65117</p></div>
      <div class="footer-col"><h4>Menu</h4><ul>
        <li><a href="index.php">Beranda</a></li><li><a href="artikel.php">Artikel</a></li>
        <li><a href="galeri1.php">Galeri</a></li><li><a href="tentangKami.php">Tentang Kami</a></li>
      </ul></div>
      <div class="footer-col"><h4>Hubungi Kami</h4><ul>
        <li><span><i class="fas fa-envelope"></i> jelajahinusa@gmail.com</span></li>
        <li><span><i class="fab fa-whatsapp"></i> 086288275353 (Revan)</span></li>
        <li><span><i class="fab fa-whatsapp"></i> 089209892410 (Baraka)</span></li>
      </ul></div>
      <div class="footer-col"><h4>Social Media</h4><div class="social-icons">
        <a href="#"><i class="fab fa-instagram"></i></a><a href="#"><i class="fab fa-facebook-f"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-youtube"></i></a>
      </div></div>
    </div>
    <div class="footer-bottom"><p>&copy; <?= date('Y') ?> JelajahinNusa. All rights reserved.</p></div>
  </div>
</footer>
<div class="toast <?= $toast ? 'show' : '' ?>"><?= htmlspecialchars($toast) ?></div>
<?php } ?>