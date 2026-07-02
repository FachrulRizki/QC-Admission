<script setup>
import { useUpSellingStore }  from '@/stores/useUpSellingStore'
import UpSellingFormDialog    from '@/views/qc-admission/up-selling/UpSellingFormDialog.vue'
import SummaryCards           from '@/components/SummaryCards.vue'
import PageHero               from '@/components/PageHero.vue'

const store = useUpSellingStore()

const showForm   = ref(false)
const editItem   = ref(null)
const showDetail = ref(false)
const detailItem = ref(null)
const showDel    = ref(false)
const delTarget  = ref(null)
const loading    = ref(false)
const snackbar   = ref({ show: false, msg: '', color: 'success' })

function todayStr() {
  const d = new Date(), p = n => String(n).padStart(2,'0')
  return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`
}

const todayFormatted = computed(() =>
  new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)

const search      = ref('')
const dateFrom    = ref(todayStr())
const dateTo      = ref(todayStr())
const filterAlasan = ref('')   // '' | 'Naik Kelas' | 'Perubahan Jaminan'

const records = computed(() => store.records ?? [])

const stats = computed(() => ({
  total:     records.value.length,
  naikKelas: records.value.filter(r => r.alasan === 'Naik Kelas').length,
  perubahan: records.value.filter(r => r.alasan === 'Perubahan Jaminan').length,
}))

const filtered = computed(() => {
  let d = records.value
  if (filterAlasan.value) {
    d = d.filter(r => r.alasan === filterAlasan.value)
  }
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

function toast(msg, color='success') { snackbar.value = { show: true, msg, color } }

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

function resetFilter() { search.value = ''; filterAlasan.value = ''; dateFrom.value = todayStr(); dateTo.value = todayStr() }

async function load() {
  loading.value = true
  try {
    await store.fetchRecords({
      per_page: 200,
      search: search.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
    })
  } catch(e) { console.error(e) }
  finally { loading.value = false }
}

// Watch date filter — re-fetch dari API saat tanggal berubah
watch([dateFrom, dateTo], () => load())

onMounted(load)
</script>

<template>
  <div>
    <!-- Header -->
    <PageHero
      icon="ri-arrow-up-circle-line"
      badge="Up Selling"
      title="Up Selling"
      subtitle="Penawaran upgrade kelas kamar rawat inap"
      color-from="#0EA5E9"
      color-to="#0369A1"
      :pills="[
        { icon: 'ri-calendar-line', text: todayFormatted },
        { icon: 'ri-database-line', text: `${stats.total} data` },
      ]"
    >
      <template #actions>
        <VBtn color="white" variant="elevated" rounded="pill" size="small" style="color:#0369A1;font-weight:700" @click="openAdd">
          <VIcon icon="ri-add-line" size="16" class="me-1" />Input Up Selling
        </VBtn>
      </template>
    </PageHero>

    <!-- Stats -->
    <SummaryCards :cards="[
      { value: stats.total,     label: 'Total Up Selling',  color: 'primary', icon: 'ri-arrow-up-circle-line' },
      { value: stats.naikKelas, label: 'Naik Kelas',        color: 'success', icon: 'ri-building-line' },
      { value: stats.perubahan, label: 'Perubahan Jaminan', color: 'info',    icon: 'ri-exchange-line' },
    ]" />

    <!-- Filter -->
    <VCard elevation="0" border rounded="xl" class="mb-4">
      <VCardText class="pa-3">
        <VRow dense align="center">
          <VCol cols="12" sm="5">
            <VTextField v-model="search" label="Cari pasien / petugas / notes" prepend-inner-icon="ri-search-line"
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
        <!-- Filter Keterangan Up Selling -->
        <div class="d-flex gap-2 mt-3 flex-wrap align-center">
          <span class="text-caption font-weight-semibold" style="color:var(--qc-text-2)">Keterangan:</span>
          <VChip
            :color="filterAlasan === '' ? 'primary' : 'default'"
            :variant="filterAlasan === '' ? 'elevated' : 'outlined'"
            size="small" class="cursor-pointer"
            @click="filterAlasan = ''"
          >Semua</VChip>
          <VChip
            :color="filterAlasan === 'Naik Kelas' ? 'success' : 'default'"
            :variant="filterAlasan === 'Naik Kelas' ? 'elevated' : 'outlined'"
            size="small" class="cursor-pointer"
            prepend-icon="ri-building-line"
            @click="filterAlasan = filterAlasan === 'Naik Kelas' ? '' : 'Naik Kelas'"
          >Naik Kelas</VChip>
          <VChip
            :color="filterAlasan === 'Perubahan Jaminan' ? 'info' : 'default'"
            :variant="filterAlasan === 'Perubahan Jaminan' ? 'elevated' : 'outlined'"
            size="small" class="cursor-pointer"
            prepend-icon="ri-exchange-line"
            @click="filterAlasan = filterAlasan === 'Perubahan Jaminan' ? '' : 'Perubahan Jaminan'"
          >Perubahan Jaminan</VChip>
        </div>
      </VCardText>
    </VCard>

    <!-- Count -->
    <div class="d-flex align-center gap-3 mb-4">
      <VChip size="small" color="primary" variant="tonal" rounded="pill">{{ filtered.length }} data</VChip>
      <span class="text-caption" style="color:var(--qc-text-2)">Klik untuk detail & edit</span>
    </div>

    <!-- List -->
    <VCard elevation="0" border rounded="xl" class="overflow-hidden">
      <div v-if="loading" class="text-center py-12">
        <VProgressCircular indeterminate color="info" size="32" />
      </div>
      <div v-else-if="!filtered.length" class="text-center py-16" style="color:var(--qc-text-2)">
        <VIcon icon="ri-arrow-up-circle-line" size="52" class="mb-3 opacity-30" />
        <p class="text-body-1 font-weight-semibold mb-1">Belum ada data</p>
        <VBtn color="info" variant="tonal" rounded="lg" size="small" class="mt-2" @click="openAdd">+ Input Up Selling</VBtn>
      </div>
      <div v-else>
        <div
          v-for="(item, idx) in filtered"
          :key="item.id"
          class="us-row"
          :class="{ 'us-row--bordered': idx < filtered.length - 1 }"
          @click="openRow(item)"
        >
          <!-- Avatar -->
          <VAvatar color="info" variant="tonal" size="40" rounded="lg" class="flex-shrink-0">
            <span style="font-size:14px;font-weight:700">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
          </VAvatar>

          <!-- Info -->
          <div class="flex-grow-1 min-width-0">
            <div class="d-flex align-center gap-2 flex-wrap">
              <span class="font-weight-semibold text-truncate" style="font-size:0.9rem;color:var(--qc-text)">{{ item.nama_pasien }}</span>
              <VChip :color="ketColor(item.alasan)" size="x-small" variant="tonal">{{ item.alasan || '—' }}</VChip>
            </div>
            <div class="d-flex align-center gap-3 mt-1 flex-wrap">
              <span class="text-caption" style="color:var(--qc-text-2)">{{ item.no_reg }}</span>
              <span v-if="item.jaminan" class="text-caption" style="color:var(--qc-text-2)">{{ item.jaminan }}</span>
              <span v-if="item.kelas" class="text-caption" style="color:var(--qc-text-2)">
                <VIcon icon="ri-hotel-bed-line" size="11" class="me-1" />{{ item.kelas }}
              </span>
            </div>
          </div>

          <!-- Right -->
          <div class="text-end flex-shrink-0">
            <p class="text-caption mb-0" style="color:var(--qc-text-2)">{{ item.petugas }}</p>
            <p class="text-caption mb-0" style="color:var(--qc-text-2)">{{ item.tgl_daftar || item.tanggal }}</p>
          </div>
        </div>
      </div>
    </VCard>

    <!-- Form Dialog -->
    <UpSellingFormDialog v-model="showForm" :edit-item="editItem" @saved="onSaved" />

    <!-- Detail Sheet -->
    <VDialog v-model="showDetail" max-width="420" scrollable>
      <VCard v-if="detailItem" rounded="xl" class="detail-card">
        <!-- Gradient header -->
        <div class="detail-hd detail-hd--blue">
          <div class="d-flex align-center gap-3">
            <div class="detail-av">{{ detailItem.nama_pasien?.charAt(0) ?? '?' }}</div>
            <div class="flex-grow-1 min-width-0">
              <p class="detail-nm text-truncate">{{ detailItem.nama_pasien }}</p>
              <span class="detail-id">{{ detailItem.no_reg }}</span>
            </div>
            <VChip class="detail-ket-chip" size="x-small">{{ detailItem.alasan || '—' }}</VChip>
            <VBtn icon variant="text" color="white" size="small" @click="showDetail=false">
              <VIcon icon="ri-close-line" size="18" />
            </VBtn>
          </div>
        </div>

        <!-- Info grid -->
        <div class="detail-grid">
          <div class="detail-cell">
            <span class="detail-lbl">tgldaftar</span>
            <span class="detail-val">{{ detailItem.tgl_daftar || '—' }}</span>
          </div>
          <div class="detail-cell">
            <span class="detail-lbl">NoMR</span>
            <span class="detail-val fw">{{ detailItem.no_mr || '—' }}</span>
          </div>
          <div class="detail-cell">
            <span class="detail-lbl">ketBayar</span>
            <span class="detail-val">{{ detailItem.jaminan || '—' }}</span>
          </div>
          <div class="detail-cell">
            <span class="detail-lbl">Kelas</span>
            <span class="detail-val">{{ detailItem.kelas || detailItem.rekomendasi_kelas || '—' }}</span>
          </div>
          <div class="detail-cell">
            <span class="detail-lbl">NamaRuang</span>
            <span class="detail-val">{{ detailItem.nama_ruang || '—' }}</span>
          </div>
          <div class="detail-cell">
            <span class="detail-lbl">NamaBangsal</span>
            <span class="detail-val">{{ detailItem.nama_bangsal || '—' }}</span>
          </div>
          <div class="detail-cell">
            <span class="detail-lbl">nama_petugas</span>
            <span class="detail-val">{{ detailItem.petugas || '—' }}</span>
          </div>
          <div class="detail-cell">
            <span class="detail-lbl">Ket_Up_Selling</span>
            <span class="detail-val">{{ detailItem.alasan || '—' }}</span>
          </div>
          <div class="detail-cell detail-cell--full">
            <span class="detail-lbl">notes</span>
            <span class="detail-val" style="white-space:pre-wrap">{{ detailItem.note || '—' }}</span>
          </div>
        </div>

        <div class="detail-actions">
          <VBtn variant="outlined" rounded="lg" size="small" @click="showDetail=false">Tutup</VBtn>
          <VBtn color="info" variant="tonal" rounded="lg" size="small" @click="openEdit(detailItem)">
            <VIcon icon="ri-pencil-line" size="14" class="me-1" />Edit
          </VBtn>
          <VBtn color="error" variant="tonal" rounded="lg" size="small" @click="delTarget=detailItem; showDel=true">
            <VIcon icon="ri-delete-bin-line" size="14" />
          </VBtn>
        </div>
      </VCard>
    </VDialog>

    <!-- Delete Confirm -->
    <VDialog v-model="showDel" max-width="320">
      <VCard rounded="xl">
        <VCardText class="pa-6 text-center">
          <VAvatar color="error" variant="tonal" size="52" rounded="xl" class="mb-3">
            <VIcon icon="ri-delete-bin-2-line" size="24" />
          </VAvatar>
          <p class="text-h6 font-weight-bold mb-1">Hapus Data?</p>
          <p class="text-body-2" style="color:var(--qc-text-2)">{{ delTarget?.nama_pasien }}</p>
        </VCardText>
        <div class="d-flex gap-2 px-5 pb-5">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showDel=false">Batal</VBtn>
          <VBtn color="error" rounded="lg" class="flex-grow-1" :loading="loading" @click="doDel">Hapus</VBtn>
        </div>
      </VCard>
    </VDialog>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom right" rounded="xl">
      {{ snackbar.msg }}
      <template #actions><VBtn variant="text" size="small" @click="snackbar.show=false">✕</VBtn></template>
    </VSnackbar>
  </div>
</template>

<style scoped>
.us-row {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px; cursor: pointer; transition: background 0.12s;
}
.us-row:hover { background: rgba(59,130,246,0.04); }
.us-row--bordered { border-bottom: 1px solid var(--qc-border); }

/* Detail card */
.detail-hd {
  padding: 18px 18px 14px;
  border-radius: 20px 20px 0 0;
}
.detail-hd--blue { background: linear-gradient(135deg, #1E3A5F, #3B82F6); }

.detail-av {
  width: 48px; height: 48px; border-radius: 13px;
  background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; font-weight: 800; color: #fff; flex-shrink: 0;
}
.detail-nm { font-size: 1rem; font-weight: 700; color: #fff; margin: 0; }
.detail-id { font-size: 0.72rem; color: rgba(255,255,255,0.7); }
.detail-ket-chip {
  background: rgba(255,255,255,0.2) !important;
  color: #fff !important;
  font-size: 0.68rem !important;
  flex-shrink: 0;
}

.detail-grid {
  display: grid; grid-template-columns: 1fr 1fr;
}
.detail-cell {
  display: flex; flex-direction: column;
  padding: 11px 18px;
  border-bottom: 1px solid var(--qc-border);
}
.detail-cell--full { grid-column: span 2; }
.detail-lbl { font-size: 0.63rem; text-transform: uppercase; letter-spacing: 0.07em; color: var(--qc-text-2); margin-bottom: 2px; font-weight: 600; }
.detail-val { font-size: 0.85rem; font-weight: 500; color: var(--qc-text); }
.detail-val.fw { font-weight: 700; }

.detail-actions {
  display: flex; gap: 8px; padding: 12px 18px;
  border-top: 1px solid var(--qc-border);
}
</style>