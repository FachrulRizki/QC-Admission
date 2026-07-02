<script setup>
import { useEdukasiLanjutanStore } from '@/stores/useEdukasiLanjutanStore'
import EdukasiDetailDialog         from '@/views/qc-admission/edukasi-lanjutan/EdukasiDetailDialog.vue'
import SummaryCards                from '@/components/SummaryCards.vue'
import PageHero                    from '@/components/PageHero.vue'

const store   = useEdukasiLanjutanStore()
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
  const h   = Math.floor(elapsedSec / 3600)
  const m   = Math.floor((elapsedSec % 3600) / 60)
  const day = Math.floor(h / 24)
  if (day >= 1) return `${day}h ${h % 24}j`
  if (h >= 1)   return `${h}j ${String(m).padStart(2,'0')}m`
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
const showDetail   = ref(false)
const detailPatient = ref(null)
const dialogMode   = ref('view')

function todayStr() {
  const d = new Date(), p = n => String(n).padStart(2,'0')
  return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`
}

const search   = ref('')
const dateFrom = ref(todayStr())   // default hari ini — tampil data up to date
const dateTo   = ref(todayStr())   // default hari ini
const statusFilter = ref('All')

const records = computed(() => store.records ?? [])

// Dedupe per no_mr — tampilkan 1 card per pasien (entry terbaru)
const uniquePatients = computed(() => {
  const map = {}
  records.value.forEach(r => {
    if (!map[r.no_mr] || r.id > map[r.no_mr].id) map[r.no_mr] = r
  })
  return Object.values(map)
})

const sesiCount = computed(() => {
  const map = {}
  records.value.forEach(r => { map[r.no_mr] = (map[r.no_mr] ?? 0) + 1 })
  return map
})

const filtered = computed(() => {
  let d = uniquePatients.value
  // Status filter (client-side, sudah ada datanya)
  if (statusFilter.value !== 'All') d = d.filter(r => r.status === statusFilter.value)
  // Search filter (client-side)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    d = d.filter(r =>
      r.nama_pasien?.toLowerCase().includes(q) ||
      r.no_mr?.toLowerCase().includes(q) ||
      r.no_reg?.toLowerCase().includes(q) ||
      r.petugas?.toLowerCase().includes(q)
    )
  }
  // Date filter sudah dilakukan server-side via load() — tidak perlu client filter
  return d
})

const stats = computed(() => ({
  total:    uniquePatients.value.length,
  menunggu: uniquePatients.value.filter(r => r.status === 'Menunggu').length,
  selesai:  uniquePatients.value.filter(r => r.status === 'Selesai').length,
  pct:      uniquePatients.value.length
    ? Math.round(uniquePatients.value.filter(r => r.status === 'Selesai').length / uniquePatients.value.length * 100) : 0,
}))

function openDetail(patient) { detailPatient.value = { ...patient }; dialogMode.value = 'view'; showDetail.value = true }
function openEdit(patient)   { detailPatient.value = { ...patient }; dialogMode.value = 'edit'; showDetail.value = true }

async function onSaved() { showDetail.value = false; await load() }

function toast(msg, color='success') { snackbar.value = { show: true, msg, color } }

function resetFilter() {
  search.value = ''
  statusFilter.value = 'All'
  dateFrom.value = todayStr()   // kembali ke hari ini, bukan kosong
  dateTo.value   = todayStr()
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
      per_page: 200,
      search:    search.value || undefined,
      status:    statusFilter.value !== 'All' ? statusFilter.value : undefined,
      date_from: dateFrom.value || undefined,
      date_to:   dateTo.value   || undefined,
    })
  } catch(e) { console.error(e) }
  finally { loading.value = false }
}

// Watch filter — re-fetch dari API saat tanggal atau status berubah
watch([dateFrom, dateTo, statusFilter], () => load())

onMounted(load)
</script>

<template>
  <div>
    <!-- Header -->
    <PageHero
      icon="ri-book-open-line"
      badge="Edukasi Lanjutan"
      title="Edukasi Lanjutan"
      subtitle="Auto dari QC ≥ 2 jam · Klik pasien untuk tambah sesi"
      color-from="#0EA5E9"
      color-to="#0369A1"
      :pills="[
        { icon: 'ri-book-open-line', text: `${stats.total} pasien` },
        { icon: 'ri-time-line', text: `${stats.menunggu} menunggu bed` },
      ]"
    >
      <template #actions>
        <VBtn color="white" variant="elevated" rounded="pill" size="small" style="color:#0369A1;font-weight:700"
          :loading="syncing" @click="syncRsus">
          <VIcon icon="ri-refresh-line" size="15" class="me-1" />Sync SIMRS
        </VBtn>
      </template>
    </PageHero>

    <!-- Stats -->
    <SummaryCards
      v-model="statusFilter"
      :cards="[
        { value: stats.total,    label: 'Total Pasien',    color: 'primary', icon: 'ri-book-open-line',    filterValue: 'All' },
        { value: stats.menunggu, label: 'Menunggu Bed',    color: 'warning', icon: 'ri-time-line',         filterValue: 'Menunggu' },
        { value: stats.selesai,  label: 'Sudah Dapat Bed', color: 'success', icon: 'ri-check-double-line', filterValue: 'Selesai', },
      ]"
    />

    <!-- Filter -->
    <VCard elevation="0" border rounded="xl" class="mb-4">
      <VCardText class="pa-3">
        <VRow dense align="center">
          <VCol cols="12" sm="4">
            <VTextField v-model="search" label="Cari pasien..." prepend-inner-icon="ri-search-line"
              variant="outlined" density="compact" hide-details clearable rounded="lg" />
          </VCol>
          <VCol cols="6" sm="3">
            <VTextField v-model="dateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="6" sm="3">
            <VTextField v-model="dateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="auto">
            <VBtn size="small" variant="text" color="secondary" @click="resetFilter; load()">Reset</VBtn>
          </VCol>
        </VRow>
        <!-- Date filter with "Lihat Semua" option -->
        <div class="d-flex gap-2 mt-3 flex-wrap align-center">
          <VChip
            v-for="s in ['All','Menunggu','Selesai']" :key="s"
            :color="statusFilter === s ? (s === 'Menunggu' ? 'warning' : s === 'Selesai' ? 'success' : 'primary') : 'default'"
            :variant="statusFilter === s ? 'elevated' : 'outlined'"
            size="small" class="cursor-pointer"
            @click="statusFilter = s"
          >{{ s === 'All' ? 'Semua Status' : s }}</VChip>
          <VDivider vertical class="mx-1" style="height:20px" />
          <!-- Chip untuk lihat semua data historis (hapus filter tanggal) -->
          <VChip
            :color="(!dateFrom && !dateTo) ? 'secondary' : 'default'"
            :variant="(!dateFrom && !dateTo) ? 'elevated' : 'outlined'"
            size="small" class="cursor-pointer"
            @click="dateFrom = ''; dateTo = ''; load()"
          >
            <VIcon icon="ri-history-line" size="11" class="me-1" />Semua Riwayat
          </VChip>
          <!-- Chip untuk kembali ke hari ini -->
          <VChip
            v-if="!dateFrom && !dateTo"
            color="primary" variant="tonal" size="small" class="cursor-pointer"
            @click="dateFrom = todayStr(); dateTo = todayStr()"
          >
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
          Data hari ini ({{ new Date(dateFrom + 'T00:00:00').toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' }) }})
        </template>
        <template v-else-if="dateFrom || dateTo">
          Periode {{ dateFrom || '—' }} s/d {{ dateTo || '—' }}
        </template>
        <template v-else>
          Semua riwayat · gunakan filter tanggal untuk mempersempit
        </template>
      </span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12">
      <VProgressCircular indeterminate color="success" size="32" />
    </div>

    <!-- Empty -->
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
      <div class="d-flex gap-2 justify-center flex-wrap">
        <VBtn variant="tonal" color="primary" rounded="lg" size="small" @click="dateFrom = ''; dateTo = ''; load()">
          <VIcon icon="ri-history-line" size="14" class="me-1" />Lihat Semua Riwayat
        </VBtn>
        <VBtn variant="tonal" color="success" rounded="lg" size="small" :loading="syncing" @click="syncRsus">
          <VIcon icon="ri-refresh-line" size="14" class="me-1" />Sync SIMRS
        </VBtn>
      </div>
    </div>

    <!-- Cards grid -->
    <VRow v-else dense>
      <VCol
        v-for="patient in filtered"
        :key="patient.no_mr"
        cols="12" sm="6" md="4" lg="3"
      >
        <VCard
          elevation="0" border rounded="xl"
          class="edu-card cursor-pointer h-100"
          @click="openDetail(patient)"
        >
          <VCardText class="pa-4">
            <!-- Top row -->
            <div class="d-flex align-start gap-3 mb-3">
              <VAvatar color="success" variant="tonal" size="44" rounded="lg">
                <span style="font-size:15px;font-weight:700">{{ patient.nama_pasien?.charAt(0) ?? '?' }}</span>
              </VAvatar>
              <div class="flex-grow-1 min-width-0">
                <p class="font-weight-semibold mb-0 text-truncate" style="font-size:0.9rem;color:var(--qc-text)">
                  {{ patient.nama_pasien }}
                </p>
                <p class="text-caption mb-0" style="color:var(--qc-text-2)">{{ patient.no_mr }}</p>
              </div>
              <!-- Session count -->
              <VChip color="warning" variant="tonal" size="x-small" class="flex-shrink-0">
                {{ sesiCount[patient.no_mr] ?? 1 }} sesi
              </VChip>
            </div>

            <!-- Status + info -->
            <div class="d-flex align-center gap-2 flex-wrap mb-2">
              <VChip
                :color="patient.status === 'Selesai' ? 'success' : 'warning'"
                variant="tonal" size="x-small"
              >{{ patient.status }}</VChip>
              <span v-if="patient.jaminan" class="text-caption" style="color:var(--qc-text-2)">{{ patient.jaminan }}</span>
              <!-- Lama menunggu bed — hanya jika masih Menunggu -->
              <VChip
                v-if="patient.status === 'Menunggu' && getWaktuMenunggu(patient)"
                :color="getWaktuColor(patient)"
                variant="tonal" size="x-small"
                prepend-icon="ri-time-line"
              >{{ getWaktuMenunggu(patient) }}</VChip>
            </div>

            <!-- Footer -->
            <div class="d-flex align-center justify-space-between">
              <span class="text-caption" style="color:var(--qc-text-2)">
                <VIcon icon="ri-user-line" size="11" class="me-1" />{{ patient.petugas }}
              </span>
              <span class="text-caption" style="color:var(--qc-text-2)">{{ patient.tanggal }}</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Detail Dialog -->
    <EdukasiDetailDialog
      v-model="showDetail"
      :patient="detailPatient"
      :mode="dialogMode"
      @saved="onSaved"
    />

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom right" rounded="xl">
      {{ snackbar.msg }}
      <template #actions><VBtn variant="text" size="small" @click="snackbar.show=false">✕</VBtn></template>
    </VSnackbar>
  </div>
</template>

<style scoped>
.edu-card {
  transition: box-shadow 0.18s, transform 0.18s, border-color 0.15s;
}
.edu-card:hover {
  box-shadow: 0 6px 20px rgba(16,185,129,0.15) !important;
  transform: translateY(-2px);
  border-color: rgba(16,185,129,0.35) !important;
}
</style>