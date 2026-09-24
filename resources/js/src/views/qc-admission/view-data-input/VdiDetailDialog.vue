<script setup>
/**
 * VdiDetailDialog — modal detail universal untuk semua tab di View Data Input
 */
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item:       { type: Object,  default: null   },
  type:       { type: String,  default: 'summary' },
})
const emit = defineEmits(['update:modelValue'])
function close() { emit('update:modelValue', false) }

// ── Metadata per type ────────────────────────────────────────────────────────
const META = {
  'summary':          { label: 'Summary Pasien',   icon: 'ri-bar-chart-box-line',   grad: ['#6366f1','#818cf8'] },
  'alasan':           { label: 'Alasan Kunjungan', icon: 'ri-question-answer-line', grad: ['#0369A1','#0EA5E9'] },
  'quality-control':  { label: 'Edukasi Awal',     icon: 'ri-shield-check-line',    grad: ['#7c3aed','#a78bfa'] },
  'edukasi-lanjutan': { label: 'Edukasi Lanjutan', icon: 'ri-book-open-line',       grad: ['#059669','#34d399'] },
  'batal-ranap':      { label: 'Batal Ranap',       icon: 'ri-close-circle-line',    grad: ['#dc2626','#f87171'] },
  'up-selling':       { label: 'Up Selling',        icon: 'ri-arrow-up-circle-line', grad: ['#d97706','#fbbf24'] },
}
const meta = computed(() => META[props.type] ?? META['summary'])

// ── Color helpers ────────────────────────────────────────────────────────────
function alasanColor(a) {
  return ({ 'Pelayanan':'primary','Kelengkapan Alat & Dokter':'warning','Teman/Kerabat':'info','Rujukan':'success','Marketing':'secondary','Sosial Media':'info' })[a] ?? 'secondary'
}
function statusColor(s) {
  return ({ 'Edukasi':'success','Edukasi lanjutan':'warning','Bedah':'success','Non Bedah':'info','Selesai':'success','Menunggu':'warning','Berhasil':'success','Tidak Berhasil':'error','Pending':'warning' })[s] ?? 'secondary'
}
function closingColor(s) {
  return s === 'Siap Closing' ? 'success' : s === 'Belum Siap Closing' ? 'error' : 'secondary'
}
function fmtDate(d) {
  if (!d) return '—'
  try { return new Date(d).toLocaleString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) }
  catch { return d }
}
</script>

