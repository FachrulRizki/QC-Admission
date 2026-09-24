<script setup>
import { useUpSellingStore } from '@/stores/useUpSellingStore'
import UpSellingFormDialog from '@/views/qc-admission/up-selling/UpSellingFormDialog.vue'
import SummaryCards from '@/components/SummaryCards.vue'
import PageHero from '@/components/PageHero.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import { usePagination } from '@/composables/usePagination'

const store = useUpSellingStore()

const showForm = ref(false)
const editItem = ref(null)
const showDetail = ref(false)
const detailItem = ref(null)
const showDel = ref(false)
const delTarget = ref(null)
const loading = ref(false)
const snackbar = ref({ show: false, msg: '', color: 'success' })

function todayStr() {
  const d = new Date(), p = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}

const todayFormatted = computed(() =>
  new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
)

const search = ref('')
const dateFrom = ref(todayStr())
const dateTo = ref(todayStr())
const filterAlasan = ref('')

const records = computed(() => store.records ?? [])

const stats = computed(() => ({
  total: records.value.length,
  naikKelas: records.value.filter(r => r.alasan === 'Naik Kelas').length,
  perubahan: records.value.filter(r => r.alasan === 'Perubahan Jaminan').length,
}))

const filtered = computed(() => {
  let d = records.value
  if (filterAlasan.value) d = d.filter(r => r.alasan === filterAlasan.value)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    d = d.filter(r =>
      r.no_reg?.toLowerCase().includes(q) ||
      r.nama_pasien?.toLowerCase().includes(q) ||
      r.petugas?.toLowerCase().includes(q) ||
      r.note?.toLowerCase().includes(q)
    )
  }
  return d
})

function ketColor(k) { return k === 'Naik Kelas' ? 'success' : 'info' }

function openRow(item)  { detailItem.value = item; showDetail.value = true }
function openAdd()      { editItem.value = null; showForm.value = true }
function openEdit(item) { editItem.value = { ...item }; showDetail.value = false; showForm.value = true }

function toast(msg, color = 'success') { snackbar.value = { show: true, msg, color } }

async function onSaved() { showForm.value = false; toast('Data disimpan.'); await load() }

async function doDel() {
  if (!delTarget.value) return
  loading.value = true
  await store.destroy(delTarget.value.id)
  loading.value = false
  showDel.value = false
  showDetail.value = false
  delTarget.value = null
  toast('Data dihapus.')
  await load()
}

function resetFilter() {
  search.value = ''
  filterAlasan.value = ''
  dateFrom.value = todayStr()
  dateTo.value = todayStr()
  load()
}

