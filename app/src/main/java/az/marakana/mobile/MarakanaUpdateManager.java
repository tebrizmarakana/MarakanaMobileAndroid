package az.marakana.mobile;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.SharedPreferences;
import android.content.Intent;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.content.pm.Signature;
import android.graphics.Color;
import android.net.Uri;
import android.os.Build;
import android.os.Handler;
import android.os.Looper;
import android.provider.Settings;
import android.view.Gravity;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.core.content.FileProvider;

import org.json.JSONObject;

import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.io.ByteArrayOutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.security.MessageDigest;
import java.util.Arrays;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

/** v171: Dashboard açıldıqdan sonra səssiz WordPress update yoxlaması, tələb olunsa bloklayan ekran.
 * Android-in sistem quraşdırma təsdiqini ötürmək mümkün deyil və ötürülmür.
 */
final class MarakanaUpdateManager {
    private static final String MANIFEST_URL = "https://marakana.az/wp-json/marakana-mobile-updates/v1/latest";
    private static final long MAX_APK_SIZE = 300L * 1024L * 1024L;
    private final Activity host;
    private final ExecutorService worker = Executors.newSingleThreadExecutor();
    private final Handler ui = new Handler(Looper.getMainLooper());
    private AlertDialog gateDialog;
    private TextView messageView;
    private ProgressBar progress;
    private Release requiredRelease;
    private File downloadedApk;
    private boolean permissionSettingsOpened = false;
    private boolean busy = false;
    private boolean releaseEnforced = false;
    private long lastBackgroundCheckMs = -1L;
    private static final long BACKGROUND_CHECK_COOLDOWN_MS = 5L * 60L * 1000L;
    private static final String UPDATE_PREFS = "marakana_mobile_update_gate";

    MarakanaUpdateManager(Activity activity) {
        host = activity;
        // Əvvəl aşkar edilmiş məcburi yeniləmə tətbiq bağlanıb-açılanda da itmir.
        // Cari APK artıq quraşdırılıbsa köhnə məcburiyyət avtomatik silinir.
        SharedPreferences saved = host.getSharedPreferences(UPDATE_PREFS, Activity.MODE_PRIVATE);
        int code = saved.getInt("code", 0);
        if (code > currentVersion()) {
            String url = saved.getString("url", "");
            String sha = saved.getString("sha", "");
            long size = saved.getLong("size", 0L);
            try {
                validateUrl(url, true);
                if (!sha.matches("[0-9a-f]{64}") || size < 10000 || size > MAX_APK_SIZE)
                    throw new IllegalArgumentException("Yanlış keş məlumatı");
                requiredRelease = new Release(code, saved.getString("name", "v" + code), url, sha,
                        size, saved.getString("notes", ""));
                releaseEnforced = true;
            } catch (Exception ignored) {
                // Keş zədələnibsə təhlükəsiz manifest yoxlaması yenidən baş tutacaq.
                saved.edit().clear().apply();
            }
        } else if (code > 0) {
            saved.edit().clear().apply();
        }
    }

    private void rememberRequiredRelease(Release r) {
        host.getSharedPreferences(UPDATE_PREFS, Activity.MODE_PRIVATE).edit()
                .putInt("code", r.code).putString("name", r.name).putString("url", r.url)
                .putString("sha", r.sha256).putLong("size", r.size)
                .putString("notes", r.notes).apply();
    }

    // Yalnız login sonrası dashboard real olaraq render edilib splash bağlandıqda çağırılır.
    // 5 dəqiqəlik limit ana səhifəyə təkrar keçidlərin hostingə əlavə yükünü azaldır.
    void checkAfterDashboardShown() {
        if (releaseEnforced) {
            if (requiredRelease != null && (gateDialog == null || !gateDialog.isShowing()))
                showRelease(requiredRelease);
            return;
        }
        long now = android.os.SystemClock.elapsedRealtime();
        if (lastBackgroundCheckMs >= 0 && now - lastBackgroundCheckMs < BACKGROUND_CHECK_COOLDOWN_MS) return;
        if (busy || host.isFinishing()) return;
        lastBackgroundCheckMs = now;
        check(false);
    }

    void checkManually() { check(true); }

    boolean isUpdateRequired() { return releaseEnforced; }

