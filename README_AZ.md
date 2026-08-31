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
