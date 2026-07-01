# Design System — Dashboard Quality Control Admission (Light Mode)

Dokumen ini mendefinisikan versi **light theme** dari Dashboard Quality Control Admission yang saat ini menggunakan dark theme (hitam + hijau). Tujuannya agar tampilan tetap mempertahankan identitas visual (hijau sebagai warna utama) namun dengan latar terang agar lebih nyaman dibaca di ruangan dengan pencahayaan tinggi / preferensi pengguna.

---

## 1. Prinsip Desain

- **Konsistensi brand**: Hijau (`#00B37E` / `#00C896`) tetap menjadi warna aksen utama, dipertahankan dari versi dark.
- **Kontras tinggi**: Teks gelap di atas latar terang untuk keterbacaan data tabel & angka.
- **Hierarki jelas**: Card/panel dibedakan lewat *elevation* (shadow tipis) alih-alih border neon seperti versi dark.
- **Data-first**: Warna dekoratif diminimalkan, angka dan status tetap jadi fokus utama.

---

## 2. Palet Warna

| Token | Hex | Penggunaan |
|---|---|---|
| `--bg-page` | `#F4F7F6` | Latar belakang utama halaman |
| `--bg-surface` | `#FFFFFF` | Latar card / panel |
| `--bg-surface-alt` | `#F0F4F3` | Latar baris tabel selang-seling (zebra) |
| `--border-subtle` | `#E1E7E5` | Garis pembatas tabel & card |
| `--brand-primary` | `#00B37E` | Header card, tombol, aksen chart |
| `--brand-primary-light` | `#D7F5EA` | Background badge / highlight ringan |
| `--brand-gradient-start` | `#00C896` | Gradasi header card (kiri) |
| `--brand-gradient-end` | `#009E6B` | Gradasi header card (kanan) |
| `--text-primary` | `#1A1F1E` | Judul, angka utama |
| `--text-secondary` | `#5C6B67` | Label, sub-teks |
| `--text-inverse` | `#FFFFFF` | Teks di atas elemen hijau solid |
| `--status-black-badge` | `#1A1F1E` | Badge status "Edukasi" (dipertahankan gelap agar tetap kontras) |
| `--status-white-badge` | `#FFFFFF` (border `#00B37E`) | Badge status "Edukasi lanjutan" |
| `--chart-bar` | `#00C896` | Warna bar chart utama |
| `--shadow-card` | `rgba(16, 24, 22, 0.08)` | Shadow bawah card |

---

## 3. Tipografi

- **Font family**: `Inter`, `Segoe UI`, sans-serif fallback.
- **Judul dashboard** (`Dashboard Quality Control Admission`): 24–28px, Bold, `--text-primary`.
- **Judul panel** (mis. "Avg Durasi Edukasi Pasien"): 15–16px, Semibold, `--text-primary`, kata kunci ditebalkan (contoh: **Edukasi Pasien**).
- **Angka besar KPI** (56.742 / 163,87): 26px, Bold, `--text-primary`.
- **Label KPI**: 12px, Medium, `--text-secondary`, uppercase opsional.
- **Isi tabel**: 13–14px, Regular, `--text-primary` untuk data, `--text-secondary` untuk header kolom.

---

## 4. Struktur Layout