    private void check(boolean manual) {
        if (busy || host.isFinishing()) return;
        busy = true;
        // Fon yoxlamasında nə splash, nə progress, nə də dialog görünür.
        if (manual) showGate("Marakana Mobile yeniləməsi", "Yeni versiya yoxlanılır…", true);
        worker.execute(() -> {
            try {
                Release latest = fetchLatest();
                ui.post(() -> {
                    busy = false;
                    if (host.isFinishing()) return;
                    if (latest != null && latest.code > currentVersion()) {
                        releaseEnforced = true;
                        if (requiredRelease == null || requiredRelease.code != latest.code) downloadedApk = null;
                        requiredRelease = latest;
                        rememberRequiredRelease(latest);
                        showRelease(latest);
                    } else if (releaseEnforced && requiredRelease != null) {
                        // Bir dəfə məcburi update tapılıbsa manifest silinsə belə login kilidi qalır.
                        showRelease(requiredRelease);
                        setMessage("Yeni versiya əvvəl aşkar edilib. Quraşdırmanı tamamlayın.");
                    } else {
                        if (manual) toast("Proqram artıq ən son versiyadadır.");
                        if (manual) closeGate();
                    }
                });
            } catch (Exception e) {
                ui.post(() -> {
                    busy = false;
                    if (host.isFinishing()) return;
                    // Əvvəldən məcburi yeniləmə aşkarlanıbsa şəbəkə xətası onu keçmir.
                    if (releaseEnforced && requiredRelease != null) {
                        showRelease(requiredRelease);
                        setMessage("Bağlantı xətası: " + displayError(e) + "\nYenidən cəhd edin.");
                    } else {
                        // Sayt müvəqqəti əlçatan deyilsə fon yoxlaması səssiz bitir;
                        // yeni release aşkarlanıbsa isə update gate açıq qalır.
                        if (manual) showUnavailable(displayError(e));
                    }
                });
            }
        });
    }

    private static String displayError(Exception error) {
        String msg = error.getMessage();
        return msg == null || msg.trim().isEmpty() ? "Serverə qoşulmaq olmadı" :
                msg.length() > 160 ? msg.substring(0,160) : msg;
    }

    private void showUnavailable(String reason) {
        if (gateDialog != null) { gateDialog.dismiss(); gateDialog = null; }
        AlertDialog.Builder builder = new AlertDialog.Builder(host)
                .setTitle("Yeniləmə yoxlanılmadı")
                .setMessage("WordPress yeniləmə xidməti cavab vermədi.\n" + reason)
                .setPositiveButton("Təkrar yoxla", (d, w) -> check(true))
                .setNegativeButton("Bağla", (d, w) -> { });
        AlertDialog alert = builder.create();
        alert.setCancelable(false);
        alert.show();
    }

    private static final class Release {
        final int code; final String name, url, sha256, notes;
        final long size;
        Release(int versionCode, String versionName, String apk, String checksum, long bytes, String detail) {
            code = versionCode; name = versionName; url = apk; sha256 = checksum;
            size = bytes; notes = detail;
        }
    }

    private Release fetchLatest() throws Exception {
        HttpURLConnection c = null;
        try {
            c = connectVerified(MANIFEST_URL + "?nonce=" + System.currentTimeMillis(), "application/json");
            if (c.getResponseCode() != 200) throw new Exception("Yeniləmə API HTTP " + c.getResponseCode());
            if (c.getContentLengthLong() > 32 * 1024) throw new Exception("Yeniləmə məlumatı böyükdür");
            byte[] buf = readLimited(c.getInputStream(), 32 * 1024);
            JSONObject j = new JSONObject(new String(buf, java.nio.charset.StandardCharsets.UTF_8));
            if (!j.optBoolean("ok", false)) throw new Exception("Yeniləmə API cavabı etibarsızdır");
            int code = j.optInt("version_code", 0);
            if (code <= 0) return null;
            if (code <= currentVersion()) return null;
            String url = j.optString("apk_url", "").trim();
            String sha = j.optString("sha256", "").trim().toLowerCase(Locale.ROOT);
            long size = j.optLong("size_bytes", 0L);
            if (!sha.matches("[0-9a-f]{64}") || size < 10000 || size > MAX_APK_SIZE) {
                throw new Exception("Versiya məlumatı natamamdır");
            }
            validateUrl(url, true);
            return new Release(code, j.optString("version_name","v"+code), url, sha, size,
                    j.optString("notes", ""));
        } finally { if (c != null) c.disconnect(); }
    }

