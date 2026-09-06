# Marakana Mobile Native v41

## v41 — Yeni icarə loading popupı + müştəri axtarışı

- `Yeni icarə yarat` düyməsinə toxunanda ortada **Yeni icarə məlumatları yüklənir...** popupı görünür.
- Forma yalnız məlumat tam yükləndikdən sonra açılır.
- Yeni icarə formasında **Müştərini ad və ya telefonla axtar** xanası əlavə olunub.
- Yazdıqca Müştəri seçimi lokal olaraq filtr olunur; əlavə server sorğusu göndərilmir.
- Forma açıldıqdan sonra avtomatik periodik yenilənmə yoxdur.
- `versionCode 41`
- `versionName 3.4.18-native-v41`
- Release artifact: `MarakanaMobile-v41-permanent-update-apk`
- Release APK: `MarakanaMobile-v41-release.apk`

---

## v40 — Müştəri detalı yüklənmə popupı

- İcarə Paneli → Müştərilər bölməsində müştərinin üzərinə klik edən kimi ortada `Müştəri məlumatları yüklənir...` popupı görünür.
- Müştəri məlumat pəncərəsi yalnız məlumat tam yükləndikdən sonra açılır.
- Yükləmə xətası olarsa popup bağlanır və xəta mesajı göstərilir.
- Müştəri detalları açıldıqdan sonra 5 saniyəlik avtomatik yenilənmə yoxdur.
- `versionCode 40`
- `versionName 3.4.17-native-v40`
- Release artifact: `MarakanaMobile-v40-permanent-update-apk`
- Release APK: `MarakanaMobile-v40-release.apk`

---

## v39 — İcarə Paneli yüklənmə və sabit ekran düzəlişi

- Müştərilər bölməsinə daxil olarkən ekranın ortasında modal `Müştəri məlumatları yüklənir...` popup-u görünür.
- Məlumat gələndə popup avtomatik bağlanır.
- `Yeni icarə yarat` forması yalnız daxil olarkən bir dəfə yüklənir; açıldıqdan sonra avtomatik refresh edilmir.
- Müştəri məlumatı pəncərəsi yalnız klik anında bir dəfə yüklənir; açıq qaldığı müddətdə avtomatik refresh edilmir.
- Müştərilər əsas siyahısında v38-dəki timeout/retry qoruması saxlanılıb.

Versiya:
- `versionCode 39`
- `versionName 3.4.16-native-v39`
- Release artifact: `MarakanaMobile-v39-permanent-update-apk`
- Release APK: `MarakanaMobile-v39-release.apk`



## v39 — İcarə Paneli bağlantı/timeout düzəlişi

- Müştərilər bölməsi yüklənərkən artıq boş ekran qalmır: yüklənmə və xəta/retry kartı görünür.
- Rental V2/Cloudflare müvəqqəti xətalarında sorğu avtomatik 2 dəfə yenidən yoxlanır.
- `Yeni icarə yarat` klikində dərhal yüklənmə məlumatı görünür; options sorğusu retry ilə işləyir.
- PC v1267 son uğurlu Müştəri/Kataloq/Detal məlumatını persistent cache-də saxlayır; mobil həmin cache-dən istifadə edə bilir.
- Müştəri detalı server müvəqqəti əlçatmaz olduqda son saxlanmış/basic məlumatla açıla bilər; warning göstərilir.
- v37-dəki yeni icarə formu, şəxsiyyət vəsiqəsi/selfi görüntüləmə və digər bütün funksiyalar qorunur.

### Versiya
- `versionCode 38`
- `versionName 3.4.16-native-v39`
- Release artifact: `MarakanaMobile-v39-permanent-update-apk`
- Release APK: `MarakanaMobile-v39-release.apk`

### PC tələbi
Tam düzəliş üçün PC proqramında **v1267 və ya daha yeni** versiya qurulmalıdır.
