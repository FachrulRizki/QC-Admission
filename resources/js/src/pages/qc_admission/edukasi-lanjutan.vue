<script setup>
import { useEdukasiLanjutanStore } from '@/stores/useEdukasiLanjutanStore'
import EdukasiDetailDialog from '@/views/qc-admission/edukasi-lanjutan/EdukasiDetailDialog.vue'
import SummaryCards from '@/components/SummaryCards.vue'
import PageHero from '@/components/PageHero.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import { usePagination } from '@/composables/usePagination'

const store = useEdukasiLanjutanStore()
const loading = ref(false)
const syncing = ref(false)
const snackbar = ref({ show: false, msg: '', color: 'success' })

// ── Live clock untuk hitung durasi menunggu ───────────────────────────────────
const now = ref(Date.now())
let ticker = null
onMounted(() => { ticker = setInterval(() => { now.value = Date.now() }, 30_000) })
onUnmounted(() => clearInterval(ticker))

/**
 * Hitung lama pasien menunggu bed sejak created_at record Edukasi Lanjutan.
 * Menampilkan "X jam Y mnt" — live update.
 */
function getWaktuMenunggu(patient) {
  if (!patient.created_at) return null
  const elapsedSec = Math.floor((now.value - new Date(patient.created_at).getTime()) / 1000)
  if (elapsedSec < 0) return null
  const h = Math.floor(elapsedSec / 3600)
  const m = Math.floor((elapsedSec % 3600) / 60)
  const day = Math.floor(h / 24)
  if (day >= 1) return `${day}h ${h % 24}j`
  if (h >= 1) return `${h}j ${String(m).padStart(2, '0')}m`
  return `${m} mnt`
}

/** Warna berdasarkan lama menunggu: > 4j = merah, > 2j = kuning, lainnya = primary */
function getWaktuColor(patient) {
  if (!patient.created_at) return 'secondary'
  const elapsedMin = Math.floor((now.value - new Date(patient.created_at).getTime()) / 60000)
  if (elapsedMin >= 240) return 'error'    // > 4 jam
  if (elapsedMin >= 120) return 'warning'  // > 2 jam
  return 'primary'
}

// ── Detail dialog ─────────────────────────────────────────────────────────────
const showDetail = ref(false)
const detailPatient = ref(null)
const dialogMode = ref('view')

function todayStr() {
  const d = new Date(), p = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}

const search = ref('')
const dateFrom = ref(todayStr())   // default hari ini — tampil data up to date
const dateTo = ref(todayStr())   // default hari ini
const statusFilter = ref('All')

const records = computed(() => store.records ?? [])

// Semua unique pasien (tanpa filter has_transfer) — untuk stats
const allUniquePatients = computed(() => {
  const map = {}
  records.value.forEach(r => {
    if (!map[r.no_mr] || r.id > map[r.no_mr].id) map[r.no_mr] = r
  })
  return Object.values(map)
})

// Unique pasien yang BELUM transfer — untuk tampilan list
// Urutkan: terlama (created_at terkecil) di atas, terbaru di bawah
const uniquePatients = computed(() => {
  return allUniquePatients.value
    .filter(r => !r.has_transfer)
    .sort((a, b) => {
      const ta = a.created_at ? new Date(a.created_at).getTime() : 0
      const tb = b.created_at ? new Date(b.created_at).getTime() : 0
      return ta - tb
    })
})

const sesiCount = computed(() => {
  const map = {}
  records.value.forEach(r => { map[r.no_mr] = (map[r.no_mr] ?? 0) + 1 })
  return map
})

// Timestamp sesi TERBARU per no_mr
const latestSesiAt = computed(() => {
  const map = {}
  records.value.forEach(r => {
    if (!r.created_at) return
    const t = new Date(r.created_at).getTime()
    if (!map[r.no_mr] || t > map[r.no_mr]) map[r.no_mr] = t
  })
  return map
})

const COOLDOWN_MS = 2 * 60 * 60 * 1000 // 2 jam