```
┌─────────────────────────────────────────────────────────────────┐
│ [Logo] Dashboard | Quality Control Admission     [Jumlah Edukasi Pasien] [Durasi Tunggu Edukasi (menit)]  │ 
|
|
├─────────────────────────────────────────────────────────────────┤
│ ┌───────────────────┐  ┌─────────────────────────┐  ┌─────────┐ │
│ │ Avg Durasi Edukasi │  │ Detail Edukasi QC Based  │  │ Filter  │ │
│ │ Pasien (table)     │  │ Status/Petugas (matrix)  │  │ Panel   │ │
│ └───────────────────┘  └─────────────────────────┘  └─────────┘ │
│ ┌────────────┐ ┌────────────────┐ ┌───────────────────────────┐ │
│ │ Jumlah     │ │ Jumlah Edukasi │ │ Bar Chart Jumlah Pasien    │ │
│ │ Edukasi    │ │ Based Status   │ │ Diedukasi Based Kebutuhan  │ │
│ │ Based      │ │ (funnel)       │ │ Kamar Pasien               │ │
│ │ Petugas    │ │                │ │                             │ │
│ └────────────┘ └────────────────┘ └───────────────────────────┘ │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ Data Detail Quality Control Admission (tabel detail, full)   │ │
│ └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

- **Grid**: 12 kolom, gap 16–20px, padding halaman 24px.
- **Card radius**: 12px, shadow: `0 2px 8px var(--shadow-card)`.
- **Header card**: strip gradasi hijau tipis (bar 4px di atas card) atau seluruh header card berlatar gradasi hijau dengan teks putih — mengikuti pola dark mode ("Avg Durasi Edukasi Pasien" & "Data Detail..." memakai header gradient hijau penuh, panel lain memakai header putih dengan judul teks tebal).

---

## 5. Komponen

### 5.1 Top Bar
- Latar putih, shadow bawah tipis (`0 1px 4px var(--shadow-card)`).
- Logo "Dashboard" tetap berupa pill hijau solid dengan teks putih.
- Label "Quality Control Admission" berlatar putih dengan border hijau tipis, icon kaca pembesar hijau.
- KPI Card (Jumlah Edukasi Pasien / Durasi Tunggu Edukasi): kotak putih, border hijau 1.5px, radius 10px, angka besar hitam, label kecil abu-abu.
- Tombol back (⟲): lingkaran outline abu-abu/hijau muda.

### 5.2 Card / Panel
- Background: `--bg-surface`.
- Border: 1px solid `--border-subtle`.
- Header: teks judul hitam dengan kata kunci **bold**, atau header gradasi hijau (untuk panel tabel utama) dengan teks putih.

### 5.3 Tabel
- Header kolom: latar `--bg-surface-alt`, teks `--text-secondary`, bold, dengan ikon sort (▾).
- Baris data: latar putih polos, garis pembatas horizontal `--border-subtle`.
- Baris hover: latar `--brand-primary-light`.
- Pagination ("1–36/36", "50001–56741/56741"): teks abu-abu kecil di kanan bawah tabel, tombol navigasi (‹ ›) berbentuk lingkaran outline hijau.

### 5.4 Badge Status
- **Edukasi**: latar hitam (`--status-black-badge`), teks putih — dipertahankan untuk kontras & signifikansi status.
- **Edukasi lanjutan**: latar putih, border hijau, teks hitam.

### 5.5 Chart

**Bar Chart (Jumlah Pasien Diedukasi Based Kebutuhan Kamar):**
- Bar: solid `--chart-bar` (#00C896), radius atas 4px.
- Label angka di atas bar: hitam, bold.
- Sumbu & gridline: abu-abu muda (`--border-subtle`).
- Legend "Record Count": kotak kecil hijau + teks abu-abu.

**Funnel Chart (Jumlah Edukasi Based Status):**
- Tahap 1 (100%): hijau solid.
- Tahap berikutnya: gradasi memudar (hijau → hijau muda → abu-abu terang), garis penghubung oranye/kuning dipertahankan sebagai penanda drop-off, teks label hitam di kiri.

### 5.6 Filter Panel (kanan atas)
- Dropdown "Pilih rentang tanggal": latar putih, border hijau, teks hitam, chevron hijau.
- Tombol filter (NamaPasien, Petugas, NoMR, Status): pill hijau solid dengan teks putih, radius penuh (pill shape), shadow tipis, chevron di kanan.

---

## 6. Aksesibilitas
- Kontras teks-ke-latar minimal WAJIB rasio 4.5:1 (teks hitam `#1A1F1E` di atas putih memenuhi).
- Badge status tetap membedakan lewat *bentuk/border*, bukan warna saja (badge "Edukasi lanjutan" pakai border + putih, "Edukasi" pakai solid gelap) agar tetap terbaca oleh pengguna buta warna.
- Ukuran font tabel minimum 13px, tombol minimum area sentuh 32x32px.

---

## 7. Contoh Token CSS

```css
:root {
  --bg-page: #F4F7F6;
  --bg-surface: #FFFFFF;
  --bg-surface-alt: #F0F4F3;
  --border-subtle: #E1E7E5;
  --brand-primary: #00B37E;
  --brand-primary-light: #D7F5EA;
  --brand-gradient-start: #00C896;
  --brand-gradient-end: #009E6B;
  --text-primary: #1A1F1E;
  --text-secondary: #5C6B67;
  --text-inverse: #FFFFFF;
  --shadow-card: rgba(16, 24, 22, 0.08);
}

.card {
  background: var(--bg-surface);
  border: 1px solid var(--border-subtle);
  border-radius: 12px;
  box-shadow: 0 2px 8px var(--shadow-card);
}

.card-header--gradient {
  background: linear-gradient(90deg, var(--brand-gradient-start), var(--brand-gradient-end));
  color: var(--text-inverse);
  border-radius: 12px 12px 0 0;
}

.badge-edukasi {
  background: var(--status-black-badge, #1A1F1E);
  color: var(--text-inverse);
}

.badge-edukasi-lanjutan {
  background: var(--bg-surface);
  color: var(--text-primary);
  border: 1.5px solid var(--brand-primary);
}
```

---

## 8. Ringkasan Perubahan dari Dark → Light

| Elemen | Dark (existing) | Light (baru) |
|---|---|---|
| Background halaman | Hitam pekat | Abu-abu sangat terang (`#F4F7F6`) |
| Background card | Hitam / hijau gelap | Putih dengan shadow tipis |
| Teks utama | Putih | Hitam pekat (`#1A1F1E`) |
| Header panel | Gradasi hijau neon | Gradasi hijau lebih kalem / putih + teks bold |
| Tabel baris | Hitam dengan garis hijau tipis | Putih dengan garis abu-abu muda |
| Badge "Edukasi" | Hitam solid | Tetap hitam solid (kontras dijaga) |
| Chart bar | Hijau terang di atas hitam | Hijau di atas putih, gridline abu-abu |
