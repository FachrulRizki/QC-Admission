<script setup>
import { useRouter } from 'vue-router'

const props = defineProps({
  items:   { type: Array,   default: () => [] },
  loading: { type: Boolean, default: false },
})

const router = useRouter()

// ── Search & checkbox filter kolom ────────────────────────────────────────────
const search = ref('')

// Kolom yang bisa ditampilkan/disembunyikan
const ALL_COLS = [
  { key: 'tanggal',      label: 'Tgl Daftar' },
  { key: 'nama_pasien',  label: 'Nama Pasien' },
  { key: 'no_mr',        label: 'No. MR' },
  { key: 'status',       label: 'Status' },
  { key: 'jam_input',    label: 'Jam Input' },
  { key: 'petugas',      label: 'Petugas' },
  { key: 'edukasi_kamar',label: 'Edukasi Kamar' },
  { key: 'note',         label: 'Note' },
  { key: 'durasi',       label: 'Durasi (mnt)' },
]
// Default: semua tampil kecuali note & edukasi_kamar (supaya tidak terlalu lebar)
const visibleCols = ref(['tanggal','nama_pasien','no_mr','status','jam_input','petugas','durasi'])
const showColMenu = ref(false)

// ── Pagination ────────────────────────────────────────────────────────────────
const page    = ref(1)
const perPage = ref(20)

const filtered = computed(() => {
  let d = props.items
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    d = d.filter(r =>
      r.nama_pasien?.toLowerCase().includes(q) ||
      r.no_mr?.toLowerCase().includes(q) ||
      r.no_reg?.toLowerCase().includes(q) ||
      r.petugas?.toLowerCase().includes(q)
    )
  }
  return d
})

const paginated = computed(() =>
  filtered.value.slice((page.value - 1) * perPage.value, page.value * perPage.value)
)
const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)))

watch(() => props.items, () => { page.value = 1 })
watch(search, () => { page.value = 1 })

function durasiToMenit(d) {
  if (!d) return '—'
  const parts = d.split(':').map(Number)
  if (parts.length === 3) return (parts[0] * 60 + parts[1]) + ' mnt'
  return d
}
function isLongDurasi(d) {
  if (!d) return false
  const parts = d.split(':').map(Number)
  return parts.length === 3 && (parts[0] * 60 + parts[1]) >= 120
}
function colVisible(key) { return visibleCols.value.includes(key) }
function toggleCol(key) {
  if (visibleCols.value.includes(key)) {
    if (visibleCols.value.length > 2) // minimal 2 kolom
      visibleCols.value = visibleCols.value.filter(k => k !== key)
  } else {
    visibleCols.value.push(key)
  }
}
</script>