const filtered = computed(() => {
  let d = uniquePatients.value

  // Sembunyikan pasien yang sesi terakhirnya < 2 jam lalu
  // (sudah diisi baru, muncul lagi setelah 2 jam)
  d = d.filter(r => {
    const latest = latestSesiAt.value[r.no_mr]
    if (!latest) return true // belum pernah ada sesi — tetap tampil
    return (now.value - latest) >= COOLDOWN_MS
  })

  // Status filter
  if (statusFilter.value !== 'All') d = d.filter(r => r.status === statusFilter.value)

  // Search filter
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

const stats = computed(() => ({
  total:    allUniquePatients.value.length,
  menunggu: allUniquePatients.value.filter(r => r.status === 'Menunggu' && !r.has_transfer).length,
  selesai:  allUniquePatients.value.filter(r => r.has_transfer || r.status === 'Selesai').length,
}))

function openDetail(patient) { detailPatient.value = { ...patient }; dialogMode.value = 'view'; showDetail.value = true }
function openEdit(patient) { detailPatient.value = { ...patient }; dialogMode.value = 'edit'; showDetail.value = true }

async function onSaved() { showDetail.value = false; await load() }

function toast(msg, color = 'success') { snackbar.value = { show: true, msg, color } }

function resetFilter() {
  search.value = ''
  statusFilter.value = 'All'
  dateFrom.value = todayStr()   // kembali ke hari ini, bukan kosong
  dateTo.value = todayStr()
  load()
}

async function syncRsus() {
  syncing.value = true
  try {
    await store.syncFromRsus()
    toast('Sync SIMRS selesai.')
    await load()
  } catch { toast('Sync gagal.', 'error') }
  finally { syncing.value = false }
}

async function load() {
  loading.value = true
  try {
    await store.fetchRecords({
      per_page: 100,
      search: search.value || undefined,
      status: statusFilter.value !== 'All' ? statusFilter.value : undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
    })
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

// Watch filter — re-fetch dari API saat tanggal atau status berubah (debounced)
let filterTimer = null
watch([dateFrom, dateTo, statusFilter], () => {
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
    <PageHero icon="ri-book-open-line" badge="Edukasi Lanjutan" title="Edukasi Lanjutan"
      subtitle="Auto dari QC ≥ 2 jam · Klik pasien untuk tambah sesi" color-from="#0EA5E9" color-to="#0369A1" :pills="[
        { icon: 'ri-user-line', text: `${stats.total} pasien` },
        { icon: 'ri-time-line', text: `${stats.menunggu} menunggu` },
      ]">
      <template #actions>
        <!-- <VBtn color="white" variant="elevated" rounded="pill" size="small" style="color:#0369A1;font-weight:700"
          :loading="syncing" @click="syncRsus">
          <VIcon icon="ri-refresh-line" size="15" class="me-1" />Sync SIMRS
        </VBtn> -->
      </template>
    </PageHero>

    <!-- Stats -->
    <SummaryCards v-model="statusFilter" :cards="[
      { value: stats.total, label: 'Total Pasien', color: 'primary', icon: 'ri-book-open-line', filterValue: 'All' },
      { value: stats.menunggu, label: 'Menunggu Bed', color: 'warning', icon: 'ri-time-line', filterValue: 'Menunggu' },
      { value: stats.selesai, label: 'Sudah Dapat Bed', color: 'success', icon: 'ri-check-double-line', filterValue: 'Selesai', },
    ]" />

    <!-- Filter -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VCardText class="pa-3">
        <VRow dense align="center">
          <VCol cols="12" sm="4">
            <VTextField v-model="search" label="Cari pasien..." prepend-inner-icon="ri-search-line" variant="outlined"
              density="compact" hide-details clearable rounded="lg" />
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
            <VSelect v-model="statusFilter" :items="[
              { title: 'Semua Status', value: 'All' },
              { title: '⏳ Menunggu Bed', value: 'Menunggu' },
              { title: '✅ Selesai', value: 'Selesai' },
            ]" item-title="title" item-value="value" label="Status" variant="outlined" density="compact" hide-details
              rounded="lg" />
          </VCol>
          <VCol cols="auto">
            <VBtn size="small" variant="text" color="secondary" @click="resetFilter">Reset</VBtn>
          </VCol>
        </VRow>
        <!-- Quick range chips -->
        <div class="d-flex gap-2 mt-2 flex-wrap align-center">
          <VChip :color="(!dateFrom && !dateTo) ? 'secondary' : 'default'"
            :variant="(!dateFrom && !dateTo) ? 'elevated' : 'outlined'" size="small" class="cursor-pointer"
            @click="dateFrom = ''; dateTo = ''; load()">
            <VIcon icon="ri-history-line" size="11" class="me-1" />Semua Riwayat
          </VChip>
          <VChip v-if="!dateFrom && !dateTo" color="primary" variant="tonal" size="small" class="cursor-pointer"
            @click="dateFrom = todayStr(); dateTo = todayStr()">
            <VIcon icon="ri-calendar-check-line" size="11" class="me-1" />Hari Ini
          </VChip>
        </div>
      </VCardText>
    </VCard>

    <!-- Count + info periode -->
    <div class="d-flex align-center gap-3 mb-4 flex-wrap">
      <VChip size="small" color="primary" variant="tonal" rounded="pill">{{ filtered.length }} pasien</VChip>
      <span class="text-caption" style="color:var(--qc-text-2)">
        <template v-if="dateFrom && dateTo && dateFrom === dateTo">
          Data hari ini ({{ new Date(dateFrom + 'T00:00:00').toLocaleDateString('id-ID', {
            day: 'numeric', month: 'long',
            year:'numeric' }) }})
        </template>
        <template v-else-if="dateFrom || dateTo">
          Periode {{ dateFrom || '—' }} s/d {{ dateTo || '—' }}
        </template>
        <template v-else>
          Semua riwayat · gunakan filter tanggal untuk mempersempit
        </template>
      </span>
    </div>

    <!-- List -->
    <VCard elevation="0" border rounded="lg" class="overflow-hidden">
      <div v-if="loading" class="text-center py-12">
        <VProgressCircular indeterminate color="success" size="32" />
        <p class="text-caption mt-3" style="color:var(--qc-text-2)">Memuat data...</p>
      </div>

      <div v-else-if="!filtered.length" class="text-center py-16" style="color:var(--qc-text-2)">
        <VIcon icon="ri-book-open-line" size="52" class="mb-3 opacity-30" />
        <p class="text-body-1 font-weight-semibold mb-1">
          {{ (dateFrom || dateTo) ? 'Tidak ada data pada periode ini' : 'Belum ada data' }}
        </p>
        <p class="text-caption mb-4">
          <template v-if="dateFrom === todayStr() || dateTo === todayStr()">
            Belum ada edukasi lanjutan hari ini · Data masuk otomatis dari QC setelah ≥ 2 jam
          </template>
          <template v-else>
            Coba filter ke tanggal lain atau klik "Semua Riwayat" untuk melihat semua data
          </template>
        </p>
        <VBtn variant="tonal" color="primary" rounded="lg" size="small" @click="dateFrom = ''; dateTo = ''; load()">
          <VIcon icon="ri-history-line" size="14" class="me-1" />Lihat Semua Riwayat
        </VBtn>
      </div>

      <div v-else>
        <div v-for="patient in paginatedFiltered" :key="patient.no_mr" class="edu-row" @click="openDetail(patient)">
          <VAvatar :color="patient.status === 'Selesai' ? 'success' : 'warning'" variant="tonal" size="40" rounded="lg"
            class="flex-shrink-0">
            <span class="font-weight-bold" style="font-size:14px">{{ patient.nama_pasien?.charAt(0) ?? '?' }}</span>
          </VAvatar>

          <div class="flex-grow-1 min-width-0">
            <div class="d-flex align-center gap-2 flex-wrap">
              <span class="font-weight-semibold" style="font-size:0.9rem;color:var(--qc-text)">{{ patient.nama_pasien }}</span>
              <VChip v-if="patient.status_ranap" color="purple" size="x-small" variant="tonal">
                <VIcon icon="ri-hospital-fill" size="10" class="me-1" />{{ patient.status_ranap }}
              </VChip>
              <VChip v-else :color="patient.status === 'Selesai' ? 'success' : 'warning'" size="x-small" variant="tonal">
                {{ patient.status }}
              </VChip>
              <VChip v-if="patient.keterangan === 'Belum Diantar'" color="orange" size="x-small" variant="tonal">
                <VIcon icon="ri-walk-line" size="10" class="me-1" />Belum Diantar
              </VChip>
            </div>
            <div class="d-flex align-center gap-3 mt-1 flex-wrap">
              <span class="text-caption" style="color:var(--qc-text-2)">
                <VIcon icon="ri-hashtag" size="11" />{{ patient.no_mr }}
              </span>
              <span v-if="patient.jaminan" class="text-caption" style="color:var(--qc-text-2)">{{ patient.jaminan }}</span>
              <span class="text-caption" style="color:var(--qc-text-2)">
                <VIcon icon="ri-user-line" size="11" class="me-1" />{{ patient.petugas }}
              </span>
              <VChip color="warning" variant="tonal" size="x-small">
                {{ sesiCount[patient.no_mr] ?? 1 }} sesi
              </VChip>
            </div>
          </div>

          <div class="text-end flex-shrink-0">
            <div v-if="patient.status === 'Menunggu' && getWaktuMenunggu(patient)"
              class="d-flex align-center gap-1 justify-end mb-1">
              <VIcon icon="ri-time-line" size="11" :color="getWaktuColor(patient)" />
              <span class="text-caption font-weight-semibold"
                :style="`color:rgb(var(--v-theme-${getWaktuColor(patient)}))`">
                {{ getWaktuMenunggu(patient) }}
              </span>
            </div>
            <p class="text-caption mb-0" style="color:var(--qc-text-2);font-size:0.65rem">{{ patient.tanggal }}</p>
          </div>
        </div>
        <PaginationBar :page="page" :page-count="pageCount" :total="filtered.length" :per-page="10"
          @update:page="setPage" />
      </div>
    </VCard>

    <!-- Detail Dialog -->
    <EdukasiDetailDialog v-model="showDetail" :patient="detailPatient" :mode="dialogMode" @saved="onSaved" />

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom right" rounded="xl">
      {{ snackbar.msg }}
      <template #actions>
        <VBtn variant="text" size="small" @click="snackbar.show = false">✕</VBtn>
      </template>
    </VSnackbar>
  </div>
</template>

<style scoped>
.edu-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 15px 16px;
  cursor: pointer;
  transition: background 0.12s;
  border-bottom: 1px solid var(--qc-border, rgba(0, 0, 0, 0.07));
}

.edu-row:last-child {
  border-bottom: none;
}

.edu-row:hover {
  background: var(--qc-green-light, rgba(0, 179, 126, 0.04));
  border-left: 3px solid rgba(16, 185, 129, 0.35);
  padding-left: 13px;
}
</style>