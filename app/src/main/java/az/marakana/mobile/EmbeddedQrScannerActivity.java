package az.marakana.mobile;

import android.Manifest;
import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.os.Bundle;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.ComponentActivity;
import androidx.camera.core.CameraSelector;
import androidx.camera.core.ImageAnalysis;
import androidx.camera.core.ImageProxy;
import androidx.camera.core.Preview;
import androidx.camera.lifecycle.ProcessCameraProvider;
import androidx.camera.view.PreviewView;
import androidx.core.content.ContextCompat;

import com.google.common.util.concurrent.ListenableFuture;
import com.google.mlkit.vision.barcode.BarcodeScanner;
import com.google.mlkit.vision.barcode.BarcodeScanning;
import com.google.mlkit.vision.barcode.BarcodeScannerOptions;
import com.google.mlkit.vision.barcode.common.Barcode;
import com.google.mlkit.vision.common.InputImage;

import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * In-app camera reader using bundled ML Kit model. No third-party scanner app,
 * browser or QR-reading Play Services overlay is needed.
 */
public final class EmbeddedQrScannerActivity extends ComponentActivity {
    public static final String RESULT_QR = "scanned_qr";
    private static final int CAMERA_REQUEST = 481;
    private PreviewView cameraPreview;
    private ProcessCameraProvider provider;
    private BarcodeScanner barcodeScanner;
    private final AtomicBoolean finishedScan = new AtomicBoolean(false);
    private final ExecutorService cameraExecutor = Executors.newSingleThreadExecutor();
    private boolean bindingStarted = false;

    private int dp(int n) { return Math.round(n * getResources().getDisplayMetrics().density); }

    @Override
    protected void onCreate(Bundle state) {
        super.onCreate(state);
        getWindow().setStatusBarColor(Color.rgb(7, 15, 28));
        getWindow().setNavigationBarColor(Color.rgb(7, 15, 28));
        FrameLayout layout = new FrameLayout(this);
        layout.setBackgroundColor(Color.BLACK);
        cameraPreview = new PreviewView(this);
        cameraPreview.setImplementationMode(PreviewView.ImplementationMode.COMPATIBLE);
        layout.addView(cameraPreview, new FrameLayout.LayoutParams(-1, -1));

        LinearLayout top = new LinearLayout(this);
        top.setOrientation(LinearLayout.VERTICAL);
        top.setGravity(Gravity.CENTER_HORIZONTAL);
        top.setPadding(dp(16), dp(22), dp(16), dp(12));
        top.setBackgroundColor(0xB9071121);
        TextView heading = new TextView(this);
        heading.setText(getIntent().getBooleanExtra("admin_mode", false)
                ? "ADMIN QR TƏSDİQİ" : "MARAKANA • QR OXUYUCU");
        heading.setTypeface(null, Typeface.BOLD);
        heading.setTextColor(Color.WHITE);
        heading.setTextSize(21);
        heading.setGravity(Gravity.CENTER);
        top.addView(heading);
        TextView sub = new TextView(this);
        sub.setText("QR kodu çərçivənin içərisində saxlayın");
        sub.setTextColor(0xFFCBD5E1);
        sub.setTextSize(14);
        sub.setGravity(Gravity.CENTER);
        sub.setPadding(0, dp(8), 0, 0);
        top.addView(sub);
        layout.addView(top, new FrameLayout.LayoutParams(-1, -2, Gravity.TOP));

        View viewfinder = new View(this);
        GradientDrawable border = new GradientDrawable();
        border.setColor(0x18000000);
        border.setStroke(dp(3), 0xFF4ADE80);
        border.setCornerRadius(dp(23));
        viewfinder.setBackground(border);
        FrameLayout.LayoutParams guide = new FrameLayout.LayoutParams(dp(258), dp(258), Gravity.CENTER);
        layout.addView(viewfinder, guide);

        Button close = new Button(this);
        close.setAllCaps(false);
        close.setText("✕  Bağla");
        close.setTextColor(Color.WHITE);
        close.setBackgroundColor(0xFF1E293B);
        close.setOnClickListener(v -> finish());
        FrameLayout.LayoutParams closeParams = new FrameLayout.LayoutParams(-1, dp(52), Gravity.BOTTOM);
        closeParams.setMargins(dp(22), 0, dp(22), dp(30));
        layout.addView(close, closeParams);
        setContentView(layout);
        if (checkSelfPermission(Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
            startCamera();
        } else {
            requestPermissions(new String[]{Manifest.permission.CAMERA}, CAMERA_REQUEST);
        }
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] results) {
        super.onRequestPermissionsResult(requestCode, permissions, results);
        if (requestCode != CAMERA_REQUEST) return;
        if (results.length > 0 && results[0] == PackageManager.PERMISSION_GRANTED) {
            startCamera();
        } else {
            Toast.makeText(this, "Kamera icazəsi verilmədi. Kodu əl ilə daxil edə bilərsiniz.",
                    Toast.LENGTH_LONG).show();
            finish();
        }
    }

    private void startCamera() {
        if (bindingStarted || isFinishing()) return;
        bindingStarted = true;
        try {
            BarcodeScannerOptions options = new BarcodeScannerOptions.Builder()
                    .setBarcodeFormats(Barcode.FORMAT_QR_CODE).build();
            barcodeScanner = BarcodeScanning.getClient(options);
            ListenableFuture<ProcessCameraProvider> future = ProcessCameraProvider.getInstance(this);
            future.addListener(() -> {
                if (isFinishing() || isDestroyed()) return;
                try {
                    provider = future.get();
                    Preview preview = new Preview.Builder().build();
                    preview.setSurfaceProvider(cameraPreview.getSurfaceProvider());
                    ImageAnalysis analysis = new ImageAnalysis.Builder()
                            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                            .build();
                    analysis.setAnalyzer(cameraExecutor, this::analyzeFrame);
                    provider.unbindAll();
                    provider.bindToLifecycle(this, CameraSelector.DEFAULT_BACK_CAMERA,
                            preview, analysis);
                } catch (Exception e) {
                    cameraError();
                }
            }, ContextCompat.getMainExecutor(this));
        } catch (Exception e) {
            cameraError();
        }
    }

    @androidx.camera.core.ExperimentalGetImage
    private void analyzeFrame(ImageProxy proxy) {
        if (finishedScan.get() || proxy.getImage() == null) {
            proxy.close();
            return;
        }
        try {
            InputImage image = InputImage.fromMediaImage(
                    proxy.getImage(), proxy.getImageInfo().getRotationDegrees());
            barcodeScanner.process(image)
                    .addOnSuccessListener(barcodes -> {
                        for (Barcode barcode : barcodes) {
                            String value = barcode.getRawValue();
                            if (value != null && !value.trim().isEmpty()
                                    && finishedScan.compareAndSet(false, true)) {
                                Intent result = new Intent();
                                result.putExtra(RESULT_QR, value);
                                setResult(Activity.RESULT_OK, result);
                                finish();
                                break;
                            }
                        }
                    })
                    .addOnCompleteListener(task -> proxy.close());
        } catch (Exception ex) {
            proxy.close();
        }
    }

    private void cameraError() {
        Toast.makeText(this, "Kamera açıla bilmədi. 6 rəqəmli kodu əl ilə yaza bilərsiniz.",
                Toast.LENGTH_LONG).show();
        finish();
    }

    @Override
    protected void onDestroy() {
        if (provider != null) provider.unbindAll();
        if (barcodeScanner != null) barcodeScanner.close();
        cameraExecutor.shutdown();
        super.onDestroy();
    }
}
