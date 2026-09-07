# Marakana Mobile Native v49

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
