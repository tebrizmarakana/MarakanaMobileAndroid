# Marakana Mobile Native v45

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
