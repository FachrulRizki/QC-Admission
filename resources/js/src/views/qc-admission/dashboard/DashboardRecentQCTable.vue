<script setup>
import { useRouter } from 'vue-router'

const props = defineProps({
  items:   { type: Array,   default: () => [] },
  loading: { type: Boolean, default: false },
})

const router  = useRouter()
const search  = ref('')
const page    = ref(1)
const perPage = 50

const filtered = computed(() => {
  if (!search.value.trim()) return props.items
  const q = search.value.toLowerCase()
  return props.items.filter(r =>
    r.nama_pasien?.toLowerCase().includes(q) ||
    r.no_mr?.toLowerCase().includes(q) ||
    r.petugas?.toLowerCase().includes(q)
  )
})

const paginated  = computed(() => filtered.value.slice((page.value - 1) * perPage, page.value * perPage))
const pageCount  = computed(() => Math.ceil(filtered.value.length / perPage))
const startIdx   = computed(() => (page.value - 1) * perPage)
const endIdx     = computed(() => Math.min(page.value * perPage, filtered.value.length))

function statusBg(s) {
  return s === 'Edukasi lanjutan' ? 'badge-lanjutan' : 'badge-edukasi'
}

watch(() => props.items, () => { page.value = 1 })
watch(search, () => { page.value = 1 })
</script>

