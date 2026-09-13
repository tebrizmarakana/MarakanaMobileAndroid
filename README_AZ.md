# Marakana Mobile Native v69

## v69 — Oyunlar ID əlaqəli bazaya keçirildi

- Mobil oyun seçimini artıq WordPress API-yə `game_ids` ilə də göndərir.
- Tək oyun və Bundle seçimlərində seçilən adlar serverdə ayrıca oyun ID-lərinə bağlanır.
- Köhnə `game_name` mətn sahəsi yalnız geriyə uyğun cache kimi saxlanılır.
- Oyun adını dəyişəndə hesablar həmin `game_id` əlaqəsini saxlayır; Bundle əlaqələri pozulmur.
- Mövcud v68 funksiyaları dəyişməyib.

- `versionCode 69`
- `versionName 3.4.46-native-v69`
- Release APK: `MarakanaMobile-v69-release.apk`

## v68 — Satılmamış hesabda məlumat aksiyaları gizlidir

- `Hesablar` bölməsində statusu `Satılmayıb` olan hesabda 1 saniyə basılı saxlayanda `Ətraflı məlumat` və `Məlumatı göndər` görünmür.
- Satılmamış hesabda yalnız `Düzənlə / Sil` qalır.
- Satılmış hesabda əvvəlki `Düzənlə / Ətraflı məlumat / Məlumatı göndər / Sil` menyusu saxlanılır.
- `Satılmayanlar` bölməsinin əvvəlki `Düzənlə / Sil` menyusu dəyişməyib.
- `versionCode 68`
- `versionName 3.4.45-native-v68`
- Release APK: `MarakanaMobile-v68-release.apk`


## v67 — Hesab ətraflı məlumat build fix

- GitHub Actions compile xətası düzəldildi: `buildAccountSalesRecordCard(...)` çağırışı yeni 3-parametrli metod imzası ilə uyğunlaşdırıldı.
- Müştəri alış tarixçəsindəki kartlar `customer` section ilə render olunur; Hesablar/Satılanlar üçün v66-da əlavə olunan Ətraflı məlumat və WhatsApp menyusu dəyişməyib.
- Node.js 20 deprecation warning build xətasının səbəbi deyil.
- `versionCode 67`
- `versionName 3.4.44-native-v67`
- Release APK: `MarakanaMobile-v67-release.apk`


## v66 — Hesab ətraflı məlumat + WhatsApp göndər
- Hesablar və Satılanlar bölmələrində hesab kartını 1 saniyə basılı saxlayanda menyu artıq `Düzənlə → Ətraflı məlumat → Məlumatı göndər → Sil` sırası ilə açılır.
- `Ətraflı məlumat` hesabın oyun/Bundle oyunları, e-mail, növ, konsol, qiymət, müştəri, telefon, satış tarixi, status, hesab ID-si, yaradılma və son dəyişiklik məlumatlarını göstərir.
- `Məlumatı göndər` həmin hesabın müştəri telefonuna WhatsApp / WhatsApp Business ilə məlumat göndərir.
- WhatsApp mətnində hər məlumat uyğun ikonla verilir; Bundle hesablarında oyunlar ayrıca sətirlərdə göstərilir.
- Telefon nömrəsi olmayan hesabda göndərmə bloklanır və xəbərdarlıq göstərilir.
- Satılmayanlar bölməsinin uzun-bas menyusu əvvəlki `Düzənlə / Sil` formasında qalır.
- v65-dəki Bundle `⧉` işarəsinin yalnız e-mail sətrində görünməsi saxlanılıb.
- WordPress companion plugin dəyişməyib: v1.0.74.

Build:
- `versionCode 66`
- `versionName 3.4.43-native-v66`
- Release APK: `MarakanaMobile-v66-release.apk`

---

## v65 — Bundle ikonu yalnız e-mail sətrində
- Hesablar / Satılanlar / Satılmayanlar bölmələrində Bundle oyun adları əvvəlki kimi alt-alta görünür.
- Oyun adlarının qarşısındakı ayrıca `🧩` ikonları çıxarıldı.
- Bundle hesab olduğunu göstərən tək `⧉` işarəsi yalnız e-mail sətrinin əvvəlində görünür.
- Tək oyunlu hesablarda e-mail sətri əvvəlki kimi ikonsuz qalır.
- v64-dəki Düzənlə rejimində Bundle oyun əlavə/çıxarma idarəsi saxlanılıb.
- WordPress companion plugin dəyişməyib: v1.0.74.

Build:
- `versionCode 65`
- `versionName 3.4.42-native-v65`
- Release APK: `MarakanaMobile-v65-release.apk`

---

## v64 — Düzənlə rejimində Bundle idarəsi
- Hesabın `Düzənlə` ekranında Oyunun adı picker-i artıq Yeni hesab yarat ekranı ilə eyni Bundle rejimini göstərir.
- Mövcud Bundle hesabı düzənləyərkən seçilmiş oyunlar əvvəlcədən işarəli açılır.
- Bundle-a yeni oyun əlavə etmək və mövcud oyunu seçimdən çıxarmaq mümkündür.
- `Seçilənləri təsdiqlə (N)` ilə yenilənmiş Bundle siyahısı hesaba yazılır və `Dəyişiklikləri yadda saxla` ilə serverə göndərilir.
- Tək oyunlu hesabı da Düzənlə içindən Bundle-a çevirmək mümkündür.
- Oyun yaratma və 1 saniyə basılı saxlayıb oyun adını Düzənlə funksiyaları Bundle rejimində saxlanılıb.
- v63-dəki Bundle oyunlarının Hesablar / Satılanlar / Satılmayanlarda alt-alta `🧩` ikonla göstərilməsi saxlanılıb.
- v62-dəki ödəniş növünün UI-dan ləğvi və Göstəricilərin yalnız Ayarlarda görünməsi saxlanılıb.
- WordPress companion plugin dəyişməyib: v1.0.74.

Build:
- `versionCode 64`
- `versionName 3.4.41-native-v64`
- Release APK: `MarakanaMobile-v64-release.apk`
