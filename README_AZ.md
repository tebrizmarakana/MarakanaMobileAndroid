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
