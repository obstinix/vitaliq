/*
 * Camera scanning for /lab/scan (feature 14). Uses the browser's
 * BarcodeDetector where it exists; elsewhere the camera panel stays hidden
 * and the form works with a handheld scanner or typing.
 */
const panel = document.querySelector('[data-camera]');
const form = document.querySelector('[data-scan-form]');
const input = document.getElementById('f-barcode');

if (panel && form && input && 'BarcodeDetector' in window && navigator.mediaDevices?.getUserMedia) {
  panel.hidden = false;
  const video = panel.querySelector('[data-camera-video]');
  const status = panel.querySelector('[data-camera-status]');
  const start = panel.querySelector('[data-camera-start]');
  let stream = null;

  const stop = () => {
    stream?.getTracks().forEach((t) => t.stop());
    stream = null;
  };

  start.addEventListener('click', async () => {
    try {
      const detector = new window.BarcodeDetector({ formats: ['qr_code', 'code_128', 'ean_13'] });
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
      video.srcObject = stream;
      await video.play();
      start.hidden = true;
      status.textContent = 'Looking for a code…';
      const tick = async () => {
        if (!stream) return;
        try {
          const codes = await detector.detect(video);
          const hit = codes.map((c) => c.rawValue.replace(/\D/g, '')).find((v) => v.length === 12);
          if (hit) {
            input.value = hit;
            status.textContent = 'Found ' + hit + '.';
            stop();
            form.requestSubmit();
            return;
          }
        } catch (e) {
          /* a frame that cannot be read is skipped */
        }
        requestAnimationFrame(tick);
      };
      tick();
    } catch (e) {
      status.textContent = 'The camera could not be opened. Use a scanner or type the digits.';
      stop();
    }
  });
  window.addEventListener('pagehide', stop);
}
