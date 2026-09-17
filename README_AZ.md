# Marakana Mobile v110 — Sol panel təsadüfi açılma düzəlişi

## Versiya
- `versionCode 110`
- `versionName 3.4.81-native-v110`

## Dəyişikliklər
- Aşağı-yuxarı scroll zamanı barmağın kiçik sağa/sola yayınması artıq sol paneli açmır.
- Drawer swipe yalnız sağa doğru aydın üfüqi jestdə başlayır: minimum başlanğıc məsafəsi artırılıb və üfüqi hərəkət şaquli hərəkətdən təxminən 1.75 dəfə güclü olmalıdır.
- Şaquli scroll üstünlük qazandıqda həmin toxunuş bitənədək drawer swipe kilidlənir.
- İcarə, Borc Dəftəri və Mətbəx bölmələrinin kateqoriya swipe jestləri də diaqonal scroll-a qarşı sərtləşdirilib.
- Menyu düyməsi ilə sol panelin açılması və qəsdən sağa swipe davranışı saxlanılıb.
- v109-dakı hesab kartı görünüşü və əvvəlki bütün funksiyalar saxlanılıb.

---

# Marakana Mobile v109 — Hesab kartı səliqə düzəlişləri

Bu versiya v108 bazasından davam edir.

- `versionCode 109`
- `versionName 3.4.81-native-v109`
- Hesablar / Satılanlar / Satılmayanlar / İcarə kartlarında sağ meta sütunu sağ kənardan təxminən bir hərflik içəri çəkildi; `Satılmayıb` sözü tam görünür və qalın qalır.
- `Satılıb` statusunda satış tarixi statusun dərhal altına keçirildi və qalın şriftlə göstərilir.
- Satılmış hesabda Məxfi kod varsa tarix sətrinin altında qalın göstərilir.
- Sol tərəfdə oyun adı ilə e-mail arasındakı artıq boşluq aradan qaldırıldı.
- E-mail sətri qalın şriftlə göstərilir; ad-soyad və telefon sətri də qalın qalır.
- v108-dəki ayrıca Məxfi kod məntiqi və bütün əvvəlki funksiyalar saxlanılıb.

---

# Marakana Mobile v108 — Hesab kartı göstəriciləri

Bu versiya v107 bazasından davam edir.

- `versionCode 108`
- `versionName 3.4.81-native-v108`
- Hesablar / Satılanlar / Satılmayanlar / İcarə kartlarında sağ yuxarı hissə eyni formata salındı:
  - qiymət
  - hesab növü • konsol
  - status
- Müştəri ad-soyadı və telefon sətri qalın şriftlə göstərilir.
- `secret_code` boş deyilsə tarix sətrinin altında `Məxfi kod: ...` qalın şriftlə göstərilir.
- v107 build fix və əvvəlki Məxfi kod məntiqi saxlanılıb.

---

# Marakana Mobile v107 — Build fix (Məxfi kod lambda)

- GitHub Actions-da `MainActivity.java:4711 local variables referenced from a lambda expression must be final or effectively final` xətası düzəldildi.
- `secretCode` üçün lambda daxilində istifadə olunan `final` istinad yaradıldı.
- Funksional davranış dəyişməyib: hər hesabın Məxfi kodu ayrıca qalır.
- `versionCode 107`
- `versionName 3.4.81-native-v107`

---

# Marakana Mobile v106 — Hər hesab üçün ayrıca Məxfi kod

- Yeni hesab yaradarkən Online / Universal / Offline növləri üçün Məxfi kod ayrı-ayrı yazılır.
- Hər kod istəyə bağlıdır və boş saxlanıla bilər.
- Eyni e-maildə olan hesabların Məxfi kodu bir-birinə bağlanmır; hər record ID öz kodunu saxlayır.
- Kopyalanmış hesab yeni ID ilə yaradılır və Məxfi kodu boş başlayır; Sat və ya Düzənlə ekranından həmin nüsxəyə ayrıca kod təyin edilə bilər.
- Bir hesabın Məxfi kodunu Düzənlə ilə dəyişmək eyni e-maildəki başqa Online / Universal / Offline və ya kopyalanmış hesabların koduna toxunmur.
- Axtarış v105-dəki kimi hər hesabın öz Məxfi koduna görə nəticə tapır.
- Companion WordPress plugin: v1.0.88.
- `versionCode 106`
- `versionName 3.4.81-native-v106`
- Release APK: `MarakanaMobile-v106-release.apk`

---

# MarakanaMobile v100 — ChatGPT tipli barmaqla izləyən sol panel

- Hesab Satışı daxil olmaqla əsas ekranlarda barmağı ekranın ortasından sağa çəkəndə sol menyu artıq birbaşa açılmır; **barmağın hərəkətini canlı izləyərək** sürüşür.
- Panelin açılma məsafəsi barmağın çəkdiyi məsafəyə uyğundur, sürətli swipe ediləndə isə buraxan kimi tamam açılır.
- Yarımçıq çəkib buraxanda məsafə/sürətə görə panel açılır və ya geri bağlanır.
- Menyu düyməsinə klik əvvəlki kimi işləyir, sadəcə artıq yumşaq sürüşmə animasiyası ilə açılır.
- `versionCode 100`
- `versionName 3.4.77-native-v100`

---

# MarakanaMobile v99 — Alt panel Satılan/Satılmayan check ikonları

- Alt paneldə **Satılanlar** ikonu qırmızı `✓` oldu.
- Alt paneldə **Satılmayanlar** ikonu yaşıl `✓` oldu.
- Digər alt panel ikonlarına toxunulmadı.
- `versionCode 99`
- `versionName 3.4.76-native-v99`

