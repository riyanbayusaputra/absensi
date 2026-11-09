/* Peta Leaflet untuk panel admin: pemilih titik kantor + peta lokasi absen */
(function () {
  const TILE = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  const ATTR = '&copy; OpenStreetMap';
  const num = (v) => { const n = parseFloat(v); return Number.isFinite(n) ? n : null; };
  const pinIcon = () => L.divIcon({
    className: '',
    html: '<div style="width:22px;height:22px;border-radius:50%;background:#4f46e5;border:3px solid #fff;box-shadow:0 1px 5px rgba(0,0,0,.55)"></div>',
    iconSize: [22, 22], iconAnchor: [11, 11],
  });

  /* ============ Pemilih titik kantor (form Lokasi Kantor) ============ */
  window.officeMap = function () {
    return {
      map: null, marker: null, circle: null, accCircle: null,
      query: '', results: [], msg: '', locating: false, searching: false,

      init() {
        if (typeof L === 'undefined') { this.msg = 'Leaflet tidak termuat. Periksa koneksi internet.'; return; }
        this.$nextTick(() => this.mount());
      },

      mount() {
        const lat = num(this.$wire.get('data.latitude'));
        const lng = num(this.$wire.get('data.longitude'));
        const has = lat !== null && lng !== null;

        this.map = L.map(this.$refs.map).setView(has ? [lat, lng] : [-2.5, 118], has ? 18 : 5);
        L.tileLayer(TILE, { maxZoom: 19, attribution: ATTR }).addTo(this.map);

        if (has) this.place(lat, lng);
        this.map.on('click', (e) => this.commit(e.latlng.lat, e.latlng.lng));
        setTimeout(() => this.map.invalidateSize(), 300);

        this.$wire.$watch('data.latitude', () => this.syncFields());
        this.$wire.$watch('data.longitude', () => this.syncFields());
        this.$wire.$watch('data.radius_m', () => this.drawRadius());
      },

      place(lat, lng) {
        const ll = [lat, lng];
        if (!this.marker) {
          this.marker = L.marker(ll, { draggable: true, icon: pinIcon() }).addTo(this.map);
          this.marker.on('dragend', () => {
            const p = this.marker.getLatLng();
            this.commit(p.lat, p.lng);
          });
        } else {
          this.marker.setLatLng(ll);
        }
        this.drawRadius();
      },

      drawRadius() {
        if (!this.marker || !this.map) return;
        const p = this.marker.getLatLng();
        const r = Math.max(0, num(this.$wire.get('data.radius_m')) || 0);
        if (!this.circle) {
          this.circle = L.circle(p, { radius: r, color: '#4f46e5', weight: 2, fillColor: '#6366f1', fillOpacity: 0.15 }).addTo(this.map);
        } else {
          this.circle.setLatLng(p);
          this.circle.setRadius(r);
        }
      },

      commit(lat, lng) {
        this.$wire.set('data.latitude', lat.toFixed(7), false);
        this.$wire.set('data.longitude', lng.toFixed(7), false);
        this.place(lat, lng);
        this.msg = 'Titik diperbarui. Jangan lupa tekan Simpan.';
      },

      /** Jika angka latitude/longitude diketik manual, pin ikut pindah. */
      syncFields() {
        const lat = num(this.$wire.get('data.latitude'));
        const lng = num(this.$wire.get('data.longitude'));
        if (lat === null || lng === null || !this.map) return;
        const cur = this.marker ? this.marker.getLatLng() : null;
        if (cur && Math.abs(cur.lat - lat) < 1e-7 && Math.abs(cur.lng - lng) < 1e-7) return;
        this.place(lat, lng);
        this.map.setView([lat, lng], Math.max(this.map.getZoom(), 17));
      },

      /** Ambil lokasi GPS terbaik selama ± 10 detik, lalu letakkan pin di sana. */
      locate() {
        if (!navigator.geolocation) { this.msg = 'Browser tidak mendukung GPS.'; return; }
        this.locating = true;
        this.msg = 'Mencari sinyal GPS... tunggu beberapa detik.';
        let best = null;
        let timer = null;

        const finish = () => {
          navigator.geolocation.clearWatch(id);
          clearTimeout(timer);
          this.locating = false;
          if (!best) { this.msg = 'Lokasi tidak didapat. Izinkan akses lokasi, atau cari alamat / klik peta.'; return; }

          const c = best.coords;
          const ll = [c.latitude, c.longitude];
          if (this.accCircle) this.map.removeLayer(this.accCircle);
          this.accCircle = L.circle(ll, { radius: c.accuracy, color: '#2563eb', weight: 1, fillColor: '#3b82f6', fillOpacity: 0.12 }).addTo(this.map);
          this.commit(c.latitude, c.longitude);
          this.map.fitBounds(this.accCircle.getBounds(), { maxZoom: 18, padding: [20, 20] });

          this.msg = c.accuracy <= 30
            ? `Akurasi ± ${Math.round(c.accuracy)} m. Geser pin bila perlu, lalu Simpan.`
            : `Akurasi hanya ± ${Math.round(c.accuracy)} m (biasanya laptop tanpa GPS). Geser pin ke titik gedung yang tepat, atau cari alamatnya.`;
        };

        const id = navigator.geolocation.watchPosition(
          (p) => {
            if (!best || p.coords.accuracy < best.coords.accuracy) best = p;
            this.msg = `Akurasi saat ini ± ${Math.round(best.coords.accuracy)} m...`;
            if (best.coords.accuracy <= 15) finish();
          },
          () => { this.msg = 'Gagal mengambil lokasi. Izinkan akses lokasi di browser.'; finish(); },
          { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 }
        );
        timer = setTimeout(finish, 10000);
      },

      /** Cari alamat lewat OpenStreetMap Nominatim (pemakaian ringan untuk admin). */
      async search() {
        const q = this.query.trim();
        if (q.length < 3) return;
        this.searching = true; this.results = []; this.msg = '';
        try {
          const r = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=5&countrycodes=id&q=' + encodeURIComponent(q));
          this.results = await r.json();
          if (!this.results.length) this.msg = 'Alamat tidak ditemukan. Coba kata kunci lain atau klik langsung di peta.';
        } catch (e) {
          this.msg = 'Pencarian gagal. Klik langsung di peta.';
        }
        this.searching = false;
      },

      pick(r) {
        const lat = parseFloat(r.lat), lng = parseFloat(r.lon);
        this.results = [];
        this.map.setView([lat, lng], 18);
        this.commit(lat, lng);
      },

      destroy() { if (this.map) this.map.remove(); },
    };
  };

  /* ============ Peta lokasi absen (modal di Data Absensi) ============ */
  window.attendanceMap = function (d) {
    return {
      map: null,
      init() { this.$nextTick(() => setTimeout(() => this.draw(), 250)); },

      draw() {
        if (typeof L === 'undefined' || !this.$refs.map) return;
        const layers = [];
        const first = d.in || d.out || d.office;
        this.map = L.map(this.$refs.map).setView([first.lat, first.lng], 17);
        L.tileLayer(TILE, { maxZoom: 19, attribution: ATTR }).addTo(this.map);

        if (d.office) {
          layers.push(L.circle([d.office.lat, d.office.lng], { radius: d.office.radius, color: '#4f46e5', weight: 2, fillColor: '#6366f1', fillOpacity: 0.12 }).addTo(this.map));
          L.circleMarker([d.office.lat, d.office.lng], { radius: 6, color: '#fff', weight: 2, fillColor: '#4f46e5', fillOpacity: 1 })
            .addTo(this.map).bindTooltip(d.office.name, { permanent: true, direction: 'top', offset: [0, -6] });
        }

        const point = (p, color, label) => {
          if (!p) return;
          layers.push(L.circle([p.lat, p.lng], { radius: p.acc || 0, color, weight: 1, fillColor: color, fillOpacity: 0.12 }).addTo(this.map));
          L.circleMarker([p.lat, p.lng], { radius: 8, color: '#fff', weight: 3, fillColor: color, fillOpacity: 1 })
            .addTo(this.map).bindTooltip(label, { permanent: true, direction: 'bottom', offset: [0, 6] });
        };
        point(d.in, '#16a34a', 'Masuk ' + (d.in ? d.in.time : ''));
        point(d.out, '#dc2626', 'Pulang ' + (d.out ? d.out.time : ''));

        if (layers.length) this.map.fitBounds(L.featureGroup(layers).getBounds(), { padding: [30, 30], maxZoom: 18 });
      },

      destroy() { if (this.map) this.map.remove(); },
    };
  };
})();