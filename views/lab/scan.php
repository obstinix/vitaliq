<div class="app-head">
  <div>
    <h1>Scan a barcode</h1>
    <p>Use a handheld scanner, type the 12 digits, or scan with this device's camera. The last digit is a check digit, so a mistyped code is caught.</p>
  </div>
</div>
<div class="vq-grid">
  <section class="panel span-6">
    <form method="post" action="<?= e(url('/lab/scan')) ?>" novalidate data-scan-form>
      <?= csrf_field() ?>
      <div class="field">
        <label for="f-barcode">Sample barcode</label>
        <input class="input input--mono" id="f-barcode" name="barcode" inputmode="numeric" autocomplete="off" autofocus maxlength="20" value="<?= e(old($old ?? [], 'barcode')) ?>"<?= aria_invalid($errors ?? [], 'barcode') ?>>
        <?= field_error($errors ?? [], 'barcode') ?>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Find sample</button></div>
    </form>
  </section>
  <section class="panel span-6" data-camera hidden>
    <div class="panel__head"><h2>Camera</h2></div>
    <video class="scan-video" data-camera-video playsinline muted></video>
    <p class="hint mt-4" data-camera-status>Point the camera at the QR code on the sample label.</p>
    <button class="btn btn--secondary mt-4" type="button" data-camera-start>Start camera</button>
  </section>
</div>
