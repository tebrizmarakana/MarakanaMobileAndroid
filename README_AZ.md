# Marakana Mobile v132 — Local IP + Online filiallar

## v132 dəyişiklikləri
- Mövcud **Local IP** sistemi saxlanılıb; əvvəlki QR/manual server bağlantısı dəyişdirilməyib.
- Yeni **Online filiallar** rejimi əlavə edilib. Online rejim `https://marakana.az` üzərindəki Marakana Remote Gateway ilə işləyir.
- Bağlantı ayarında `Local IP` və `Online filiallar` seçimi var.
- Online rejimdə Master Token Android Keystore AES/GCM ilə cihazda şifrəli saxlanılır.
- Master Token ilə filial siyahısı yüklənir, filialın `Online / Offline` statusu görünür və filial seçilir.
- Seçilmiş filialın mövcud `/api/mobile/*` funksiyaları Remote Gateway vasitəsilə həmin filial PC-sinin lokal mobil serverinə relay olunur.
- Mobil istifadəçi giriş şifrələri, rolları və session token məntiqi əvvəlki PC mobil API-si ilə eyni qalır.
- Mətbəx background servisi də Local/Online bağlantı rejimini dəstəkləyir.
- Local və Online parametrləri ayrı saxlanır; Online rejim Local IP ünvanını silmir.
- Routerdə port açmaq və statik public IP tələb olunmur; filial PC-si serverə outbound HTTPS sorğuları edir.
- `versionCode 132`, `versionName 3.4.94-native-v132`.

## Online rejim üçün tələb olunanlar
1. `Marakana Remote Gateway v1.1.0` WordPress pluginini `marakana.az` saytında aktiv edin.
2. Hər filial PC proqramını `Marakana v1555` və ya uyğun yeni versiyaya yeniləyin.
3. Filial PC-də mövcud `Filial / Master Token` bağlantısı aktiv olmalıdır.
4. Mobil tətbiqdə `Bağlantı ayarı -> Online filiallar` bölməsindən Master Token yazıb filialı seçin.

## Təhlükəsizlik
- Remote Gateway yalnız `/api/mobile/*` yollarını relay edir.
- PC gələn yolu yenidən yoxlayır və sorğunu yalnız `127.0.0.1` üzərindəki öz lokal mobil serverinə göndərir.
- Arbitrary URL/host relay edilmir.
- WordPress gateway Master Token ilə mobil sorğunu, Filial/Master Token ilə PC polling/result sorğularını təsdiqləyir.
- Local IP rejimi internet relay-dən asılı deyil.

---

Marakana Mobile v128

## v128 — Borc Dəftəri / hissə-hissə əmək haqqı
- İşçi kartında Qalıq əmək haqqı görünür.
- İşçi əməliyyatlarında Əmək haqqı ver düyməsi var.
- Borc varsa əvvəl maaşdan avtomatik silinir.
- Götürüləcək əmək haqqı ayrıca yazılır; götürülməyən hissə Qalıq əmək haqqı kimi qalır.
- Mobile server payload expected_salary, expected_debt və employee_amount göndərir.
- `versionCode 128`, `versionName 3.4.92-native-v128`.

# Marakana Mobile v122 — Hesab Satışı növləri / Online e-mail guard

- `versionCode 122`
- `versionName 3.4.85-native-v122`

## Dəyişikliklər
- `Yeni hesab yarat` formasından Konsol seçimi çıxarıldı. Yeni hesab stokda `Satılmayıb` kimi konsolsuz yaradılır.
- `Sat` və `İcarə ver` axınında Konsol seçimi məcburidir: `PS4`, `PS5`, `PS4/PS5`.
- Yeni hesab növləri: `Online`, `Universal`, `Universal PS4`. Əvvəlki `Offline` adı `Universal PS4` ilə əvəz olundu.
- Hər hesab növünün ayrıca Məxfi kod axını saxlanılıb.
- v120 Mətbəx qaydası saxlanılıb: `Hazırdır` yalnız aktiv məhsulları PC-yə göndərir; tam ləğvdə yalnız lokal `Təmizlə` qalır.

## Server uyğunluğu
- Hesab Satışı companion WordPress pluginini `v1.0.89` versiyasına yenilə. Bu versiya Satılmayıb hesabın konsolsuz yaradılmasını və `Universal PS4` hesab növünü dəstəkləyir.
## v124
- Hesab Satışı > Sat: Universal PS4 -> PS4 avtomatik və fiks; Universal PS5 -> PS5 avtomatik və fiks. Online hesabda konsol seçimi manual qalır.
- Konsol seçimlərindən PS4/PS5 kombinə variantı çıxarıldı; yalnız PS4 və PS5 qaldı.


- v125: Eyni e-mail üzrə Online hesab varsa Universal PS4/PS5 yeni hesabları Online hesabın eyni oyun/Bundle siyahısına kilidlənir; fərqli oyun seçmək olmur.
