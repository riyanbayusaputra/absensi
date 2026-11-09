window.faceAttend = function (cfg) {
  const detOpts = () => new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 });
  const STABLE_FRAMES = 6;        // wajah stabil ± 1 detik, lalu absen terkirim otomatis
  const FIX_WINDOW_MS = 10000;    // pakai fix GPS terbaik dalam 10 detik terakhir
  const TILE = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

  const haversine = (a1, o1, a2, o2) => {
    const R = 6371000, r = (d) => (d * Math.PI) / 180;
    const dA = r(a2 - a1), dO = r(o2 - o1);
    const h = Math.sin(dA / 2) ** 2 + Math.cos(r(a1)) * Math.cos(r(a2)) * Math.sin(dO / 2) ** 2;
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
  };

  let modelsLoaded = false;

  return {
    mode: 'idle',          // idle | loading | scan | sending | done | error
    msg: '',
    errorMsg: '',
    result: null,
    faceOk: false,
    stable: 0,
    gps: null,
    fixes: [],
    gpsErr: '',
    watchId: null,
    stream: null,
    timer: null,
    busy: false,
    finalizing: false,
    map: null,
    mapLayers: [],
    userDot: null,
    accCircle: null,
    fitted: false,

    init() {
      this.startGps();
      this.$nextTick(() => this.mapInit());
      this.$watch('mode', () => setTimeout(() => this.map && this.map.invalidateSize(), 200));
    },

    // ---------- Batas akurasi (tahan terhadap config kosong) ----------
    maxAcc() {
      const v = Number(cfg && cfg.maxAccuracy);
      return Number.isFinite(v) && v > 0 ? v : 0;
    },

    // ---------- Peta Leaflet ----------
    mapInit() {
      if (typeof L === 'undefined' || !this.$refs.map) return;
      const locs = cfg.locations || [];
      const first = locs[0];

      this.map = L.map(this.$refs.map, { zoomControl: false })
        .setView(first ? [first.lat, first.lng] : [-2.5, 118], first ? 17 : 5);
      L.tileLayer(TILE, { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(this.map);

      this.mapLayers = locs.map((l) => ({
        circle: L.circle([l.lat, l.lng], { radius: l.radius, color: '#4f46e5', weight: 2, fillColor: '#6366f1', fillOpacity: 0.14 }).addTo(this.map),
        dot: L.circleMarker([l.lat, l.lng], { radius: 6, color: '#fff', weight: 2, fillColor: '#4f46e5', fillOpacity: 1 })
          .addTo(this.map).bindTooltip(l.name, { permanent: true, direction: 'top', offset: [0, -6] }),
      }));

      setTimeout(() => this.map.invalidateSize(), 250);
      this.mapUpdate();
    },

    mapUpdate() {
      if (!this.map) return;
      const f = this.bestFix();
      if (!f) return;
      const ll = [f.lat, f.lng];

      if (!this.userDot) {
        this.accCircle = L.circle(ll, { radius: f.acc, color: '#2563eb', weight: 1, fillColor: '#3b82f6', fillOpacity: 0.15 }).addTo(this.map);
        this.userDot = L.circleMarker(ll, { radius: 8, color: '#fff', weight: 3, fillColor: '#2563eb', fillOpacity: 1 }).addTo(this.map);
      } else {
        this.userDot.setLatLng(ll);
        this.accCircle.setLatLng(ll);
        this.accCircle.setRadius(f.acc);
      }

      if (!this.fitted) { this.fitMap(); this.fitted = true; }
    },

    fitMap() {
      if (!this.map) return;
      const parts = [];
      const n = this.nearest();
      if (n && this.mapLayers[n.i]) parts.push(this.mapLayers[n.i].circle);
      else this.mapLayers.forEach((x) => parts.push(x.circle));
      if (this.accCircle) parts.push(this.accCircle);
      if (!parts.length) return;
      this.map.fitBounds(L.featureGroup(parts).getBounds(), { padding: [24, 24], maxZoom: 18 });
    },

    // ---------- GPS ----------
    startGps() {
      if (!navigator.geolocation) { this.gpsErr = 'Browser tidak mendukung GPS.'; return; }
      this.stopGps();
      this.fixes = [];
      this.watchId = navigator.geolocation.watchPosition(
        (pos) => {
          this.gpsErr = '';
          const f = {
            lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy,
            speed: pos.coords.speed, alt: pos.coords.altitude, at: Date.now(),
          };
          this.gps = f;
          this.fixes.push(f);
          this.fixes = this.fixes.filter((x) => f.at - x.at <= FIX_WINDOW_MS);
          this.mapUpdate();
        },
        (err) => {
          this.gpsErr = err.code === 1
            ? 'Izin lokasi ditolak. Aktifkan lokasi untuk browser ini.'
            : (err.code === 3 ? 'GPS lambat merespons. Pindah ke area terbuka.' : 'Lokasi belum tersedia. Pastikan GPS aktif.');
        },
        { enableHighAccuracy: true, maximumAge: 0, timeout: 30000 }
      );
    },
    stopGps() {
      if (this.watchId !== null) navigator.geolocation.clearWatch(this.watchId);
      this.watchId = null;
    },

    /** Fix paling akurat dalam 10 detik terakhir. */
    bestFix() {
      const now = Date.now();
      const recent = this.fixes.filter((f) => now - f.at <= FIX_WINDOW_MS);
      if (!recent.length) return this.gps;
      return recent.reduce((a, b) => (b.acc < a.acc ? b : a));
    },
       gpsOk() {
      const f = this.bestFix();
      if (!f) return false;
      const m = this.maxAcc();
      return m === 0 || f.acc <= m;
    },
    nearest() {
      const f = this.bestFix();
      if (!f || !cfg.locations || !cfg.locations.length) return null;
      let best = null;
      cfg.locations.forEach((l, i) => {
        const d = haversine(f.lat, f.lng, l.lat, l.lng);
        if (!best || d - l.radius < best.d - best.radius) best = { name: l.name, d, radius: l.radius, i };
      });
      if (best) best.inside = best.d <= best.radius;
      return best;
    },
       gpsText() {
      const f = this.bestFix();
      if (!f) return this.gpsErr || 'Mencari lokasi...';
      const m = this.maxAcc();
      return `Akurasi GPS ± ${Math.round(f.acc)} m` + (m > 0 ? ` (perlu ≤ ${m} m)` : '');
    },
    gpsWarn() {
      const f = this.bestFix();
      return f && f.acc > 150
        ? 'Akurasi GPS rendah, posisi di peta bisa kurang tepat. Coba dekat jendela atau di luar ruangan.'
        : '';
    },
    areaState() {
      const n = this.nearest();
      return n ? (n.inside ? 'in' : 'out') : 'none';
    },
    ready() { return this.faceOk && this.stable >= STABLE_FRAMES; },

    // ---------- Mulai ----------
    async start() {
      this.mode = 'loading'; this.msg = 'Menyiapkan kamera...'; this.errorMsg = '';
      this.resetFace();
      if (this.watchId === null) this.startGps();

      try {
        if (typeof faceapi === 'undefined') {
          throw new Error('Library face-api.js tidak termuat. Periksa file public/js/face-api.js');
        }
        if (!modelsLoaded) {
          const url = cfg.modelUrl || '/models';
          await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(url),
            faceapi.nets.faceLandmark68Net.loadFromUri(url),
            faceapi.nets.faceRecognitionNet.loadFromUri(url),
          ]);
          modelsLoaded = true;
        }
        this.stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }, audio: false,
        });
        const v = this.$refs.video;
        v.srcObject = this.stream;
        await v.play();
      } catch (e) {
        this.stopCamera();
        if (e && e.name === 'NotAllowedError') {
          this.fail('Izin kamera ditolak. Aktifkan izin kamera di pengaturan browser.');
        } else if (e && e.message && e.message.includes('face-api')) {
          this.fail(e.message);
        } else {
          this.fail('Kamera/model gagal dimuat. Pastikan memakai HTTPS atau localhost.');
        }
        return;
      }

      this.mode = 'scan';
      this.msg = 'Hadap lurus ke kamera';
      this.loop();
    },

    resetFace() {
      this.faceOk = false;
      this.stable = 0;
      this.finalizing = false;
    },

    loop() {
      this.timer = setTimeout(async () => {
        if (this.mode !== 'scan') return;
        await this.tick();
        this.loop();
      }, 60);
    },

    async tick() {
      if (this.busy || this.finalizing) return;
      this.busy = true;
      try {
        const v = this.$refs.video;
        const dets = await faceapi.detectAllFaces(v, detOpts()).withFaceLandmarks();

        if (dets.length !== 1) {
          this.resetFace();
          this.msg = dets.length === 0 ? 'Wajah tidak terdeteksi' : 'Hanya boleh satu wajah';
          return;
        }
        const d = dets[0];
        const size = d.detection.box.width / v.videoWidth;
        if (size < 0.28) { this.resetFace(); this.msg = 'Dekatkan wajah ke kamera'; return; }
        if (size > 0.8)  { this.resetFace(); this.msg = 'Jauhkan sedikit wajah dari kamera'; return; }

        const p = d.landmarks.positions;
        const ratio = (p[30].x - p[0].x) / (p[16].x - p[0].x);
        if (ratio < 0.38 || ratio > 0.62) { this.resetFace(); this.msg = 'Hadap lurus ke kamera'; return; }

        this.faceOk = true;
        this.stable++;

        if (this.stable < STABLE_FRAMES) { this.msg = 'Tahan posisi...'; return; }

        await this.captureAndSend();
      } finally {
        this.busy = false;
      }
    },

    async captureAndSend() {
      const f = this.bestFix();

      if (!f) {
        this.msg = this.gpsErr || 'Mencari lokasi... (pastikan GPS aktif dan izin lokasi diberikan)';
        return;
      }
    const m = this.maxAcc();
      if (m > 0 && f.acc > m) {
        this.msg = `Akurasi GPS ${Math.round(f.acc)} m, perlu ≤ ${m} m. Pindah ke area terbuka.`;
        return;
      }
      const n = this.nearest();
      if (n && !n.inside) {
        this.msg = `Di luar area: ${Math.round(n.d)} m dari ${n.name} (batas ${n.radius} m)`;
        return;
      }

      this.finalizing = true;
      this.msg = 'Memverifikasi...';
      const v = this.$refs.video;
      const full = await faceapi.detectSingleFace(v, detOpts()).withFaceLandmarks().withFaceDescriptor();
      if (!full) { this.resetFace(); return; }

      const c = document.createElement('canvas');
      c.width = 480;
      c.height = Math.round(v.videoHeight * (480 / v.videoWidth));
      c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
      const photo = c.toDataURL('image/jpeg', 0.75);

      this.stopCamera();
      this.mode = 'sending';
      this.msg = 'Mengirim absen...';

      try {
        const res = await this.$wire.submit({
          lat: f.lat, lng: f.lng, accuracy: f.acc, speed: f.speed, altitude: f.alt,
          gps_ts: f.at, client_ts: Date.now(),
          descriptor: Array.from(full.descriptor), photo,
        });
        if (res && res.ok) {
          this.result = res; this.mode = 'done'; this.stopGps();
          setTimeout(() => { window.location.href = cfg.homeUrl; }, 3500);
        } else {
          this.fail((res && res.message) || 'Absen gagal. Coba lagi.');
        }
      } catch (e) {
        this.fail('Koneksi bermasalah atau sesi berakhir. Muat ulang halaman lalu coba lagi.');
      }
    },

    fail(message) {
      this.errorMsg = message;
      this.mode = 'error';
      this.stopCamera();
    },

    retry() { this.errorMsg = ''; this.mode = 'idle'; this.resetFace(); },

    stopCamera() {
      clearTimeout(this.timer);
      if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
      this.stream = null;
    },

    destroy() {
      this.stopCamera();
      this.stopGps();
      if (this.map) this.map.remove();
    },
  };
};