<template>
  <VCard class="ds-card" elevation="0">
    <!-- Header gradient -->
    <div class="ds-card-header ds-card-header--gradient" style="justify-content: space-between; padding: 12px 20px;">
      <span class="ds-card-title--white">Data Detail <strong>Quality Control</strong> Admission</span>
      <div class="d-flex align-center gap-3">
        <!-- Search -->
        <div class="qc-search-wrap">
          <span class="qc-search-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
          </span>
          <input v-model="search" class="qc-search" placeholder="Cari pasien / petugas..." />
        </div>
        <button class="qc-goto-btn" @click="router.push('/quality-control')">
          Lihat Semua →
        </button>
      </div>
    </div>

    <div class="qc-table-scroll">
      <table class="ds-table">
        <thead>
          <tr>
            <th class="ds-th">tgldaftar</th>
            <th class="ds-th">NamaPasien</th>
            <th class="ds-th">NoMR</th>
            <th class="ds-th">Status</th>
            <th class="ds-th">Jam_Input</th>
            <th class="ds-th">Petugas</th>
            <th class="ds-th">Note</th>
            <th class="ds-th">Edukasi_kamar</th>
            <th class="ds-th text-end">Durasi Tunggu Edukasi (Menit)</th>
          </tr>
        </thead>
        <tbody v-if="loading">
          <tr><td colspan="9" class="text-center py-8"><VProgressCircular indeterminate size="28" color="#00B37E" /></td></tr>
        </tbody>
        <tbody v-else-if="!paginated.length">
          <tr>
            <td colspan="9" class="ds-td text-center py-10 text-medium-emphasis">
              <div>
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#5C6B67" stroke-width="1.5" opacity="0.4" class="mb-2" style="display:block;margin:0 auto 8px">
                  <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                Belum ada data QC untuk periode ini
              </div>
            </td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr v-for="row in paginated" :key="row.no_reg + row.jam_input" class="ds-tr">
            <td class="ds-td text-caption">{{ row.tanggal }}</td>
            <td class="ds-td font-weight-medium">{{ row.nama_pasien }}</td>
            <td class="ds-td text-caption font-weight-bold">{{ row.no_mr }}</td>
            <td class="ds-td">
              <span :class="statusBg(row.status)" class="status-badge">
                {{ row.status }}
              </span>
            </td>
            <td class="ds-td text-caption">{{ row.jam_input }}</td>
            <td class="ds-td">{{ row.petugas }}</td>
            <td class="ds-td text-caption note-cell">{{ row.note || '—' }}</td>
            <td class="ds-td text-caption">{{ row.edukasi_kamar || '—' }}</td>
            <td class="ds-td text-end">
              <span
                :class="[
                  'durasi-badge',
                  (parseInt(row.durasi_tunggu) >= 120 || (row.durasi_tunggu ?? '').startsWith('02') || (row.durasi_tunggu ?? '').startsWith('0') === false)
                    ? 'durasi-badge--warn' : 'durasi-badge--ok'
                ]"
              >
                {{ durasiToMenit(row.durasi_tunggu) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="ds-pagination">
      <span class="ds-pagination__info">
        {{ filtered.length === 0 ? '0' : startIdx + 1 }}–{{ endIdx }}/{{ filtered.length }}
      </span>
      <button class="ds-pg-btn" :disabled="page <= 1" @click="page--">‹</button>
      <button class="ds-pg-btn" :disabled="page >= pageCount" @click="page++">›</button>
    </div>
  </VCard>
</template>

<script>
function durasiToMenit(d) {
  if (!d) return '—'
  const parts = d.split(':').map(Number)
  if (parts.length === 3) {
    const total = parts[0] * 60 + parts[1]
    return total + ' mnt'
  }
  return d
}
</script>

<style scoped>
.qc-table-scroll { overflow-x: auto; max-height: 340px; overflow-y: auto; }
.ds-table { width: 100%; border-collapse: collapse; min-width: 900px; }
.ds-th {
  background: #F0F4F3; color: #5C6B67;
  font-size: 0.7rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.04em;
  padding: 8px 12px; white-space: nowrap;
  border-bottom: 1px solid #E1E7E5;
  position: sticky; top: 0; z-index: 2;
}
.ds-td {
  padding: 7px 12px; font-size: 0.82rem;
  color: #1A1F1E; border-bottom: 1px solid #E1E7E5;
  vertical-align: top;
}
.ds-tr:hover td { background: #F4FBF8; }
.note-cell { max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #5C6B67; }

/* Status badges — persis dari design.md */
.status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; white-space: nowrap; }
.badge-edukasi          { background: #1A1F1E; color: #fff; }
.badge-lanjutan         { background: #fff; color: #1A1F1E; border: 1.5px solid #00B37E; }

/* Durasi badge */
.durasi-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; }
.durasi-badge--ok   { background: #D7F5EA; color: #005C42; }
.durasi-badge--warn { background: #FFF3CD; color: #856404; }

/* Search */
.qc-search-wrap { position: relative; display: flex; align-items: center; }
.qc-search-icon { position: absolute; left: 8px; color: rgba(255,255,255,0.7); pointer-events: none; display: flex; }
.qc-search {
  background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
  border-radius: 8px; padding: 5px 10px 5px 28px; color: #fff; font-size: 0.8rem;
  width: 200px; outline: none;
}
.qc-search::placeholder { color: rgba(255,255,255,0.6); }
.qc-search:focus { background: rgba(255,255,255,0.25); }

.qc-goto-btn {
  background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.35);
  border-radius: 8px; padding: 5px 12px; font-size: 0.8rem; cursor: pointer;
  transition: background 0.15s; white-space: nowrap;
}
.qc-goto-btn:hover { background: rgba(255,255,255,0.3); }

.ds-pagination {
  display: flex; align-items: center; justify-content: flex-end;
  gap: 6px; padding: 8px 16px; border-top: 1px solid #E1E7E5;
}
.ds-pagination__info { font-size: 0.75rem; color: #5C6B67; margin-right: 4px; }
.ds-pg-btn {
  width: 28px; height: 28px; border-radius: 50%; border: 1.5px solid #00B37E;
  background: transparent; color: #00B37E; font-size: 1rem; cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: background 0.15s;
}
.ds-pg-btn:hover:not(:disabled) { background: #D7F5EA; }
.ds-pg-btn:disabled { border-color: #E1E7E5; color: #E1E7E5; cursor: default; }
</style>
