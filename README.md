# Git: GitHub'Ä±n Ã¶nerdiÄŸi gÃ¼venli varsayÄ±lanlar
.DS_Store
Thumbs.db
desktop.ini

# Yerel WordPress ayarlarÄ± ve sÄ±rlar
/wp-config.php
/.env
/.env.*
!/.env.example
*.pem
*.key
*.p12
*.pfx

# VeritabanÄ±, kiÅŸisel iÃ§erik, gÃ¼nlÃ¼k ve yedekler
*.sql
*.sql.gz
*.dump
*.bak
*.log
/debug.log
/wp-content/uploads/
/wp-content/cache/
/wp-content/upgrade/
/wp-content/backups/
/wp-content/ai1wm-backups/
/.pt-backup/
**/*.backup*

# Test hesaplarÄ± / tarayÄ±cÄ± profilleri / test Ã§Ä±ktÄ±larÄ±
/wp-content/plugins/eren-athletic-core/tests/
**/chrome-profile*/
/test-results/
/playwright-report/
@' | Set-Content -LiteralPath (Join-Path $stage '.gitignore') -Encoding UTF8

@'
# Eren Athletic â€” WordPress kaynak dosyalarÄ±

Bu depo, Eren Athletic spor kompleksi iÃ§in hazÄ±rlanmÄ±ÅŸ Ã¶zel WordPress eklentisi ve alt temasÄ±nÄ± iÃ§erir. WordPress Ã§ekirdeÄŸi, veritabanÄ±, kullanÄ±cÄ± hesaplarÄ±, yerel yedekler ve Medya KÃ¼tÃ¼phanesi dosyalarÄ± gÃ¼venlik ve lisans incelemesi nedeniyle bu kopyaya alÄ±nmamÄ±ÅŸtÄ±r.

## Ä°Ã§erik

- `wp-content/plugins/eren-athletic-core`: salon iÃ§erikleri, antrenÃ¶r/PT, rezervasyon takvimi, fitness Ã¼yelik baÅŸvurularÄ±, kullanÄ±cÄ± paneli ve eriÅŸilebilirlik arayÃ¼zÃ¼ iÃ§in proje kodu.
- `wp-content/themes/fitness-and-gym-wda-eren`: Eren Athletic'in siyah-sarÄ± alt temasÄ±, duyarlÄ± sayfa ÅŸablonlarÄ± ve eriÅŸilebilirlik stilleri.
- `wp-config.example.php`: gizli bilgi iÃ§ermeyen yerel WordPress yapÄ±landÄ±rma Ã¶rneÄŸi.
- `wp-content/themes/fitness-and-gym-wda-eren/assets/fonts/Inter-LICENSE.txt`: kullanÄ±lan Inter fontunun SIL Open Font License 1.1 metni.

## Gereksinimler

- Apache ve MySQL/MariaDB iÃ§eren XAMPP
- WordPress 6.0 veya Ã¼stÃ¼
- PHP 7.4 veya Ã¼stÃ¼
- Eren Athletic alt temasÄ± iÃ§in `Fitness and Gym WDA` Ã¼st temasÄ±

Ãœst tema WordPress.org'da yayÄ±mlanÄ±yor: <https://wordpress.org/themes/fitness-and-gym-wda/>. Bu kopyada yerel kurulumdaki Ã¼st tema dosyalarÄ± bulunmuyor. Yereldeki sÃ¼rÃ¼m `1.1.5`, WordPress.org dizinindeki sÃ¼rÃ¼m ise denetim sÄ±rasÄ±nda `1.1.4` olarak listeleniyordu. SÃ¼rÃ¼m kaynaÄŸÄ± ve gÃ¶rsel lisanslarÄ± eÅŸleÅŸmediÄŸi iÃ§in yerel Ã¼Ã§Ã¼ncÃ¼ taraf tema kopyasÄ± daÄŸÄ±tÄ±ma eklenmedi. TemayÄ± resmi kaynaktan ayrÄ± kurun ve alt temayla uyumluluÄŸunu doÄŸrulayÄ±n.

## Yerel kurulum

