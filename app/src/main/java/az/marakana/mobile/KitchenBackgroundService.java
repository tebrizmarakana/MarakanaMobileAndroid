package az.marakana.mobile;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.media.AudioAttributes;
import android.media.RingtoneManager;
import android.net.Uri;
import android.os.Build;
import android.os.IBinder;
import android.util.Base64;

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
import java.util.HashSet;
import java.util.Locale;
import java.util.Set;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import javax.crypto.Cipher;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;

public class KitchenBackgroundService extends Service {
    private static final String PREFS = "marakana_native_mobile";
    private static final String KEY_SERVER = "server_base";
    private static final String KEY_USERNAME = "username";
    private static final String KEY_ROLE = "role";
    private static final String KEY_KITCHEN_BG_PASSWORD_ENC = "kitchen_bg_password_enc";
    private static final String KEY_KITCHEN_BG_PASSWORD_IV = "kitchen_bg_password_iv";
    private static final String KEY_KITCHEN_BG_SNAPSHOT_INITIALIZED = "kitchen_bg_snapshot_initialized";
    private static final String KEY_KITCHEN_BG_KNOWN_TICKETS = "kitchen_bg_known_tickets";
    private static final String KEY_KITCHEN_NOTIFICATION_SOUND = "kitchen_notification_sound";
    private static final String KITCHEN_NOTIFICATION_SILENT = "__silent__";
    private static final String KITCHEN_NOTIFICATION_CHANNEL_PREFIX = "marakana_kitchen_orders_";
    private static final String SERVICE_CHANNEL_ID = "marakana_kitchen_background_v32";
    private static final String KEYSTORE_ALIAS = "marakana_mobile_login_key";
    private static final int SERVICE_NOTIFICATION_ID = 32001;
    private static final long POLL_DELAY_MS = 1000L;
    private static final long RETRY_DELAY_MS = 3000L;

    private final ExecutorService worker = Executors.newSingleThreadExecutor();
    private volatile boolean running = false;
    private SharedPreferences prefs;
    private String sessionToken = "";

    @Override
    public void onCreate() {
        super.onCreate();
        prefs = getSharedPreferences(PREFS, MODE_PRIVATE);
        createServiceChannel();
        startForeground(SERVICE_NOTIFICATION_ID, buildServiceNotification());
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        if (!"kitchen".equals(prefs.getString(KEY_ROLE, "hall"))) {
            stopSelf();
            return START_NOT_STICKY;
        }
        if (!running) {
            running = true;
            worker.execute(this::monitorLoop);
        }
        return START_STICKY;
    }

    @Override
    public void onDestroy() {
        running = false;
        sessionToken = "";
        worker.shutdownNow();
        super.onDestroy();
    }

    @Override
    public IBinder onBind(Intent intent) { return null; }

    private void monitorLoop() {
        while (running) {
            if (!"kitchen".equals(prefs.getString(KEY_ROLE, "hall"))) {
                stopSelf();
                return;
            }

            String serverBase = normalizeServerBase(prefs.getString(KEY_SERVER, ""));
            String username = prefs.getString(KEY_USERNAME, "").trim();
            String password = loadKitchenBackgroundPassword();
            if (serverBase.isEmpty() || username.isEmpty() || password.isEmpty()) {
                sleep(RETRY_DELAY_MS);
                continue;
            }

            try {
                if (sessionToken.isEmpty()) sessionToken = login(serverBase, username, password);
                JSONObject result = request(serverBase, "/api/mobile/kitchen/tickets", "GET", null, sessionToken);
                JSONArray tickets = result.optJSONArray("tickets");
                if (tickets == null) tickets = new JSONArray();
                processTickets(tickets);
                sleep(POLL_DELAY_MS);
            } catch (Exception ex) {
                String message = ex.getMessage() == null ? "" : ex.getMessage().toLowerCase(Locale.ROOT);
                if (message.contains("401") || message.contains("sessiya") || message.contains("token")) {
                    sessionToken = "";
                }
                sleep(RETRY_DELAY_MS);
            }
        }
    }

    private String login(String serverBase, String username, String password) throws Exception {
        JSONObject payload = new JSONObject();
        payload.put("username", username);
        payload.put("password", password);
        payload.put("role", "kitchen");
        JSONObject result = request(serverBase, "/api/mobile/login", "POST", payload, "");
        String token = result.optString("token", "").trim();
        if (token.isEmpty()) throw new RuntimeException("Mətbəx fon sessiyası yaradılmadı.");
        return token;
    }

