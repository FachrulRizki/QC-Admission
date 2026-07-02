<script setup>
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'
import BatalRanapFormDialog   from '@/views/qc-admission/batal-ranap/BatalRanapFormDialog.vue'
import BatalRanapDetailDialog from '@/views/qc-admission/batal-ranap/BatalRanapDetailDialog.vue'
import SummaryCards           from '@/components/SummaryCards.vue'
import PageHero               from '@/components/PageHero.vue'

const store = useBatalRanapStore()

const showForm   = ref(false)
const editItem   = ref(null)
const showDetail = ref(false)
const detailItem = ref(null)
const loading    = ref(false)
const snackbar   = ref({ show: false, msg: '', color: 'success' })

function todayStr() {
  const d = new Date(), p = n => String(n).padStart(2,'0')
  return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`
}

const search        = ref('')
const dateFrom      = ref(todayStr())
const dateTo        = ref(todayStr())
const filterClosing = ref(null)  // null = semua | '' = belum closing | 'Siap Closing' | 'Belum Siap Closing'

const records = computed(() => store.records ?? [])

const stats = computed(() => ({
  total:        records.value.length,
  belumClosing: records.value.filter(r => !r.status_closing).length,
  siap:         records.value.filter(r => r.status_closing === 'Siap Closing').length,
  belumSiap:    records.value.filter(r => r.status_closing === 'Belum Siap Closing').length,
}))

const filtered = computed(() => {
  let d = records.value
  if (filterClosing.value === '') d = d.filter(r => !r.status_closing)
  else if (filterClosing.value)   d = d.filter(r => r.status_closing === filterClosing.value)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    d = d.filter(r =>
      r.no_reg?.toLowerCase().includes(q) ||
      r.nama_pasien?.toLowerCase().includes(q) ||
      r.petugas?.toLowerCase().includes(q)
    )
  }
  return d
})

function statusOkColor(s)  { return { Bedah:'success', 'Non Bedah':'info' }[s] ?? 'secondary' }
function statusOkLabel(s)  { return s || 'Belum Verifikasi' }
function closingColor(s)   { return s === 'Siap Closing' ? 'success' : s === 'Belum Siap Closing' ? 'error' : 'secondary' }

function openRow(item)  { detailItem.value = item; showDetail.value = true }
function openAdd()      { editItem.value = null; showForm.value = true }
function openEdit(item) { editItem.value = { ...item }; showDetail.value = false; showForm.value = true }

function toast(msg, color='success') { snackbar.value = { show: true, msg, color } }

async function onSaved()    { showForm.value = false; toast('Data disimpan.'); await load() }
async function onVerified() { toast('Verifikasi disimpan.'); await load() }

function resetFilter() { search.value = ''; filterClosing.value = null; dateFrom.value = todayStr(); dateTo.value = todayStr() }

async function load() {
  loading.value = true
  try {
    await store.fetchRecords({
      per_page: 200,
      search:    search.value   || undefined,
      date_from: dateFrom.value || undefined,
      date_to:   dateTo.value   || undefined,
    })
  } catch(e) { console.error(e) }
  finally { loading.value = false }
}

// Watch date filter — re-fetch dari API saat tanggal berubah
watch([dateFrom, dateTo], () => load())

// Watch filterOk — filter client-side sudah jalan, tidak perlu re-fetch
// tapi kalau mau server-side bisa tambah: watch(filterOk, () => load())

onMounted(load)
</script>

<template>
  <div>
    <!-- Header -->
    <PageHero
      icon="ri-close-circle-line"
      badge="Batal Ranap"
      title="Batal Ranap"
      subtitle="Kelola pembatalan rawat inap & verifikasi closing"
      color-from="#0EA5E9"
      color-to="#0369A1"
      :pills="[
        { icon: 'ri-database-line', text: `${stats.total} data` },
        { icon: 'ri-time-line', text: `${stats.belum} blm verifikasi` },
      ]"
    >
      <template #actions>
        <VBtn color="white" variant="elevated" rounded="pill" size="small" style="color:#0369A1;font-weight:700" @click="openAdd">
          <VIcon icon="ri-add-line" size="16" class="me-1" />Input Batal Ranap
        </VBtn>
      </template>
    </PageHero>

    <!-- Stats (clickable filter) -->
    <SummaryCards
      v-model="filterClosing"
      :cards="[
        { value: stats.total,        label: 'Total',              color: 'primary', icon: 'ri-close-circle-line',    filterValue: null },
        { value: stats.siap,         label: 'Siap Closing',       color: 'success', icon: 'ri-checkbox-circle-line', filterValue: 'Siap Closing' },
        { value: stats.belumSiap,    label: 'Belum Siap Closing', color: 'error',   icon: 'ri-close-circle-line',    filterValue: 'Belum Siap Closing' },
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
          <VCol cols="6" sm="2">
            <VTextField v-model="dateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="12" sm="3">
            <VSelect
              v-model="filterClosing"
              :items="[
                { title: 'Semua Status',          value: null },
                { title: '👍 Siap Closing',        value: 'Siap Closing' },
                { title: '🔴 Belum Siap Closing',  value: 'Belum Siap Closing' },
              ]"
              item-title="title" item-value="value"
              label="Status Closing" variant="outlined" density="compact" hide-details rounded="lg"
            />
          </VCol>
          <VCol cols="auto">
            <VBtn size="small" variant="text" color="secondary" @click="resetFilter; load()">Reset</VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Info -->
    <div class="d-flex align-center gap-3 mb-4 flex-wrap">
      <VChip size="small" color="primary" variant="tonal" rounded="pill">{{ filtered.length }} data</VChip>
      <span class="text-caption" style="color:var(--qc-text-2)">Klik pasien untuk detail & verifikasi</span>
    </div>

    <!-- List -->
    <VCard elevation="0" border rounded="xl" class="overflow-hidden">
      <div v-if="loading" class="text-center py-12">
        <VProgressCircular indeterminate color="error" size="32" />
      </div>
      <div v-else-if="!filtered.length" class="text-center py-16" style="color:var(--qc-text-2)">
        <VIcon icon="ri-inbox-line" size="52" class="mb-3 opacity-30" />
        <p class="text-body-1 font-weight-semibold mb-1">Belum ada data</p>
        <VBtn color="error" variant="tonal" rounded="lg" size="small" class="mt-2" @click="openAdd">+ Input Batal Ranap</VBtn>
      </div>
      <div v-else>
        <div
          v-for="(item, idx) in filtered"
          :key="item.id"
          class="br-row"
          @click="openRow(item)"
        >
          <!-- Avatar -->
          <VAvatar color="error" variant="tonal" size="40" rounded="lg" class="flex-shrink-0">
            <span style="font-size:14px;font-weight:700">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
          </VAvatar>

          <!-- Info -->
          <div class="flex-grow-1 min-width-0">
            <div class="d-flex align-center gap-2 flex-wrap">
              <span class="font-weight-semibold text-truncate" style="font-size:0.9rem;color:var(--qc-text)">{{ item.nama_pasien }}</span>
              <VChip :color="statusOkColor(item.status_ok)" size="x-small" variant="tonal">
                {{ statusOkLabel(item.status_ok) }}
              </VChip>
              <VChip v-if="item.status_closing" :color="closingColor(item.status_closing)" size="x-small" variant="tonal">
                {{ item.status_closing }}
              </VChip>
            </div>
            <div class="d-flex align-center gap-3 mt-1 flex-wrap">
              <span class="text-caption" style="color:var(--qc-text-2)">{{ item.no_reg }}</span>
              <span v-if="item.keterangan_batal" class="text-caption" style="color:var(--qc-text-2)">
                <VIcon icon="ri-error-warning-line" size="11" class="me-1" />{{ item.keterangan_batal }}
              </span>
            </div>
          </div>

          <!-- Right -->
          <div class="text-end flex-shrink-0">
            <p class="text-caption mb-0" style="color:var(--qc-text-2)">{{ item.petugas }}</p>
            <p class="text-caption mb-0" style="color:var(--qc-text-2)">{{ item.tanggal }}</p>
          </div>
        </div>
      </div>
    </VCard>

    <!-- Dialogs -->
    <BatalRanapFormDialog v-model="showForm" :edit-item="editItem" @saved="onSaved" />

    <BatalRanapDetailDialog
      v-model="showDetail"
      :item="detailItem"
      @verified="onVerified"
      @edit="openEdit"
    />

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom right" rounded="xl">
      {{ snackbar.msg }}
      <template #actions><VBtn variant="text" size="small" @click="snackbar.show=false">✕</VBtn></template>
    </VSnackbar>
  </div>
</template>

<style scoped>
.br-row {
  display: flex; align-items: center; gap: 12px;
  padding: 15px 16px;
  cursor: pointer; transition: background 0.12s;
  border-bottom: 1px solid var(--qc-border, rgba(0,0,0,0.07));
}
.br-row:last-child { border-bottom: none; }
.br-row:hover {
  background: rgba(239,68,68,0.04);
  border-left: 3px solid rgba(239,68,68,0.35);
  padding-left: 13px;
}
</style>