<template>
  <VDialog :model-value="modelValue" max-width="520" @update:model-value="close">
    <VCard v-if="item" rounded="xl" class="vdd overflow-hidden">

      <!-- ── Banner ──────────────────────────────────────────────────────── -->
      <div class="vdd-banner" :style="`background: linear-gradient(135deg, ${meta.grad[0]} 0%, ${meta.grad[1]} 100%)`">
        <div class="vdd-blob vdd-blob--1" /><div class="vdd-blob vdd-blob--2" />
        <div class="vdd-bi">
          <!-- Avatar -->
          <div class="vdd-av">{{ item.nama_pasien?.charAt(0) ?? '?' }}</div>
          <!-- Info -->
          <div class="vdd-info">
            <p class="vdd-badge">
              <VIcon :icon="meta.icon" size="10" class="me-1" />{{ meta.label }}
            </p>
            <h2 class="vdd-name">{{ item.nama_pasien || '—' }}</h2>
            <p class="vdd-sub">
              {{ item.no_mr || item.no_reg || '—' }}
              <template v-if="item.jaminan"> · {{ item.jaminan }}</template>
            </p>
          </div>
          <!-- Close -->
          <button class="vdd-close" @click="close">
            <VIcon icon="ri-close-line" size="16" />
          </button>
        </div>
      </div>

      <!-- ── Scrollable body ─────────────────────────────────────────────── -->
      <div class="vdd-body">

        <!-- ══════════════ SUMMARY ══════════════════════════════════════════ -->
        <template v-if="type === 'summary'">
          <!-- Count chips -->
          <div class="vdd-count-row mb-4">
            <div v-if="item.qc"     class="vdd-count vdd-count--primary">
              <VIcon icon="ri-shield-check-line" size="16" /><span>{{ item.qc }}</span><small>Edukasi Awal</small>
            </div>
            <div v-if="item.edukasi" class="vdd-count vdd-count--warning">
              <VIcon icon="ri-book-open-line" size="16" /><span>{{ item.edukasi }}</span><small>Edu. Lanjutan</small>
            </div>
            <div v-if="item.batal"  class="vdd-count vdd-count--error">
              <VIcon icon="ri-close-circle-line" size="16" /><span>{{ item.batal }}</span><small>Batal Ranap</small>
            </div>
            <div v-if="item.up"     class="vdd-count vdd-count--success">
              <VIcon icon="ri-arrow-up-circle-line" size="16" /><span>{{ item.up }}</span><small>Up Selling</small>
            </div>
            <div v-if="item.alasan" class="vdd-count vdd-count--info">
              <VIcon icon="ri-question-answer-line" size="16" /><span>{{ item.alasan }}</span><small>Alasan</small>
            </div>
          </div>
          <div class="vdd-grid">
            <div class="vdd-cell"><span class="vdd-lbl">No. MR</span><span class="vdd-val mono">{{ item.no_mr || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jaminan</span><span class="vdd-val">{{ item.jaminan || '—' }}</span></div>
            <div class="vdd-cell vdd-cell--full"><span class="vdd-lbl">Nama Pasien</span><span class="vdd-val">{{ item.nama_pasien || '—' }}</span></div>
            <div class="vdd-cell vdd-cell--full"><span class="vdd-lbl">Tanggal Terakhir</span><span class="vdd-val">{{ item.tanggal || '—' }}</span></div>
          </div>
        </template>

        <!-- ══════════════ ALASAN ════════════════════════════════════════════ -->
        <template v-else-if="type === 'alasan'">
          <div class="vdd-highlight mb-3" :class="`vdd-highlight--${alasanColor(item.alasan)}`">
            <VIcon icon="ri-question-answer-line" size="18" />
            <div>
              <p class="vdd-lbl mb-0">Alasan Kunjungan</p>
              <p class="vdd-val fw mb-0">{{ item.alasan || '—' }}</p>
            </div>
          </div>
          <div class="vdd-grid">
            <div class="vdd-cell"><span class="vdd-lbl">No. Reg</span><span class="vdd-val mono">{{ item.no_reg || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">No. MR</span><span class="vdd-val mono">{{ item.no_mr || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jaminan</span><span class="vdd-val">{{ item.jaminan || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Petugas</span><span class="vdd-val">{{ item.petugas || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Tanggal</span><span class="vdd-val">{{ item.tanggal || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Ruangan</span><span class="vdd-val">{{ item.nama_bangsal || item.nama_ruang || '—' }}</span></div>
            <div v-if="item.catatan" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Catatan</span>
              <span class="vdd-val" style="white-space:pre-wrap">{{ item.catatan }}</span>
            </div>
          </div>
        </template>

        <!-- ══════════════ QUALITY CONTROL (EDUKASI AWAL) ═══════════════════ -->
        <template v-else-if="type === 'quality-control'">
          <div class="vdd-status-row mb-3">
            <VChip v-if="item.status" :color="statusColor(item.status)" variant="tonal" size="small">
              <VIcon icon="ri-shield-check-line" size="12" class="me-1" />{{ item.status }}
            </VChip>
            <VChip v-if="item.durasi_tunggu" color="secondary" variant="tonal" size="small">
              <VIcon icon="ri-time-line" size="12" class="me-1" />{{ item.durasi_tunggu }}
            </VChip>
          </div>
          <div class="vdd-grid">
            <div class="vdd-cell"><span class="vdd-lbl">No. MR</span><span class="vdd-val mono">{{ item.no_mr || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">No. Reg</span><span class="vdd-val mono">{{ item.no_reg || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jaminan</span><span class="vdd-val">{{ item.jaminan || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Petugas</span><span class="vdd-val">{{ item.petugas || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Tanggal Input</span><span class="vdd-val">{{ item.tanggal || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Durasi Tunggu</span><span class="vdd-val">{{ item.durasi_tunggu || '—' }}</span></div>
            <div v-if="item.diagnosa" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Diagnosa</span><span class="vdd-val">{{ item.diagnosa }}</span>
            </div>
            <div v-if="item.ketersediaan_kamar" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Ketersediaan Kamar</span><span class="vdd-val">{{ item.ketersediaan_kamar }}</span>
            </div>
            <div v-if="item.note" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Note</span><span class="vdd-val" style="white-space:pre-wrap">{{ item.note }}</span>
            </div>
          </div>
        </template>

        <!-- ══════════════ EDUKASI LANJUTAN ═════════════════════════════════ -->
        <template v-else-if="type === 'edukasi-lanjutan'">
          <div class="vdd-status-row mb-3">
            <VChip v-if="item.keterangan === 'Sudah Masuk Kamar'" color="success" variant="tonal" size="small">
              <VIcon icon="ri-home-heart-line" size="12" class="me-1" />Sudah Masuk Kamar
            </VChip>
            <VChip v-else-if="item.keterangan === 'Belum Diantar'" color="orange" variant="tonal" size="small">
              <VIcon icon="ri-walk-line" size="12" class="me-1" />Belum Diantar
            </VChip>
            <VChip v-else-if="item.status" :color="statusColor(item.status)" variant="tonal" size="small">
              {{ item.status }}
            </VChip>
            <VChip v-if="item.status_ranap" color="purple" variant="tonal" size="small">
              <VIcon icon="ri-hospital-fill" size="12" class="me-1" />{{ item.status_ranap }}
            </VChip>
          </div>
          <div class="vdd-grid">
            <div class="vdd-cell"><span class="vdd-lbl">No. MR</span><span class="vdd-val mono">{{ item.no_mr || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">No. Reg</span><span class="vdd-val mono">{{ item.no_reg || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jaminan</span><span class="vdd-val">{{ item.jaminan || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Petugas</span><span class="vdd-val">{{ item.petugas || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Tanggal</span><span class="vdd-val">{{ item.tanggal || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Bulan</span><span class="vdd-val">{{ item.bulan || '—' }}</span></div>
            <div v-if="item.edukasi_kamar" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Edukasi Kamar / Ruangan</span>
              <span class="vdd-val" style="white-space:pre-wrap">{{ item.edukasi_kamar }}</span>
            </div>
            <div v-if="item.note" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Note Kamar</span><span class="vdd-val">{{ item.note }}</span>
            </div>
            <div v-if="item.keluarga_pasien" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Keluarga Pasien</span><span class="vdd-val">{{ item.keluarga_pasien }}</span>
            </div>
            <div v-if="item.ttd_keluarga_pasien" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Tanda Tangan Keluarga</span>
              <img :src="item.ttd_keluarga_pasien" alt="TTD"
                style="height:40px;border:1px solid #eee;border-radius:6px;margin-top:4px" />
            </div>
            <div class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Waktu Input</span><span class="vdd-val">{{ fmtDate(item.created_at) }}</span>
            </div>
          </div>
        </template>

        <!-- ══════════════ BATAL RANAP ═══════════════════════════════════════ -->
        <template v-else-if="type === 'batal-ranap'">
          <!-- Keterangan batal highlight -->
          <div class="vdd-highlight vdd-highlight--error mb-3">
            <VIcon icon="ri-close-circle-line" size="18" />
            <div>
              <p class="vdd-lbl mb-0">Keterangan Batal</p>
              <p class="vdd-val fw mb-0">{{ item.keterangan_batal || '—' }}</p>
            </div>
          </div>
          <div class="vdd-status-row mb-3">
            <VChip v-if="item.status_ok" :color="item.status_ok === 'Bedah' ? 'success' : 'info'" variant="tonal" size="small">
              {{ item.status_ok }}
            </VChip>
            <VChip v-else color="secondary" variant="tonal" size="small">Belum Diverifikasi</VChip>
            <VChip v-if="item.status_closing" :color="closingColor(item.status_closing)" variant="tonal" size="small">
              {{ item.status_closing }}
            </VChip>
            <VChip v-else color="secondary" variant="outlined" size="small">Belum Closing</VChip>
          </div>
          <div class="vdd-grid">
            <div class="vdd-cell"><span class="vdd-lbl">No. Reg</span><span class="vdd-val mono">{{ item.no_reg || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">No. MR</span><span class="vdd-val mono">{{ item.no_mr || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Tgl. Daftar</span><span class="vdd-val">{{ item.tgl_daftar || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jam Daftar</span><span class="vdd-val">{{ item.jam_daftar || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Tgl. Input</span><span class="vdd-val">{{ item.tanggal || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jam Input</span><span class="vdd-val">{{ item.jam_input || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jaminan</span><span class="vdd-val">{{ item.jaminan || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Petugas</span><span class="vdd-val">{{ item.petugas || '—' }}</span></div>
            <div v-if="item.bed_id" class="vdd-cell">
              <span class="vdd-lbl">Kode Bed IGD</span><span class="vdd-val">{{ item.bed_id }}</span>
            </div>
            <div v-if="item.ketersediaan_kamar" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Ketersediaan Kamar</span><span class="vdd-val">{{ item.ketersediaan_kamar }}</span>
            </div>
            <div v-if="item.diagnosa" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Diagnosa</span><span class="vdd-val">{{ item.diagnosa }}</span>
            </div>
            <div v-if="item.note" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Note</span><span class="vdd-val" style="white-space:pre-wrap">{{ item.note }}</span>
            </div>
          </div>
        </template>

        <!-- ══════════════ UP SELLING ════════════════════════════════════════ -->
        <template v-else-if="type === 'up-selling'">
          <div class="vdd-highlight vdd-highlight--warning mb-3">
            <VIcon icon="ri-arrow-up-circle-line" size="18" />
            <div>
              <p class="vdd-lbl mb-0">Keterangan Up Selling</p>
              <p class="vdd-val fw mb-0">{{ item.alasan || '—' }}</p>
            </div>
          </div>
          <div class="vdd-grid">
            <div class="vdd-cell"><span class="vdd-lbl">No. Reg</span><span class="vdd-val mono">{{ item.no_reg || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">No. MR</span><span class="vdd-val mono">{{ item.no_mr || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Tgl. Daftar</span><span class="vdd-val">{{ item.tgl_daftar || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jaminan</span><span class="vdd-val">{{ item.jaminan || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Petugas</span><span class="vdd-val">{{ item.petugas || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Nama Ruang</span><span class="vdd-val">{{ item.nama_ruang || '—' }}</span></div>
            <div v-if="item.note" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Notes</span>
              <span class="vdd-val" style="white-space:pre-wrap">{{ item.note }}</span>
            </div>
          </div>
        </template>

      </div>

      <!-- ── Footer ─────────────────────────────────────────────────────── -->
      <div class="vdd-footer">
        <VBtn variant="outlined" rounded="lg" size="small" @click="close">Tutup</VBtn>
      </div>

    </VCard>
  </VDialog>
</template>

<style scoped>
.vdd { display: flex; flex-direction: column; max-height: 90dvh; }

.vdd-body {
  flex: 1 1 auto;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 16px;
}

.vdd-footer {
  flex-shrink: 0;
  display: flex; gap: 8px;
  padding: 12px 16px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-surface-variant), 0.25);
}

/* ── Banner ── */
.vdd-banner {
  position: relative; overflow: hidden; flex-shrink: 0;
  padding: 14px 16px 12px;
}
.vdd-blob { position: absolute; border-radius: 50%; background: #fff; }
.vdd-blob--1 { width: 160px; height: 160px; opacity: 0.09; top: -50px; right: -20px; }
.vdd-blob--2 { width: 70px;  height: 70px;  opacity: 0.07; bottom: -22px; right: 80px; }

.vdd-bi { position: relative; z-index: 2; display: flex; align-items: center; gap: 10px; }

.vdd-av {
  flex-shrink: 0; width: 44px; height: 44px; border-radius: 50%;
  background: rgba(255,255,255,0.25); border: 2px solid rgba(255,255,255,0.4);
  display: flex; align-items: center; justify-content: center;
  font-size: 17px; font-weight: 800; color: #fff;
}

.vdd-info { flex: 1; min-width: 0; overflow: hidden; }
.vdd-badge {
  display: inline-flex; align-items: center;
  font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
  color: rgba(255,255,255,0.75); margin: 0 0 2px;
}
.vdd-name {
  font-size: 0.92rem; font-weight: 800; color: #fff; margin: 0 0 2px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.vdd-sub { font-size: 0.65rem; color: rgba(255,255,255,0.78); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.vdd-close {
  flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%;
  background: rgba(255,255,255,0.2); border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  color: #fff; transition: background 0.15s; align-self: flex-start;
}
.vdd-close:hover { background: rgba(255,255,255,0.35); }

/* ── Info grid ── */
.vdd-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px; overflow: hidden;
}
.vdd-cell {
  display: flex; flex-direction: column;
  padding: 9px 12px;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.vdd-cell:nth-child(even) { border-right: none; }
.vdd-cell:last-child,
.vdd-cell:nth-last-child(2):nth-child(odd):not(.vdd-cell--full) { border-bottom: none; }
.vdd-cell--full { grid-column: span 2; border-right: none; }
.vdd-cell--full:last-child { border-bottom: none; }

.vdd-lbl {
  font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.07em;
  color: rgba(var(--v-theme-on-surface), 0.42); margin-bottom: 2px; font-weight: 600;
}
.vdd-val { font-size: 0.84rem; font-weight: 500; color: rgba(var(--v-theme-on-surface), 0.87); }
.vdd-val.fw { font-weight: 700; }
.vdd-val.mono { font-family: monospace; font-size: 0.82rem; }

/* ── Highlight box ── */
.vdd-highlight {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 12px 14px; border-radius: 12px;
}
.vdd-highlight--primary   { background: rgba(var(--v-theme-primary), 0.07); border: 1px solid rgba(var(--v-theme-primary), 0.2); color: rgb(var(--v-theme-primary)); }
.vdd-highlight--warning   { background: rgba(var(--v-theme-warning), 0.07); border: 1px solid rgba(var(--v-theme-warning), 0.2); color: rgb(var(--v-theme-warning)); }
.vdd-highlight--info      { background: rgba(var(--v-theme-info), 0.07);    border: 1px solid rgba(var(--v-theme-info),    0.2); color: rgb(var(--v-theme-info)); }
.vdd-highlight--success   { background: rgba(var(--v-theme-success), 0.07); border: 1px solid rgba(var(--v-theme-success), 0.2); color: rgb(var(--v-theme-success)); }
.vdd-highlight--error     { background: rgba(var(--v-theme-error), 0.07);   border: 1px solid rgba(var(--v-theme-error),   0.2); color: rgb(var(--v-theme-error)); }
.vdd-highlight--secondary { background: rgba(var(--v-theme-on-surface), 0.05); border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); color: rgba(var(--v-theme-on-surface), 0.6); }

/* ── Status row ── */
.vdd-status-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

/* ── Summary count cards ── */
.vdd-count-row { display: flex; gap: 8px; flex-wrap: wrap; }
.vdd-count {
  flex: 1 1 0; min-width: 70px;
  display: flex; flex-direction: column; align-items: center;
  gap: 3px; padding: 10px 8px;
  border-radius: 12px; border: 1px solid transparent;
  font-size: 0.7rem;
}
.vdd-count span { font-size: 1.3rem; font-weight: 800; line-height: 1; }
.vdd-count small { font-size: 0.62rem; opacity: 0.7; text-align: center; }

.vdd-count--primary  { background: rgba(var(--v-theme-primary), 0.08); border-color: rgba(var(--v-theme-primary), 0.2); color: rgb(var(--v-theme-primary)); }
.vdd-count--warning  { background: rgba(var(--v-theme-warning), 0.08); border-color: rgba(var(--v-theme-warning), 0.2); color: rgb(var(--v-theme-warning)); }
.vdd-count--error    { background: rgba(var(--v-theme-error),   0.08); border-color: rgba(var(--v-theme-error),   0.2); color: rgb(var(--v-theme-error)); }
.vdd-count--success  { background: rgba(var(--v-theme-success), 0.08); border-color: rgba(var(--v-theme-success), 0.2); color: rgb(var(--v-theme-success)); }
.vdd-count--info     { background: rgba(var(--v-theme-info),    0.08); border-color: rgba(var(--v-theme-info),    0.2); color: rgb(var(--v-theme-info)); }
</style>
