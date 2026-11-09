window.faceEnroll = function (cfg = {}) {
  const opts = () => new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });
  let modelsLoaded = false;

  return {
    gallery: !!cfg.gallery,
    modelUrl: cfg.modelUrl || '/models',
    consent: false,
    mode: 'idle',        // idle | camera | preview | saving
    message: '',
    faceOk: false,
    busy: false,
    timer: null,
    stream: null,
    descriptor: null,
    photo: null,

    async loadModels() {
      if (typeof faceapi === 'undefined') {
        throw new Error('Library face-api.js tidak termuat. Periksa file public/js/face-api.js');
      }
      if (modelsLoaded) return;
      await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(this.modelUrl),
        faceapi.nets.faceLandmark68Net.loadFromUri(this.modelUrl),
        faceapi.nets.faceRecognitionNet.loadFromUri(this.modelUrl),
      ]);
      modelsLoaded = true;
    },

    needConsent() {
      if (this.consent) return false;
      this.message = 'Centang persetujuan terlebih dahulu.';
      return true;
    },

    // ---------- Kamera ----------
    async startCamera() {
      if (this.needConsent()) return;
      this.message = 'Menyiapkan kamera...';
      try {
        await this.loadModels();
        this.stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'user', width: { ideal: 960 }, height: { ideal: 720 } },
          audio: false,
        });
        const v = this.$refs.video;
        v.srcObject = this.stream;
        await v.play();
      } catch (e) {
        this.stopCamera();
        if (e && e.name === 'NotAllowedError') {
          this.message = 'Izin kamera ditolak. Aktifkan izin kamera di pengaturan browser.';
        } else if (e && e.message && e.message.includes('face-api')) {
          this.message = e.message;
        } else {
          this.message = 'Kamera/model gagal dimuat. Pastikan memakai HTTPS atau localhost.';
        }
        return;
      }
      this.mode = 'camera';
      this.message = 'Hadap lurus ke kamera';
      this.loop();
    },

    loop() {
      this.timer = setTimeout(async () => {
        if (this.mode !== 'camera') return;
        await this.checkLive();
        this.loop();
      }, 250);
    },

    async checkLive() {
      if (this.busy) return;
      this.busy = true;
      try {
        const v = this.$refs.video;
        const dets = await faceapi.detectAllFaces(v, opts());
        if (dets.length !== 1) {
          this.faceOk = false;
          this.message = dets.length === 0 ? 'Wajah tidak terdeteksi' : 'Hanya boleh satu wajah';
          return;
        }
        const size = dets[0].box.width / v.videoWidth;
        if (size < 0.28) { this.faceOk = false; this.message = 'Dekatkan wajah ke kamera'; return; }
        if (size > 0.8)  { this.faceOk = false; this.message = 'Jauhkan sedikit wajah dari kamera'; return; }
        if (dets[0].score < 0.7) { this.faceOk = false; this.message = 'Cari tempat yang lebih terang'; return; }
        this.faceOk = true;
        this.message = 'Siap! Tekan Ambil Foto';
      } finally {
        this.busy = false;
      }
    },

    async shoot() {
      if (!this.faceOk) return;
      const v = this.$refs.video;
      const w = Math.min(640, v.videoWidth);
      const c = document.createElement('canvas');
      c.width = w;
      c.height = Math.round(v.videoHeight * (w / v.videoWidth));
      c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
      this.stopCamera();
      this.mode = 'idle';
      await this.process(c);
    },

    // ---------- Galeri ----------
    async pickFile(e) {
      const file = e.target.files && e.target.files[0];
      e.target.value = '';
      if (!file) return;
      if (this.needConsent()) return;
      if (!file.type.startsWith('image/')) { this.message = 'File harus berupa gambar.'; return; }

      this.message = 'Memproses foto...';
      try {
        await this.loadModels();
      } catch (err) {
        this.message = err.message || 'Model wajah gagal dimuat.';
        return;
      }

      try {
        let src;
        try {
          src = await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch (_) {
          src = await new Promise((res, rej) => {
            const img = new Image();
            img.onload = () => res(img);
            img.onerror = rej;
            img.src = URL.createObjectURL(file);
          });
        }
        const w = Math.min(640, src.width);
        const c = document.createElement('canvas');
        c.width = w;
        c.height = Math.round(src.height * (w / src.width));
        c.getContext('2d').drawImage(src, 0, 0, c.width, c.height);
        if (src.close) src.close();
        await this.process(c);
      } catch (_) {
        this.message = 'Foto tidak bisa dibaca. Coba foto lain.';
      }
    },

    // ---------- Validasi foto + hitung descriptor ----------
    async process(canvas) {
      this.message = 'Memeriksa foto...';
      const dets = await faceapi.detectAllFaces(canvas, opts()).withFaceLandmarks().withFaceDescriptors();

      if (dets.length === 0) { this.message = 'Wajah tidak terdeteksi. Gunakan foto selfie yang jelas dan terang.'; return; }
      if (dets.length > 1)   { this.message = 'Ada lebih dari satu wajah di foto.'; return; }

      const d = dets[0];
      const size = d.detection.box.width / canvas.width;
      if (size < 0.2)              { this.message = 'Wajah terlalu kecil. Foto lebih dekat.'; return; }
      if (d.detection.score < 0.7) { this.message = 'Foto kurang jelas/terang. Coba lagi.'; return; }

      const p = d.landmarks.positions;
      const ratio = (p[30].x - p[0].x) / (p[16].x - p[0].x);
      if (ratio < 0.35 || ratio > 0.65) { this.message = 'Wajah harus menghadap lurus ke kamera.'; return; }

      this.descriptor = Array.from(d.descriptor);
      this.photo = canvas.toDataURL('image/jpeg', 0.8);
      this.mode = 'preview';
      this.message = '';
    },

    // ---------- Kirim ----------
    async submit() {
      if (!this.descriptor || !this.photo || this.mode === 'saving') return;
      this.mode = 'saving';
      await this.$wire.save([this.descriptor], this.photo, this.consent);
      // Jika sukses, server me-redirect. Jika gagal validasi, kembali ke preview.
      if (this.mode === 'saving') this.mode = 'preview';
    },

    retake() {
      this.descriptor = null;
      this.photo = null;
      this.mode = 'idle';
      this.message = '';
    },

    stopCamera() {
      clearTimeout(this.timer);
      if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
      this.stream = null;
      this.faceOk = false;
    },

    destroy() { this.stopCamera(); },
  };
};