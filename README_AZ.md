# Marakana Mobile Native v79

## v79 — Satılanlarda müştəri məlumatları e-mail sətrində

- `Satılanlar` kartında e-mail, müştəri adı, telefon və satış tarixi artıq eyni məlumat sətrində göstərilir.
- Görünüş: `email • Ad Soyad • Telefon • Tarix`.
- Digər bölmələrin kart düzülüşü dəyişdirilməyib.
- `versionCode 79`
- `versionName 3.4.56-native-v79`

## v78 — Status rəngləri və başlıq sətrində göstəricilər

- `Satılıb` sözü qırmızı rəngdə göstərilir.
- `Satılmayıb` sözü yaşıl rəngdə göstərilir.
- `İcarə` statusu narıncı rəngdə qalır.
- Satılanlar / Satılmayanlar / İcarə bölmələrində `Universal • PS5 • Satılıb` tipli göstərici artıq oyun adının olduğu üst sətirdə göstərilir.
- Bundle hesabda göstərici birinci oyun adının sətirində göstərilir.


## v77 — Satılan hesabı kopyalamadan əvvəl təsdiq

- **Satılanlar → Kopyala** seçiləndə artıq hesab dərhal kopyalanmır.
- Əvvəlcə **Hesabı kopyala** təsdiqləmə pəncərəsi açılır.
- Pəncərədə yeni nüsxənin **Satılmayan** kimi yaradılacağı, müştəri məlumatları və satış tarixinin boş qalacağı göstərilir.
- **Kopyala** basıldıqda əvvəlki v76 qaydası ilə yeni Satılmayan nüsxə yaradılır.
- **Xeyr** basıldıqda heç bir dəyişiklik edilmir.
- `versionCode 78`
- `versionName 3.4.55-native-v78`
- Release APK: `MarakanaMobile-v78-release.apk`


## v76 — Satılan hesabı Satılmayan kimi kopyala

- **Satılanlar** bölməsində hesabın əməliyyat menyusuna **Kopyala** düyməsi əlavə edildi.
- **Kopyala** basıldıqda həmin hesabın oyun/bundle, e-mail, hesab növü, konsol və qiymət məlumatları ilə yeni nüsxəsi yaradılır.
- Yeni nüsxə avtomatik **Satılmayıb** statusunda olur.
- Müştəri adı, telefon və satış tarixi yeni nüsxədə boş qalır; əvvəlki alıcının məlumatları kopyalanmır.
- Bu funksiya eyni oyun hesabını ikinci müştəriyə satmaq lazım olduqda istifadə oluna bilər.
- Kopyalamadan sonra tətbiq yeni yaradılan hesabı görmək üçün **Satılmayanlar** bölməsinə keçir.
- `versionCode 76`
- `versionName 3.4.53-native-v76`
- Release APK: `MarakanaMobile-v76-release.apk`

## v75 — Satış tarixi təqvim seçimi

- Satılmayanlar → **Sat** ekranında **Satış tarixi** sahəsinə klikləyəndə Android təqvimi açılır.
- İstənilən tarix təqvimdən seçilə bilir və sahəyə `DD-MM-YYYY` formatında yazılır.
- Təqvimdə ayrıca **Bu gün** düyməsi var; klikləyəndə cihazın bugünkü tarixi seçilir.
- **Hesabı düzəlt** ekranındakı Satış tarixi sahəsi də eyni təqvim seçimini istifadə edir.
- Tarix sahəsi klaviatura ilə əl ilə dəyişdirilmir; yalnız təqvimdən seçilir.
- `versionCode 75`
- `versionName 3.4.52-native-v75`
- Release APK: `MarakanaMobile-v75-release.apk`

## v74 — Sat / İcarə məlumatları yalnız baxış + Satılmayıb sıfırlama