async function load() {
  loading.value = true
  try {
    await store.fetchRecords({
      per_page: 100,
      search: search.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
    })
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

let filterTimer = null
watch([dateFrom, dateTo], () => {
  clearTimeout(filterTimer)
  filterTimer = setTimeout(() => load(), 300)
})

// ── Pagination ────────────────────────────────────────────────────────────────
const { page, pageCount, paginated: paginatedFiltered, setPage } = usePagination(filtered, 10)

onMounted(load)
</script>

<template>
  <div>
    <!-- Header -->
    <PageHero icon="ri-arrow-up-circle-line" badge="Up Selling" title="Up Selling"
      subtitle="Penawaran upgrade kelas kamar rawat inap" color-from="#0EA5E9" color-to="#0369A1" :pills="[
        { icon: 'ri-calendar-line', text: todayFormatted },
        { icon: 'ri-database-line', text: `${stats.total} data` },
      ]">
      <template #actions>
        <VBtn color="white" variant="elevated" rounded="pill" size="small" style="color:#0369A1;font-weight:700"
          @click="openAdd">
          <VIcon icon="ri-add-line" size="16" class="me-1" />Input Up Selling
        </VBtn>
      </template>
    </PageHero>

    <!-- Stats -->
    <SummaryCards :cards="[
      { value: stats.total,     label: 'Total Up Selling',    color: 'primary', icon: 'ri-arrow-up-circle-line' },
      { value: stats.naikKelas, label: 'Naik Kelas',          color: 'success', icon: 'ri-building-line' },
      { value: stats.perubahan, label: 'Perubahan Jaminan',   color: 'info',    icon: 'ri-exchange-line' },
    ]" />

    <!-- Filter -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VCardText class="pa-3">
        <VRow dense align="center">
          <VCol cols="12" sm="4">
            <VTextField v-model="search" label="Cari pasien / petugas / notes" prepend-inner-icon="ri-search-line"
              variant="outlined" density="compact" hide-details clearable rounded="lg" />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details
              rounded="lg" />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details
              rounded="lg" />
          </VCol>
          <VCol cols="12" sm="3">
            <VSelect v-model="filterAlasan" :items="[
              { title: 'Semua Keterangan', value: '' },
              { title: '🏢 Naik Kelas',         value: 'Naik Kelas' },
              { title: '🔄 Perubahan Jaminan',  value: 'Perubahan Jaminan' },
            ]" item-title="title" item-value="value" label="Keterangan" variant="outlined" density="compact"
              hide-details rounded="lg" />
          </VCol>
          <VCol cols="auto">
            <VBtn size="small" variant="text" color="secondary" @click="resetFilter">Reset</VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Count -->
    <div class="d-flex align-center gap-3 mb-4">
      <VChip size="small" color="primary" variant="tonal" rounded="pill">{{ filtered.length }} data</VChip>
      <span class="text-caption" style="color:var(--qc-text-2)">Klik baris untuk detail & edit</span>
    </div>

    <!-- List -->
    <VCard elevation="0" border rounded="lg" class="overflow-hidden">
      <div v-if="loading" class="text-center py-12">
        <VProgressCircular indeterminate color="primary" size="32" />
        <p class="text-caption mt-3" style="color:var(--qc-text-2)">Memuat data...</p>
      </div>

      <div v-else-if="!filtered.length" class="text-center py-16" style="color:var(--qc-text-2)">
        <VIcon icon="ri-arrow-up-circle-line" size="52" class="mb-3 opacity-30" />
        <p class="text-body-1 font-weight-semibold mb-1">Belum ada data</p>
        <p class="text-caption mb-4">Klik "Input Up Selling" untuk menambah data</p>
        <VBtn color="primary" variant="tonal" rounded="lg" size="small" @click="openAdd">
          <VIcon icon="ri-add-line" size="15" class="me-1" />Input Up Selling
        </VBtn>
      </div>

      <div v-else>
        <div v-for="item in paginatedFiltered" :key="item.id" class="us-row" @click="openRow(item)">
          <VAvatar :color="ketColor(item.alasan)" variant="tonal" size="40" rounded="lg" class="flex-shrink-0">
            <span style="font-size:14px;font-weight:700">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
          </VAvatar>

          <div class="flex-grow-1 min-width-0">
            <div class="d-flex align-center gap-2 flex-wrap">
              <span class="font-weight-semibold text-truncate" style="font-size:0.9rem;color:var(--qc-text)">
                {{ item.nama_pasien }}
              </span>
              <VChip :color="ketColor(item.alasan)" size="x-small" variant="tonal">{{ item.alasan || '—' }}</VChip>
            </div>
            <div class="d-flex align-center gap-3 mt-1 flex-wrap">
              <span class="text-caption" style="color:var(--qc-text-2)">
                <VIcon icon="ri-hashtag" size="11" />{{ item.no_reg }}
              </span>
              <span v-if="item.jaminan" class="text-caption" style="color:var(--qc-text-2)">{{ item.jaminan }}</span>
              <span v-if="item.kelas" class="text-caption" style="color:var(--qc-text-2)">
                <VIcon icon="ri-hotel-bed-line" size="11" class="me-1" />{{ item.kelas }}
              </span>
              <span v-if="item.nama_bangsal" class="text-caption" style="color:var(--qc-text-2)">
                <VIcon icon="ri-hospital-line" size="11" class="me-1" />{{ item.nama_bangsal }}
              </span>
            </div>
          </div>

          <div class="text-end flex-shrink-0">
            <p class="text-caption mb-0 font-weight-medium" style="color:var(--qc-text)">{{ item.petugas }}</p>
            <p class="text-caption mb-0" style="color:var(--qc-text-2)">{{ item.tgl_daftar || item.tanggal }}</p>
          </div>
        </div>
        <PaginationBar :page="page" :page-count="pageCount" :total="filtered.length" :per-page="10"
          @update:page="setPage" />
      </div>
    </VCard>

    <!-- Form Dialog -->
    <UpSellingFormDialog v-model="showForm" :edit-item="editItem" @saved="onSaved" />

    <!-- Detail Dialog -->
    <VDialog v-model="showDetail" max-width="460">
      <VCard v-if="detailItem" rounded="lg" class="qc-dlg-card overflow-hidden">

        <!-- Banner header — sama dengan QC detail -->
        <div class="us-detail-header">
          <div class="blob b1" /><div class="blob b2" /><div class="blob b3" />
          <div class="d-flex align-center gap-3" style="position:relative;z-index:2">
            <div class="us-detail-av">{{ detailItem.nama_pasien?.charAt(0) ?? '?' }}</div>
            <div class="flex-grow-1 min-width-0">
              <p class="us-detail-name text-truncate">{{ detailItem.nama_pasien }}</p>
              <p class="us-detail-sub mb-0">{{ detailItem.no_reg }}{{ detailItem.no_mr ? ` · MR ${detailItem.no_mr}` : '' }}</p>
            </div>
            <button class="us-close-btn" @click="showDetail = false">
              <VIcon icon="ri-close-line" size="16" />
            </button>
          </div>
          <!-- Status pills -->
          <div class="d-flex gap-2 mt-3 flex-wrap" style="position:relative;z-index:2">
            <span class="us-pill" :class="detailItem.alasan === 'Naik Kelas' ? 'us-pill--ok' : 'us-pill--info'">
              <VIcon :icon="detailItem.alasan === 'Naik Kelas' ? 'ri-building-line' : 'ri-exchange-line'" size="12" class="me-1" />
              {{ detailItem.alasan || '—' }}
            </span>
            <span v-if="detailItem.kelas" class="us-pill us-pill--neutral">
              <VIcon icon="ri-hotel-bed-line" size="12" class="me-1" />{{ detailItem.kelas }}
            </span>
          </div>
        </div>

        <!-- Info grid — sama persis dengan pola qc-info-grid -->
        <div class="qc-dlg-body">
        <div class="qc-info-grid">
          <div class="qc-info-cell">
            <span class="qc-lbl">Tgl. Daftar</span>
            <span class="qc-val">{{ detailItem.tgl_daftar || '—' }}</span>
          </div>
          <div class="qc-info-cell">
            <span class="qc-lbl">No. MR</span>
            <span class="qc-val font-weight-bold">{{ detailItem.no_mr || '—' }}</span>
          </div>
          <div class="qc-info-cell">
            <span class="qc-lbl">Jaminan</span>
            <span class="qc-val">{{ detailItem.jaminan || '—' }}</span>
          </div>
          <div class="qc-info-cell">
            <span class="qc-lbl">Kelas</span>
            <span class="qc-val">{{ detailItem.kelas_diambil || detailItem.kelas || detailItem.rekomendasi_kelas || '—' }}</span>
          </div>
          <div class="qc-info-cell">
            <span class="qc-lbl">Nama Ruang</span>
            <span class="qc-val">{{ detailItem.nama_ruang || '—' }}</span>
          </div>
          <div class="qc-info-cell">
            <span class="qc-lbl">Bangsal</span>
            <span class="qc-val">{{ detailItem.nama_bangsal || '—' }}</span>
          </div>
          <div class="qc-info-cell">
            <span class="qc-lbl">Petugas</span>
            <span class="qc-val">{{ detailItem.petugas || '—' }}</span>
          </div>
          <div class="qc-info-cell">
            <span class="qc-lbl">Keterangan</span>
            <span class="qc-val">{{ detailItem.alasan || '—' }}</span>
          </div>
          <div class="qc-info-cell qc-info-cell--full">
            <span class="qc-lbl">Catatan</span>
            <span class="qc-val" style="white-space:pre-wrap">{{ detailItem.note || '—' }}</span>
          </div>
        </div>

        <!-- Action bar — sama dengan QC -->
        </div>
        <div class="qc-action-bar">
          <VBtn variant="outlined" rounded="lg" class="qc-action-btn" @click="showDetail = false">Tutup</VBtn>
          <VBtn color="primary" variant="tonal" rounded="lg" class="qc-action-btn qc-action-btn--grow"
            @click="openEdit(detailItem)">
            <VIcon icon="ri-pencil-line" size="15" class="me-1" />Edit Data
          </VBtn>
          <VBtn color="error" variant="tonal" rounded="lg" class="qc-action-btn"
            @click="delTarget = detailItem; showDel = true">
            <VIcon icon="ri-delete-bin-line" size="15" />
          </VBtn>
        </div>
      </VCard>
    </VDialog>

    <!-- Delete Confirm -->
    <VDialog v-model="showDel" max-width="340">
      <VCard rounded="lg">
        <VCardText class="pa-6 text-center">
          <VAvatar color="error" variant="tonal" size="56" rounded="lg" class="mb-3">
            <VIcon icon="ri-delete-bin-2-line" size="26" />
          </VAvatar>
          <p class="text-h6 font-weight-bold mb-1">Hapus Data?</p>
          <p class="text-body-2" style="color:var(--qc-text-2)">
            Data <strong>{{ delTarget?.nama_pasien }}</strong> akan dihapus permanen.
          </p>
        </VCardText>
        <div class="d-flex gap-2 px-5 pb-5">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showDel = false">Batal</VBtn>
          <VBtn color="error" rounded="lg" class="flex-grow-1" :loading="loading" @click="doDel">
            <VIcon icon="ri-delete-bin-line" size="15" class="me-1" />Hapus
          </VBtn>
        </div>
      </VCard>
    </VDialog>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom right" rounded="xl">
      {{ snackbar.msg }}
      <template #actions>
        <VBtn variant="text" size="small" @click="snackbar.show = false">✕</VBtn>
      </template>
    </VSnackbar>
  </div>
