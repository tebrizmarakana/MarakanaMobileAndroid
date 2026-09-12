# Marakana Mobile Native v57

## v57 — Hesab Satışı: göstərici adı və alt panel ikonları
- `Göstəricilər` hissəsində `Stok` adı `Satılmayanlar` olaraq dəyişdirildi.
- `Satılan` göstəricisi `Satılanlar` kimi uyğunlaşdırıldı.
- Alt paneldə `Satılanlar` üçün ✅, `Satılmayanlar` üçün 📦 ikonları istifadə olunur.
- Hesablar/Müştəri ikonları və əvvəlki Hesab Satışı funksiyaları saxlanılıb.
- `versionCode 57`
- `versionName 3.4.34-native-v57`
- Release APK: `MarakanaMobile-v57-release.apk`

---

## v56 — Hesab Satışı: Yeni müştəri yarat

- `Yeni hesab satışı` düyməsinin yanında `Yeni müştəri yarat` düyməsi əlavə edildi.
- Yeni müştəri formunda Ad soyad və Telefon daxil edilərək WordPress bazasına yadda saxlanır.
- Yaradılan müştəri `Müştəri` bölməsində dərhal görünür; alış etməyibsə 0 alış kimi göstərilir.
- Eyni telefonla sonradan hesab satışı ediləndə həmin müştərinin alış statistikası avtomatik birləşir.
- WordPress Mobile API companion versiyası: `1.0.72`.
- `versionCode 56`
- `versionName 3.4.33-native-v56`
- Release APK: `MarakanaMobile-v56-release.apk`


## Əvvəlki versiyalar

# Marakana Mobile Native v54

## v54 — Hesab Satışı: Satılmayanlar + 3 saniyə basılı saxla menyusu
- Alt paneldə `Stok` adı `Satılmayanlar` olaraq dəyişdirildi.
- Hesablar / Satılan / Satılmayanlar siyahılarında görünən `Düzəliş` və `Sil` düymələri ləğv edildi.
- Hesab kartının üzərində 3 saniyə basılı saxlayanda `Düzənlə` və `Sil` seçimləri açılır.
- Barmaqla sürüşdürmə başladıqda 3 saniyəlik seçim ləğv olunur ki, siyahı normal scroll işləsin.
- `versionCode 54`
- `versionName 3.4.31-native-v54`
- Release APK: `MarakanaMobile-v54-release.apk`


# Marakana Mobile Native v53

## v53 — Hesab Satışı naviqasiya düzəlişi

- Hesab Satışı ekranının yuxarı hissəsindəki **Bağlantı** düyməsi ləğv edildi.
- Alt paneldəki **Ayarlar** düyməsi yuxarı hissəyə, əvvəlki Bağlantı düyməsinin yerinə keçirildi.
- Alt panel indi yalnız **Hesablar / Satılan / Stok / Müştəri** bölmələrini göstərir.
- WordPress ünvanı və API açarını dəyişmək yenə **Ayarlar → Mobil bağlantı** hissəsindən mümkündür.
- **Yenilə** düyməsi yuxarı hissədə əvvəlki kimi qalır.
- `versionCode 53`
- `versionName 3.4.30-native-v53`
- Release APK: `MarakanaMobile-v53-release.apk`


## v52 — Hesab Satışı build düzəlişi

- `MainActivity` daxilində təkrar yaranmış `money(double)` helper-i silindi.
- GitHub Actions `compileReleaseJavaWithJavac` mərhələsindəki `method money(double) is already defined` xətası aradan qaldırıldı.
- v51-dəki WordPress Hesab Satışı native mobil modulu olduğu kimi saxlanılıb.

## v51 — WordPress Hesab Satışı native mobil modulu