<template>
  <VCard class="ds-card" elevation="0">
    <!-- Header -->
    <div class="rqc-header">
      <div class="d-flex align-center gap-2 flex-shrink-0">
        <div class="rqc-avatar">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
        </div>
        <div>
          <p class="rqc-title">Data Quality Control</p>
          <p class="rqc-sub">{{ filtered.length }} record · klik nama pasien untuk detail</p>
        </div>
      </div>

      <div class="d-flex align-center gap-2 flex-wrap justify-end flex-grow-1">
        <!-- Search -->
        <div class="rqc-search-wrap">
          <svg class="rqc-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input
            v-model="search"
            class="rqc-search"
            placeholder="Cari nama / no MR / petugas..."
          />
          <button v-if="search" class="rqc-search-clear" @click="search = ''">✕</button>
        </div>

        <!-- Column toggle -->
        <div style="position:relative">
          <button class="rqc-col-btn" @click="showColMenu = !showColMenu" :class="showColMenu ? 'rqc-col-btn--active' : ''">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
              <line x1="8" y1="18" x2="21" y2="18"/>
              <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/>
              <line x1="3" y1="18" x2="3.01" y2="18"/>
            </svg>
            Kolom
          </button>
          <Transition name="col-fade">
            <div v-if="showColMenu" class="rqc-col-menu">
              <p class="rqc-col-menu__title">Tampilkan Kolom</p>
              <label
                v-for="col in ALL_COLS"
                :key="col.key"
                class="rqc-col-item"
              >
                <input
                  type="checkbox"
                  :checked="colVisible(col.key)"
                  class="rqc-col-check"
                  @change="toggleCol(col.key)"
                />
                <span>{{ col.label }}</span>
              </label>
            </div>
          </Transition>
        </div>

        <button class="rqc-goto" @click="router.push('/quality-control')">
          Lihat Semua →
        </button>
      </div>
    </div>

    <!-- Table — no horizontal scroll, hanya tampilkan kolom terpilih -->
    <div class="rqc-table-wrap">
      <!-- Loading -->
      <div v-if="loading" class="rqc-empty">
        <VProgressCircular indeterminate size="28" color="var(--qc-green)" />
        <span>Memuat data...</span>
      </div>

      <!-- Empty -->
      <div v-else-if="!paginated.length" class="rqc-empty">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#5C6B67" stroke-width="1.5" opacity="0.4">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <span>{{ search ? 'Tidak ditemukan' : 'Belum ada data QC periode ini' }}</span>
      </div>

      <!-- Table -->
      <table v-else class="rqc-table">
        <thead>
          <tr>
            <th v-if="colVisible('tanggal')"       class="rqc-th">Tgl Daftar</th>
            <th                                     class="rqc-th">Nama Pasien</th>
            <th v-if="colVisible('no_mr')"          class="rqc-th hide-xs">No. MR</th>
            <th v-if="colVisible('status')"         class="rqc-th">Status</th>
            <th v-if="colVisible('jam_input')"      class="rqc-th hide-xs">Jam Input</th>
            <th v-if="colVisible('petugas')"        class="rqc-th hide-sm">Petugas</th>
            <th v-if="colVisible('edukasi_kamar')"  class="rqc-th hide-sm">Edukasi Kamar</th>
            <th v-if="colVisible('note')"           class="rqc-th hide-sm">Note</th>
            <th v-if="colVisible('durasi')"         class="rqc-th text-end hide-xs">Durasi</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in paginated"
            :key="row.id ?? (row.no_reg + row.jam_input)"
            class="rqc-tr"
          >
            <td v-if="colVisible('tanggal')"      class="rqc-td rqc-td--muted">{{ row.tanggal }}</td>
            <!-- Nama pasien — klik buka detail -->
            <td class="rqc-td rqc-td--name">
              <div class="d-flex align-center gap-2">
                <div class="rqc-avatar-sm">{{ row.nama_pasien?.charAt(0) ?? '?' }}</div>
                <span>{{ row.nama_pasien }}</span>
              </div>
            </td>
            <td v-if="colVisible('no_mr')"         class="rqc-td rqc-td--mono hide-xs">{{ row.no_mr }}</td>
            <td v-if="colVisible('status')"        class="rqc-td">
              <span :class="row.status === 'Edukasi lanjutan' ? 'badge-lanjutan' : 'badge-edukasi'">
                {{ row.status }}
              </span>
            </td>
            <td v-if="colVisible('jam_input')"     class="rqc-td rqc-td--muted hide-xs">{{ row.jam_input }}</td>
            <td v-if="colVisible('petugas')"       class="rqc-td hide-sm">{{ row.petugas }}</td>
            <td v-if="colVisible('edukasi_kamar')" class="rqc-td rqc-td--muted hide-sm">{{ row.edukasi_kamar || '—' }}</td>
            <td v-if="colVisible('note')"          class="rqc-td rqc-td--note hide-sm">{{ row.note || '—' }}</td>
            <td v-if="colVisible('durasi')"        class="rqc-td text-end hide-xs">
              <span :class="isLongDurasi(row.durasi_tunggu) ? 'durasi-warn' : 'durasi-ok'">
                {{ durasiToMenit(row.durasi_tunggu) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="rqc-footer">
      <span class="rqc-footer__info">
        {{ filtered.length === 0 ? '0' : (page - 1) * perPage + 1 }}–{{ Math.min(page * perPage, filtered.length) }}
        dari {{ filtered.length }}
      </span>
      <div class="d-flex gap-1">
        <button class="rqc-pg-btn" :disabled="page <= 1" @click="page--">‹</button>
        <button class="rqc-pg-btn" :disabled="page >= pageCount" @click="page++">›</button>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
/* ── Card container ──────────────────────────────────────────────────────────── */
.ds-card {
  background: #fff !important;
  border: 1px solid #E1E7E5 !important;
  border-radius: 14px !important;
  box-shadow: 0 2px 12px rgba(16,24,22,0.07) !important;
  overflow: hidden;
}

/* ── Header ──────────────────────────────────────────────────────────────────── */
.rqc-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 18px;
  background: linear-gradient(135deg, var(--qc-green-dark), var(--qc-green));
  flex-wrap: wrap;
}
.rqc-avatar {
  width: 38px; height: 38px;
  border-radius: 10px;
  background: rgba(255,255,255,0.15);
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  flex-shrink: 0;
}
.rqc-title { font-size: 0.9rem; font-weight: 700; color: #fff; margin: 0; }
.rqc-sub   { font-size: 0.72rem; color: rgba(255,255,255,0.75); margin: 0; }

/* Search */
.rqc-search-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.rqc-search-icon {
  position: absolute;
  left: 9px;
  color: rgba(255,255,255,0.65);
  pointer-events: none;
}
.rqc-search {
  background: rgba(255,255,255,0.15);
  border: 1px solid rgba(255,255,255,0.25);
  border-radius: 8px;
  padding: 6px 28px 6px 30px;
  color: #fff;
  font-size: 0.8rem;
  width: 200px;
  outline: none;
  transition: background 0.15s;
}
.rqc-search::placeholder { color: rgba(255,255,255,0.55); }
.rqc-search:focus { background: rgba(255,255,255,0.22); }
.rqc-search-clear {
  position: absolute; right: 8px;
  background: none; border: none;
  color: rgba(255,255,255,0.7); font-size: 0.7rem;
  cursor: pointer; padding: 0;
}

/* Column button */
.rqc-col-btn {
  display: flex; align-items: center; gap: 5px;
  background: rgba(255,255,255,0.15);
  border: 1px solid rgba(255,255,255,0.25);
  border-radius: 8px;
  padding: 6px 11px;
  color: #fff; font-size: 0.8rem; font-weight: 600;
  cursor: pointer; transition: background 0.15s;
  white-space: nowrap;
}
.rqc-col-btn:hover, .rqc-col-btn--active { background: rgba(255,255,255,0.25); }

/* Column dropdown */
.rqc-col-menu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  background: #fff;
  border: 1px solid #E1E7E5;
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  padding: 10px 12px;
  z-index: 50;
  min-width: 170px;
}
.rqc-col-menu__title {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #5C6B67;
  margin: 0 0 8px;
}
.rqc-col-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 5px 4px;
  font-size: 0.82rem;
  color: #1A1F1E;
  cursor: pointer;
  border-radius: 6px;
  transition: background 0.12s;
}
.rqc-col-item:hover { background: #F0F4F3; }
.rqc-col-check {
  accent-color: var(--qc-green);
  width: 15px; height: 15px;
  cursor: pointer;
}

/* Go to button */
.rqc-goto {
  background: rgba(255,255,255,0.18);
  border: 1px solid rgba(255,255,255,0.3);
  border-radius: 8px;
  padding: 6px 12px;
  color: #fff; font-size: 0.8rem; font-weight: 600;
  cursor: pointer; transition: background 0.15s; white-space: nowrap;
}
.rqc-goto:hover { background: rgba(255,255,255,0.28); }

/* ── Table wrapper — NO horizontal scroll ────────────────────────────────────── */
.rqc-table-wrap {
  width: 100%;
  overflow-x: hidden; /* tidak scroll horizontal */
  overflow-y: auto;
  max-height: 360px;
  -webkit-overflow-scrolling: touch;
}

/* ── Table ───────────────────────────────────────────────────────────────────── */
.rqc-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed; /* kolom tidak melebihi lebar kontainer */
}
.rqc-th {
  background: #F5F7FA;
  color: #5C6B67;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 9px 12px;
  border-bottom: 2px solid #E1E7E5;
  position: sticky; top: 0; z-index: 2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rqc-td {
  padding: 8px 12px;
  font-size: 0.82rem;
  color: #1A1F1E;
  border-bottom: 1px solid #F0F4F3;
  vertical-align: middle;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rqc-tr:hover td { background: #F4FBF8; }

.rqc-td--muted { color: #5C6B67; font-size: 0.78rem; }
.rqc-td--mono  { font-family: 'JetBrains Mono', monospace; font-size: 0.78rem; font-weight: 700; }
.rqc-td--note  { max-width: 140px; color: #5C6B67; }
.rqc-td--name  { font-weight: 600; max-width: 160px; }

/* Avatar kecil untuk nama pasien */
.rqc-avatar-sm {
  width: 26px; height: 26px;
  border-radius: 7px;
  background: rgba(0,179,126,0.12);
  color: var(--qc-green-dark);
  font-size: 0.7rem;
  font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  text-transform: uppercase;
}

/* Status badges */
.badge-edukasi {
  display: inline-block;
  padding: 2px 9px;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 700;
  background: #1A1F1E;
  color: #fff;
  white-space: nowrap;
}
.badge-lanjutan {
  display: inline-block;
  padding: 2px 9px;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 700;
  background: #fff;
  color: var(--qc-green);
  border: 1.5px solid var(--qc-green);
  white-space: nowrap;
}

/* Durasi badges */
.durasi-ok   { display:inline-block; padding:2px 7px; border-radius:10px; font-size:0.72rem; font-weight:700; background:var(--qc-green-light); color:var(--qc-green-dark); }
.durasi-warn { display:inline-block; padding:2px 7px; border-radius:10px; font-size:0.72rem; font-weight:700; background:#FFF3CD; color:#856404; }

/* ── Responsive — hide columns on small screens ──────────────────────────────── */
@media (max-width: 599px) {
  .hide-xs { display: none !important; }
  .rqc-search { width: 140px; }
  .rqc-table-wrap { max-height: 280px; }
}
@media (max-width: 768px) {
  .hide-sm { display: none !important; }
}

/* ── Pagination ──────────────────────────────────────────────────────────────── */
.rqc-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  padding: 8px 16px;
  border-top: 1px solid #E1E7E5;
}
.rqc-footer__info { font-size: 0.75rem; color: #5C6B67; }
.rqc-pg-btn {
  width: 28px; height: 28px;
  border-radius: 8px;
  border: 1.5px solid var(--qc-green);
  background: transparent;
  color: var(--qc-green);
  font-size: 1rem;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.12s;
}
.rqc-pg-btn:hover:not(:disabled) { background: var(--qc-green-light); }
.rqc-pg-btn:disabled { border-color: #E1E7E5; color: #E1E7E5; cursor: default; }

/* ── Column menu transition ──────────────────────────────────────────────────── */
.col-fade-enter-active, .col-fade-leave-active { transition: opacity 0.12s, transform 0.12s; }
.col-fade-enter-from, .col-fade-leave-to { opacity: 0; transform: translateY(-4px); }

/* Text align right helper */
.text-end { text-align: right !important; }
</style>

