@echo off
setlocal
cd /d "%~dp0"

echo ========================================
echo   Marakana Mobile - Android APK Build
echo ========================================
echo.

where gradle >nul 2>nul
if %errorlevel%==0 (
    echo Gradle tapildi. Debug APK build edilir...
    gradle --no-daemon :app:assembleDebug
    if %errorlevel%==0 (
        echo.
        echo APK hazirdir:
        echo app\build\outputs\apk\debug\app-debug.apk
        exit /b 0
    )
)

echo Gradle command tapilmadi ve ya build alinmadi.
echo Layiheni Android Studio ile acin ve:
echo Build ^> Build APK(s)
echo secin.
echo.
pause
exit /b 1