    private void processTickets(JSONArray tickets) {
        Set<String> known = new HashSet<>(prefs.getStringSet(KEY_KITCHEN_BG_KNOWN_TICKETS, new HashSet<>()));
        Set<String> current = new HashSet<>();
        boolean initialized = prefs.getBoolean(KEY_KITCHEN_BG_SNAPSHOT_INITIALIZED, false);

        for (int i = 0; i < tickets.length(); i++) {
            JSONObject ticket = tickets.optJSONObject(i);
            if (ticket == null) continue;
            String key = ticketKey(ticket);
            current.add(key);
            boolean ready = "ready".equalsIgnoreCase(ticket.optString("status", ""));
            if (initialized && !known.contains(key) && !ready) {
                showKitchenOrderNotification(ticket);
            }
        }

        if (!initialized) {
            known.clear();
            known.addAll(current);
            initialized = true;
        } else {
            known.addAll(current);
            if (known.size() > 1500) {
                known.clear();
                known.addAll(current);
            }
        }

        prefs.edit()
                .putBoolean(KEY_KITCHEN_BG_SNAPSHOT_INITIALIZED, initialized)
                .putStringSet(KEY_KITCHEN_BG_KNOWN_TICKETS, new HashSet<>(known))
                .apply();
    }

    private String ticketKey(JSONObject ticket) {
        int id = ticket.optInt("id", 0);
        if (id > 0) return "id:" + id;
        return ticket.optString("station_name", "") + "|"
                + ticket.optString("created_at_text", "") + "|"
                + ticket.optString("created_at", "") + "|"
                + String.valueOf(ticket.optJSONArray("items"));
    }

    private void showKitchenOrderNotification(JSONObject ticket) {
        String station = ticket.optString("station_name", "Mətbəx");
        int totalQty = ticket.optInt("total_qty", 0);
        if (totalQty <= 0) {
            JSONArray items = ticket.optJSONArray("items");
            if (items != null) {
                for (int i = 0; i < items.length(); i++) {
                    JSONObject item = items.optJSONObject(i);
                    if (item != null) totalQty += Math.max(1, item.optInt("qty", 1));
                }
            }
        }
        String shortText = station + " • " + totalQty + " məhsul";
        StringBuilder details = new StringBuilder(shortText);
        JSONArray items = ticket.optJSONArray("items");
        if (items != null) {
            for (int i = 0; i < items.length(); i++) {
                JSONObject item = items.optJSONObject(i);
                if (item == null) continue;
                details.append("\n• ")
                        .append(item.optString("name", "Məhsul"))
                        .append(" x")
                        .append(item.optInt("qty", 1));
            }
        }

        Intent openApp = new Intent(this, MainActivity.class);
        openApp.putExtra("open_kitchen", true);
        openApp.addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP | Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_NEW_TASK);
        int ticketId = ticket.optInt("id", 0);
        int requestCode = ticketId > 0 ? 51000 + ticketId : (int) (System.currentTimeMillis() & 0x7fffffff);
        PendingIntent contentIntent = PendingIntent.getActivity(
                this,
                requestCode,
                openApp,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );

