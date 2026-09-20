# Marakana Mobile v119 — Ləğv edilmiş mətbəx sifarişini lokal təmizlə

- `versionCode 119`
- `versionName 3.4.84-native-v119`
- Mətbəx rejimində bütün məhsulları çıxarılmış/azaldılıb sıfırlanmış sifariş artıq `Hazırdır` kimi işarələnmir.
- Belə tam ləğv edilmiş sifariş kartında yalnız `Təmizlə` düyməsi görünür.
- `Təmizlə` yalnız həmin mobil cihazdakı siyahıdan kartı gizlədir; PC/serverə `ready`, `preparing` və ya başqa cavab/API əməliyyatı göndərmir.
- Qismən azaldılmış, amma aktiv məhsulu qalan sifarişlərdə `Hazırdır` axını əvvəlki kimi qalır.
- Təmizlənmiş ticket ID-ləri server ünvanına görə mobil cihazda lokal saxlanılır; eyni ticket yenidən aktiv məhsul alarsa avtomatik yenidən görünür.

IP ilə açılan web Mətbəx paneli üçün uyğun PC baza: v1365.
