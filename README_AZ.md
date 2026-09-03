# Marakana Mobile Native v32

## v32 — Mətbəx fon bildiriş xidməti

- Mətbəx rejimində uğurlu girişdən sonra ayrıca fon xidməti aktivləşir.
- APK ekrandan bağlansa/silinsə də fon xidməti PC serverini yoxlamağa davam edir.
- Yeni sifariş gələndə heads-up bildiriş, seçilmiş bildiriş səsi və sifariş detalları görünür.
- Bildirişə toxunanda birbaşa Mətbəx rejiminə qayıtmağa çalışır.
- Telefon restart olduqda və ya APK update edildikdə Mətbəx rejimi aktiv qalıbsa xidmət yenidən başlayır.
- Başqa mobil rola keçəndə və ya Çıxış ediləndə fon xidməti və mətbəx fon giriş məlumatı söndürülür.
- Şifrə Android Keystore AES/GCM ilə ayrıca şifrəli saxlanılır.
- Ekran açıq olanda əvvəlki 0.75 saniyəlik canlı siyahı yenilənməsi qalır; fon bildiriş xidməti yeni sifariş bildirişini ayrıca idarə edir və dublikat bildiriş vermir.
- Bildiriş səsi menyunun aşağısında, Çıxış düyməsinin üstündə qalır və seçilmiş səs adı görünür.

### Vacib Android davranışı
Fon xidməti işləyərkən Android aşağı prioritetli `Marakana Mətbəx — Fon bildirişləri aktivdir` daimi servis bildirişi göstərə bilər. Bu, tətbiq ekrandan bağlananda da sifarişləri dərhal yoxlamaq üçün lazımdır. İstifadəçi Android Ayarlarından tətbiqə `Force stop / Məcburi dayandır` etsə, Android yenidən tətbiq açılana qədər fon işini bloklaya bilər.

### Şəbəkə
Telefon PC server ünvanına çıxış əldə etməlidir. Server lokal IP-dirsə telefon eyni LAN/Wi‑Fi şəbəkəsində olmalıdır.

### Versiya
- `versionCode 32`
- `versionName 3.4.9-native-v32`
- Release artifact: `MarakanaMobile-v32-permanent-update-apk`
