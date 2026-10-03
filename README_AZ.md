# Marakana Mobile v137 — Saved Password Auto Re-login

- `versionCode 137`
- `versionName 3.4.99-native-v137`

## v137 — Yadda saxlanmış şifrə ilə avtomatik yenidən giriş

- APK köhnə versiyanın üzərinə update ediləndə yadda saxlanmış credential qorunur və tətbiq açılışda avtomatik daxil olur.
- Online ↔ Local bağlantı dəyişəndə şifrə yadda saxlanıbsa login ekranı tələb olunmur.
- QR ilə Local server dəyişməsində də eyni auto-login qaydası işləyir.
- Müvəqqəti bağlantı xətası yadda saxlanmış şifrəni silmir.
- Manual `Çıxış` auto-login-i yenə söndürür.
- Uninstall/reinstall Android app data və Keystore-u sildiyi üçün bu qaydaya daxil deyil.


## Online rejim
WordPress-də ayrıca server-authoritative plugin-i olan mobil modullar artıq PC Remote Gateway queue-suna düşmür:

- **Borc Dəftəri** → `marakana-debt/v2/mobile/*`
- **İcarə Paneli** → `marakana-rental/v2/mobile/*`
- **Hesab Satışı** → `marakana-account-sales/v1/*`
- **Mesaj qutusu** → `marakana-branch-messages/v1/messages`

Bütün direct sorğular `https://marakana.az` ünvanına HTTPS ilə gedir və APK-da saxlanılan **Master Token + seçilmiş filial ID** istifadə olunur. Online rejimdə bu 4 plugin modulunu açarkən ayrıca PC `admin` sessiyası yaratmaq üçün Gateway login-i də edilmir; buna görə modul açılışı relay gecikməsini gözləmir.

## Gateway-də qalan hissələr
PC-nin canlı lokal vəziyyətinə bağlı funksiyalar Gateway-də qalır:

- giriş / mobil sessiya və rol keçidi
- **Terminallar / Zal**
- **Mətbəx** (hazırda ayrıca WordPress data plugin-i yoxdur; canlı sifariş PC-dən gəlir)
- terminal məhsul/sifariş əməliyyatları
- Admin QR təsdiqi

Bu səbəbdən Borc / İcarə / Hesab Satışı / Mesaj qutusu açılarkən PC relay poll-u gözlənmir. Terminallar və Mətbəx isə filial PC-si Online olmalıdır.

## Local IP rejimi
Local IP rejimi əvvəlki kimi saxlanılıb. `/api/mobile/*` çağırışları birbaşa lokal PC-yə gedir; WordPress direct mapper yalnız Online rejimdə aktivdir.

## WordPress uyğunluğu
v136 üçün:

1. **Marakana Filiallar v1.0.2+** — Master Token və filial scope.
2. **Marakana Borc Dəftəri V2 v2.0.20** — yeni `/mobile/*` direct compatibility endpoint-ləri.
3. **Marakana İcarə Paneli V2 v0.17.14** — yeni `/mobile/*` direct compatibility endpoint-ləri.
4. **Marakana Hesab Satışı v1.0.92** — artıq Master Token-i qəbul edir, dəyişiklik tələb etmir.
5. **Marakana Filial Mesajları v1.0.2** — artıq Master Token-i qəbul edir, dəyişiklik tələb etmir.
6. **Marakana Remote Gateway v1.2.0** — yalnız PC-live hissələr üçün qalır.

## PC uyğunluğu
`Marakana_v1555_MOBILE_REMOTE_GATEWAY_RELAY.zip` olduğu kimi qala bilər. v135 üçün PC update tələb olunmur.

## Filial keçidi
v134-də əlavə edilmiş sol paneldəki filial keçidi saxlanılıb. Filial dəyişəndə direct WordPress sorğularının `X-Marakana-Branch-Id` header-i də avtomatik yeni filiala keçir.


## v136 — Sol panel filial siyahısı
- Filial adları əvvəlki uğurlu siyahıdan cache edilir və drawer açılan kimi dərhal göstərilir.
- `Online / Offline` statusu sonradan arxa planda yenilənir.
- Status gələnə qədər `Yoxlanılır…` görünür.
- Filial sahəsi sabit 3-sətir hündürlüyündə daxili scroll sahəsidir; filiallar sonradan gəlsə belə MENYU/Terminallar/Mətbəx/Borc Dəftəri aşağı düşmür.
- Yeni filial sayı 3-dən çox olarsa yalnız filial sahəsinin içi scroll olur.
- Local IP və v135 Plugin Direct Hybrid marşrutları dəyişməyib.