- Admin sol menyusuna **Hesab Satışı** əlavə edildi.
- Modul WordPress-dəki `Marakana Playstation Hesab Satışı` plugininin eyni bazası ilə REST API üzərindən işləyir.
- Hesablar, Satılan, Stok, Müştərilər və Ayarlar bölmələri native Android UI ilə açılır.
- Yeni hesab əlavə etmək, mövcud hesabı düzəltmək/silmək, müştəri alışlarını görmək və plugin ayarlarını dəyişmək mümkündür.
- İlk girişdə WordPress ünvanı və plugin Ayarlarındakı **Mobil API açarı** yazılır; API açarı Android Keystore ilə şifrəli saxlanılır.
- WordPress companion plugin faylı `v1.0.71` mobil REST API dəstəyi verir.
- `versionCode 52`
- `versionName 3.4.29-native-v52`
- Release APK: `MarakanaMobile-v52-release.apk`

---


## v50 — Filiala görə avtomatik Admin QR server keçidi

- Admin QR hansı filialın PC-sindən yaradılıbsa, mobil tətbiq QR-dakı `server` / `IP:port` ünvanını əsas götürür.
- Mobil başqa filiala qoşulu olsa belə artıq `Bu Admin QR başqa PC serverinə aiddir` xətası ilə bloklanmır.
- QR serveri fərqlidirsə əvvəl `/api/mobile/ping` ilə həmin PC yoxlanılır, sonra cari istifadəçi/şifrə ilə həmin filialda avtomatik sessiya yaradılır.
- Avtomatik giriş uğurludursa mobilin cari serveri və sessiyası həmin filiala keçirilir və Admin QR həmin PC-də təsdiqlənir.
- Həmin filialda şifrə fərqlidirsə tətbiq QR serverinə keçir, giriş ekranını açır və pending QR-ni saxlayır; girişdən sonra təsdiq avtomatik davam edir.
- Telefon Kamera → browser → Marakana Mobile və tətbiqdaxili `Admin QR təsdiqi` skaneri eyni filial-avtomatik-keçid məntiqindən istifadə edir.
- QR-dakı PC əlçatan deyilsə cari işlək filial sessiyası korlanmır.
- `versionCode 50`
- `versionName 3.4.27-native-v50`
- Release APK: `MarakanaMobile-v50-release.apk`


## v49 — Chrome/Android Admin QR tətbiq keçidi

- PC v1280-də browser səhifəsində əsas düymə artıq standart Android `intent://` URI istifadə edir.
- APK `marakana://` və `marakanaadmin://` custom scheme-lərini host məhdudiyyəti olmadan qəbul edir; tətbiq daxilində yalnız `admin-approval` hostu təsdiq edilir.
- Tətbiq `onResume()` zamanı da pending Admin QR deep-link-i yoxlayır.
- Məqsəd: Kamera QR-ni browserdə açdıqdan sonra **Marakana Mobile-da aç** düyməsinə toxunanda səhifədə qalmaq əvəzinə APK birbaşa açılsın.
- `versionCode 49`
- `versionName 3.4.26-native-v49`
- Release APK: `MarakanaMobile-v49-release.apk`

## v48 — Telefon kamerasından Admin QR təsdiqi

- PC v1276 Admin QR kodunu `marakana://admin-approval?...` deep-link kimi yaradır.
- Telefonun standart Kamera/QR tətbiqi kodu oxuduqda Marakana Mobile ilə açmaq mümkündür.
- Tətbiq açılan kimi challenge avtomatik qəbul edilir; aktiv rol Admin deyilsə, istifadəçinin Admin icazəsi varsa Admin sessiyasına keçib təsdiqləyir.
- APK bağlı olub auto-login aktivdirsə, deep-link login tamamlanana qədər gözləyir və sonra təsdiqləyir.
- QR-da şifrə yoxdur; birdəfəlik challenge serverdə yoxlanılır.
- Köhnə v45 JSON QR-ları da daxili scanner ilə işləməyə davam edir.

- `versionCode 46`
- `versionName 3.4.23-native-v48`
- Release APK: `MarakanaMobile-v48-release.apk`


## v45 — İcarə yaradılarkən gözləmə popupı