</template>

<style scoped>
/* ── Detail dialog card ── */
.qc-dlg-card { display: flex; flex-direction: column; max-height: 92dvh; }
.qc-dlg-body { flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; }

/* ── Row list ── */
.us-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 15px 16px;
  cursor: pointer;
  transition: background 0.12s;
  border-bottom: 1px solid var(--qc-border, rgba(0, 0, 0, 0.07));
}
.us-row:last-child { border-bottom: none; }
.us-row:hover {
  background: rgba(14, 165, 233, 0.04);
  border-left: 3px solid rgba(14, 165, 233, 0.35);
  padding-left: 13px;
}

/* ── Detail banner ── */
.us-detail-header {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #0369A1 0%, #0EA5E9 60%, #38BDF8 100%);
  padding: 20px 20px 16px;
  border-radius: 8px 8px 0 0;
}
.blob {
  position: absolute;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.08);
}
.b1 { width: 180px; height: 180px; top: -60px; right: -30px; }
.b2 { width: 90px;  height: 90px;  bottom: -35px; right: 100px; }
.b3 { width: 55px;  height: 55px;  top: 5px; right: 180px; background: rgba(255,255,255,0.05); }

.us-detail-av {
  width: 52px; height: 52px;
  border-radius: 10px;
  flex-shrink: 0;
  background: rgba(255, 255, 255, 0.2);
  border: 2px solid rgba(255, 255, 255, 0.35);
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; font-weight: 800; color: #fff;
}
.us-detail-name {
  font-size: 1.05rem; font-weight: 700; color: #fff; margin: 0 0 2px;
}
.us-detail-sub {
  font-size: 0.75rem; color: rgba(255,255,255,0.75);
}
.us-close-btn {
  background: rgba(255,255,255,0.18); border: none; cursor: pointer;
  width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  color: #fff; transition: background 0.15s;
}
.us-close-btn:hover { background: rgba(255,255,255,0.32); }