1. WordPress'i resmi kaynaktan indirin: <https://wordpress.org/download/>. DosyalarÄ± XAMPP'nin htdocs altÄ±nda yeni bir proje klasÃ¶rÃ¼ne Ã§Ä±karÄ±n.
2. XAMPP Control Panel'den Apache ve MySQL'i baÅŸlatÄ±n. phpMyAdmin Ã¼zerinden yeni ve boÅŸ bir veritabanÄ± oluÅŸturun.
3. `wp-config.example.php` dosyasÄ±nÄ± WordPress kÃ¶kÃ¼ne kopyalayÄ±p adÄ±nÄ± `wp-config.php` yapÄ±n. VeritabanÄ± adÄ±, kullanÄ±cÄ± adÄ±, parola ve benzersiz gÃ¼venlik anahtarlarÄ±nÄ± yalnÄ±zca yerel dosyada doldurun. GerÃ§ek ayar dosyasÄ±nÄ± Git'e eklemeyin.
4. WordPress kurulum sihirbazÄ±nÄ± tamamlayÄ±n.
5. `Fitness and Gym WDA` Ã¼st temasÄ±nÄ± WordPress yÃ¶netim panelinden veya resmi WordPress.org temasÄ±ndan kurup etkinleÅŸtirin.
6. Bu depodaki `wp-content/themes/fitness-and-gym-wda-eren` ve `wp-content/plugins/eren-athletic-core` klasÃ¶rlerini WordPress'in `wp-content/themes/` ve `wp-content/plugins/` klasÃ¶rlerine kopyalayÄ±n.
7. YÃ¶netim panelinde Ã¶nce `Fitness and Gym WDA â€“ Eren Athletic` alt temasÄ±nÄ±, sonra `Eren Athletic Core` eklentisini etkinleÅŸtirin. Gerekiyorsa **Ayarlar â†’ KalÄ±cÄ± BaÄŸlantÄ±lar** ekranÄ±nda deÄŸiÅŸiklik yapmadan **DeÄŸiÅŸiklikleri Kaydet** dÃ¼ÄŸmesine basarak baÄŸlantÄ±larÄ± yenileyin.
8. Ãœyelik, salon, antrenÃ¶r, Ã§alÄ±ÅŸma saati, menÃ¼ ve sayfa iÃ§eriklerini yÃ¶netim panelinde yeniden girin. Bu depo, Ã§alÄ±ÅŸan sitenin veritabanÄ±nÄ± veya gerÃ§ek kullanÄ±cÄ±/baÅŸvuru kayÄ±tlarÄ±nÄ± iÃ§ermez.
9. YalnÄ±zca kullanma ve yayÄ±mlama izniniz olan gÃ¶rselleri Medya KÃ¼tÃ¼phanesine yÃ¼kleyin. Bu depoya yÃ¼klenen Ã¶zel fotoÄŸraflar ve orijinal logo dahil edilmemiÅŸtir.

## YapÄ±landÄ±rma ve veri gÃ¼venliÄŸi

`wp-config.example.php` yalnÄ±zca yer tutucular iÃ§erir; doÄŸrudan Ã§alÄ±ÅŸÄ±r gizli anahtar veya parola yoktur. `wp-config.php`, `.env`, SQL dÃ¶kÃ¼mleri, `wp-content/uploads`, test hesaplarÄ±, testler, yerel gÃ¼nlÃ¼kler ve yedekler `.gitignore` ile dÄ±ÅŸlanÄ±r. GerÃ§ek kullanÄ±cÄ± verileri veya Ã§alÄ±ÅŸan veritabanÄ± bu depoda bulunmamalÄ±dÄ±r.

## DaÄŸÄ±tÄ±m ve lisans notlarÄ±

- ÃœÃ§Ã¼ncÃ¼ taraf Ã¼st tema dosyalarÄ± ve Medya KÃ¼tÃ¼phanesindeki gÃ¶rseller bu kopyaya dahil deÄŸildir; lisans/sÃ¼rÃ¼m eÅŸleÅŸmesi ayrÄ±ca doÄŸrulanmalÄ±dÄ±r.
- Inter fontu, eÅŸlik eden SIL Open Font License 1.1 metniyle birlikte tutulmuÅŸtur.
- Projeye Ã¶zel alt tema ve eklenti iÃ§in kaynakta bir `LICENSE` dosyasÄ± yoktur. Kamuya aÃ§Ä±k GitHub yayÄ±nÄ± Ã¶ncesinde proje sahibinin daÄŸÄ±tÄ±m lisansÄ± kararÄ± eklenmelidir. Bu hazÄ±rlÄ±k kopyasÄ±, lisans seÃ§imi yapÄ±lmÄ±ÅŸ olduÄŸu anlamÄ±na gelmez.
- GitHub'a yÃ¼kleme bu hazÄ±rlÄ±kta yapÄ±lmamÄ±ÅŸtÄ±r.
