<script setup>
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'
import BatalRanapDetailDialog from '@/views/qc-admission/batal-ranap/BatalRanapDetailDialog.vue'
import PageHero from '@/components/PageHero.vue'

const store = useBatalRanapStore()
const search = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const loading = ref(false)
const showDetail = ref(false)
const selectedItem = ref(null)

const todayFormatted = computed(() =>
  new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)

const headers = [
  { title: 'Tanggal', key: 'tanggal', sortable: true },
  { title: 'No. Reg', key: 'no_reg', sortable: true },
  { title: 'Nama Pasien', key: 'nama_pasien', sortable: true },
  { title: 'Keterangan Batal', key: 'keterangan_batal', sortable: true },
  { title: 'Ruangan', key: 'ruangan', sortable: true },
  { title: 'Status', key: 'status_ok', sortable: true, align: 'center' },
  { title: 'Petugas', key: 'petugas', sortable: true },
]

function statusColor(s) {
  return { Bedah: 'success', 'Non Bedah': 'info' }[s] ?? 'secondary'
}

/**
 * Konversi berbagai format tanggal ke Date object.
 * Format dari DB: 'DD/MM/YYYY, HH.mm.ss' | 'YYYY-MM-DD' | ISO
 */
function parseTanggal(str) {
  if (!str) return null
  const dmyMatch = str.match(/^(\d{2})\/(\d{2})\/(\d{4})/)
  if (dmyMatch) return new Date(`${dmyMatch[3]}-${dmyMatch[2]}-${dmyMatch[1]}`)
  const d = new Date(str)
  return isNaN(d) ? null : d
}

const records = computed(() => store.records ?? [])

const filtered = computed(() => {
  let d = records.value
  const q = search.value.toLowerCase().trim()
  if (q) d = d.filter(r => Object.values(r).some(v => String(v ?? '').toLowerCase().includes(q)))
  if (dateFrom.value || dateTo.value) {
    const from = dateFrom.value ? new Date(dateFrom.value + 'T00:00:00') : null
    const to = dateTo.value ? new Date(dateTo.value + 'T23:59:59') : null
    d = d.filter(r => {
      const tgl = parseTanggal(r.tanggal || '')
      if (!tgl) return true
      if (from && tgl < from) return false
      if (to && tgl > to) return false
      return true
    })
  }
  return d
})

const stats = computed(() => ({
  total: records.value.length,
  belum: records.value.filter(r => !r.status_ok).length,
  bedah: records.value.filter(r => r.status_ok === 'Bedah').length,
  nonBedah: records.value.filter(r => r.status_ok === 'Non Bedah').length,
}))

function openDetail(item) {
  selectedItem.value = item
  showDetail.value = true
}

async function doRefresh() {
  loading.value = true
  try { await store.fetchRecords({ per_page: 200 }) }
  catch (e) { console.error(e) }
  finally { loading.value = false }
}

onMounted(() => doRefresh())
</script>

<template>
  <div>
    <!-- Hero -->
    <PageHero icon="ri-close-circle-line" badge="Kasir · View Only" title="Data Batal Ranap"
      subtitle="Monitoring pembatalan rawat inap · Klik baris untuk detail" color-from="#0EA5E9" color-to="#0369A1"
      :pills="[
        { icon: 'ri-calendar-line', text: todayFormatted },
        { icon: 'ri-database-line', text: `${stats.total} data` },
      ]">
      <template #actions>
        <VChip color="white" variant="elevated" size="small" prepend-icon="ri-eye-line" style="color:#9F1239">
          View Only
        </VChip>
      </template>
    </PageHero>

    <!-- Stats -->
    <VRow dense class="mb-4">
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="text-center pa-3">
          <p class="text-h5 font-weight-bold text-primary mb-0">{{ stats.total }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Total</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="text-center pa-3">
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-warning))">{{ stats.belum }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Belum Diverifikasi</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="text-center pa-3">
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-success))">{{ stats.bedah }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Bedah</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="text-center pa-3">
          <p class="text-h5 font-weight-bold text-info mb-0">{{ stats.nonBedah }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Non Bedah</p>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filter -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VCardText class="py-3">
        <VRow dense align="center">
          <VCol cols="12" sm="7">
            <VTextField v-model="search" placeholder="Cari nama pasien, no reg, petugas..."
              prepend-inner-icon="ri-search-line" variant="outlined" density="compact" hide-details clearable />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol cols="auto">
            <VBtn icon variant="text" size="small" color="secondary" @click="search = ''; dateFrom = ''; dateTo = ''">
              <VIcon icon="ri-refresh-line" />
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Count -->
    <div class="d-flex align-center gap-2 mb-3">
      <VChip size="small" color="primary" variant="tonal">{{ filtered.length }} data</VChip>
      <span class="text-caption text-disabled">dari {{ records.length }} total</span>
    </div>

    <!-- Table — klik baris buka modal detail -->
    <VCard elevation="0" border rounded="lg">
      <VDataTable :headers="headers" :items="filtered" density="compact" hover :loading="loading" :items-per-page="15"
        class="batal-table" @click:row="(_, { item }) => openDetail(item)">
        <template #item.status_ok="{ item }">
          <VChip :color="statusColor(item.status_ok)" size="small" variant="tonal" label>
            {{ item.status_ok || 'Belum Diverifikasi' }}
          </VChip>
        </template>

        <template #item.nama_pasien="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <VAvatar color="error" variant="tonal" size="28" rounded="lg">
              <span style="font-size:11px;font-weight:700">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
            </VAvatar>
            <span class="text-body-2 font-weight-medium">{{ item.nama_pasien }}</span>
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-10 text-medium-emphasis">
            <VIcon icon="ri-inbox-line" size="40" class="mb-2 opacity-40" />
            <p class="mb-1 font-weight-medium">Tidak ada data</p>
            <p class="text-caption mb-0">Belum ada data batal ranap yang masuk</p>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Detail dialog — view only untuk kasir -->
    <BatalRanapDetailDialog v-model="showDetail" :item="selectedItem" @verified="doRefresh" />
  </div>
</template>