.us-pill {
  display: inline-flex; align-items: center;
  padding: 3px 10px; border-radius: 20px;
  font-size: 0.72rem; font-weight: 700;
}
.us-pill--ok      { background: rgba(34,197,94,0.28);  color: #d1fae5; }
.us-pill--info    { background: rgba(56,189,248,0.28);  color: #e0f2fe; }
.us-pill--neutral { background: rgba(255,255,255,0.18); color: rgba(255,255,255,0.9); }

/* ── Info grid — sama dengan quality-control ── */
.qc-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  padding: 4px 0;
}
.qc-info-cell {
  display: flex; flex-direction: column;
  padding: 11px 20px;
  border-bottom: 1px solid var(--qc-border);
  border-right: 1px solid var(--qc-border);
}
.qc-info-cell:nth-child(even) { border-right: none; }
.qc-info-cell--full { grid-column: span 2; border-right: none; }
.qc-lbl {
  font-size: 0.63rem; text-transform: uppercase;
  letter-spacing: 0.07em; color: var(--qc-text-2);
  margin-bottom: 2px; font-weight: 600;
}
.qc-val { font-size: 0.875rem; font-weight: 500; color: var(--qc-text); }

/* ── Action bar ── */
.qc-action-bar {
  display: flex; gap: 8px;
  padding: 14px 16px;
  border-top: 1px solid var(--qc-border);
  flex-wrap: wrap;
}
.qc-action-btn { min-height: 40px; font-size: 0.85rem; font-weight: 600; }
.qc-action-btn--grow { flex: 1; }

@media (max-width: 400px) {
  .qc-action-bar { flex-direction: column; }
  .qc-action-btn { width: 100%; }
}
</style>
