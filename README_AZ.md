# Marakana Mobile Native Android v2

Bu layihə əvvəlki WebView versiyası deyil.

## Əsas fərq
- `/mobile` səhifəsini açmır.
- Android `WebView` istifadə etmir.
- Bütün ekranlar native Android UI-dır.
- PC-dəki Marakana proqramına yalnız JSON API ilə qoşulur.
- Lokal IP/domen yalnız ilk dəfə **Server bağlantısı** ekranında yazılır və yadda saxlanılır.
- Sonradan tətbiq normal Android tətbiqi kimi Login / Terminallar / Borc Dəftəri / Mətbəx ekranlarını göstərir.

## Native funksiyalar
- Server ping və ayarının yadda saxlanması
- Zal / Mətbəx / Admin login
- Terminal və masa siyahısı
- Terminal detalı və cari sifarişlər
- Vaxt açma (60/120/180 dəq və vaxtsız, pult sayı)
- Məhsul axtarışı, miqdar seçimi və toplu sifariş
- Sifarişdən 1 ədəd azaltma
- Admin Borc Dəftəri: İşçi / Müştəri / Firma
- Borclu axtarışı, toplam borc
- Yeni borclu, Artır, Azalt, Tarixçə
- Native Mətbəx sifarişləri və Hazırlanır/Hazırdır statusları

## Build
GitHub Actions daxil edilib. Repository-yə push etdikdən sonra:
Actions -> Build Marakana Mobile Native APK -> Run workflow

Artifact: `MarakanaMobile-Native-debug-apk` -> `app-debug.apk`

## Server ünvanı
Nümunə:
`192.168.1.20:8765`

və ya HTTPS domeniniz varsa:
`https://app.example.com`

Tətbiq avtomatik `/mobile` əlavə etmir; API çağırışlarını `/api/mobile/...` ünvanına edir.

## v4 düzəlişləri
- Giriş ekranına ayrıca **Borc Dəftəri** rejimi əlavə edildi. Bu seçim serverə `admin` rolu ilə daxil olur və birbaşa native Borc Dəftərini açır.
- Adi **Admin** girişində də yuxarı menyuda **Borc Dəftəri** görünür.
- **Şifrəni yadda saxla və avtomatik daxil ol** seçimi əlavə edildi. Şifrə Android Keystore AES/GCM ilə şifrələnərək cihazda saxlanılır.
- Tətbiq bağlanıb yenidən açılanda yadda saxlama aktivdirsə avtomatik giriş edir.
- İstifadəçi **Çıxış** düyməsinə özü basanda avtomatik giriş söndürülür.

## v5 dəyişiklikləri
- Borc Dəftərində İşçi / Müştəri / Firma kateqoriyaları WhatsApp tipli sabit aşağı footer-a köçürüldü və tam eni 3 bərabər hissəyə bölür.
- Borc Dəftərinin yuxarısındakı ayrıca mavi “Borc Dəftəri” keçid düyməsi ləğv edildi.
- Əsas ekranların sol yuxarısına ☰ menyu əlavə edildi. Admin girişində menyuda “Terminallar / Zal”, “Borc Dəftəri”, “Mətbəx” görünür; digər rollarda yalnız icazəli əsas bölmə görünür.
- “Çıxış” menyunun ən aşağı hissəsinə köçürüldü.
- Android versiyası `versionCode 5`, `versionName 2.2.0-native-v5` oldu. Növbəti buraxılışlarda `versionCode` mütləq artırılmalıdır.

### APK update haqqında vacib qeyd
Android köhnə APK-nın üstünə yeni APK quraşdırmaq üçün həm `applicationId` eyni, həm `versionCode` daha böyük, həm də imza sertifikatı eyni olmalıdır. Bu layihədə `applicationId` dəyişməyib (`az.marakana.mobile`) və v5-də versionCode artırılıb. GitHub-da hər build üçün eyni signing key saxlanmalıdır; əks halda Android köhnə tətbiqi silməyi tələb edə bilər. v5 workflow-u `~/.android/debug.keystore` faylını `actions/cache` ilə sabit saxlayır və ilk v5 build-də bir dəfə signing key yaradır. Buna görə v5-dən sonrakı build-lər eyni cache/signing key ilə update kimi quraşdırıla bilər. Əvvəlki v4 APK başqa GitHub runner debug açarı ilə imzalanıbsa, v5-ə keçiddə bir dəfə silib yenidən quraşdırmaq lazım gələ bilər; bundan sonra versionCode artırılmaqla update axını davam edir.


## v6 dəyişiklikləri
- Borc Dəftəri ekranı daha tam enli edildi və müxtəlif cihazlarda daha yaxşı uyğunlaşması üçün kənar boşluqlar azaldıldı.
- Alt kateqoriya paneli WhatsApp tərzində yeniləndi: ikonlu, tam enli, seçilən tab açıq boz rənglə vurğulanır.
- Borc kateqoriyaları arasında sağa/sola sürüşdürmə əlavə edildi.
- Borc Dəftərində soldan sürüşdürmə və menyu düyməsi ilə yan menyu açılır.
- Android geri düyməsi ilə Tarixçə və digər alt ekranlardan əvvəlki ekrana qayıtmaq dəstəyi əlavə edildi.
- Android versiyası `versionCode 6`, `versionName 2.3.0-native-v6` oldu.