---

# MarakanaMobile v98 — Yadda saxlayandan sonra eyni menyuda qal

- Hesab hansı bölmədə əməliyyat olunursa, yadda saxlanandan sonra artıq başqa bölməyə avtomatik keçmir.
- `Satılmayanlar → Sat` etdikdə satış yadda saxlanır və ekran yenə `Satılmayanlar` bölməsində qalır.
- `Satılmayanlar → İcarə ver` etdikdə də eyni bölmədə qalır.
- `Hesablar`, `Satılanlar`, `Satılmayanlar`, `İcarə` bölmələrindən `Düzənlə` ediləndə save sonrası həmin bölməyə qayıdır.
- `Təhvil aldım`, `Kopyala`, `Sil`, `Yeni hesab yarat` və `Yeni müştəri yarat` əməliyyatlarında da başladığın menyu qorunur.
- `versionCode 98`
- `versionName 3.4.75-native-v98`

---

# MarakanaMobile v97 — Boş qiymət + təkrar müştəri nömrəsi blok

- `Yeni hesab yarat` formasında **Qiymət** xanası artıq tam boş açılır; `35.50` nümunəsi də göstərilmir.
- Yeni müştəri yaradılarkən eyni telefon nömrəsi artıq varsa ikinci dəfə qeydiyyata icazə verilmir.
- Bu yoxlama həm əsas `Yeni müştəri yarat`, həm də Sat / İcarə içindəki sürətli müştəri yarat pəncərəsində işləyir.
- Müştəri düzəlişində öz mövcud nömrəsini saxlamaq olar, amma başqa müştərinin nömrəsinə dəyişmək bloklanır.
- `versionCode 97`
- `versionName 3.4.74-native-v97`

---

# MarakanaMobile v96 — 30 günlük Zibil qutusu

- `Yenilə` düyməsinin sağında qırmızı **🗑 Zibil qutusu** düyməsi əlavə edildi.
- Hesab silinəndə artıq birbaşa bazadan yox olmur; 30 günlük zibil qutusuna köçürülür.
- Zibil qutusunda hesabın üzərinə klik edib **Geri yüklə** və ya **Birdəfəlik sil** etmək olur.
- Silinmə tarixi və neçə gün qaldığı göstərilir.
- 30 gün tamam olduqda server köhnə zibil qeydlərini avtomatik təmizləyir.
- `versionCode 96`
- `versionName 3.4.73-native-v96`

---

# MarakanaMobile v95 — Sat / İcarə zamanı sürətli müştəri yarat

- `Hesabı sat` və `İcarə ver` axınında `Ad soyad` seçim pəncərəsi eyni qayda ilə işləyir.
- Axtarışda uyğun müştəri tapılmadıqda **`Yeni müştəri yarat`** düyməsi görünür.
- Düyməyə klik edəndə müştərinin ad soyadı və telefon nömrəsi daxil edilərək yaradılır.
- Yeni müştəri yaradılan kimi həmin satış/icarə formasında avtomatik seçilir.
- Telefon əvvəlki qayda ilə avtomatik `+994...` formatına çevrilir.
- `versionCode 95`
- `versionName 3.4.72-native-v95`

---

# MarakanaMobile v94 — Son əməliyyat və yeni müştəri sıralaması

- Hesablar, Satılanlar, Satılmayanlar və İcarə bölmələrində **ən son əməliyyat edilən / son dəyişən hesab həmişə yuxarıda** göstərilir.
- Sıralama əvvəlcə `updated_at`, sonra `created_at`, sonra ID ilə aparılır.
- Müştəri bölməsində **ən son yaradılan müştəri həmişə ən yuxarıda** göstərilir.
- Dəqiq müştəri yaradılma sıralaması üçün plugin v1.0.85 `customer_created_at` sahəsini Mobil API-yə əlavə edir.
- `versionCode 94`
- `versionName 3.4.71-native-v94`

---

# MarakanaMobile v93 — İcarədə hər zaman Təhvil aldım, Sil gizlidir

- `İcarə` bölməsində müddət bitib-bitməməsindən asılı olmayaraq bütün aktiv icarə hesablarında **Təhvil aldım** düyməsi görünür.
- `Təhvil aldım` hesabı dərhal `Satılmayıb` statusuna qaytarır və müştəri/icarə sahələrini təmizləyir.
- `İcarə` bölməsində **Sil** düyməsi tam gizlədildi ki, səhvən hesab bazadan silinməsin.
- Digər bölmələrdə Sil davranışı dəyişməyib.
- `versionCode 93`
- `versionName 3.4.70-native-v93`

---

# MarakanaMobile v92 — Sat/İcarə qiyməti + Təhvil aldım

- `Satılmayanlar → Sat` və `Satılmayanlar → İcarə ver` axınlarında qiymət artıq ayrıca redaktə edilə bilir.
- Satış zamanı `Satış qiyməti`, icarə zamanı `İcarə qiyməti` sahəsi cari qiymətlə açılır və əməliyyatdan əvvəl dəyişdirilə bilir.
- İcarə müddəti bitmiş hesabın əməliyyat menyusunda **`Təhvil aldım`** düyməsi görünür.
- `Təhvil aldım` təsdiqlənəndə icarə bağlanır, müştəri/telefon/icarə müddəti təmizlənir və hesab avtomatik **Satılmayıb** statusuna keçir.
- Təhvil alınan hesab birbaşa `Satılmayanlar` bölməsində açılır.
- `versionCode 92`
- `versionName 3.4.69-native-v92`

---

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