    private int currentVersion() {
        try {
            PackageInfo info = host.getPackageManager().getPackageInfo(host.getPackageName(), 0);
            return Build.VERSION.SDK_INT >= 28 ? (int) info.getLongVersionCode() : info.versionCode;
        } catch (Exception ignored) { return 176; }
    }

    private void showRelease(Release r) {
        String notes = r.notes.trim().isEmpty() ? "Yeni funksiyalar və düzəlişlər" : r.notes.trim();
        if (notes.length() > 1000) notes = notes.substring(0,1000);
        showGate("Yeniləmə mövcuddur — " + r.name,
                "Yeni versiya: " + r.name + "\n" + notes +
                "\n\nProqrama daxil olmaq üçün əvvəl yeniləyin.", false);
        if (gateDialog != null) {
            gateDialog.getButton(AlertDialog.BUTTON_POSITIVE).setText(
                    downloadedApk != null && downloadedApk.isFile() ? "Quraşdır" : "Yüklə və quraşdır");
            gateDialog.getButton(AlertDialog.BUTTON_NEGATIVE).setText("Təkrar yoxla");
            gateDialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v -> {
                if (downloadedApk != null && downloadedApk.isFile()) installDownloaded();
                else startDownload(r);
            });
            gateDialog.getButton(AlertDialog.BUTTON_NEGATIVE).setOnClickListener(v -> check(false));
        }
    }

    private void showGate(String title, String text, boolean indefinite) {
        if (gateDialog != null) { gateDialog.dismiss(); gateDialog = null; }
        LinearLayout layout = new LinearLayout(host);
        layout.setPadding(dp(23),dp(18),dp(23),dp(8));
        layout.setOrientation(LinearLayout.VERTICAL);
        messageView = new TextView(host);
        messageView.setText(text);
        messageView.setTextColor(Color.rgb(31,41,53));
        messageView.setTextSize(15);
        layout.addView(messageView);
        progress = new ProgressBar(host,null,android.R.attr.progressBarStyleHorizontal);
        progress.setIndeterminate(indefinite);
        progress.setMax(100);
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,dp(18));
        p.topMargin=dp(14);
        layout.addView(progress,p);
        if (!indefinite) progress.setVisibility(android.view.View.GONE);
        AlertDialog dlg = new AlertDialog.Builder(host)
                .setTitle(title)
                .setView(layout)
                .setPositiveButton("Yüklə və quraşdır", null)
                .setNegativeButton("Təkrar yoxla", null)
                .create();
        dlg.setCancelable(false);
        dlg.setCanceledOnTouchOutside(false);
        gateDialog = dlg;
        dlg.show();
        if (indefinite) {
            dlg.getButton(AlertDialog.BUTTON_POSITIVE).setVisibility(android.view.View.GONE);
            dlg.getButton(AlertDialog.BUTTON_NEGATIVE).setVisibility(android.view.View.GONE);
        }
    }

    private int dp(int d) { return (int)(host.getResources().getDisplayMetrics().density*d+.5f); }
    private void setMessage(String message) { if (messageView != null) messageView.setText(message); }
    private void closeGate() { if (gateDialog != null) { gateDialog.dismiss(); gateDialog = null; } }
    private void toast(String s) { Toast.makeText(host, s, Toast.LENGTH_LONG).show(); }

    private void startDownload(Release r) {
        if (busy) return;
        busy = true;
        if (gateDialog != null) {
            gateDialog.getButton(AlertDialog.BUTTON_POSITIVE).setEnabled(false);
            gateDialog.getButton(AlertDialog.BUTTON_NEGATIVE).setEnabled(false);
        }
        setMessage("APK saytdan endirilir… 0%");
        if (progress != null) { progress.setVisibility(android.view.View.VISIBLE); progress.setIndeterminate(false); progress.setProgress(0); }
        worker.execute(() -> {
            try {
                File f = downloadAndVerify(r);
                ui.post(() -> {
                    busy = false;
                    downloadedApk = f;
                    setMessage("APK endirildi və təhlükəsizlik yoxlamasından keçdi.\nQuraşdırmanı telefonda təsdiqləyin.");
                    if (progress != null) progress.setProgress(100);
                    if (gateDialog != null) {
                        gateDialog.getButton(AlertDialog.BUTTON_POSITIVE).setEnabled(true);
                        gateDialog.getButton(AlertDialog.BUTTON_POSITIVE).setText("Quraşdır");
                        gateDialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v -> installDownloaded());
                        gateDialog.getButton(AlertDialog.BUTTON_NEGATIVE).setEnabled(true);
                    }
                    installDownloaded();
                });
            } catch (Exception e) {
                ui.post(() -> {
                    busy = false;
                    setMessage("Yükləmə xətası: " + displayError(e) + "\nYenidən cəhd edin.");
                    if (gateDialog != null) {
                        gateDialog.getButton(AlertDialog.BUTTON_POSITIVE).setEnabled(true);
                        gateDialog.getButton(AlertDialog.BUTTON_NEGATIVE).setEnabled(true);
                    }
                });
            }
        });
    }

    private File downloadAndVerify(Release r) throws Exception {
        File dir = new File(host.getCacheDir(), "marakana-updates");
        if (!dir.exists() && !dir.mkdirs()) throw new Exception("Yükləmə qovluğu yaradıla bilmədi");
        if (dir.getUsableSpace() < r.size + 20L*1024L*1024L) throw new Exception("Telefon yaddaşında boş yer azdır");
        File partial = new File(dir, "update-"+r.code+"-downloading.apk");
        File target = new File(dir, "update-"+r.code+".apk");
        if (partial.exists()) partial.delete();
        HttpURLConnection c = null;
        try {
            c = connectVerified(r.url, "application/vnd.android.package-archive");
            if (c.getResponseCode() != 200) throw new Exception("APK HTTP " + c.getResponseCode());
            if (c.getContentLengthLong() > MAX_APK_SIZE) throw new Exception("APK ölçüsü limitdən böyükdür");
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            long total = 0;
            long lastTick = 0;
            try (InputStream in = c.getInputStream(); FileOutputStream out = new FileOutputStream(partial)) {
                byte[] buf = new byte[64 * 1024]; int n;
                while ((n=in.read(buf))!=-1) {
                    total += n;
                    if (total > MAX_APK_SIZE || total > r.size) throw new Exception("APK ölçüsü uyğun deyil");
                    out.write(buf, 0, n);
                    digest.update(buf, 0, n);
                    long now = System.currentTimeMillis();
                    if (now-lastTick > 200) {
                        final int pct = (int)(100L * total / r.size);
                        ui.post(() -> { setMessage("APK endirilir… " + pct + "%"); if(progress!=null) progress.setProgress(pct); });
                        lastTick=now;
                    }
                }
            }
            if (total != r.size) throw new Exception("APK yarımçıq endirildi");
            if (!hex(digest.digest()).equals(r.sha256)) throw new Exception("APK SHA-256 uyğun deyil");
            verifyApkIdentity(partial, r.code);
            if (target.exists() && !target.delete()) throw new Exception("Köhnə APK silinmədi");
            if (!partial.renameTo(target)) throw new Exception("APK saxlanmadı");
            return target;
        } finally { if(c!=null)c.disconnect(); if(partial.exists())partial.delete(); }
    }

    private void verifyApkIdentity(File f, int version) throws Exception {
        PackageManager pm = host.getPackageManager();
        int flags = Build.VERSION.SDK_INT >= 28 ? PackageManager.GET_SIGNING_CERTIFICATES : PackageManager.GET_SIGNATURES;
        PackageInfo installed = pm.getPackageInfo(host.getPackageName(), flags);
        PackageInfo archive = pm.getPackageArchiveInfo(f.getAbsolutePath(), flags);
        if (archive == null || !host.getPackageName().equals(archive.packageName))
            throw new Exception("APK paket adı yanlışdır");
        long archiveCode = Build.VERSION.SDK_INT >= 28 ? archive.getLongVersionCode() : archive.versionCode;
        if (archiveCode != version || archiveCode <= currentVersion()) throw new Exception("APK versiyası uyğun deyil");
        Signature[] installedSigners, archiveSigners;
        if (Build.VERSION.SDK_INT >= 28) {
            installedSigners = installed.signingInfo == null ? null : installed.signingInfo.getApkContentsSigners();
            archiveSigners = archive.signingInfo == null ? null : archive.signingInfo.getApkContentsSigners();
        } else {
            installedSigners = installed.signatures;
            archiveSigners = archive.signatures;
        }
        if (installedSigners == null || archiveSigners == null ||
                installedSigners.length == 0 || !Arrays.equals(installedSigners, archiveSigners)) {
            throw new Exception("APK imzası cari tətbiqlə uyğun deyil");
        }
    }

    private void installDownloaded() {
        if (downloadedApk == null || !downloadedApk.isFile()) return;
        if (Build.VERSION.SDK_INT >= 26 && !host.getPackageManager().canRequestPackageInstalls()) {
            permissionSettingsOpened = true;
            setMessage("Android icazəsi lazımdır. Açılan ekranda 'Bu mənbədən icazə ver' seçimini aktiv edin.");
            Intent settings = new Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES,
                    Uri.parse("package:" + host.getPackageName()));
            try { host.startActivity(settings); }
            catch (Exception e) { permissionSettingsOpened = false; toast("Quraşdırma icazəsi ekranı açılmadı"); }
            return;
        }
        try {
            Uri uri = FileProvider.getUriForFile(host,
                    host.getPackageName() + ".mobileupdate.fileprovider", downloadedApk);
            Intent intent = new Intent(Intent.ACTION_VIEW);
            intent.setDataAndType(uri, "application/vnd.android.package-archive");
            intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            host.startActivity(intent);
        } catch (Exception e) {
            setMessage("Quraşdırma ekranı açılmadı: " + displayError(e));
        }
    }

    void destroy() { worker.shutdownNow(); }

    void onHostResume() {
        if (!permissionSettingsOpened) return;
        permissionSettingsOpened = false;
        if (Build.VERSION.SDK_INT >= 26 && host.getPackageManager().canRequestPackageInstalls()) installDownloaded();
    }

    private static byte[] readLimited(InputStream stream, int limit) throws Exception {
        try (InputStream in=stream; ByteArrayOutputStream out=new ByteArrayOutputStream()) {
            byte[] buf=new byte[4096];int n;
            while((n=in.read(buf))!=-1) {
                if(out.size()+n > limit)throw new Exception("Server cavabı çox böyükdür");
                out.write(buf,0,n);
            }
            return out.toByteArray();
        }
    }

    private static String hex(byte[] b) {
        StringBuilder out=new StringBuilder(b.length*2);
        for(byte x:b)out.append(String.format(Locale.ROOT,"%02x",x&0xff));
        return out.toString();
    }

    private static void validateUrl(String url, boolean apk) throws Exception {
        URL u=new URL(url);
        String host=u.getHost().toLowerCase(Locale.ROOT);
        if(!"https".equalsIgnoreCase(u.getProtocol()) ||
                !("marakana.az".equals(host) || "www.marakana.az".equals(host)) ||
                (u.getPort()!=-1 && u.getPort()!=443) ||
                u.getUserInfo()!=null || u.getRef()!=null) throw new Exception("HTTPS yeniləmə ünvanı təhlükəsiz deyil");
        if(apk && (!u.getPath().contains("/marakana-mobile-updates/") || !u.getPath().endsWith(".apk")))
            throw new Exception("APK ünvanı etibarsızdır");
    }

    private static HttpURLConnection connectVerified(String original, String accept) throws Exception {
        String url=original;
        for(int hop=0;hop<3;hop++) {
            validateUrl(url, !accept.contains("json"));
            HttpURLConnection c=(HttpURLConnection)new URL(url).openConnection();
            c.setInstanceFollowRedirects(false);
            c.setConnectTimeout(accept.contains("json") ? 5000 : 12000);
            c.setReadTimeout(accept.contains("json") ? 8000 : 25000);
            c.setRequestProperty("Accept", accept);
            c.setRequestProperty("User-Agent", "Mozilla/5.0 (Linux; Android 13; Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36");
            c.setRequestProperty("Cache-Control", "no-cache");
            int status=c.getResponseCode();
            if(status==301 || status==302 || status==307 || status==308) {
                String location=c.getHeaderField("Location"); c.disconnect();
                if(location==null || location.trim().isEmpty())throw new Exception("Boş yönləndirmə");
                URL redirected=new URL(new URL(url),location);
                url=redirected.toExternalForm(); continue;
            }
            return c;
        }
        throw new Exception("Çoxlu yönləndirmə");
    }
}
