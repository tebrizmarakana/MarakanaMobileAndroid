package az.marakana.mobile;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.graphics.drawable.ColorDrawable;
import android.net.Uri;
import android.os.Bundle;
import android.text.InputType;
import android.util.Base64;
import android.view.GestureDetector;
import android.view.Gravity;
import android.view.MotionEvent;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.CheckBox;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.PopupWindow;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.ScrollView;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import com.google.mlkit.vision.codescanner.GmsBarcodeScanner;
import com.google.mlkit.vision.codescanner.GmsBarcodeScannerOptions;
import com.google.mlkit.vision.codescanner.GmsBarcodeScanning;
import com.google.mlkit.vision.barcode.common.Barcode;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.security.KeyStore;
import javax.crypto.Cipher;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;
import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyProperties;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.LinkedHashMap;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

/**
 * Marakana Mobile Native v2.
 *
 * This app does NOT open /mobile in WebView. The Android UI is native and only
 * communicates with the existing Marakana PC mobile REST API.
 */
public class MainActivity extends Activity {
    private static final String PREFS = "marakana_native_mobile";
    private static final String KEY_SERVER = "server_base";
    private static final String KEY_USERNAME = "username";
    private static final String KEY_ROLE = "role";
    private static final String KEY_ADMIN_DEBT_ONLY = "admin_debt_only";
    private static final String KEY_AUTO_LOGIN = "auto_login";
    private static final String KEY_PASSWORD_ENC = "password_enc";
    private static final String KEY_PASSWORD_IV = "password_iv";
    private static final String KEY_WHATSAPP_PACKAGE = "whatsapp_default_package";
    private static final String KEYSTORE_ALIAS = "marakana_mobile_login_key";

    private static final int BG = Color.rgb(240, 245, 250);
    private static final int CARD = Color.WHITE;
    private static final int TEXT = Color.rgb(31, 50, 69);
    private static final int MUTED = Color.rgb(105, 127, 149);
    private static final int BLUE = Color.rgb(48, 132, 239);
    private static final int GREEN = Color.rgb(28, 133, 90);
    private static final int ORANGE = Color.rgb(181, 90, 37);
    private static final int BORDER = Color.rgb(216, 227, 238);
    private static final int MENU_SCRIM = Color.argb(105, 18, 32, 46);

    private final ExecutorService io = Executors.newCachedThreadPool();
    private SharedPreferences prefs;
    private LinearLayout root;
    private LinearLayout content;
    private ProgressBar busy;
    private EditText serverAddressInput = null;

