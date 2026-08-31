package az.marakana.mobile;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.Context;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.graphics.drawable.ColorDrawable;
import android.os.Bundle;
import android.text.InputType;
import android.util.Base64;
import android.view.Gravity;
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

    private String serverBase = "";
    private String sessionToken = "";
    private String username = "";
    private String role = "hall";
    private String roleLabel = "Zal";
    private boolean adminDebtOnly = false;

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

    private int dp(int v) { return Math.round(v * getResources().getDisplayMetrics().density); }

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
        clear();
        LinearLayout shell = new LinearLayout(this);
        shell.setOrientation(LinearLayout.VERTICAL);
        shell.setPadding(dp(14), dp(12), dp(14), dp(14));
        content.addView(shell, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        LinearLayout header = buildMainHeader(title, back, backAction);
        shell.addView(header);

        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        shell.addView(scroll, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f));
        return scroll;
    }

    private LinearLayout buildMainHeader(String title, boolean back, Runnable backAction) {
        LinearLayout header = new LinearLayout(this);
        header.setOrientation(LinearLayout.HORIZONTAL);
        header.setGravity(Gravity.CENTER_VERTICAL);
        header.setPadding(dp(4), 0, dp(4), dp(10));

        if (back) {
            Button b = button("‹", CARD, TEXT);
            b.setTextSize(26);
            b.setLayoutParams(new LinearLayout.LayoutParams(dp(52), dp(46)));
            b.setOnClickListener(v -> { if (backAction != null) backAction.run(); });
            header.addView(b);
        } else if (!sessionToken.isEmpty()) {
            Button menu = button("☰", CARD, TEXT);
            menu.setTextSize(22);
            menu.setContentDescription("Menyu");
            menu.setLayoutParams(new LinearLayout.LayoutParams(dp(52), dp(46)));
            menu.setOnClickListener(v -> showNavigationMenu());
            header.addView(menu);
        }

        TextView h = text(title, 21, TEXT, true);
        LinearLayout.LayoutParams hp = new LinearLayout.LayoutParams(0, dp(48), 1f);
        hp.setMargins((back || !sessionToken.isEmpty()) ? dp(10) : 0, 0, 0, 0);
        header.addView(h, hp);
        return header;
    }

    private void showNavigationMenu() {
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
        FrameLayout.LayoutParams panelLp = new FrameLayout.LayoutParams((int)(getResources().getDisplayMetrics().widthPixels * 0.82f), ViewGroup.LayoutParams.MATCH_PARENT, Gravity.START);
        overlay.addView(panel, panelLp);

        TextView appTitle = text("Marakana Mobile", 22, TEXT, true);
        panel.addView(appTitle, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(42)));
        TextView account = text((username.isEmpty() ? "İstifadəçi" : username) + "  •  " + roleLabel, 13, MUTED, true);
        panel.addView(account, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(34)));
        spacer(panel, 8);

        final PopupWindow[] holder = new PopupWindow[1];
        if (role.equals("admin")) {
            addDrawerItem(panel, "Terminallar / Zal", () -> { holder[0].dismiss(); showTerminals(); });
            addDrawerItem(panel, "Borc Dəftəri", () -> { holder[0].dismiss(); showDebt("İşçi"); });
            addDrawerItem(panel, "Mətbəx", () -> { holder[0].dismiss(); showKitchen(); });
        } else if (role.equals("kitchen")) {
            addDrawerItem(panel, "Mətbəx", () -> { holder[0].dismiss(); showKitchen(); });
        } else {
            addDrawerItem(panel, "Terminallar / Zal", () -> { holder[0].dismiss(); showTerminals(); });
        }

        View flex = new View(this);
        panel.addView(flex, new LinearLayout.LayoutParams(1, 0, 1f));

        Button exit = button("Çıxış", Color.rgb(255, 246, 246), Color.rgb(176, 54, 54));
        exit.setLayoutParams(new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(52)));
        panel.addView(exit);

        PopupWindow popup = new PopupWindow(overlay, ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT, true);
        holder[0] = popup;
        popup.setBackgroundDrawable(new ColorDrawable(Color.TRANSPARENT));
        popup.setOutsideTouchable(true);
        scrim.setOnClickListener(v -> popup.dismiss());
        exit.setOnClickListener(v -> { popup.dismiss(); logout(); });
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
        body.setPadding(dp(2), dp(4), dp(2), dp(22));
        scroll.addView(body, new ScrollView.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        return body;
    }

    private String normalizeServerBase(String raw) {
        String v = raw == null ? "" : raw.trim();
        if (v.isEmpty()) return "";
        if (!v.toLowerCase(Locale.ROOT).startsWith("http://") && !v.toLowerCase(Locale.ROOT).startsWith("https://")) v = "http://" + v;
        while (v.endsWith("/")) v = v.substring(0, v.length() - 1);
        if (v.endsWith("/mobile")) v = v.substring(0, v.length() - 7);
        if (v.endsWith("/api")) v = v.substring(0, v.length() - 4);
        return v;
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

        EditText server = input("192.168.1.20:8765 və ya server domeni");
        server.setText(serverBase);
        body.addView(server);
        Button connect = button("Serverə qoşul", BLUE, Color.WHITE);
        LinearLayout.LayoutParams cp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(54));
        cp.setMargins(0, dp(12), 0, 0);
        connect.setLayoutParams(cp);
        body.addView(connect);
        connect.setOnClickListener(v -> {
            String value = normalizeServerBase(server.getText().toString());
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

        EditText user = input("İstifadəçi adı");
        user.setText(username);
        body.addView(user);
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
            String u = user.getText().toString().trim();
            String p = pass.getText().toString();
            int rolePosition = roleSpin.getSelectedItemPosition();
            String selectedRole = rolePosition == 1 ? "kitchen" : (rolePosition == 2 || rolePosition == 3) ? "admin" : "hall";
            boolean selectedDebtOnly = rolePosition == 3;
            if (u.isEmpty()) { toast("İstifadəçi adını yazın."); return; }
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
                Button remove = button("1 ədəd azalt", Color.rgb(255, 245, 239), ORANGE);
                remove.setLayoutParams(new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(44)));
                final String itemName = o.optString("name", ""); final double unit = o.optDouble("unit_price", 0);
                remove.setOnClickListener(v -> removeOrder(name, itemName, unit));
                oc.addView(remove);
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

    private void removeOrder(String station, String item, double unitPrice) {
        JSONObject p = new JSONObject(); try { p.put("station_name", station); p.put("name", item); p.put("unit_price", unitPrice); p.put("qty", 1); } catch(Exception ignored) {}
        postJson("/api/mobile/order/remove", p, r -> showStation(station));
    }

    private void showProducts(String station) {
        ScrollView sv = screenWithBody("Sifariş • " + station, true, () -> showStation(station));
        LinearLayout body = scrollBody(sv);
        EditText search = input("Məhsul axtar"); body.addView(search); spacer(body, 10);
        LinearLayout list = new LinearLayout(this); list.setOrientation(LinearLayout.VERTICAL); body.addView(list);
        Map<String,Integer> quantities = new HashMap<>();
        final JSONArray[] allProducts = {new JSONArray()};

        Button send = button("Sifarişi göndər", GREEN, Color.WHITE); send.setVisibility(View.GONE); body.addView(send);
        send.setOnClickListener(v -> {
            JSONArray items = new JSONArray();
            for (Map.Entry<String,Integer> e : quantities.entrySet()) if (e.getValue() > 0) { JSONObject x = new JSONObject(); try { x.put("barcode", e.getKey()); x.put("qty", e.getValue()); items.put(x); } catch(Exception ignored) {} }
            if (items.length()==0) return;
            JSONObject p = new JSONObject(); try { p.put("station_name", station); p.put("items", items); } catch(Exception ignored) {}
            postJson("/api/mobile/order/batch_add", p, r -> showStation(station));
        });

        loadJson("/api/mobile/products", result -> {
            JSONArray products = result.optJSONArray("products"); allProducts[0] = products == null ? new JSONArray() : products;
            Runnable render = () -> {
                list.removeAllViews(); String q = search.getText().toString().trim().toLowerCase(Locale.ROOT);
                for (int i=0;i<allProducts[0].length();i++) {
                    JSONObject product = allProducts[0].optJSONObject(i); if (product==null) continue;
                    String pn = product.optString("name", ""); if (!q.isEmpty() && !pn.toLowerCase(Locale.ROOT).contains(q)) continue;
                    String barcode = product.optString("barcode", "");
                    LinearLayout c = card(); LinearLayout top = new LinearLayout(this); top.setOrientation(LinearLayout.HORIZONTAL); top.setGravity(Gravity.CENTER_VERTICAL);
                    top.addView(text(pn, 15, TEXT, true), new LinearLayout.LayoutParams(0, dp(44), 1f));
                    top.addView(text(money(product.optDouble("price",0)), 14, GREEN, true), new LinearLayout.LayoutParams(dp(105), dp(44))); c.addView(top);
                    TextView qty = text("Seçim: " + quantities.getOrDefault(barcode,0), 13, MUTED, true); c.addView(qty, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(34)));
                    LinearLayout actions = new LinearLayout(this); actions.setOrientation(LinearLayout.HORIZONTAL);
                    Button minus = button("−", CARD, ORANGE); Button plus = button("+", Color.rgb(234,247,241), GREEN);
                    actions.addView(minus, new LinearLayout.LayoutParams(0, dp(44), 1f)); LinearLayout.LayoutParams pl=new LinearLayout.LayoutParams(0,dp(44),1f); pl.setMargins(dp(8),0,0,0); actions.addView(plus,pl); c.addView(actions);
                    plus.setOnClickListener(v -> { quantities.put(barcode, quantities.getOrDefault(barcode,0)+1); qty.setText("Seçim: " + quantities.get(barcode)); send.setVisibility(View.VISIBLE); });
                    minus.setOnClickListener(v -> { int nv=Math.max(0,quantities.getOrDefault(barcode,0)-1); quantities.put(barcode,nv); qty.setText("Seçim: "+nv); send.setVisibility(hasPositive(quantities)?View.VISIBLE:View.GONE); });
                    list.addView(c);
                }
            };
            search.addTextChangedListener(new SimpleTextWatcher(render)); render.run();
        });
    }

    private boolean hasPositive(Map<String,Integer> map) { for (Integer v: map.values()) if (v!=null && v>0) return true; return false; }

    private void showDebt(String category) {
        clear();
        LinearLayout shell = new LinearLayout(this);
        shell.setOrientation(LinearLayout.VERTICAL);
        shell.setPadding(dp(14), dp(12), dp(14), 0);
        content.addView(shell, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        shell.addView(buildMainHeader("Borc Dəftəri", false, null));

        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        shell.addView(scroll, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f));
        LinearLayout body = scrollBody(scroll);
        body.setPadding(dp(2), dp(4), dp(2), dp(14));

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

        shell.addView(buildDebtFooter(category), new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(72)));

        loadJson("/api/mobile/debt/list?category=" + urlEncode(category), result -> {
            JSONArray records = result.optJSONArray("records");
            if (records == null) records = new JSONArray();
            final JSONArray finalRecords = records;
            Runnable render = () -> renderDebtRecords(recordsHost, finalRecords, category, search.getText().toString());
            search.addTextChangedListener(new SimpleTextWatcher(render));
            render.run();
        });
    }

    private LinearLayout buildDebtFooter(String activeCategory) {
        LinearLayout footer = new LinearLayout(this);
        footer.setOrientation(LinearLayout.HORIZONTAL);
        footer.setGravity(Gravity.CENTER);
        footer.setPadding(dp(4), dp(8), dp(4), dp(8));
        footer.setBackground(bg(Color.WHITE, 18, BORDER));
        footer.setElevation(dp(8));

        String[] categories = {"İşçi", "Müştəri", "Firma"};
        for (int i = 0; i < categories.length; i++) {
            String category = categories[i];
            boolean active = category.equals(activeCategory);
            Button tab = button(category, active ? Color.rgb(232, 243, 255) : Color.WHITE, active ? BLUE : MUTED);
            tab.setTextSize(14);
            tab.setLayoutParams(new LinearLayout.LayoutParams(0, dp(54), 1f));
            LinearLayout.LayoutParams lp = (LinearLayout.LayoutParams) tab.getLayoutParams();
            if (i > 0) lp.setMargins(dp(4), 0, 0, 0);
            tab.setLayoutParams(lp);
            if (active) tab.setBackground(bg(Color.rgb(232, 243, 255), 14, BLUE));
            else tab.setBackground(bg(Color.WHITE, 14, Color.TRANSPARENT));
            tab.setOnClickListener(v -> showDebt(category));
            footer.addView(tab);
        }
        return footer;
    }

    private void renderDebtRecords(LinearLayout host, JSONArray records, String category, String query) {
        host.removeAllViews(); String q=query==null?"":query.trim().toLowerCase(Locale.ROOT); double total=0;
        for(int i=0;i<records.length();i++){ JSONObject r=records.optJSONObject(i); if(r==null)continue; total+=r.optDouble("total_debt",0); }
        LinearLayout sum=card(); sum.addView(text("Toplam borc",13,MUTED,true)); sum.addView(text(money(total),22,TEXT,true),new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,dp(40))); host.addView(sum);
        for(int i=0;i<records.length();i++) {
            JSONObject r=records.optJSONObject(i); if(r==null)continue;
            String hay=(r.optString("full_name","")+" "+r.optString("phone","")+" "+r.optString("id","")).toLowerCase(Locale.ROOT); if(!q.isEmpty()&&!hay.contains(q))continue;
            LinearLayout c=card(); LinearLayout row=new LinearLayout(this); row.setOrientation(LinearLayout.HORIZONTAL); row.setGravity(Gravity.CENTER_VERTICAL);
            row.addView(text(r.optString("full_name",""),17,TEXT,true),new LinearLayout.LayoutParams(0,dp(38),1f)); TextView amt=text(money(r.optDouble("total_debt",0)),17,GREEN,true); amt.setGravity(Gravity.RIGHT|Gravity.CENTER_VERTICAL); row.addView(amt,new LinearLayout.LayoutParams(dp(145),dp(38))); c.addView(row);
            c.addView(text(r.optString("phone","")+"  •  ID: "+r.optString("id","")+"  •  "+r.optString("last_change",""),12,MUTED,true),new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,dp(36)));
            LinearLayout acts=new LinearLayout(this); acts.setOrientation(LinearLayout.HORIZONTAL);
            Button inc=button("Artır",Color.rgb(234,247,241),GREEN); Button dec=button("Azalt",Color.rgb(255,245,239),ORANGE); Button hist=button("Tarixçə",Color.rgb(238,246,255),BLUE);
            acts.addView(inc,new LinearLayout.LayoutParams(0,dp(46),1f)); LinearLayout.LayoutParams x=new LinearLayout.LayoutParams(0,dp(46),1f);x.setMargins(dp(6),0,0,0);acts.addView(dec,x); LinearLayout.LayoutParams y=new LinearLayout.LayoutParams(0,dp(46),1f);y.setMargins(dp(6),0,0,0);acts.addView(hist,y); c.addView(acts);
            inc.setOnClickListener(v->debtChangeDialog(category,r,"increase")); dec.setOnClickListener(v->debtChangeDialog(category,r,"decrease")); hist.setOnClickListener(v->showDebtHistory(r)); host.addView(c);
        }
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

    private void showKitchen() {
        ScrollView sv=screenWithBody("Mətbəx",false,null); LinearLayout body=scrollBody(sv); loadJson("/api/mobile/kitchen/tickets",result->{JSONArray tickets=result.optJSONArray("tickets");if(tickets==null||tickets.length()==0){body.addView(empty("Mətbəx sifarişi yoxdur."));return;}for(int i=0;i<tickets.length();i++){JSONObject t=tickets.optJSONObject(i);if(t==null)continue;LinearLayout c=card();LinearLayout row=new LinearLayout(this);row.setOrientation(LinearLayout.HORIZONTAL);row.addView(text(t.optString("station_name",""),18,TEXT,true),new LinearLayout.LayoutParams(0,dp(40),1f));row.addView(text(t.optString("status_label","Yeni"),14,t.optString("status","").equals("ready")?GREEN:BLUE,true),new LinearLayout.LayoutParams(dp(110),dp(40)));c.addView(row);c.addView(text(t.optString("created_at_text","")+"  •  "+t.optInt("total_qty",0)+" məhsul",12,MUTED,true),new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,dp(32)));JSONArray items=t.optJSONArray("items");if(items!=null)for(int j=0;j<items.length();j++){JSONObject it=items.optJSONObject(j);if(it!=null)c.addView(text("• "+it.optString("name","")+" x"+it.optInt("qty",1),14,TEXT,false),new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,dp(30)));}LinearLayout a=new LinearLayout(this);a.setOrientation(LinearLayout.HORIZONTAL);Button prep=button("Hazırlanır",Color.rgb(238,246,255),BLUE);Button ready=button("Hazırdır",Color.rgb(234,247,241),GREEN);a.addView(prep,new LinearLayout.LayoutParams(0,dp(46),1f));LinearLayout.LayoutParams rp=new LinearLayout.LayoutParams(0,dp(46),1f);rp.setMargins(dp(8),0,0,0);a.addView(ready,rp);c.addView(a);int id=t.optInt("id",0);prep.setOnClickListener(v->updateKitchen(id,"preparing"));ready.setOnClickListener(v->updateKitchen(id,"ready"));body.addView(c);}}); }

    private void updateKitchen(int ticketId,String status){JSONObject p=new JSONObject();try{p.put("ticket_id",ticketId);p.put("status",status);}catch(Exception ignored){}postJson("/api/mobile/kitchen/ticket/update",p,r->showKitchen());}

    private void logout() {
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

    private static class SimpleTextWatcher implements android.text.TextWatcher {
        private final Runnable action; SimpleTextWatcher(Runnable action){this.action=action;}
        public void beforeTextChanged(CharSequence s,int start,int count,int after){}
        public void onTextChanged(CharSequence s,int start,int before,int count){action.run();}
        public void afterTextChanged(android.text.Editable s){}
    }
}