- Yeni icarə formasında son təsdiqdə **Yarat** basıldıqda ortada `İcarə yaradılır, gözləyin...` popupı görünür.
- Server cavab verənə qədər popup bağlanmır.
- Uğurlu yaradılmada popup bağlanır və Aktiv icarələr açılır.
- Xəta olduqda popup bağlanır və xəta mesajı göstərilir.

## v45 — Sadələşdirilmiş giriş

- Giriş ekranından Zal / Mətbəx / Admin / Borc Dəftəri bölmə seçimi çıxarıldı.
- İstifadəçi yalnız PC istifadəçisini seçib şifrəsini yazır.
- Tətbiq əvvəlki/uyğun rol ilə daxil olmağa çalışır; icazə yoxdursa Zal, Mətbəx və Admin rollarını avtomatik yoxlayıb icazə verilən rol ilə daxil olur.
- Girişdən sonra sol menyuda yalnız istifadəçinin həqiqətən icazəsi olan bölmələr görünür və oradan rol/bölmə dəyişmək mümkündür.
- Girişdə istifadəçi siyahısında artıq full name / Sistem administrator / Zal nəzarətçisi kimi əlavə mətn göstərilmir; yalnız `username` görünür.
- İcarə Paneli və canlı müştəri axtarışı daxil olmaqla əvvəlki funksiyalar qorunur.

## Versiya

- `versionCode 44`
- `versionName 3.4.21-native-v45`
- Release artifact: `MarakanaMobile-v45-permanent-update-apk`
- Release APK: `MarakanaMobile-v45-release.apk`


## v45 — Admin QR təsdiqi
- PC v1275-də Admin şifrəsi tələb olunan mərkəzi təsdiq pəncərələrində göstərilən birdəfəlik QR kodu oxuyur.
- Sol menyuda yalnız Admin səlahiyyəti olan istifadəçiyə `Admin QR təsdiqi` görünür.
- QR təsdiqi üçün mobil sessiya Admin rolunda olmalıdır; tətbiq lazım gələrsə Admin roluna keçir.
- QR kodda Admin şifrəsi və ya şifrə hash-i yoxdur; yalnız 60 saniyəlik birdəfəlik challenge tokeni var.
- Uğurlu oxunuşdan sonra PC pəncərəsi şifrə yazılmadan təsdiqlənir.


## v48 Admin QR kamera + daxili skaner düzəlişi
- PC v1278 HTTP QR-ni APK daxilindəki Admin QR skaneri birbaşa tanıyır.
- HTTP QR-dan challenge və PC server ünvanı oxunur.
- QR başqa PC-yə aiddirsə səhv serverə təsdiq göndərilmir.
- Admin icazəsi olan istifadəçi başqa rejimdədirsə Admin QR skaneri özü Admin rejiminə keçir.
- Sistem Kamera yolu köhnə marakana deep-link, HTTP bridge və explicit Android intent formatları ilə uyğun saxlanılıb.


## v48 Admin QR düzəlişi
- Kamera HTTP səhifəsində Marakana Mobile düyməsi birbaşa custom deep-link açır.
- APK daxili Admin QR scanner artıq cari Zal/Mətbəx rolundan asılı deyil; real Admin icazəsi serverdə yoxlanır.
- versionCode 48 / versionName 3.4.25-native-v48.

## v51 Hesab Satışı bağlantısını aktivləşdirmək

1. `WORDPRESS_HESAB_SATISI_MOBILE_API/marakana-playstation-hesab-satisi.php` faylını mövcud WordPress Hesab Satışı plugininin əsas PHP faylı ilə əvəz et.
2. WordPress Admin → Hesab Satışı → Ayarlar → Mobil APK bağlantısı bölməsindən API açarını götür.
3. Mobil APK-da Admin → Hesab Satışı → WordPress ünvanı + API açarı yaz.
4. API açarı telefonda Android Keystore ilə şifrəli saxlanılır.