# Marakana Mobile v140 — Target Branch Messages

- `versionCode 140`
- `versionName 3.5.02-native-v140`

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


## v137 — Local PC-ni avtomatik tap
- Local IP bağlantısı ekranına `PC-ni avtomatik tap` düyməsi əlavə edildi.
- Telefonun aktiv lokal IPv4 subneti təhlükəsiz şəkildə skan edilir.
- Əvvəl default `8765`, nəticə yoxdursa PC-nin fallback aralığı `8766-8784` yoxlanılır.
- Yalnız `/api/mobile/ping` cavabında Marakana `ok=true` verən server qəbul olunur.
- Tapılan `server_url` normalize edilir, son ping ilə yenidən təsdiqlənir və yalnız bundan sonra Local bağlantı aktivləşdirilir.
- Axtarış zamanı IP yazmaq lazım deyil; nəticə tapılarsa avtomatik qoşulur.
- Routerdə AP/Client Isolation varsa discovery bunu keçə bilməz; telefonun PC-yə lokal trafik göndərə bilməsi şərtdir.


## v138 — Eyni LAN-da bir neçə PC seçimi

- `PC-ni avtomatik tap` artıq ilk tapılan PC-yə avtomatik qoşulmur.
- Eyni lokal şəbəkədə tapılan bütün Marakana PC-lər siyahıda göstərilir.
- Hər PC `IP:port` ilə ayrılır; operator istədiyi sətri seçib `Qoşul` basır.
- Yalnız `/api/mobile/ping` cavabı Marakana serveri kimi təsdiqlənən hostlar siyahıya düşür.
- 8765 və PC fallback portları 8766-8784 birlikdə nəzərə alınır.
- Seçilmiş PC qoşulmadan əvvəl ayrıca son ping ilə yenidən təsdiqlənir.
- Tək PC tapılsa da eyni seçim pəncərəsi göstərilir; manual IP və QR yolları saxlanılıb.
- AP/Client Isolation varsa discovery şəbəkə blokunu keçə bilməz.


## v139 — Local PC seçimi + update təhlükəsizliyi
- `PC-ni avtomatik tap` bir PC tapsa belə birbaşa qoşulmur; seçim pəncərəsində PC seçili görünür və yalnız `Qoşul` basıldıqdan sonra bağlantı aktivləşir.
- Birdən çox PC tapılarsa heç biri əvvəlcədən seçilmir; operator istədiyi PC-ni seçib `Qoşul` basır.
- `applicationId` dəyişməyib (`az.marakana.mobile`), `versionCode=139`.
- Release APK daimi signing məlumatları olmadan build edilmir. Bu, səhv/unsigned APK-nın update kimi quraşdırılmağa çalışılmasının qarşısını alır.
- GitHub Actions artifact/APK adı v139-a düzəldilib və build sonrası package + versionCode ayrıca yoxlanır.


## v140 — Mesajı filial üzrə göndərmə
- Master Mesaj qutusunda `Yeni mesaj göndər` formunda `Hədəf filial` seçimi var.
- `Bütün filiallar` və ya konkret filial seçilə bilər.
- Konkret filial hədəfi yalnız server `target_branch_messages` capability qaytaranda aktiv olur.
- Mesaj kartlarında Master görünüşündə `Hədəf` göstərilir və `Gözləyən` yalnız real hədəfə görə hesablanır.
- Filial Mesajları plugin v1.0.3 artıq bu payload-u dəstəkləyir; yeni plugin dəyişikliyi tələb olunmur.