        String channelId = ensureKitchenOrderChannel();
        Notification.Builder builder = new Notification.Builder(this, channelId)
                .setSmallIcon(android.R.drawable.ic_dialog_info)
                .setContentTitle("Yeni mətbəx sifarişi")
                .setContentText(shortText)
                .setStyle(new Notification.BigTextStyle().bigText(details.toString()))
                .setContentIntent(contentIntent)
                .setAutoCancel(true)
                .setCategory(Notification.CATEGORY_MESSAGE)
                .setPriority(Notification.PRIORITY_HIGH)
                .setVisibility(Notification.VISIBILITY_PUBLIC)
                .setWhen(System.currentTimeMillis())
                .setShowWhen(true);

        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager != null) {
            int notificationId = ticketId > 0 ? 41000 + ticketId : requestCode;
            manager.notify(notificationId, builder.build());
        }
    }

    private void createServiceChannel() {
        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager == null || Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return;
        if (manager.getNotificationChannel(SERVICE_CHANNEL_ID) == null) {
            NotificationChannel channel = new NotificationChannel(
                    SERVICE_CHANNEL_ID,
                    "Mətbəx fon xidməti",
                    NotificationManager.IMPORTANCE_LOW
            );
            channel.setDescription("Tətbiq bağlı olanda da mətbəx sifarişlərini yoxlayır.");
            channel.setSound(null, null);
            channel.enableVibration(false);
            manager.createNotificationChannel(channel);
        }
    }

    private Notification buildServiceNotification() {
        Intent openApp = new Intent(this, MainActivity.class);
        openApp.putExtra("open_kitchen", true);
        PendingIntent pendingIntent = PendingIntent.getActivity(
                this,
                32002,
                openApp,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );
        return new Notification.Builder(this, SERVICE_CHANNEL_ID)
                .setSmallIcon(android.R.drawable.ic_popup_sync)
                .setContentTitle("Marakana Mətbəx")
                .setContentText("Fon bildirişləri aktivdir")
                .setContentIntent(pendingIntent)
                .setOngoing(true)
                .setCategory(Notification.CATEGORY_SERVICE)
                .build();
    }

    private String kitchenNotificationChannelId() {
        String stored = prefs.getString(KEY_KITCHEN_NOTIFICATION_SOUND, "");
        if (stored == null || stored.trim().isEmpty()) stored = "default";
        return KITCHEN_NOTIFICATION_CHANNEL_PREFIX + Integer.toHexString(stored.hashCode());
    }

    private Uri getKitchenNotificationSoundUri() {
        String stored = prefs.getString(KEY_KITCHEN_NOTIFICATION_SOUND, "");
        if (KITCHEN_NOTIFICATION_SILENT.equals(stored)) return null;
        if (stored != null && !stored.trim().isEmpty()) {
            try { return Uri.parse(stored); } catch (Exception ignored) {}
        }
        return RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION);
    }

    private String ensureKitchenOrderChannel() {
        String channelId = kitchenNotificationChannelId();
        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager == null) return channelId;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && manager.getNotificationChannel(channelId) == null) {
            NotificationChannel channel = new NotificationChannel(
                    channelId,
                    "Mətbəx sifarişləri",
                    NotificationManager.IMPORTANCE_HIGH
            );
            channel.setDescription("Yeni mətbəx sifarişi gələndə yuxarı bildiriş və səs göstərir.");
            channel.enableVibration(true);
            Uri sound = getKitchenNotificationSoundUri();
            if (sound == null) {
                channel.setSound(null, null);
            } else {
                AudioAttributes attributes = new AudioAttributes.Builder()
                        .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                        .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                        .build();
                channel.setSound(sound, attributes);
            }
            manager.createNotificationChannel(channel);
        }
        return channelId;
    }

    private String loadKitchenBackgroundPassword() {
        String enc = prefs.getString(KEY_KITCHEN_BG_PASSWORD_ENC, "");
        String iv = prefs.getString(KEY_KITCHEN_BG_PASSWORD_IV, "");
        if (enc.isEmpty() || iv.isEmpty()) return "";
        try {
            KeyStore keyStore = KeyStore.getInstance("AndroidKeyStore");
            keyStore.load(null);
            if (!keyStore.containsAlias(KEYSTORE_ALIAS)) return "";
            SecretKey key = ((KeyStore.SecretKeyEntry) keyStore.getEntry(KEYSTORE_ALIAS, null)).getSecretKey();
            Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
            GCMParameterSpec spec = new GCMParameterSpec(128, Base64.decode(iv, Base64.NO_WRAP));
            cipher.init(Cipher.DECRYPT_MODE, key, spec);
            byte[] raw = cipher.doFinal(Base64.decode(enc, Base64.NO_WRAP));
            return new String(raw, StandardCharsets.UTF_8);
        } catch (Exception ex) {
            return "";
        }
    }

    private String normalizeServerBase(String raw) {
        String value = raw == null ? "" : raw.trim();
        if (value.isEmpty()) return "";
        if (!value.startsWith("http://") && !value.startsWith("https://")) value = "http://" + value;
        while (value.endsWith("/")) value = value.substring(0, value.length() - 1);
        return value;
    }

    private JSONObject request(String serverBase, String path, String method, JSONObject payload, String token) throws Exception {
        HttpURLConnection connection = null;
        try {
            URL url = new URL(serverBase + path);
            connection = (HttpURLConnection) url.openConnection();
            connection.setRequestMethod(method);
            connection.setConnectTimeout(5000);
            connection.setReadTimeout(5000);
            connection.setRequestProperty("Accept", "application/json");
            connection.setRequestProperty("Content-Type", "application/json; charset=utf-8");
            if (token != null && !token.isEmpty()) connection.setRequestProperty("X-Session-Token", token);
            if (payload != null && !"GET".equalsIgnoreCase(method)) {
                connection.setDoOutput(true);
                byte[] raw = payload.toString().getBytes(StandardCharsets.UTF_8);
                try (OutputStream os = connection.getOutputStream()) { os.write(raw); }
            }

            int code = connection.getResponseCode();
            InputStream stream = code >= 200 && code < 300 ? connection.getInputStream() : connection.getErrorStream();
            String body = readAll(stream);
            if (code < 200 || code >= 300) throw new RuntimeException("HTTP " + code + ": " + body);
            if (body.trim().isEmpty()) return new JSONObject();
            return new JSONObject(body);
        } finally {
            if (connection != null) connection.disconnect();
        }
    }

    private String readAll(InputStream input) throws Exception {
        if (input == null) return "";
        StringBuilder sb = new StringBuilder();
        try (BufferedReader reader = new BufferedReader(new InputStreamReader(input, StandardCharsets.UTF_8))) {
            String line;
            while ((line = reader.readLine()) != null) sb.append(line);
        }
        return sb.toString();
    }

    private void sleep(long millis) {
        try { Thread.sleep(millis); }
        catch (InterruptedException ignored) { Thread.currentThread().interrupt(); }
    }
}
