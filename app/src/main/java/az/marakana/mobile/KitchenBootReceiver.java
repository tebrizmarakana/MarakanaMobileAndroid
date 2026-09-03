package az.marakana.mobile;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Build;

public class KitchenBootReceiver extends BroadcastReceiver {
    private static final String PREFS = "marakana_native_mobile";
    private static final String KEY_ROLE = "role";
    private static final String KEY_KITCHEN_BG_PASSWORD_ENC = "kitchen_bg_password_enc";
    private static final String KEY_KITCHEN_BG_PASSWORD_IV = "kitchen_bg_password_iv";

    @Override
    public void onReceive(Context context, Intent intent) {
        SharedPreferences prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
        boolean kitchen = "kitchen".equals(prefs.getString(KEY_ROLE, "hall"));
        boolean hasPassword = !prefs.getString(KEY_KITCHEN_BG_PASSWORD_ENC, "").isEmpty()
                && !prefs.getString(KEY_KITCHEN_BG_PASSWORD_IV, "").isEmpty();
        if (!kitchen || !hasPassword) return;

        Intent serviceIntent = new Intent(context, KitchenBackgroundService.class);
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) context.startForegroundService(serviceIntent);
            else context.startService(serviceIntent);
        } catch (Exception ignored) {}
    }
}