- Satılmayanlar bölməsində `Sat` və `İcarə ver` seçiləndə oyun/hesab məlumatları ayrıca baxış kartında göstərilir və dəyişdirilə bilmir.
- Baxış kartında oyun/bundle, e-mail, növ, konsol, qiymət və seçilmiş status görünür.
- `Sat` axınında yalnız müştəri adı, telefon və satış tarixi daxil edilir.
- `İcarə ver` axınında yalnız müştəri adı, telefon, icarə müddəti və vahidi daxil edilir.
- Satılmış hesab `Düzənlə` içində `Satılmayıb` edilərək yadda saxlananda müştəri adı, telefon və satış tarixi boşaldılır.
- Bu halda hesab avtomatik `Satılmayanlar` bölməsinə qayıdır və təmiz stok kimi görünür.
- `versionCode 74`
- `versionName 3.4.51-native-v74`
- Release APK: `MarakanaMobile-v74-release.apk`

## v73 — Satılmayan hesabda Sat / İcarə ver sürətli əməliyyatları
- Satılmayanlar bölməsində hesaba bir dəfə klik edəndə menyunun yuxarısında `Sat` və `İcarə ver` əməliyyatları görünür.
- `Sat` seçiləndə forma avtomatik Satılıb statusu ilə açılır və satış tarixi boşdursa bugünkü tarix qoyulur.
- `İcarə ver` seçiləndə forma avtomatik İcarə statusu ilə açılır.
- Satılmayan hesabda Ətraflı məlumat və Məlumatı göndər gizli qalır.
- `versionCode 73`
- `versionName 3.4.50-native-v73`
- Release APK: `MarakanaMobile-v73-release.apk`

## v72 — Hesab kartında tək klik + modern əməliyyat menyusu
- Hesablar / Satılanlar / Satılmayanlar / İcarə bölmələrində hesab kartına artıq 1 saniyə basılı saxlamaq lazım deyil; bir dəfə klik menyunu açır.
- Açılan menyu ikonlu, kart tipli və müasir görünüşə keçirildi.
- Satılmayıb statusunda əvvəlki qayda saxlanır: Ətraflı məlumat və Məlumatı göndər göstərilmir.
- `versionCode 72`
- `versionName 3.4.49-native-v72`
- Release APK: `MarakanaMobile-v72-release.apk`


## v71 — Hesab Satışı İcarə statusu
- Hesab statusları: Satılıb / Satılmayıb / İcarə.
- İcarə seçiləndə müddət Saat və ya Gün ilə qeyd olunur.
- Alt paneldə ayrıca ⏳ İcarə bölməsi var.
- İcarə kartlarında qalan müddət, bitməyə yaxın xəbərdarlıq və müddəti bitən status görünür.
- `versionCode 71`
- `versionName 3.4.48-native-v71`
- Release APK: `MarakanaMobile-v71-release.apk`

## v70 — Oyun siyahısı serverdən həmişə təzələnir
- Oyunun adı picker-i hər açılışda WordPress `/overview?section=settings` API-sindən təzə `game_names` siyahısını alır.
- Serverdə baza/oyun kataloqu təmizlənibsə, əvvəldən RAM-da qalmış oyun adları artıq görünmür.
- Yeni hesab/Düzənlə formu açılarkən oyun və müştəri seçimləri də serverdən yenilənir; boş server cavabı lokal siyahını da boşaldır.
- WordPress companion v1.0.78-də “Bazanı tam təmizlə” oyun ID cədvəlini və köhnə kataloq option-unu da silir.
- `versionCode 70`
- `versionName 3.4.47-native-v70`
- Release APK: `MarakanaMobile-v70-release.apk`

## v69 — Oyunlar ID əlaqəli bazaya keçirildi
- Oyunlar WordPress-də ayrıca numeric `game_id` ilə saxlanır.
- Bundle hesablar hesab↔oyun many-to-many əlaqə cədvəli ilə işləyir.
- Mobil create/edit payload-ları `game_ids` göndərir; köhnə `game_name` geriyə uyğun cache kimi qalır.
- Mövcud köhnə oyun adları v1.0.75+ server migration-u ilə ID əlaqələrinə çevrilir.
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