    private String serverBase = "";
    private String sessionToken = "";
    private String username = "";
    private String role = "hall";
    private String roleLabel = "Zal";
    private boolean adminDebtOnly = false;
    private String sessionPassword = "";
    private boolean canHall = false;
    private boolean canKitchen = false;
    private boolean canAdmin = false;
    private final Map<String, LinkedHashMap<String, OrderCartItem>> orderCarts = new HashMap<>();
    private Runnable currentBackAction = null;
    private PopupWindow activeNavigationPopup = null;
    private static final String[] DEBT_CATEGORIES = {"İşçi", "Müştəri", "Firma"};
    private static final String[] KITCHEN_CATEGORIES = {"Hazırlanır", "Hazırdır"};

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        requestWindowFeature(Window.FEATURE_NO_TITLE);
        prefs = getSharedPreferences(PREFS, MODE_PRIVATE);
        serverBase = normalizeServerBase(prefs.getString(KEY_SERVER, ""));
        username = prefs.getString(KEY_USERNAME, "");
        role = prefs.getString(KEY_ROLE, "hall");
        adminDebtOnly = prefs.getBoolean(KEY_ADMIN_DEBT_ONLY, false);
        buildRoot();
        if (serverBase.isEmpty()) {
            showServerSetup();
        } else if (prefs.getBoolean(KEY_AUTO_LOGIN, false) && !username.isEmpty()) {
            String savedPassword = loadSavedPassword();
            if (!savedPassword.isEmpty()) {
                autoLogin(username, savedPassword, role, adminDebtOnly);
            } else {
                showLogin();
            }
        } else {
            showLogin();
        }
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        io.shutdownNow();
    }

    @Override
    public void onBackPressed() {
        if (activeNavigationPopup != null && activeNavigationPopup.isShowing()) {
            activeNavigationPopup.dismiss();
            return;
        }
        if (currentBackAction != null) {
            Runnable action = currentBackAction;
            currentBackAction = null;
            action.run();
            return;
        }
        super.onBackPressed();
    }

    private int dp(int v) { return Math.round(v * getResources().getDisplayMetrics().density); }

    private int getStatusBarInset() {
        int result = 0;
        int resId = getResources().getIdentifier("status_bar_height", "dimen", "android");
        if (resId > 0) result = getResources().getDimensionPixelSize(resId);
        return result;
    }

    private GradientDrawable bg(int color, int radius, int strokeColor) {
        GradientDrawable d = new GradientDrawable();
        d.setColor(color);
        d.setCornerRadius(dp(radius));
        if (strokeColor != Color.TRANSPARENT) d.setStroke(dp(1), strokeColor);
        return d;
    }

    private TextView text(String value, float sp, int color, boolean bold) {
        TextView t = new TextView(this);
        t.setText(value);
        t.setTextSize(sp);
        t.setTextColor(color);
        t.setGravity(Gravity.CENTER_VERTICAL);
        if (bold) t.setTypeface(Typeface.DEFAULT, Typeface.BOLD);
        return t;
    }

    private Button button(String label, int background, int foreground) {
        Button b = new Button(this);
        b.setText(label);
        b.setAllCaps(false);
        b.setTextSize(15);
        b.setTextColor(foreground);
        b.setTypeface(Typeface.DEFAULT, Typeface.BOLD);
        b.setMinHeight(0);
        b.setMinimumHeight(0);
        b.setPadding(dp(14), 0, dp(14), 0);
        b.setBackground(bg(background, 14, background == CARD ? BORDER : background));
        b.setLayoutParams(new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(52)));
        return b;
    }

    private EditText input(String hint) {
        EditText e = new EditText(this);
        e.setHint(hint);
        e.setSingleLine(true);
        e.setTextColor(TEXT);
        e.setHintTextColor(Color.rgb(145, 159, 174));
        e.setTextSize(15);
        e.setPadding(dp(14), 0, dp(14), 0);
        e.setBackground(bg(CARD, 14, BORDER));
        e.setLayoutParams(new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(54)));
        return e;
    }

    private LinearLayout card() {
        LinearLayout c = new LinearLayout(this);
        c.setOrientation(LinearLayout.VERTICAL);
        c.setPadding(dp(16), dp(14), dp(16), dp(14));
        c.setBackground(bg(CARD, 18, BORDER));
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT);
        lp.setMargins(0, 0, 0, dp(12));
        c.setLayoutParams(lp);
        return c;
    }

    private void buildRoot() {
        root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setBackgroundColor(BG);
        setContentView(root);

        busy = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        busy.setIndeterminate(true);
        busy.setVisibility(View.GONE);
        root.addView(busy, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(3)));

        content = new LinearLayout(this);
        content.setOrientation(LinearLayout.VERTICAL);
        root.addView(content, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f));
    }

    private void clear() { content.removeAllViews(); }

    private void setBusy(boolean value) {
        runOnUiThread(() -> busy.setVisibility(value ? View.VISIBLE : View.GONE));
    }

    private ScrollView screenWithBody(String title, boolean back, Runnable backAction) {
        currentBackAction = back ? backAction : null;
        clear();
        LinearLayout shell = new LinearLayout(this);
        shell.setOrientation(LinearLayout.VERTICAL);
        shell.setPadding(dp(14), dp(12), dp(14), dp(14));
        content.addView(shell, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        LinearLayout header = buildMainHeader(title, back, backAction);
        shell.addView(header);

        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        if (!back && !sessionToken.isEmpty()) installGlobalDrawerSwipe(scroll);
        shell.addView(scroll, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f));
        return scroll;
    }

    private void installGlobalDrawerSwipe(View target) {
        final float[] startX = {0f};
        final float[] startY = {0f};
        final int openThreshold = dp(52);
        target.setOnTouchListener((v, event) -> {
            if (event.getActionMasked() == MotionEvent.ACTION_DOWN) {
                startX[0] = event.getX();
                startY[0] = event.getY();
            } else if (event.getActionMasked() == MotionEvent.ACTION_UP) {
                float dx = event.getX() - startX[0];
                float dy = event.getY() - startY[0];
                if (dx >= openThreshold && Math.abs(dx) > Math.abs(dy) * 1.15f) {
                    showNavigationMenu();
                }
            }
            return false;
        });
    }

    private View buildChatGptMenuButton() {
        FrameLayout outer = new FrameLayout(this);
        outer.setBackground(bg(CARD, 18, BORDER));
        outer.setElevation(dp(3));
        outer.setClickable(true);
        outer.setFocusable(true);
        outer.setContentDescription("Menyu");
        outer.setLayoutParams(new LinearLayout.LayoutParams(dp(52), dp(46)));

        LinearLayout bars = new LinearLayout(this);
        bars.setOrientation(LinearLayout.VERTICAL);
        bars.setGravity(Gravity.CENTER_VERTICAL);
        int padH = dp(15);
        int padV = dp(10);
        bars.setPadding(padH, padV, padH, padV);

        int[] widths = {dp(18), dp(18), dp(12)};
        for (int i = 0; i < widths.length; i++) {
            View line = new View(this);
            GradientDrawable d = new GradientDrawable();
            d.setColor(TEXT);
            d.setCornerRadius(dp(2));
            line.setBackground(d);
            LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(widths[i], dp(3));
            if (i > 0) lp.topMargin = dp(5);
            bars.addView(line, lp);
        }

        outer.addView(bars, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT, Gravity.CENTER));
        outer.setOnClickListener(v -> showNavigationMenu());
        return outer;
    }

    private LinearLayout buildMainHeader(String title, boolean back, Runnable backAction) {
        LinearLayout header = new LinearLayout(this);
        header.setOrientation(LinearLayout.HORIZONTAL);
        header.setGravity(Gravity.CENTER_VERTICAL);
        header.setPadding(dp(4), getStatusBarInset() + dp(6), dp(4), dp(10));

        if (back) {
            Button b = button("‹", CARD, TEXT);
            b.setTextSize(26);
            b.setLayoutParams(new LinearLayout.LayoutParams(dp(52), dp(46)));
            b.setOnClickListener(v -> { if (backAction != null) backAction.run(); });
            header.addView(b);
        } else if (!sessionToken.isEmpty()) {
            header.addView(buildChatGptMenuButton());
        }

        TextView h = text(title, 21, TEXT, true);
        LinearLayout.LayoutParams hp = new LinearLayout.LayoutParams(0, dp(48), 1f);
        hp.setMargins((back || !sessionToken.isEmpty()) ? dp(10) : 0, 0, 0, 0);
        header.addView(h, hp);
        return header;
    }

    private void showNavigationMenu() {
        if (activeNavigationPopup != null && activeNavigationPopup.isShowing()) {
            return;
        }

        FrameLayout overlay = new FrameLayout(this);
        overlay.setBackgroundColor(Color.TRANSPARENT);

        View scrim = new View(this);
        scrim.setBackgroundColor(MENU_SCRIM);
        overlay.addView(scrim, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        LinearLayout panel = new LinearLayout(this);
        panel.setOrientation(LinearLayout.VERTICAL);
        panel.setPadding(dp(18), dp(22), dp(18), dp(18));
        panel.setBackgroundColor(Color.WHITE);
        panel.setElevation(dp(12));
        FrameLayout.LayoutParams panelLp = new FrameLayout.LayoutParams((int) (getResources().getDisplayMetrics().widthPixels * 0.82f), ViewGroup.LayoutParams.MATCH_PARENT, Gravity.START);
        overlay.addView(panel, panelLp);

        TextView appTitle = text("Marakana Mobile", 22, TEXT, true);
        panel.addView(appTitle, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(42)));
        TextView account = text((username.isEmpty() ? "İstifadəçi" : username) + "  •  " + roleLabel, 13, MUTED, true);
        panel.addView(account, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(34)));
        spacer(panel, 8);

        final PopupWindow[] holder = new PopupWindow[1];
        if (canHall) {
            addDrawerItem(panel, "Terminallar / Zal", () -> {
                holder[0].dismiss();
                switchMobileRole("hall", false, this::showTerminals);
            });
        }
        if (canKitchen) {
            addDrawerItem(panel, "Mətbəx", () -> {
                holder[0].dismiss();
                switchMobileRole("kitchen", false, this::showKitchen);
            });
        }
        if (canAdmin) {
            addDrawerItem(panel, "Borc Dəftəri", () -> {
                holder[0].dismiss();
                switchMobileRole("admin", true, () -> showDebt("İşçi"));
            });
        }

        View flex = new View(this);
        panel.addView(flex, new LinearLayout.LayoutParams(1, 0, 1f));

        Button exit = button("Çıxış", Color.rgb(255, 246, 246), Color.rgb(176, 54, 54));
        exit.setLayoutParams(new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(52)));
        panel.addView(exit);

        PopupWindow popup = new PopupWindow(overlay, ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT, true);
        holder[0] = popup;
        activeNavigationPopup = popup;
        popup.setBackgroundDrawable(new ColorDrawable(Color.TRANSPARENT));
        popup.setOutsideTouchable(true);
        popup.setOnDismissListener(() -> activeNavigationPopup = null);
        scrim.setOnClickListener(v -> popup.dismiss());
        exit.setOnClickListener(v -> {
            popup.dismiss();
            logout();
        });
        popup.showAtLocation(content, Gravity.START | Gravity.TOP, 0, 0);
    }

    private void addDrawerItem(LinearLayout panel, String label, Runnable action) {
        Button item = button(label, CARD, TEXT);
        item.setGravity(Gravity.START | Gravity.CENTER_VERTICAL);
        item.setLayoutParams(new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(54)));
        LinearLayout.LayoutParams lp = (LinearLayout.LayoutParams) item.getLayoutParams();
        lp.setMargins(0, 0, 0, dp(8));
        item.setLayoutParams(lp);
        item.setOnClickListener(v -> action.run());
        panel.addView(item);
    }

    private LinearLayout scrollBody(ScrollView scroll) {
        LinearLayout body = new LinearLayout(this);
        body.setOrientation(LinearLayout.VERTICAL);
        body.setPadding(0, dp(4), 0, dp(22));
        scroll.addView(body, new ScrollView.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        return body;
    }

    private String normalizeServerBase(String raw) {
        String v = raw == null ? "" : raw.trim();
        if (v.isEmpty()) return "";
        if (!v.toLowerCase(Locale.ROOT).startsWith("http://") && !v.toLowerCase(Locale.ROOT).startsWith("https://")) v = "http://" + v;
        while (v.endsWith("/")) v = v.substring(0, v.length() - 1);
        String lower = v.toLowerCase(Locale.ROOT);
        if (lower.endsWith("/mobile")) v = v.substring(0, v.length() - 7);
        else if (lower.endsWith("/api")) v = v.substring(0, v.length() - 4);
        return v;
    }

    private String extractServerBaseFromQr(String raw) {
        String value = raw == null ? "" : raw.trim();
        if (value.isEmpty()) return "";

        // QR JSON formatında olarsa tanınan sahələrdən ünvanı götür.
        try {
            JSONObject obj = new JSONObject(value);
            String[] keys = {"server", "server_url", "base", "base_url", "url", "mobile_url"};
            for (String key : keys) {
                String candidate = obj.optString(key, "").trim();
                if (!candidate.isEmpty()) {
                    value = candidate;
                    break;
                }
            }
        } catch (Exception ignored) {}

        // QR mətnində əlavə yazı varsa ilk http/https ünvanını çıxar.
        try {
            java.util.regex.Matcher matcher = java.util.regex.Pattern
                    .compile("(https?://[^\\s]+)", java.util.regex.Pattern.CASE_INSENSITIVE)
                    .matcher(value);
            if (matcher.find()) value = matcher.group(1);
        } catch (Exception ignored) {}

        // Sonda QR mətnindən qala bilən sadə durğu işarələrini təmizlə.
        value = value.replaceAll("[\\)\\]\\}>,;]+$", "");
        return normalizeServerBase(value);
    }


    private SecretKey getOrCreateLoginKey() throws Exception {
        KeyStore keyStore = KeyStore.getInstance("AndroidKeyStore");
        keyStore.load(null);
        if (keyStore.containsAlias(KEYSTORE_ALIAS)) {
            return ((KeyStore.SecretKeyEntry) keyStore.getEntry(KEYSTORE_ALIAS, null)).getSecretKey();
        }
        KeyGenerator generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore");
        KeyGenParameterSpec spec = new KeyGenParameterSpec.Builder(
                KEYSTORE_ALIAS,
                KeyProperties.PURPOSE_ENCRYPT | KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .build();
        generator.init(spec);
        return generator.generateKey();
    }

    private void savePasswordSecurely(String password) {
        try {
            if (password == null || password.isEmpty()) {
                clearSavedPassword();
                return;
            }
            Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
            cipher.init(Cipher.ENCRYPT_MODE, getOrCreateLoginKey());
            byte[] encrypted = cipher.doFinal(password.getBytes(StandardCharsets.UTF_8));
            String enc = Base64.encodeToString(encrypted, Base64.NO_WRAP);
            String iv = Base64.encodeToString(cipher.getIV(), Base64.NO_WRAP);
            prefs.edit().putString(KEY_PASSWORD_ENC, enc).putString(KEY_PASSWORD_IV, iv).apply();
        } catch (Exception ex) {
            clearSavedPassword();
        }
    }

    private String loadSavedPassword() {
        String enc = prefs.getString(KEY_PASSWORD_ENC, "");
        String iv = prefs.getString(KEY_PASSWORD_IV, "");
        if (enc.isEmpty() || iv.isEmpty()) return "";
        try {
            Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
            GCMParameterSpec spec = new GCMParameterSpec(128, Base64.decode(iv, Base64.NO_WRAP));
            cipher.init(Cipher.DECRYPT_MODE, getOrCreateLoginKey(), spec);
            byte[] raw = cipher.doFinal(Base64.decode(enc, Base64.NO_WRAP));
            return new String(raw, StandardCharsets.UTF_8);
        } catch (Exception ex) {
            clearSavedPassword();
            return "";
        }
    }

    private void clearSavedPassword() {
        prefs.edit().remove(KEY_PASSWORD_ENC).remove(KEY_PASSWORD_IV).putBoolean(KEY_AUTO_LOGIN, false).apply();
    }

    private void autoLogin(String savedUser, String savedPassword, String savedRole, boolean savedDebtOnly) {
        ScrollView sv = screenWithBody("Marakana Mobile", false, null);
        LinearLayout body = scrollBody(sv);
        body.setGravity(Gravity.CENTER);
        TextView t = text("Giriş edilir…", 20, TEXT, true);
        t.setGravity(Gravity.CENTER);
        body.addView(t, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(90)));
        performLogin(savedUser, savedPassword, savedRole, savedDebtOnly, true, false);
    }

    private void performLogin(String u, String p, String selectedRole, boolean debtOnly, boolean rememberPassword, boolean showLoginOnFailure) {
        setBusy(true);
        io.execute(() -> {
            try {
                JSONObject payload = new JSONObject();
                payload.put("username", u);
                payload.put("password", p);
                payload.put("role", selectedRole);
                JSONObject result = request(serverBase, "/api/mobile/login", "POST", payload, "");
                sessionToken = result.optString("token", "");
                username = result.optString("username", u);
                role = result.optString("role", selectedRole);
                roleLabel = result.optString("role_label", role);
                adminDebtOnly = role.equals("admin") && debtOnly;
                sessionPassword = p;
                refreshAllowedMobileRoles(username, p, role);
                SharedPreferences.Editor editor = prefs.edit()
                        .putString(KEY_USERNAME, username)
                        .putString(KEY_ROLE, role)
                        .putBoolean(KEY_ADMIN_DEBT_ONLY, adminDebtOnly)
                        .putBoolean(KEY_AUTO_LOGIN, rememberPassword);
                editor.apply();
                if (rememberPassword) savePasswordSecurely(p); else clearSavedPassword();
                runOnUiThread(() -> {
                    if (role.equals("kitchen")) showKitchen();
                    else if (role.equals("admin") && adminDebtOnly) showDebt("İşçi");
                    else showTerminals();
                });
            } catch (Exception ex) {
                if (!showLoginOnFailure) {
                    clearSavedPassword();
                    runOnUiThread(() -> {
                        showLogin();
                        toast("Avtomatik giriş alınmadı. Şifrəni yenidən daxil edin.");
                    });
                } else {
                    showError(ex);
                }
            } finally {
                setBusy(false);
            }
        });
    }

    private void refreshAllowedMobileRoles(String loginUser, String password, String activeRole) {
        canHall = "hall".equals(activeRole);
        canKitchen = "kitchen".equals(activeRole);
        canAdmin = "admin".equals(activeRole);
        String[] candidates = {"hall", "kitchen", "admin"};
        for (String candidate : candidates) {
            if (candidate.equals(activeRole)) continue;
            String probeToken = "";
            try {
                JSONObject probePayload = new JSONObject();
                probePayload.put("username", loginUser);
                probePayload.put("password", password);
                probePayload.put("role", candidate);
                JSONObject probeResult = request(serverBase, "/api/mobile/login", "POST", probePayload, "");
                probeToken = probeResult.optString("token", "");
                if ("hall".equals(candidate)) canHall = true;
                else if ("kitchen".equals(candidate)) canKitchen = true;
                else if ("admin".equals(candidate)) canAdmin = true;
            } catch (Exception ignored) {
                if ("hall".equals(candidate)) canHall = false;
                else if ("kitchen".equals(candidate)) canKitchen = false;
                else if ("admin".equals(candidate)) canAdmin = false;
            } finally {
                if (!probeToken.isEmpty()) {
                    try { request(serverBase, "/api/mobile/logout", "POST", new JSONObject(), probeToken); }
                    catch (Exception ignored) {}
                }
            }
        }
    }

    private void switchMobileRole(String targetRole, boolean debtOnly, Runnable openTarget) {
        String normalizedTarget = targetRole == null ? "hall" : targetRole.trim().toLowerCase(Locale.ROOT);
        if (normalizedTarget.isEmpty()) normalizedTarget = "hall";
        if (normalizedTarget.equals(role)) {
            adminDebtOnly = "admin".equals(normalizedTarget) && debtOnly;
            if (openTarget != null) openTarget.run();
            return;
        }

        String password = sessionPassword;
        if (password.isEmpty()) password = loadSavedPassword();
        if (password.isEmpty()) {
            toast("Bu bölməyə keçid üçün sessiya şifrəsi tapılmadı. Yenidən daxil olun.");
            return;
        }

        final String roleToUse = normalizedTarget;
        final String passwordToUse = password;
        setBusy(true);
        io.execute(() -> {
            try {
                JSONObject payload = new JSONObject();
                payload.put("username", username);
                payload.put("password", passwordToUse);
                payload.put("role", roleToUse);
                JSONObject result = request(serverBase, "/api/mobile/login", "POST", payload, "");
                String newToken = result.optString("token", "");
                if (newToken.isEmpty()) throw new RuntimeException("Yeni mobil sessiya yaradılmadı.");
                String oldToken = sessionToken;
                sessionToken = newToken;
                role = result.optString("role", roleToUse);
                roleLabel = result.optString("role_label", role);
                adminDebtOnly = "admin".equals(role) && debtOnly;
                prefs.edit().putString(KEY_ROLE, role).putBoolean(KEY_ADMIN_DEBT_ONLY, adminDebtOnly).apply();
                if (!oldToken.isEmpty() && !oldToken.equals(newToken)) {
                    try { request(serverBase, "/api/mobile/logout", "POST", new JSONObject(), oldToken); }
                    catch (Exception ignored) {}
                }
                runOnUiThread(() -> {
                    if (openTarget != null) openTarget.run();
                });
            } catch (Exception ex) {
                showError(ex);
            } finally {
                setBusy(false);
            }
        });
    }

    private void startQrServerScanner() {
        try {
            GmsBarcodeScannerOptions options = new GmsBarcodeScannerOptions.Builder()
                    .setBarcodeFormats(Barcode.FORMAT_QR_CODE)
                    .enableAutoZoom()
                    .build();

            GmsBarcodeScanner scanner = GmsBarcodeScanning.getClient(this, options);
            toast("QR oxuyucu açılır…");

            scanner.startScan()
                    .addOnSuccessListener(barcode -> {
                        String scanned = barcode == null ? "" : barcode.getRawValue();
                        if (scanned == null || scanned.trim().isEmpty()) {
                            toast("QR kodda məlumat tapılmadı.");
                            return;
                        }
                        connectToScannedServer(scanned);
                    })
                    .addOnCanceledListener(() -> toast("QR kod oxunması ləğv edildi."))
                    .addOnFailureListener(error -> {
                        String detail = error == null || error.getMessage() == null
                                ? ""
                                : error.getMessage().trim();
                        if (detail.isEmpty()) {
                            toast("QR oxuyucu açıla bilmədi. Google Play xidmətlərini yoxlayın.");
                        } else {
                            toast("QR oxuyucu açıla bilmədi: " + detail);
                        }
                    });
        } catch (Throwable error) {
            toast("QR oxuyucu açıla bilmədi. Google Play xidmətlərini yoxlayın.");
        }
    }

    private void connectToScannedServer(String scannedValue) {
        String value = extractServerBaseFromQr(scannedValue);
        if (value.isEmpty()) {
            toast("QR kodda server ünvanı tapılmadı.");
            return;
        }

        // QR oxunan kimi ünvanı server xanasında göstər.
        runOnUiThread(() -> {
            if (serverAddressInput != null) {
                serverAddressInput.setText(value);
                serverAddressInput.setSelection(value.length());
            }
            toast("QR koddan server ünvanı əlavə olundu.");
        });

        // Ünvan göründükdən sonra avtomatik qoşulmanı da yoxla.
        setBusy(true);
        io.execute(() -> {
            try {
                request(value, "/api/mobile/ping", "GET", null, "");
                serverBase = value;
                prefs.edit().putString(KEY_SERVER, value).apply();
                runOnUiThread(() -> {
                    toast("QR kodla serverə qoşuldu.");
                    showLogin();
                });
            } catch (Exception ex) {
                // Qoşulma alınmasa server ekranında qalır və oxunan ünvan xanada qalır.
                runOnUiThread(() -> toast("Ünvan əlavə olundu, amma serverə avtomatik qoşulmaq alınmadı."));
            } finally {
                setBusy(false);
            }
        });
    }

    private void showServerSetup() {
        ScrollView sv = screenWithBody("Marakana Mobile", false, null);
        LinearLayout body = scrollBody(sv);
        body.setGravity(Gravity.CENTER_HORIZONTAL);

        TextView nativeBadge = text("NATIVE ANDROID", 12, BLUE, true);
        nativeBadge.setGravity(Gravity.CENTER);
        nativeBadge.setBackground(bg(Color.rgb(232, 243, 255), 12, Color.rgb(183, 215, 250)));
        body.addView(nativeBadge, new LinearLayout.LayoutParams(dp(170), dp(38)));

        TextView title = text("Server bağlantısı", 27, TEXT, true);
        title.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams tlp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(64));
        tlp.setMargins(0, dp(20), 0, 0);
        body.addView(title, tlp);

        TextView hint = text("Bu ünvan yalnız API bağlantısı üçündür. Tətbiqin ekranı sayt deyil və WebView istifadə etmir.", 14, MUTED, false);
        hint.setGravity(Gravity.CENTER);
        hint.setPadding(dp(8), 0, dp(8), dp(20));
        body.addView(hint);

        serverAddressInput = input("192.168.1.20:8765 və ya server domeni");
        serverAddressInput.setText(serverBase);
        body.addView(serverAddressInput);
        Button connect = button("Serverə qoşul", BLUE, Color.WHITE);
        LinearLayout.LayoutParams cp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(54));
        cp.setMargins(0, dp(12), 0, 0);
        connect.setLayoutParams(cp);
        body.addView(connect);

        Button qrConnect = button("▦  QR kodla qoşul", CARD, TEXT);
        LinearLayout.LayoutParams qrp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(54));
        qrp.setMargins(0, dp(10), 0, 0);
        qrConnect.setLayoutParams(qrp);
        body.addView(qrConnect);
        qrConnect.setOnClickListener(v -> startQrServerScanner());

        connect.setOnClickListener(v -> {
            String value = normalizeServerBase(serverAddressInput.getText().toString());
            if (value.isEmpty()) { toast("Server ünvanını yazın."); return; }
            setBusy(true);
            io.execute(() -> {
                try {
                    request(value, "/api/mobile/ping", "GET", null, "");
                    serverBase = value;
                    prefs.edit().putString(KEY_SERVER, value).apply();
                    runOnUiThread(this::showLogin);
                } catch (Exception ex) { showError(ex); }
                finally { setBusy(false); }
            });
        });
    }

    private void showLogin() {
        sessionToken = "";
        sessionPassword = "";
        canHall = false;
        canKitchen = false;
        canAdmin = false;
        ScrollView sv = screenWithBody("Marakana Mobile", false, null);
        LinearLayout body = scrollBody(sv);
        body.setGravity(Gravity.CENTER_HORIZONTAL);

        TextView badge = text("NATIVE", 12, GREEN, true);
        badge.setGravity(Gravity.CENTER);
        badge.setBackground(bg(Color.rgb(232, 248, 240), 12, Color.rgb(185, 226, 207)));
        body.addView(badge, new LinearLayout.LayoutParams(dp(110), dp(36)));

        TextView t = text("Giriş", 28, TEXT, true);
        t.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams tp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(66));
        tp.setMargins(0, dp(12), 0, 0);
        body.addView(t, tp);

        Spinner userSpin = new Spinner(this);
        userSpin.setBackground(bg(CARD, 14, BORDER));
        List<String> loginUsernames = new ArrayList<>();
        List<String> loginUserLabels = new ArrayList<>();
        loginUserLabels.add("İstifadəçilər yüklənir…");
        ArrayAdapter<String> userAdapter = new ArrayAdapter<>(this, android.R.layout.simple_spinner_dropdown_item, loginUserLabels);
        userSpin.setAdapter(userAdapter);
        LinearLayout.LayoutParams usp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(54));
        body.addView(userSpin, usp);

        TextView userHint = text("PC proqramındakı mobil icazəli istifadəçilər avtomatik yüklənir.", 12, MUTED, false);
        LinearLayout.LayoutParams uhp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(34));
        uhp.setMargins(dp(4), dp(2), 0, 0);
        body.addView(userHint, uhp);

        io.execute(() -> {
            try {
                JSONObject usersResult = request(serverBase, "/api/mobile/users", "GET", null, "");
                JSONArray usersArray = usersResult.optJSONArray("users");
                List<String> loadedUsernames = new ArrayList<>();
                List<String> loadedLabels = new ArrayList<>();
                if (usersArray != null) {
                    for (int i = 0; i < usersArray.length(); i++) {
                        JSONObject item = usersArray.optJSONObject(i);
                        if (item == null) continue;
                        String itemUsername = item.optString("username", "").trim();
                        if (itemUsername.isEmpty()) continue;
                        String fullName = item.optString("full_name", "").trim();
                        String label = fullName.isEmpty() || fullName.equalsIgnoreCase(itemUsername)
                                ? itemUsername
                                : fullName + " (" + itemUsername + ")";
                        loadedUsernames.add(itemUsername);
                        loadedLabels.add(label);
                    }
                }
                runOnUiThread(() -> {
                    loginUsernames.clear();
                    loginUsernames.addAll(loadedUsernames);
                    loginUserLabels.clear();
                    if (loadedLabels.isEmpty()) {
                        loginUserLabels.add("Mobil giriş icazəli istifadəçi tapılmadı");
                        userHint.setText("PC proqramında istifadəçiyə Mobil Panel icazəsi verilməlidir.");
                    } else {
                        loginUserLabels.addAll(loadedLabels);
                        userHint.setText("İstifadəçini siyahıdan seçin.");
                    }
                    userAdapter.notifyDataSetChanged();

                    if (!username.isEmpty() && !loginUsernames.isEmpty()) {
                        for (int i = 0; i < loginUsernames.size(); i++) {
                            if (username.equalsIgnoreCase(loginUsernames.get(i))) {
                                userSpin.setSelection(i);
                                break;
                            }
                        }
                    }
                });
            } catch (Exception ex) {
                runOnUiThread(() -> {
                    loginUsernames.clear();
                    loginUserLabels.clear();
                    loginUserLabels.add("İstifadəçilər yüklənmədi");
                    userAdapter.notifyDataSetChanged();
                    userHint.setText("Desktop proqramı v1250+ olmalıdır və serverə əlçatan olmalıdır.");
                    toast("PC istifadəçi siyahısı alınmadı.");
                });
            }
        });

        EditText pass = input("Şifrə");
        pass.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_PASSWORD);
        String rememberedPassword = prefs.getBoolean(KEY_AUTO_LOGIN, false) ? loadSavedPassword() : "";
        if (!rememberedPassword.isEmpty()) pass.setText(rememberedPassword);
        LinearLayout.LayoutParams pp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(54));
        pp.setMargins(0, dp(10), 0, 0);
        pass.setLayoutParams(pp);
        body.addView(pass);

        Spinner roleSpin = new Spinner(this);
        List<String> roles = new ArrayList<>(); roles.add("Zal"); roles.add("Mətbəx"); roles.add("Admin"); roles.add("Borc Dəftəri");
        ArrayAdapter<String> adapter = new ArrayAdapter<>(this, android.R.layout.simple_spinner_dropdown_item, roles);
        roleSpin.setAdapter(adapter);
        roleSpin.setBackground(bg(CARD, 14, BORDER));
        int selected = role.equals("kitchen") ? 1 : (role.equals("admin") && adminDebtOnly) ? 3 : role.equals("admin") ? 2 : 0;
        roleSpin.setSelection(selected);
        LinearLayout.LayoutParams rp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(54));
        rp.setMargins(0, dp(10), 0, 0);
        body.addView(roleSpin, rp);

        CheckBox remember = new CheckBox(this);
        remember.setText("Şifrəni yadda saxla və avtomatik daxil ol");
        remember.setTextColor(TEXT);
        remember.setTextSize(14);
        remember.setChecked(prefs.getBoolean(KEY_AUTO_LOGIN, false));
        LinearLayout.LayoutParams remp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(48));
        remp.setMargins(dp(4), dp(6), 0, 0);
        body.addView(remember, remp);

        Button login = button("Daxil ol", BLUE, Color.WHITE);
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(56));
        lp.setMargins(0, dp(14), 0, 0);
        login.setLayoutParams(lp);
        body.addView(login);

        Button server = button("Server ayarı", CARD, TEXT);
        LinearLayout.LayoutParams sp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(52));
        sp.setMargins(0, dp(10), 0, 0);
        server.setLayoutParams(sp);
        body.addView(server);
        server.setOnClickListener(v -> showServerSetup());

        login.setOnClickListener(v -> {
            int userPosition = userSpin.getSelectedItemPosition();
            if (userPosition < 0 || userPosition >= loginUsernames.size()) {
                toast("İstifadəçini siyahıdan seçin.");
                return;
            }
            String u = loginUsernames.get(userPosition);
            String p = pass.getText().toString();
            int rolePosition = roleSpin.getSelectedItemPosition();
            String selectedRole = rolePosition == 1 ? "kitchen" : (rolePosition == 2 || rolePosition == 3) ? "admin" : "hall";
            boolean selectedDebtOnly = rolePosition == 3;
            if (p.isEmpty()) { toast("Şifrəni yazın."); return; }
            performLogin(u, p, selectedRole, selectedDebtOnly, remember.isChecked(), true);
        });
    }


    private Button smallButton(String label, boolean active) {
        Button b = button(label, active ? BLUE : CARD, active ? Color.WHITE : TEXT);
        b.setLayoutParams(new LinearLayout.LayoutParams(dp(Math.max(105, label.length() * 10 + 40)), dp(46)));
        LinearLayout.LayoutParams lp = (LinearLayout.LayoutParams)b.getLayoutParams(); lp.setMargins(0, 0, dp(8), 0); b.setLayoutParams(lp);
        return b;
    }

    private void showTerminals() {
        ScrollView sv = screenWithBody("Terminallar", false, null);
        LinearLayout body = scrollBody(sv);
        TextView status = text("Yüklənir…", 14, MUTED, false);
        body.addView(status, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(48)));
        loadJson("/api/mobile/terminals", result -> {
            status.setVisibility(View.GONE);
            JSONArray arr = result.optJSONArray("terminals");
            if (arr == null || arr.length() == 0) { body.addView(empty("Terminal tapılmadı.")); return; }
            for (int i=0; i<arr.length(); i++) {
                JSONObject station = arr.optJSONObject(i); if (station != null) body.addView(stationCard(station));
            }
        });
    }

    private View stationCard(JSONObject s) {
        LinearLayout c = card();
        LinearLayout top = new LinearLayout(this); top.setOrientation(LinearLayout.HORIZONTAL); top.setGravity(Gravity.CENTER_VERTICAL);
        String name = s.optString("name", "Terminal");
        TextView n = text(name, 18, TEXT, true); top.addView(n, new LinearLayout.LayoutParams(0, dp(40), 1f));
        boolean active = s.optBoolean("active", false);
        TextView amount = text(String.format(Locale.US, "%.2f AZN", s.optDouble("current_total", 0)), 17, active ? GREEN : MUTED, true);
        amount.setGravity(Gravity.RIGHT | Gravity.CENTER_VERTICAL); top.addView(amount, new LinearLayout.LayoutParams(dp(135), dp(40)));
        c.addView(top);
        TextView meta = text((active ? s.optString("elapsed_label", "Aktiv") : (s.optString("kind", "terminal").equals("table") ? "Masa bağlıdır" : "Açılmayıb")) + "  •  Sifariş: " + s.optInt("order_count", 0), 13, MUTED, true);
        c.addView(meta, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(34)));
        c.setClickable(true); c.setOnClickListener(v -> showStation(name));
        return c;
    }

    private void showStation(String name) {
        ScrollView sv = screenWithBody(name, true, this::showTerminals);
        LinearLayout body = scrollBody(sv);
        loadJson("/api/mobile/station?name=" + urlEncode(name), result -> {
            JSONObject s = result.optJSONObject("station"); if (s == null) return;
            LinearLayout summary = card();
            summary.addView(text(s.optString("elapsed_label", ""), 18, TEXT, true));
            summary.addView(text("Yekun: " + money(s.optDouble("current_total", 0)) + "  •  Sifariş: " + money(s.optDouble("order_total", 0)), 14, MUTED, true), new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(40)));
            body.addView(summary);

            if (!s.optBoolean("active", false) && s.optString("kind", "terminal").equals("terminal")) {
                Button open = button("Vaxt aç", GREEN, Color.WHITE); body.addView(open); open.setOnClickListener(v -> showOpenTimeDialog(name));
                spacer(body, 10);
            }
            Button products = button("Sifariş əlavə et", BLUE, Color.WHITE); body.addView(products); products.setOnClickListener(v -> showProducts(name));
            spacer(body, 12);

            JSONArray orders = s.optJSONArray("orders");
            TextView oh = text("Cari sifarişlər", 17, TEXT, true); body.addView(oh, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(44)));
            if (orders == null || orders.length() == 0) { body.addView(empty("Sifariş yoxdur.")); return; }
            for (int i=0; i<orders.length(); i++) {
                JSONObject o = orders.optJSONObject(i); if (o == null) continue;
                LinearLayout oc = card();
                LinearLayout row = new LinearLayout(this); row.setOrientation(LinearLayout.HORIZONTAL); row.setGravity(Gravity.CENTER_VERTICAL);
                row.addView(text(o.optString("name", ""), 15, TEXT, true), new LinearLayout.LayoutParams(0, dp(40), 1f));
                row.addView(text("x" + o.optInt("qty", 0) + "  " + money(o.optDouble("total", 0)), 14, GREEN, true), new LinearLayout.LayoutParams(dp(135), dp(40)));
                oc.addView(row);
                final String itemName = o.optString("name", "");
                final double unit = o.optDouble("unit_price", 0);
                if (canAdmin) {
                    Button remove = button("1 ədəd azalt", Color.rgb(255, 245, 239), ORANGE);
                    remove.setLayoutParams(new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(44)));
                    remove.setOnClickListener(v -> confirmRemoveOrder(name, itemName, unit));
                    oc.addView(remove);
                }
                body.addView(oc);
            }
        });
    }

    private void showOpenTimeDialog(String station) {
        LinearLayout box = new LinearLayout(this); box.setOrientation(LinearLayout.VERTICAL); box.setPadding(dp(18), dp(8), dp(18), 0);
        Spinner mode = new Spinner(this); String[] modes = {"60 dəqiqə", "120 dəqiqə", "180 dəqiqə", "Vaxtsız"}; mode.setAdapter(new ArrayAdapter<>(this, android.R.layout.simple_spinner_dropdown_item, modes)); box.addView(mode);
        Spinner pads = new Spinner(this); String[] p = {"1 pult", "2 pult", "3 pult", "4 pult"}; pads.setAdapter(new ArrayAdapter<>(this, android.R.layout.simple_spinner_dropdown_item, p)); box.addView(pads);
        new AlertDialog.Builder(this).setTitle("Vaxt aç • " + station).setView(box).setNegativeButton("Ləğv", null).setPositiveButton("Aç", (d,w) -> {
            JSONObject payload = new JSONObject();
            try {
                payload.put("station_name", station); payload.put("pad_count", pads.getSelectedItemPosition()+1);
                if (mode.getSelectedItemPosition() == 3) payload.put("mode", "untimed");
                else { payload.put("mode", "timed"); payload.put("pick_mode", "time"); payload.put("value", (mode.getSelectedItemPosition()+1)*60); }
            } catch(Exception ignored) {}
            postJson("/api/mobile/station/open_time", payload, r -> showStation(station));
        }).show();
    }

    private void confirmRemoveOrder(String station, String item, double unitPrice) {
        if (!canAdmin) {
            toast("Sifarişi azaltmaq üçün Admin icazəsi tələb olunur.");
            return;
        }
        new AlertDialog.Builder(this)
                .setTitle("Sifarişi azalt")
                .setMessage(item + " məhsulundan 1 ədəd azaltmaq istəyirsiniz?")
                .setNegativeButton("Ləğv", null)
                .setPositiveButton("Təsdiqlə", (dialog, which) -> removeOrder(station, item, unitPrice))
                .show();
    }

    private void removeOrder(String station, String item, double unitPrice) {
        if (!canAdmin) {
            toast("Sifarişi azaltmaq üçün Admin icazəsi tələb olunur.");
            return;
        }
        JSONObject p = new JSONObject();
        try {
            p.put("station_name", station);
            p.put("name", item);
            p.put("unit_price", unitPrice);
            p.put("qty", 1);
        } catch(Exception ignored) {}
        postJson("/api/mobile/order/remove", p, r -> showStation(station));
    }

    private LinkedHashMap<String, OrderCartItem> cartForStation(String station) {
        LinkedHashMap<String, OrderCartItem> cart = orderCarts.get(station);
        if (cart == null) {
            cart = new LinkedHashMap<>();
            orderCarts.put(station, cart);
        }
        return cart;
    }

    private int cartTotalQty(String station) {
        int total = 0;
        for (OrderCartItem item : cartForStation(station).values()) total += item.qty;
        return total;
    }

    private double cartTotalAmount(String station) {
        double total = 0;
        for (OrderCartItem item : cartForStation(station).values()) total += item.price * item.qty;
        return total;
    }

    private String cartButtonLabel(String station) {
        int qty = cartTotalQty(station);
        if (qty <= 0) return "🛒 Səbət";
        return "🛒 Səbət • " + qty + " ədəd • " + money(cartTotalAmount(station));
    }

    private void showProducts(String station) {
        ScrollView sv = screenWithBody("Sifariş • " + station, true, () -> showStation(station));
        LinearLayout body = scrollBody(sv);

        Button cartButton = button(cartButtonLabel(station), GREEN, Color.WHITE);
        body.addView(cartButton);
        cartButton.setOnClickListener(v -> showOrderCart(station));
        spacer(body, 10);

        EditText search = input("Məhsul axtar");
        body.addView(search);
        spacer(body, 10);

        LinearLayout list = new LinearLayout(this);
        list.setOrientation(LinearLayout.VERTICAL);
        body.addView(list);
        final JSONArray[] allProducts = {new JSONArray()};

        loadJson("/api/mobile/products", result -> {
            JSONArray products = result.optJSONArray("products");
            allProducts[0] = products == null ? new JSONArray() : products;

            final Runnable[] productRender = new Runnable[1];
            productRender[0] = () -> {
                list.removeAllViews();
                cartButton.setText(cartButtonLabel(station));
                String q = search.getText().toString().trim().toLowerCase(Locale.ROOT);
                for (int i = 0; i < allProducts[0].length(); i++) {
                    JSONObject product = allProducts[0].optJSONObject(i);
                    if (product == null) continue;
                    final String productName = product.optString("name", "");
                    if (!q.isEmpty() && !productName.toLowerCase(Locale.ROOT).contains(q)) continue;
                    final String barcode = product.optString("barcode", "");
                    final double price = product.optDouble("price", 0);

                    LinearLayout c = card();
                    LinearLayout top = new LinearLayout(this);
                    top.setOrientation(LinearLayout.HORIZONTAL);
                    top.setGravity(Gravity.CENTER_VERTICAL);
                    top.addView(text(productName, 15, TEXT, true), new LinearLayout.LayoutParams(0, dp(48), 1f));
                    top.addView(text(money(price), 14, GREEN, true), new LinearLayout.LayoutParams(dp(110), dp(48)));
                    c.addView(top);

                    OrderCartItem current = cartForStation(station).get(barcode);
                    String hintText = current == null
                            ? "Miqdar seçib səbətə əlavə etmək üçün toxun"
                            : "Səbətdə: " + current.qty + " ədəd • Miqdarı dəyişmək üçün toxun";
                    TextView hint = text(hintText, 12, current == null ? MUTED : GREEN, true);
                    c.addView(hint, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(30)));
                    c.setClickable(true);
                    c.setFocusable(true);
                    c.setOnClickListener(v -> showProductQuantityPicker(
                            station, barcode, productName, price,
                            () -> {
                                cartButton.setText(cartButtonLabel(station));
                                if (productRender[0] != null) productRender[0].run();
                            }
                    ));
                    list.addView(c);
                }
            };
            search.addTextChangedListener(new SimpleTextWatcher(() -> {
                if (productRender[0] != null) productRender[0].run();
            }));
            productRender[0].run();
        });
    }

    private void showProductQuantityPicker(
            String station,
            String barcode,
            String productName,
            double price,
            Runnable onChanged
    ) {
        String[] choices = new String[10];
        for (int i = 0; i < choices.length; i++) choices[i] = (i + 1) + " ədəd";

        OrderCartItem existing = cartForStation(station).get(barcode);
        AlertDialog.Builder builder = new AlertDialog.Builder(this)
                .setTitle(productName + " • Miqdar")
                .setItems(choices, (dialog, which) -> {
                    putProductInCart(station, barcode, productName, price, which + 1);
                    toast((which + 1) + " ədəd səbətə əlavə edildi.");
                    if (onChanged != null) onChanged.run();
                })
                .setNegativeButton("Ləğv", null);

        if (existing != null) {
            builder.setNeutralButton("Səbətdən sil", (dialog, which) -> {
                cartForStation(station).remove(barcode);
                toast(productName + " səbətdən silindi.");
                if (onChanged != null) onChanged.run();
            });
        }
        builder.show();
    }

    private void putProductInCart(String station, String barcode, String name, double price, int qty) {
        if (qty < 1 || qty > 10) return;
        LinkedHashMap<String, OrderCartItem> cart = cartForStation(station);
        OrderCartItem existing = cart.get(barcode);
        if (existing == null) {
            cart.put(barcode, new OrderCartItem(barcode, name, price, qty));
        } else {
            existing.name = name;
            existing.price = price;
            existing.qty = qty;
        }
    }

    private void showOrderCart(String station) {
        ScrollView sv = screenWithBody("Səbət • " + station, true, () -> showProducts(station));
        LinearLayout body = scrollBody(sv);
        LinkedHashMap<String, OrderCartItem> cart = cartForStation(station);

        if (cart.isEmpty()) {
            body.addView(empty("Səbət boşdur."));
            spacer(body, 12);
            Button products = button("Məhsullara bax", BLUE, Color.WHITE);
            body.addView(products);
            products.setOnClickListener(v -> showProducts(station));
            return;
        }

        LinearLayout summary = card();
        summary.addView(text("Sifariş xülasəsi", 18, TEXT, true), new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(40)));
        summary.addView(
                text(cartTotalQty(station) + " ədəd • Yekun: " + money(cartTotalAmount(station)), 15, GREEN, true),
                new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(36))
        );
        body.addView(summary);

        for (OrderCartItem item : new ArrayList<>(cart.values())) {
            LinearLayout c = card();

            LinearLayout row = new LinearLayout(this);
            row.setOrientation(LinearLayout.HORIZONTAL);
            row.setGravity(Gravity.CENTER_VERTICAL);
            row.addView(text(item.name, 15, TEXT, true), new LinearLayout.LayoutParams(0, dp(42), 1f));
            TextView qtyTotal = text("x" + item.qty + "  " + money(item.price * item.qty), 14, GREEN, true);
            qtyTotal.setGravity(Gravity.RIGHT | Gravity.CENTER_VERTICAL);
            row.addView(qtyTotal, new LinearLayout.LayoutParams(dp(150), dp(42)));
            c.addView(row);

            TextView unit = text("1 ədəd: " + money(item.price), 12, MUTED, false);
            c.addView(unit, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(28)));

            LinearLayout actions = new LinearLayout(this);
            actions.setOrientation(LinearLayout.HORIZONTAL);

            Button edit = button("Miqdarı dəyiş", Color.rgb(238, 246, 255), BLUE);
            Button remove = button("Sil", Color.rgb(255, 245, 239), ORANGE);
            actions.addView(edit, new LinearLayout.LayoutParams(0, dp(44), 1f));
            LinearLayout.LayoutParams rlp = new LinearLayout.LayoutParams(dp(92), dp(44));
            rlp.setMargins(dp(8), 0, 0, 0);
            actions.addView(remove, rlp);
            c.addView(actions);

            edit.setOnClickListener(v -> showProductQuantityPicker(
                    station, item.barcode, item.name, item.price,
                    () -> showOrderCart(station)
            ));
            remove.setOnClickListener(v -> {
                cartForStation(station).remove(item.barcode);
                showOrderCart(station);
            });

            body.addView(c);
        }

        spacer(body, 6);
        Button send = button(
                "Sifarişi göndər • " + money(cartTotalAmount(station)),
                GREEN,
                Color.WHITE
        );
        body.addView(send);
        send.setOnClickListener(v -> confirmSendOrderCart(station));

        spacer(body, 10);
        Button continueShopping = button("Məhsul əlavə etməyə davam et", CARD, TEXT);
        body.addView(continueShopping);
        continueShopping.setOnClickListener(v -> showProducts(station));

        spacer(body, 10);
        Button clearCart = button("Səbəti təmizlə", Color.rgb(255, 245, 239), ORANGE);
        body.addView(clearCart);
        clearCart.setOnClickListener(v -> new AlertDialog.Builder(this)
                .setTitle("Səbəti təmizlə")
                .setMessage("Səbətdəki bütün məhsulları silmək istəyirsiniz?")
                .setNegativeButton("Ləğv", null)
                .setPositiveButton("Təmizlə", (d, w) -> {
                    cartForStation(station).clear();
                    showOrderCart(station);
                })
                .show());
    }

    private void confirmSendOrderCart(String station) {
        if (cartForStation(station).isEmpty()) {
            toast("Səbət boşdur.");
            return;
        }
        new AlertDialog.Builder(this)
                .setTitle("Sifarişi göndər")
                .setMessage(
                        station + "\n\n" +
                        "Miqdar: " + cartTotalQty(station) + " ədəd\n" +
                        "Yekun: " + money(cartTotalAmount(station)) +
                        "\n\nSifarişi terminala göndərmək istəyirsiniz?"
                )
                .setNegativeButton("Ləğv", null)
                .setPositiveButton("Göndər", (dialog, which) -> sendOrderCart(station))
                .show();
    }

    private void sendOrderCart(String station) {
        LinkedHashMap<String, OrderCartItem> cart = cartForStation(station);
        if (cart.isEmpty()) {
            toast("Səbət boşdur.");
            return;
        }

        JSONArray items = new JSONArray();
        JSONObject payload = new JSONObject();
        try {
            for (OrderCartItem cartItem : cart.values()) {
                JSONObject item = new JSONObject();
                item.put("barcode", cartItem.barcode);
                item.put("qty", cartItem.qty);
                items.put(item);
            }
            payload.put("station_name", station);
            payload.put("items", items);
        } catch (Exception ignored) {}

        postJson("/api/mobile/order/batch_add", payload, result -> {
            cart.clear();
            toast("Sifariş göndərildi.");
            showStation(station);
        });
    }

    private void showDebt(String category) {
        currentBackAction = null;
        clear();

        LinearLayout shell = new LinearLayout(this);
        shell.setOrientation(LinearLayout.VERTICAL);
        shell.setPadding(dp(8), dp(10), dp(8), 0);
        content.addView(shell, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        shell.addView(buildMainHeader("Borc Dəftəri", false, null));

        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        shell.addView(scroll, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f));

        LinearLayout body = scrollBody(scroll);
        body.setPadding(0, dp(4), 0, dp(92));

        Button create = button("Yeni borclu yarat", BLUE, Color.WHITE);
        body.addView(create);
        create.setOnClickListener(v -> createDebtorDialog(category));
        spacer(body, 10);

        EditText search = input("Ad, telefon və ya ID ilə axtar");
        body.addView(search);
        spacer(body, 10);

        LinearLayout recordsHost = new LinearLayout(this);
        recordsHost.setOrientation(LinearLayout.VERTICAL);
        body.addView(recordsHost);

        installDebtGestures(scroll, category);
        installDebtGestures(body, category);

        LinearLayout footer = buildDebtFooter(category);
        shell.addView(footer, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(86)));

        loadJson("/api/mobile/debt/list?category=" + urlEncode(category), result -> {
            JSONArray records = result.optJSONArray("records");
            if (records == null) records = new JSONArray();
            final JSONArray finalRecords = records;
            Runnable render = () -> renderDebtRecords(recordsHost, finalRecords, category, search.getText().toString());
            search.addTextChangedListener(new SimpleTextWatcher(render));
            render.run();
        });
    }

    private void installDebtGestures(View target, String category) {
        final int swipeThreshold = dp(48);
        GestureDetector detector = new GestureDetector(this, new GestureDetector.SimpleOnGestureListener() {
            @Override
            public boolean onDown(MotionEvent e) {
                return true;
            }

            @Override
            public boolean onFling(MotionEvent e1, MotionEvent e2, float velocityX, float velocityY) {
                if (e1 == null || e2 == null) return false;
                float dx = e2.getX() - e1.getX();
                float dy = e2.getY() - e1.getY();
                if (Math.abs(dx) < swipeThreshold || Math.abs(dx) <= Math.abs(dy)) return false;

                if (dx > 0) {
                    // Sağa swipe: əvvəlki kateqoriya. Birinci kateqoriyadayıqsa sol menyunu aç.
                    String previous = shiftDebtCategory(category, -1);
                    if (!previous.equals(category)) showDebt(previous);
                    else showNavigationMenu();
                } else {
                    // Sola swipe: növbəti kateqoriya.
                    String next = shiftDebtCategory(category, 1);
                    if (!next.equals(category)) showDebt(next);
                }
                return true;
            }
        });
        target.setOnTouchListener((v, event) -> {
            detector.onTouchEvent(event);
            return false;
        });
    }

    private String shiftDebtCategory(String current, int direction) {
        int index = 0;
        for (int i = 0; i < DEBT_CATEGORIES.length; i++) {
            if (DEBT_CATEGORIES[i].equals(current)) {
                index = i;
                break;
            }
        }
        int next = index + direction;
        if (next < 0 || next >= DEBT_CATEGORIES.length) return current;
        return DEBT_CATEGORIES[next];
    }

    private LinearLayout buildDebtFooter(String activeCategory) {
        LinearLayout footer = new LinearLayout(this);
        footer.setOrientation(LinearLayout.HORIZONTAL);
        footer.setGravity(Gravity.CENTER);
        footer.setPadding(dp(6), dp(8), dp(6), dp(8));
        footer.setBackground(bg(Color.WHITE, 22, BORDER));
        footer.setElevation(dp(12));

        String[] icons = {"👤", "👥", "🏢"};
        for (int i = 0; i < DEBT_CATEGORIES.length; i++) {
            String category = DEBT_CATEGORIES[i];
            boolean active = category.equals(activeCategory);
            LinearLayout tab = buildDebtFooterTab(icons[i], category, active);
            LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.MATCH_PARENT, 1f);
            if (i > 0) lp.setMargins(dp(6), 0, 0, 0);
            tab.setLayoutParams(lp);
            tab.setOnClickListener(v -> showDebt(category));
            footer.addView(tab);
        }
        return footer;
    }

    private LinearLayout buildDebtFooterTab(String iconText, String label, boolean active) {
        LinearLayout tab = new LinearLayout(this);
        tab.setOrientation(LinearLayout.VERTICAL);
        tab.setGravity(Gravity.CENTER);
        tab.setPadding(dp(8), dp(6), dp(8), dp(6));
        int bgColor = active ? Color.rgb(238, 241, 245) : Color.WHITE;
        int stroke = active ? Color.rgb(210, 218, 226) : Color.TRANSPARENT;
        tab.setBackground(bg(bgColor, 18, stroke));

        TextView icon = text(iconText, 18, active ? TEXT : MUTED, false);
        icon.setGravity(Gravity.CENTER);
        tab.addView(icon, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(24)));

        TextView title = text(label, 14, active ? TEXT : MUTED, true);
        title.setGravity(Gravity.CENTER);
        tab.addView(title, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(24)));
        return tab;
    }

    private void renderDebtRecords(LinearLayout host, JSONArray records, String category, String query) {
        host.removeAllViews();
        String q = query == null ? "" : query.trim().toLowerCase(Locale.ROOT);
        double total = 0;
        for (int i = 0; i < records.length(); i++) {
            JSONObject r = records.optJSONObject(i);
            if (r == null) continue;
            total += r.optDouble("total_debt", 0);
        }

        LinearLayout sum = card();
        sum.addView(text("Toplam borc", 13, MUTED, true));
        sum.addView(text(money(total), 22, TEXT, true), new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(40)));
        host.addView(sum);

        for (int i = 0; i < records.length(); i++) {
            JSONObject r = records.optJSONObject(i);
            if (r == null) continue;
            String hay = (r.optString("full_name", "") + " " + r.optString("phone", "") + " " + r.optString("id", "")).toLowerCase(Locale.ROOT);
            if (!q.isEmpty() && !hay.contains(q)) continue;

            LinearLayout c = card();
            LinearLayout row = new LinearLayout(this);
            row.setOrientation(LinearLayout.HORIZONTAL);
            row.setGravity(Gravity.CENTER_VERTICAL);
            row.addView(text(r.optString("full_name", ""), 17, TEXT, true), new LinearLayout.LayoutParams(0, dp(38), 1f));
            TextView amt = text(money(r.optDouble("total_debt", 0)), 17, GREEN, true);
            amt.setGravity(Gravity.RIGHT | Gravity.CENTER_VERTICAL);
            row.addView(amt, new LinearLayout.LayoutParams(dp(145), dp(38)));
            c.addView(row);

            c.addView(text(r.optString("phone", "") + "  •  ID: " + r.optString("id", "") + "  •  " + r.optString("last_change", ""), 12, MUTED, true),
                    new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(36)));

            c.setClickable(true);
            c.setFocusable(true);
            c.setOnClickListener(v -> showDebtActions(category, r));
            host.addView(c);
        }
    }

    private void showDebtActions(String category, JSONObject record) {
        LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL);
        box.setPadding(dp(14), dp(6), dp(14), dp(4));

        TextView balance = text("Cari borc: " + money(record.optDouble("total_debt", 0)), 15, GREEN, true);
        box.addView(balance, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(42)));

        Button increase = button("Borcu artır", Color.rgb(234, 247, 241), GREEN);
        box.addView(increase, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(50)));

        Button decrease = button("Borcu azalt", Color.rgb(255, 245, 239), ORANGE);
        LinearLayout.LayoutParams decreaseLp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(50));
        decreaseLp.setMargins(0, dp(8), 0, 0);
        box.addView(decrease, decreaseLp);

        Button history = button("Tarixçə", Color.rgb(238, 246, 255), BLUE);
        LinearLayout.LayoutParams historyLp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(50));
        historyLp.setMargins(0, dp(8), 0, 0);
        box.addView(history, historyLp);

        Button notify = button("Bildiriş göndər", Color.rgb(232, 248, 240), GREEN);
        LinearLayout.LayoutParams notifyLp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(50));
        notifyLp.setMargins(0, dp(8), 0, 0);
        box.addView(notify, notifyLp);

        Button call = button("Zəng et", Color.rgb(245, 247, 250), TEXT);
        LinearLayout.LayoutParams callLp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(50));
        callLp.setMargins(0, dp(8), 0, 0);
        box.addView(call, callLp);

        AlertDialog dialog = new AlertDialog.Builder(this)
                .setTitle(record.optString("full_name", "Borclu"))
                .setView(box)
                .setNegativeButton("Bağla", null)
                .create();

        increase.setOnClickListener(v -> {
            dialog.dismiss();
            debtChangeDialog(category, record, "increase");
        });
        decrease.setOnClickListener(v -> {
            dialog.dismiss();
            debtChangeDialog(category, record, "decrease");
        });
        history.setOnClickListener(v -> {
            dialog.dismiss();
            showDebtHistory(record);
        });
        notify.setOnClickListener(v -> {
            dialog.dismiss();
            sendDebtNotificationWhatsApp(category, record);
        });
        call.setOnClickListener(v -> {
            dialog.dismiss();
            callDebtContact(record);
        });
        dialog.show();
    }

    private void callDebtContact(JSONObject record) {
        String phone = record.optString("phone", "").trim();
        if (phone.isEmpty()) {
            toast("Bu qeyd üçün telefon nömrəsi yoxdur.");
            return;
        }
        try {
            String dialPhone = phone.replaceAll("[^0-9+]", "");
            Intent intent = new Intent(Intent.ACTION_DIAL, Uri.parse("tel:" + Uri.encode(dialPhone)));
            startActivity(intent);
        } catch (Exception ex) {
            toast("Telefon tətbiqi açıla bilmədi.");
        }
    }

    private void sendDebtNotificationWhatsApp(String category, JSONObject record) {
        String phone = normalizeWhatsAppPhone(record.optString("phone", ""));
        if (phone.isEmpty()) {
            toast("Bu borclunun telefon nömrəsi yoxdur.");
            return;
        }

        String name = record.optString("full_name", category.equals("Firma") ? "Firma" : "Müştəri").trim();
        double debt = record.optDouble("total_debt", 0);
        String lastChange = record.optString("last_change", "").trim();

        StringBuilder message = new StringBuilder();
        if (category.equals("Firma")) {
            message.append("📣 *ÖDƏNİŞ MƏLUMATI*\n\n");
            message.append("🏢 *Firma:* ").append(name.isEmpty() ? "Firma" : name).append("\n");
            message.append("💳 *Sizə olan cari borcumuz:* ").append(money(debt)).append("\n");
            if (!lastChange.isEmpty()) message.append("🕒 *Son dəyişiklik:* ").append(lastChange).append("\n");
            message.append("\n🤝 *Məlumat:* Borcumuzu mümkün qədər tez ödəməyə çalışacağıq.\n");
            message.append("🙏 Ödəniş etdikdə sizə məlumat veriləcək.\n\n");
            message.append("🎮 *Marakana Game Center*");
        } else {
            message.append("🔔 *BORC BİLDİRİŞİ*\n\n");
            message.append("👤 *Ad Soyad:* ").append(name.isEmpty() ? "Müştəri" : name).append("\n");
            message.append("💰 *Cari borc:* ").append(money(debt)).append("\n");
            if (!lastChange.isEmpty()) message.append("🕒 *Son dəyişiklik:* ").append(lastChange).append("\n");
            message.append("\n⚠️ *Xatırlatma:* Borcunuzu mümkün qədər tez ödəməyinizi xahiş edirik.\n");
            message.append("🙏 Ödəniş etdikdən sonra məlumat verməyiniz kifayətdir.\n\n");
            message.append("🎮 *Marakana Game Center*");
        }

        openWhatsAppChooser(phone, message.toString());
    }

    private void openWhatsAppChooser(String phone, String message) {
        final String standardPackage = "com.whatsapp";
        final String businessPackage = "com.whatsapp.w4b";
        boolean hasStandard = isPackageInstalled(standardPackage);
        boolean hasBusiness = isPackageInstalled(businessPackage);

        String savedPackage = prefs.getString(KEY_WHATSAPP_PACKAGE, "");
        if (!savedPackage.isEmpty() && isPackageInstalled(savedPackage)) {
            openWhatsAppPackage(savedPackage, phone, message);
            return;
        }

        if (hasStandard && hasBusiness) {
            LinearLayout box = new LinearLayout(this);
            box.setOrientation(LinearLayout.VERTICAL);
            box.setPadding(dp(18), dp(4), dp(18), 0);

            CheckBox remember = new CheckBox(this);
            remember.setText("Seçimi default olaraq yadda saxla");
            remember.setTextColor(TEXT);
            remember.setTextSize(14);
            box.addView(remember, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(48)));

            AlertDialog dialog = new AlertDialog.Builder(this)
                    .setTitle("WhatsApp seç")
                    .setMessage("Bildirişi hansı WhatsApp ilə göndərmək istəyirsiniz?")
                    .setView(box)
                    .setNegativeButton("Ləğv", null)
                    .create();

            dialog.setButton(AlertDialog.BUTTON_POSITIVE, "WhatsApp", (d, which) -> { });
            dialog.setButton(AlertDialog.BUTTON_NEUTRAL, "WhatsApp Business", (d, which) -> { });
            dialog.setOnShowListener(d -> {
                dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v -> {
                    if (remember.isChecked()) prefs.edit().putString(KEY_WHATSAPP_PACKAGE, standardPackage).apply();
                    dialog.dismiss();
                    openWhatsAppPackage(standardPackage, phone, message);
                });
                dialog.getButton(AlertDialog.BUTTON_NEUTRAL).setOnClickListener(v -> {
                    if (remember.isChecked()) prefs.edit().putString(KEY_WHATSAPP_PACKAGE, businessPackage).apply();
                    dialog.dismiss();
                    openWhatsAppPackage(businessPackage, phone, message);
                });
            });
            dialog.show();
            return;
        }

        if (hasBusiness) {
            openWhatsAppPackage(businessPackage, phone, message);
            return;
        }
        if (hasStandard) {
            openWhatsAppPackage(standardPackage, phone, message);
            return;
        }

        try {
            String url = "https://wa.me/" + phone + "?text=" + urlEncode(message);
            startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
        } catch (Exception ex) {
            toast("WhatsApp və WhatsApp Business tapılmadı.");
        }
    }

    private boolean isPackageInstalled(String packageName) {
        try {
            getPackageManager().getPackageInfo(packageName, 0);
            return true;
        } catch (Exception ex) {
            return false;
        }
    }

    private void openWhatsAppPackage(String packageName, String phone, String message) {
        String url = "https://wa.me/" + phone + "?text=" + urlEncode(message);
        Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
        intent.setPackage(packageName);
        try {
            startActivity(intent);
        } catch (Exception ex) {
            prefs.edit().remove(KEY_WHATSAPP_PACKAGE).apply();
            toast("Seçilmiş WhatsApp açıla bilmədi. Yenidən seçim edin.");
        }
    }

    private String normalizeWhatsAppPhone(String raw) {
        String digits = raw == null ? "" : raw.replaceAll("[^0-9]", "");
        if (digits.isEmpty()) return "";
        if (digits.startsWith("00994")) digits = digits.substring(2);
        if (digits.startsWith("994")) return digits;
        if (digits.startsWith("0") && digits.length() >= 10) return "994" + digits.substring(1);
        if (digits.length() == 9) return "994" + digits;
        return digits;
    }

    private void debtChangeDialog(String category, JSONObject record, String action) {
        LinearLayout box=new LinearLayout(this);box.setOrientation(LinearLayout.VERTICAL);box.setPadding(dp(18),dp(6),dp(18),0); EditText amount=input("Məbləğ");amount.setInputType(InputType.TYPE_CLASS_NUMBER|InputType.TYPE_NUMBER_FLAG_DECIMAL);box.addView(amount); EditText note=input("Qeyd (istəyə bağlı)");LinearLayout.LayoutParams np=new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,dp(54));np.setMargins(0,dp(8),0,0);note.setLayoutParams(np);box.addView(note);
        String label=action.equals("decrease")?"Azalt":"Artır";
        new AlertDialog.Builder(this).setTitle(label+" • "+record.optString("full_name","")).setView(box).setNegativeButton("Ləğv",null).setPositiveButton(label,(d,w)->{ JSONObject p=new JSONObject();try{p.put("category",category);p.put("debt_id",record.optString("id",""));p.put("action",action);p.put("amount",amount.getText().toString());p.put("note",note.getText().toString());}catch(Exception ignored){} postJson("/api/mobile/debt/update",p,r->showDebt(category)); }).show();
    }

    private void createDebtorDialog(String category) {
        LinearLayout box=new LinearLayout(this);box.setOrientation(LinearLayout.VERTICAL);box.setPadding(dp(18),dp(6),dp(18),0); EditText name=input(category.equals("Firma")?"Firma adı":"Ad Soyad");box.addView(name); EditText phone=input("Telefon"); addDialogField(box,phone); EditText amount=input("İlkin borc (0 ola bilər)");amount.setInputType(InputType.TYPE_CLASS_NUMBER|InputType.TYPE_NUMBER_FLAG_DECIMAL);addDialogField(box,amount); EditText note=input("Qeyd");addDialogField(box,note);
        new AlertDialog.Builder(this).setTitle("Yeni borclu • "+category).setView(box).setNegativeButton("Ləğv",null).setPositiveButton("Yarat",(d,w)->{ JSONObject p=new JSONObject();try{p.put("category",category);p.put("full_name",name.getText().toString());p.put("phone",phone.getText().toString());p.put("amount",amount.getText().toString());p.put("note",note.getText().toString());}catch(Exception ignored){} postJson("/api/mobile/debt/create",p,r->showDebt(category)); }).show();
    }

    private void addDialogField(LinearLayout box, EditText e) { LinearLayout.LayoutParams p=new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,dp(54));p.setMargins(0,dp(8),0,0);e.setLayoutParams(p);box.addView(e); }

    private void showDebtHistory(JSONObject record) {
        ScrollView sv=screenWithBody("Tarixçə",true,()->showDebt(record.optString("category","İşçi"))); LinearLayout body=scrollBody(sv); LinearLayout head=card();head.addView(text(record.optString("full_name",""),19,TEXT,true));head.addView(text("Cari borc: "+money(record.optDouble("total_debt",0)),15,GREEN,true),new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,dp(38)));body.addView(head); JSONArray h=record.optJSONArray("history"); if(h==null||h.length()==0){body.addView(empty("Tarixçə yoxdur."));return;} for(int i=0;i<h.length();i++){JSONObject x=h.optJSONObject(i);if(x==null)continue;LinearLayout c=card();c.addView(text(x.optString("action","Əməliyyat")+"  •  "+money(x.optDouble("amount",0)),15,TEXT,true));c.addView(text(x.optString("timestamp","")+"  •  Qalıq: "+money(x.optDouble("balance_after",0)),12,MUTED,true),new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,dp(34)));if(!x.optString("note","").isEmpty())c.addView(text(x.optString("note",""),13,MUTED,false));body.addView(c);} }

    private void showKitchen() { showKitchen("Hazırlanır"); }

    private void showKitchen(String category) {
        currentBackAction = null;
        clear();

        LinearLayout shell = new LinearLayout(this);
        shell.setOrientation(LinearLayout.VERTICAL);
        shell.setPadding(dp(8), dp(10), dp(8), 0);
        content.addView(shell, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        shell.addView(buildMainHeader("Mətbəx", false, null));

        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        shell.addView(scroll, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f));

        LinearLayout body = scrollBody(scroll);
        body.setPadding(0, dp(4), 0, dp(92));

        LinearLayout recordsHost = new LinearLayout(this);
        recordsHost.setOrientation(LinearLayout.VERTICAL);
        body.addView(recordsHost);

        installKitchenGestures(scroll, category);
        installKitchenGestures(body, category);

        LinearLayout footer = buildKitchenFooter(category);
        shell.addView(footer, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(86)));

        loadJson("/api/mobile/kitchen/tickets", result -> {
            JSONArray tickets = result.optJSONArray("tickets");
            if (tickets == null) tickets = new JSONArray();
            renderKitchenTickets(recordsHost, tickets, category);
        });
    }

    private void installKitchenGestures(View target, String category) {
        final int swipeThreshold = dp(48);
        GestureDetector detector = new GestureDetector(this, new GestureDetector.SimpleOnGestureListener() {
            @Override
            public boolean onDown(MotionEvent e) {
                return true;
            }

            @Override
            public boolean onFling(MotionEvent e1, MotionEvent e2, float velocityX, float velocityY) {
                if (e1 == null || e2 == null) return false;
                float dx = e2.getX() - e1.getX();
                float dy = e2.getY() - e1.getY();
                if (Math.abs(dx) < swipeThreshold || Math.abs(dx) <= Math.abs(dy)) return false;

                if (dx > 0) {
                    // Hazırdır -> Hazırlanır -> sol menyu.
                    if ("Hazırdır".equals(category)) showKitchen("Hazırlanır");
                    else showNavigationMenu();
                } else {
                    // Hazırlanır -> Hazırdır.
                    if ("Hazırlanır".equals(category)) showKitchen("Hazırdır");
                }
                return true;
            }
        });
        target.setOnTouchListener((v, event) -> {
            detector.onTouchEvent(event);
            return false;
        });
    }

    private LinearLayout buildKitchenFooter(String activeCategory) {
        LinearLayout footer = new LinearLayout(this);
        footer.setOrientation(LinearLayout.HORIZONTAL);
        footer.setGravity(Gravity.CENTER);
        footer.setPadding(dp(6), dp(8), dp(6), dp(8));
        footer.setBackground(bg(Color.WHITE, 22, BORDER));
        footer.setElevation(dp(12));

        String[] icons = {"⏳", "✅"};
        for (int i = 0; i < KITCHEN_CATEGORIES.length; i++) {
            String category = KITCHEN_CATEGORIES[i];
            boolean active = category.equals(activeCategory);
            LinearLayout tab = buildKitchenFooterTab(icons[i], category, active);
            LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.MATCH_PARENT, 1f);
            if (i > 0) lp.setMargins(dp(6), 0, 0, 0);
            tab.setLayoutParams(lp);
            tab.setOnClickListener(v -> showKitchen(category));
            footer.addView(tab);
        }
        return footer;
    }

    private LinearLayout buildKitchenFooterTab(String iconText, String label, boolean active) {
        LinearLayout tab = new LinearLayout(this);
        tab.setOrientation(LinearLayout.VERTICAL);
        tab.setGravity(Gravity.CENTER);
        tab.setPadding(dp(8), dp(6), dp(8), dp(6));
        int bgColor = active ? Color.rgb(238, 241, 245) : Color.WHITE;
        int stroke = active ? Color.rgb(210, 218, 226) : Color.TRANSPARENT;
        tab.setBackground(bg(bgColor, 18, stroke));

        TextView icon = text(iconText, 17, active ? TEXT : MUTED, false);
        icon.setGravity(Gravity.CENTER);
        tab.addView(icon, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(24)));

        TextView title = text(label, 14, active ? TEXT : MUTED, true);
        title.setGravity(Gravity.CENTER);
        tab.addView(title, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(24)));
        return tab;
    }

    private void renderKitchenTickets(LinearLayout host, JSONArray tickets, String category) {
        host.removeAllViews();
        boolean showReady = "Hazırdır".equals(category);
        int visible = 0;
        for (int i = 0; i < tickets.length(); i++) {
            JSONObject t = tickets.optJSONObject(i);
            if (t == null) continue;
            boolean isReady = "ready".equalsIgnoreCase(t.optString("status", ""));
            if (showReady != isReady) continue;
            visible++;

            LinearLayout c = card();
            LinearLayout row = new LinearLayout(this);
            row.setOrientation(LinearLayout.HORIZONTAL);
            row.addView(text(t.optString("station_name", ""), 18, TEXT, true), new LinearLayout.LayoutParams(0, dp(40), 1f));
            row.addView(text(t.optString("status_label", showReady ? "Hazırdır" : "Hazırlanır"), 14, isReady ? GREEN : BLUE, true), new LinearLayout.LayoutParams(dp(110), dp(40)));
            c.addView(row);
            c.addView(text(t.optString("created_at_text", "") + "  •  " + t.optInt("total_qty", 0) + " məhsul", 12, MUTED, true), new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(32)));

            JSONArray items = t.optJSONArray("items");
            if (items != null) {
                for (int j = 0; j < items.length(); j++) {
                    JSONObject it = items.optJSONObject(j);
                    if (it != null) c.addView(text("• " + it.optString("name", "") + " x" + it.optInt("qty", 1), 14, TEXT, false), new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(30)));
                }
            }

            LinearLayout a = new LinearLayout(this);
            a.setOrientation(LinearLayout.HORIZONTAL);
            int id = t.optInt("id", 0);
            if (showReady) {
                Button undo = button("Geri al", Color.rgb(238, 246, 255), BLUE);
                a.addView(undo, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(46)));
                undo.setOnClickListener(v -> updateKitchen(id, "preparing", category));
            } else {
                Button ready = button("Hazırdır", Color.rgb(234, 247, 241), GREEN);
                a.addView(ready, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(46)));
                ready.setOnClickListener(v -> updateKitchen(id, "ready", category));
            }
            c.addView(a);
            host.addView(c);
        }
        if (visible == 0) host.addView(empty(showReady ? "Hazırdır sifarişi yoxdur." : "Hazırlanır sifarişi yoxdur."));
    }

    private void updateKitchen(int ticketId,String status,String refreshCategory){JSONObject p=new JSONObject();try{p.put("ticket_id",ticketId);p.put("status",status);}catch(Exception ignored){}postJson("/api/mobile/kitchen/ticket/update",p,r->showKitchen(refreshCategory));}

    private void logout() {
        currentBackAction = null;
        sessionPassword = "";
        canHall = false;
        canKitchen = false;
        canAdmin = false;
        orderCarts.clear();
        prefs.edit().putBoolean(KEY_AUTO_LOGIN, false).apply();
        if (sessionToken.isEmpty()) { showLogin(); return; }
        String old=sessionToken; sessionToken=""; io.execute(()->{try{request(serverBase,"/api/mobile/logout","POST",new JSONObject(),old);}catch(Exception ignored){}runOnUiThread(this::showLogin);});
    }

    private TextView empty(String msg) { TextView e=text(msg,14,MUTED,true);e.setGravity(Gravity.CENTER);e.setBackground(bg(CARD,16,BORDER));e.setPadding(dp(12),dp(20),dp(12),dp(20));return e; }
    private void spacer(LinearLayout l,int h){View v=new View(this);l.addView(v,new LinearLayout.LayoutParams(1,dp(h)));}
    private String money(double v){return String.format(Locale.US,"%.2f AZN",v);}
    private void toast(String s){runOnUiThread(()->Toast.makeText(this,s,Toast.LENGTH_LONG).show());}
    private String urlEncode(String s){try{return java.net.URLEncoder.encode(s,"UTF-8");}catch(Exception e){return s;}}

    private interface JsonConsumer { void accept(JSONObject object); }

    private void loadJson(String path, JsonConsumer success) {
        setBusy(true); io.execute(()->{try{JSONObject r=request(serverBase,path,"GET",null,sessionToken);runOnUiThread(()->success.accept(r));}catch(Exception ex){handleApiError(ex);}finally{setBusy(false);}});
    }
    private void postJson(String path, JSONObject payload, JsonConsumer success) {
        setBusy(true); io.execute(()->{try{JSONObject r=request(serverBase,path,"POST",payload,sessionToken);runOnUiThread(()->success.accept(r));}catch(Exception ex){handleApiError(ex);}finally{setBusy(false);}});
    }
    private void handleApiError(Exception ex){String m=ex.getMessage()==null?"Bağlantı xətası":ex.getMessage();if(m.contains("401")||m.toLowerCase(Locale.ROOT).contains("sessiya")){sessionToken="";runOnUiThread(()->{toast("Sessiya bitib. Yenidən daxil olun.");showLogin();});}else showError(ex);}
    private void showError(Exception ex){toast(ex.getMessage()==null?ex.toString():ex.getMessage());}

    private JSONObject request(String base, String path, String method, JSONObject payload, String token) throws Exception {
        URL url=new URL(base+path); HttpURLConnection c=(HttpURLConnection)url.openConnection(); c.setConnectTimeout(7000);c.setReadTimeout(10000);c.setRequestMethod(method);c.setRequestProperty("Accept","application/json"); if(token!=null&&!token.isEmpty())c.setRequestProperty("X-Session-Token",token);
        if(payload!=null&&method.equals("POST")){c.setDoOutput(true);c.setRequestProperty("Content-Type","application/json; charset=utf-8");byte[] bytes=payload.toString().getBytes(StandardCharsets.UTF_8);try(OutputStream os=c.getOutputStream()){os.write(bytes);}}
        int code=c.getResponseCode();InputStream is=(code>=200&&code<300)?c.getInputStream():c.getErrorStream();StringBuilder sb=new StringBuilder();if(is!=null)try(BufferedReader br=new BufferedReader(new InputStreamReader(is,StandardCharsets.UTF_8))){String line;while((line=br.readLine())!=null)sb.append(line);}String raw=sb.toString();JSONObject obj=raw.isEmpty()?new JSONObject():new JSONObject(raw);if(code<200||code>=300){String err=obj.optString("error","HTTP "+code);throw new Exception(code+": "+err);}return obj;
    }

    private static class OrderCartItem {
        final String barcode;
        String name;
        double price;
        int qty;

        OrderCartItem(String barcode, String name, double price, int qty) {
            this.barcode = barcode == null ? "" : barcode;
            this.name = name == null ? "" : name;
            this.price = price;
            this.qty = qty;
        }
    }

    private static class SimpleTextWatcher implements android.text.TextWatcher {
        private final Runnable action; SimpleTextWatcher(Runnable action){this.action=action;}
        public void beforeTextChanged(CharSequence s,int start,int count,int after){}
        public void onTextChanged(CharSequence s,int start,int before,int count){action.run();}
        public void afterTextChanged(android.text.Editable s){}
    }
}