## v7 dəyişiklikləri
- Başlıq və sol menyu düyməsinin yuxarı məsafəsi artırıldı ki, Android status bar (saat, batareya, bildiriş ikonları) üzərinə çıxmasın.
- Üst hissə WhatsApp-a yaxın görünüş üçün aşağı salındı.
- Android versiyası `versionCode 7`, `versionName 2.3.1-native-v7` oldu.


## v8 dəyişiklikləri
- Sol yuxarı menyu düyməsi ChatGPT stilinə yaxınlaşdırıldı: yazı əvəzinə modern 3-xətli ikon istifadə olunur.
- Menyu əsas ekranlarda ChatGPT kimi soldan sağa sürüşdürmə ilə açılır.
- Android versiyası `versionCode 8`, `versionName 2.4.0-native-v8` oldu.


## v9 dəyişiklikləri
- Mətbəx ekranına Borc Dəftəri kimi alt kateqoriya paneli əlavə edildi.
- Alt paneldə `Hazırlanır` və `Hazırdır` bölmələri ayrıca göstərilir; hazır olan sifarişlər yalnız `Hazırdır`, qalanlar `Hazırlanır` bölməsində görünür.
- Mətbəx kateqoriyaları alt paneldən toxunaraq və sürüşdürərək dəyişdirilə bilir.
- Status dəyişəndə siyahı uyğun kateqoriyaya yenidən açılır.
- Android versiyası `versionCode 9`, `versionName 2.5.0-native-v9` oldu.


## v10 dəyişiklikləri
- Mətbəx `Hazırlanır` kateqoriyasında artıq yalnız `Hazırdır` düyməsi görünür.
- Mətbəx `Hazırdır` kateqoriyasında artıq yalnız `Geri al` düyməsi görünür.
- `Hazırlanır` bölməsində sifariş `Hazırdır` ediləndə tətbiq avtomatik `Hazırdır` tabına keçmir; istifadəçi eyni kateqoriyada qalır.
- `Geri al` ediləndə də mövcud kateqoriya qorunur və siyahı həmin tab üzrə yenilənir.
- Android versiyası `versionCode 10`, `versionName 2.5.1-native-v10` oldu.


## v11 dəyişiklikləri
- Sol menyu artıq yalnız cari giriş roluna görə yox, həmin istifadəçinin bütün mobil panel səlahiyyətlərinə görə qurulur.
- Məsələn, Mətbəx istifadəçisi eyni zamanda Zal icazəsinə sahibdirsə, sol menyuda `Terminallar / Zal` keçidi də görünür.
- Zal, Mətbəx və Admin/Borc icazələri giriş zamanı eyni istifadəçi şifrəsi ilə arxa planda yoxlanılır; ayrıca admin şifrəsi tələb olunmur.
- Bölmələr arasında keçiddə tətbiq həmin istifadəçinin eyni şifrəsi ilə uyğun mobil rola səssiz keçid edir və yeni sessiya yaradır.
- Android versiyası `versionCode 11`, `versionName 2.6.0-native-v11` oldu.


## v12 dəyişiklikləri
- Sifariş əlavə etmə ekranında `+ / -` düymələri ləğv edildi. Məhsul kartına toxunanda 1–10 arası miqdar seçimi açılır və seçilən miqdar dərhal sifarişə əlavə edilir.
- Cari sifarişlərdə `1 ədəd azalt` əməliyyatı yalnız Admin səlahiyyəti olan istifadəçiyə görünür.
- Admin səlahiyyəti olan istifadəçi azalt düyməsinə basanda əməliyyat dərhal icra olunmur; əvvəl təsdiqləmə popup-u açılır, yalnız `Təsdiqlə` seçildikdən sonra 1 ədəd azaldılır.
- Android versiyası `versionCode 12`, `versionName 2.7.0-native-v12` oldu.


## v13 dəyişiklikləri
- Əsas ekranlarda barmaqla soldan-sağa yox, ekranın istənilən hissəsindən sağa sürüşdürmə ilə sol menyu açılır.
- Borc Dəftəri və Mətbəx kateqoriya swipe məntiqi qorunur; uzun sağ swipe menyunu açır, qısa swipe kateqoriyanı dəyişir.
- Swipe daha təbii Android drawer davranışına yaxınlaşdırıldı.
- Android versiyası `versionCode 13`, `versionName 2.8.0-native-v13` oldu.


## v14 dəyişiklikləri
- Qısa swipe də aktiv edildi; uzun swipe tələb olunmur.
- Borc Dəftərində sağ swipe əvvəlki kateqoriyaya qaytarır; artıq birinci kateqoriyadadırsa növbəti sağ swipe sol menyunu açır.
- Sola swipe növbəti Borc Dəftəri kateqoriyasına keçir.
- Eyni pilləli swipe məntiqi Mətbəx üçün də tətbiq edildi: Hazırdır -> sağ swipe -> Hazırlanır -> sağ swipe -> sol menyu.
- Digər əsas ekranlarda sol menyunu açmaq üçün swipe həddi də qısaldıldı.
- Android versiyası `versionCode 14`, `versionName 2.8.1-native-v14` oldu.
