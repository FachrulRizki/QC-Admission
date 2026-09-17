<script setup>
import axios from 'axios'
import { useAlasanStore } from '@/stores/useAlasanStore'
import { useMasterDataStore } from '@/stores/useMasterDataStore'
import { usePegawaiStore } from '@/stores/usePegawaiStore'
import { useAuthStore } from '@/stores/useAuthStore'
import PageHero from '@/components/PageHero.vue'

const auth        = useAuthStore()
const store       = useAlasanStore()
const masterStore = useMasterDataStore()
const pegawaiStore = usePegawaiStore()

// ── State ─────────────────────────────────────────────────────────────────────
const loadingPasien   = ref(false)
const pasienAktif     = ref([])
const searchPasien    = ref('')
const snackbar        = ref({ show: false, text: '', color: 'success' })

// Dialog input alasan
const dialogOpen      = ref(false)
const selectedPasien  = ref(null)
const formAlasan      = ref('')
const formCatatan     = ref('')
const formPetugas     = ref('')
const formError       = ref('')
const saving          = ref(false)
const editItem        = ref(null)

// Dialog riwayat
const dialogRiwayat   = ref(false)
const riwayatPasien   = ref(null)
const loadingRiwayat  = ref(false)
const riwayatData     = ref([])

// Tab aktif
const activeTab       = ref('pendaftaran')

// ── Fetch pendaftaran aktif ────────────────────────────────────────────────────
async function fetchPendaftaranAktif() {
  loadingPasien.value = true
  try {
    const { data } = await axios.get('/api/alasan/pendaftaran-aktif', {
      params: searchPasien.value.trim() ? { search: searchPasien.value.trim() } : {},
    })
    pasienAktif.value = data.data ?? []
  } catch (e) {
    notify('Gagal memuat data pendaftaran aktif', 'error')
  } finally {
    loadingPasien.value = false
  }
}

// Debounce search
let searchTimer = null
watch(searchPasien, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(fetchPendaftaranAktif, 350)
})

// ── Buka dialog input alasan untuk pasien ─────────────────────────────────────
function openAlasanDialog(pasien) {
  selectedPasien.value = pasien
  formAlasan.value     = ''
  formCatatan.value    = ''
  formPetugas.value    = auth.user?.preferred_username ?? auth.user?.name ?? ''
  formError.value      = ''
  editItem.value       = null
  dialogOpen.value     = true
  masterStore.fetch()
  pegawaiStore.fetch()
}

function openEditDialog(item) {
  editItem.value        = item
  selectedPasien.value  = {
    no_reg:       item.no_reg,
    no_mr:        item.no_mr,
    nama_pasien:  item.nama_pasien,
    ket_bayar:    item.jaminan,
    tgl_daftar:   item.tgl_daftar,
    jam_daftar:   item.jam_daftar,
    nama_ruang:   item.nama_ruang,
    nama_bangsal: item.nama_bangsal,
  }
  formAlasan.value    = item.alasan   ?? ''
  formCatatan.value   = item.catatan  ?? ''
  formPetugas.value   = item.petugas  ?? ''
  formError.value     = ''
  dialogOpen.value    = true
  masterStore.fetch()
  pegawaiStore.fetch()
}

// ── Simpan alasan ─────────────────────────────────────────────────────────────
async function handleSave() {
  formError.value = ''
  if (!formAlasan.value) { formError.value = 'Alasan wajib dipilih.'; return }

  saving.value = true
  const now = new Date()
  const p   = n => String(n).padStart(2, '0')
  const jam = `${p(now.getHours())}.${p(now.getMinutes())}.${p(now.getSeconds())}`
  const tgl = `${p(now.getDate())}/${p(now.getMonth() + 1)}/${now.getFullYear()}, ${jam}`

  const payload = {
    tanggal:      tgl,
    jam_input:    jam,
    no_reg:       selectedPasien.value.no_reg,
    no_mr:        selectedPasien.value.no_mr        ?? '',
    nama_pasien:  selectedPasien.value.nama_pasien   ?? '',
    jaminan:      selectedPasien.value.ket_bayar     ?? '',
    tgl_daftar:   selectedPasien.value.tgl_daftar   ?? '',
    jam_daftar:   selectedPasien.value.jam_daftar   ?? '',
    nama_ruang:   selectedPasien.value.nama_ruang   ?? '',
    nama_bangsal: selectedPasien.value.nama_bangsal ?? '',
    alasan:       formAlasan.value,
    catatan:      formCatatan.value,
    petugas:      formPetugas.value,
  }

  const result = editItem.value
    ? await store.update(editItem.value.id, { alasan: payload.alasan, catatan: payload.catatan, petugas: payload.petugas })
    : await store.store(payload)

  saving.value = false

  if (result?.success !== false) {
    notify(editItem.value ? 'Data berhasil diperbarui.' : 'Alasan berhasil disimpan.', 'success')
    dialogOpen.value = false
    store.fetchRecords()
  } else {
    formError.value = result?.message ?? 'Gagal menyimpan.'
  }
}

// ── Hapus ─────────────────────────────────────────────────────────────────────
async function handleDelete(item) {
  if (!confirm(`Hapus alasan "${item.alasan}" untuk ${item.nama_pasien}?`)) return
  const result = await store.destroy(item.id)
  if (result.success) notify('Data berhasil dihapus.', 'success')
  else notify(result.message, 'error')
}

// ── Riwayat ───────────────────────────────────────────────────────────────────
function openRiwayat(pasien) {
  riwayatPasien.value = pasien
  dialogRiwayat.value = true
  loadingRiwayat.value = true
  riwayatData.value   = []
  axios.get('/api/alasan', { params: { search: pasien.no_reg, per_page: 50 } })
    .then(r => { riwayatData.value = r.data.data ?? [] })
    .catch(() => {})
    .finally(() => { loadingRiwayat.value = false })
}

// ── Helper ────────────────────────────────────────────────────────────────────
function notify(text, color = 'success') {
  snackbar.value = { show: true, text, color }
}

function alasanColor(a) {
  const map = {
    'Pelayanan':                'primary',
    'Kelengkapan Alat & Dokter':'warning',
    'Teman/Kerabat':            'info',
    'Rujukan':                  'success',
    'Marketing':                'purple',
    'Sosial Media':             'pink',
  }
  return map[a] ?? 'secondary'
}

const searchRiwayat = ref('')
const filteredRiwayat = computed(() => {
  if (!searchRiwayat.value.trim()) return riwayatData.value
  const q = searchRiwayat.value.toLowerCase()
  return riwayatData.value.filter(r => Object.values(r).some(v => String(v ?? '').toLowerCase().includes(q)))
})

// ── Filters riwayat ───────────────────────────────────────────────────────────
const searchRiwayatAll = ref('')
const filteredRecords  = computed(() => {
  if (!searchRiwayatAll.value.trim()) return store.records
  const q = searchRiwayatAll.value.toLowerCase()
  return store.records.filter(r => Object.values(r).some(v => String(v ?? '').toLowerCase().includes(q)))
})

const todayFormatted = computed(() =>
  new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
)

onMounted(() => {
  fetchPendaftaranAktif()
  if (auth.hasPermission('alasan:view')) store.fetchRecords({ per_page: 200 })
})
</script>

<template>
  <div>
    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <PageHero
      icon="ri-question-answer-line"
      badge="Alasan Kunjungan"
      title="Alasan Kunjungan"
      subtitle="Input alasan pasien memilih RS · data pendaftaran aktif"
      color-from="#7C3AED"
      color-to="#4F46E5"
      :pills="[
        { icon: 'ri-calendar-line', text: todayFormatted },
        { icon: 'ri-user-heart-line', text: `${pasienAktif.length} pasien aktif` },
      ]"
    />

    <!-- ── Tabs ───────────────────────────────────────────────────────────── -->
    <VCard elevation="0" border rounded="xl" class="mb-4">
      <VTabs v-model="activeTab" color="deep-purple" density="compact">
        <VTab value="pendaftaran">
          <VIcon icon="ri-user-heart-line" size="15" class="me-1" />
          Pasien Aktif
        </VTab>
        <VTab v-if="auth.hasPermission('alasan:view')" value="riwayat">
          <VIcon icon="ri-history-line" size="15" class="me-1" />
          Riwayat Input
        </VTab>
      </VTabs>
      <VDivider />

      <!-- ── TAB: Pasien Aktif ─────────────────────────────────────────── -->
      <div v-show="activeTab === 'pendaftaran'" class="pa-3">
        <VRow dense align="center" class="mb-3">
          <VCol cols="12" sm="6" md="5">
            <VTextField
              v-model="searchPasien"
              label="Cari No. Reg / No. MR / Nama Pasien"
              prepend-inner-icon="ri-search-line"
              variant="outlined"
              density="compact"
              hide-details
              clearable
              rounded="lg"
            />
          </VCol>
          <VCol cols="auto">
            <VBtn size="small" variant="tonal" color="deep-purple" prepend-icon="ri-refresh-line" @click="fetchPendaftaranAktif">
              Refresh
            </VBtn>
          </VCol>
          <VCol cols="auto" class="ms-auto">
            <VChip size="small" color="deep-purple" variant="tonal" rounded="pill">
              {{ pasienAktif.length }} pasien
            </VChip>
          </VCol>
        </VRow>

        <!-- Loading -->
        <div v-if="loadingPasien" class="text-center py-12">
          <VProgressCircular indeterminate color="deep-purple" size="32" />
          <p class="text-caption mt-2 text-disabled">Memuat data pendaftaran aktif...</p>
        </div>

        <!-- Empty -->
        <div v-else-if="!pasienAktif.length" class="text-center py-14" style="color: #94a3b8">
          <VIcon icon="ri-user-search-line" size="52" class="mb-3 opacity-30" />
          <p class="text-body-1 font-weight-semibold mb-1">Tidak ada pasien aktif</p>
          <p class="text-caption">Status = '1' &amp; Status_Pulang = 'belum'</p>
        </div>

        <!-- List pasien -->
        <VCard v-else elevation="0" border rounded="xl" class="overflow-hidden">
          <div v-for="(p, idx) in pasienAktif" :key="p.no_reg + idx" class="alasan-row"
            :class="{ 'alasan-row--bordered': idx < pasienAktif.length - 1 }">
            <!-- Avatar -->
            <VAvatar color="deep-purple" variant="tonal" size="40" rounded="md" class="flex-shrink-0">
              <span style="font-size:13px;font-weight:700">{{ p.nama_pasien?.charAt(0) ?? '?' }}</span>
            </VAvatar>

            <!-- Info -->
            <div class="flex-grow-1 min-width-0">
              <div class="d-flex align-center gap-2 flex-wrap mb-1">
                <span class="font-weight-semibold" style="font-size:0.875rem">{{ p.nama_pasien }}</span>
                <VChip v-if="p.ket_bayar" size="x-small" color="info" variant="tonal">{{ p.ket_bayar }}</VChip>
                <VChip v-if="p.nama_bangsal" size="x-small" color="secondary" variant="tonal">{{ p.nama_bangsal }}</VChip>
              </div>
              <div class="d-flex align-center gap-3 flex-wrap">
                <span class="text-caption" style="color:#64748b">
                  <VIcon icon="ri-hashtag" size="10" class="me-1" />{{ p.no_reg }}
                </span>
                <span v-if="p.no_mr" class="text-caption" style="color:#64748b">
                  MR: {{ p.no_mr }}
                </span>
                <span v-if="p.tgl_daftar" class="text-caption" style="color:#64748b">
                  <VIcon icon="ri-calendar-line" size="10" class="me-1" />{{ p.tgl_daftar }}
                  <span v-if="p.jam_daftar">{{ p.jam_daftar }}</span>
                </span>
              </div>
            </div>

            <!-- Actions -->
            <div class="d-flex align-center gap-2 flex-shrink-0">
              <VBtn
                v-if="auth.hasPermission('alasan:view')"
                size="x-small"
                variant="tonal"
                color="secondary"
                icon="ri-history-line"
                rounded="lg"
                @click="openRiwayat(p)"
              />
              <VBtn
                v-if="auth.hasPermission('alasan:write')"
                size="small"
                color="deep-purple"
                variant="tonal"
                rounded="lg"
                prepend-icon="ri-add-line"
                @click="openAlasanDialog(p)"
              >
                Input Alasan
              </VBtn>
            </div>
          </div>
        </VCard>
      </div>

      <!-- ── TAB: Riwayat Input ─────────────────────────────────────────── -->
      <div v-if="activeTab === 'riwayat' && auth.hasPermission('alasan:view')" class="pa-3">
        <VRow dense align="center" class="mb-3">
          <VCol cols="12" sm="6" md="5">
            <VTextField
              v-model="searchRiwayatAll"
              label="Cari riwayat..."
              prepend-inner-icon="ri-search-line"
              variant="outlined"
              density="compact"
              hide-details
              clearable
              rounded="lg"
            />
          </VCol>
          <VCol cols="auto" class="ms-auto">
            <VChip size="small" color="deep-purple" variant="tonal" rounded="pill">
              {{ filteredRecords.length }} data
            </VChip>
          </VCol>
        </VRow>

        <!-- Loading -->
        <div v-if="store.loading" class="text-center py-10">
          <VProgressCircular indeterminate color="deep-purple" size="28" />
        </div>

        <!-- Empty -->
        <div v-else-if="!filteredRecords.length" class="text-center py-14" style="color:#94a3b8">
          <VIcon icon="ri-inbox-line" size="48" class="mb-3 opacity-30" />
          <p class="text-body-1 font-weight-semibold">Belum ada riwayat alasan</p>
        </div>

        <!-- List riwayat -->
        <VCard v-else elevation="0" border rounded="xl" class="overflow-hidden">
          <div v-for="(item, idx) in filteredRecords" :key="item.id" class="alasan-row"
            :class="{ 'alasan-row--bordered': idx < filteredRecords.length - 1 }">
            <!-- Avatar -->
            <VAvatar :color="alasanColor(item.alasan)" variant="tonal" size="40" rounded="md" class="flex-shrink-0">
              <VIcon icon="ri-question-answer-line" size="18" />
            </VAvatar>

            <!-- Info -->
            <div class="flex-grow-1 min-width-0">
              <div class="d-flex align-center gap-2 flex-wrap mb-1">
                <span class="font-weight-semibold" style="font-size:0.875rem">{{ item.nama_pasien }}</span>
                <VChip :color="alasanColor(item.alasan)" size="x-small" variant="tonal">{{ item.alasan }}</VChip>
              </div>
              <div class="d-flex align-center gap-3 flex-wrap">
                <span class="text-caption" style="color:#64748b">{{ item.no_reg }}</span>
                <span v-if="item.catatan" class="text-caption text-truncate" style="color:#64748b;max-width:200px">
                  <VIcon icon="ri-chat-3-line" size="10" class="me-1" />{{ item.catatan }}
                </span>
              </div>
            </div>

            <!-- Right -->
            <div class="text-end flex-shrink-0" style="min-width:80px">
              <p class="text-caption mb-0 font-weight-medium">{{ item.petugas || '—' }}</p>
              <p class="text-caption mb-0" style="color:#64748b">{{ item.tanggal || '—' }}</p>
              <!-- Actions -->
              <div v-if="auth.hasPermission('alasan:write')" class="d-flex gap-1 justify-end mt-1">
                <VBtn size="x-small" variant="tonal" color="warning" icon="ri-edit-line" rounded="md"
                  @click="openEditDialog(item)" />
                <VBtn v-if="auth.hasPermission('alasan:delete')" size="x-small" variant="tonal" color="error"
                  icon="ri-delete-bin-line" rounded="md" @click="handleDelete(item)" />
              </div>
            </div>
          </div>
        </VCard>
      </div>
    </VCard>

    <!-- ── Dialog: Input Alasan ───────────────────────────────────────────── -->
    <VDialog v-model="dialogOpen" max-width="500" persistent scrollable>
      <VCard rounded="xl" class="overflow-hidden">
        <!-- Banner -->
        <div class="alasan-dlg-banner">
          <div class="adb-inner">
            <div class="adb-icon">
              <VIcon icon="ri-question-answer-line" size="20" color="white" />
            </div>
            <div class="adb-text">
              <p class="adb-sub">Alasan Kunjungan</p>
              <h3 class="adb-title">{{ editItem ? 'Edit Alasan' : 'Input Alasan Pasien' }}</h3>
            </div>
            <button class="adb-close" @click="dialogOpen = false">
              <VIcon icon="ri-close-line" size="16" />
            </button>
          </div>
        </div>

        <VCardText class="pa-0">
          <div v-if="formError" class="px-5 pt-4">
            <VAlert type="error" variant="tonal" density="compact" closable @click:close="formError = ''">
              {{ formError }}
            </VAlert>
          </div>

          <div class="px-5 py-4 d-flex flex-column gap-4">
            <!-- Info pasien (read-only) -->
            <div v-if="selectedPasien" class="form-section form-section--purple">
              <div class="fs-header fs-header--purple">
                <VIcon icon="ri-user-heart-line" size="14" />
                <span>Data Pasien</span>
              </div>
              <div class="fs-body">
                <div class="info-chip-grid">
                  <div class="icg-cell icg-cell--span2">
                    <span class="icg-lbl">Nama Pasien</span>
                    <span class="icg-val icg-val--accent">{{ selectedPasien.nama_pasien }}</span>
                  </div>
                  <div class="icg-cell">
                    <span class="icg-lbl">No. Reg</span>
                    <span class="icg-val icg-val--mono">{{ selectedPasien.no_reg }}</span>
                  </div>
                  <div class="icg-cell">
                    <span class="icg-lbl">Jaminan</span>
                    <span class="icg-val">{{ selectedPasien.ket_bayar || selectedPasien.jaminan || '—' }}</span>
                  </div>
                  <div class="icg-cell">
                    <span class="icg-lbl">Ruangan</span>
                    <span class="icg-val">{{ selectedPasien.nama_bangsal || selectedPasien.nama_ruang || '—' }}</span>
                  </div>
                  <div class="icg-cell">
                    <span class="icg-lbl">Tgl. Daftar</span>
                    <span class="icg-val">{{ selectedPasien.tgl_daftar || '—' }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Form alasan -->
            <div class="form-section form-section--purple">
              <div class="fs-header fs-header--purple">
                <VIcon icon="ri-question-line" size="14" />
                <span>Alasan Memilih RS</span>
                <span class="fs-required-badge">Wajib Diisi</span>
              </div>
              <div class="fs-body d-flex flex-column gap-3">
                <VSelect
                  v-model="formAlasan"
                  :items="masterStore.alasanPilihList"
                  label="Alasan *"
                  variant="outlined"
                  density="compact"
                  prepend-inner-icon="ri-question-answer-line"
                  clearable
                  hide-details="auto"
                />
                <VTextarea
                  v-model="formCatatan"
                  label="Catatan (opsional)"
                  variant="outlined"
                  density="compact"
                  rows="3"
                  auto-grow
                  prepend-inner-icon="ri-chat-3-line"
                  hide-details="auto"
                  placeholder="Tambahan keterangan atau informasi lain..."
                />
              </div>
            </div>

            <!-- Petugas -->
            <div class="form-section form-section--info">
              <div class="fs-header fs-header--info">
                <VIcon icon="ri-nurse-line" size="14" />
                <span>Petugas</span>
              </div>
              <div class="fs-body">
                <VAutocomplete
                  v-model="formPetugas"
                  :items="pegawaiStore.namaList"
                  label="Petugas"
                  variant="outlined"
                  density="compact"
                  prepend-inner-icon="ri-nurse-line"
                  clearable
                  hide-details="auto"
                  :loading="pegawaiStore.loading"
                  no-data-text="Memuat petugas..."
                />
              </div>
            </div>
          </div>
        </VCardText>

        <!-- Footer -->
        <div class="dlg-footer">
          <VBtn variant="outlined" rounded="lg" size="small" @click="dialogOpen = false">Batal</VBtn>
          <VBtn color="deep-purple" rounded="lg" class="flex-grow-1" prepend-icon="ri-save-line"
            :loading="saving || store.loading" @click="handleSave">
            {{ editItem ? 'Simpan Perubahan' : 'Simpan Alasan' }}
          </VBtn>
        </div>
      </VCard>
    </VDialog>

    <!-- ── Dialog: Riwayat Pasien ─────────────────────────────────────────── -->
    <VDialog v-model="dialogRiwayat" max-width="520" scrollable>
      <VCard rounded="xl">
        <VCardTitle class="d-flex align-center gap-2 pa-4">
          <VIcon icon="ri-history-line" color="deep-purple" />
          <span>Riwayat Alasan</span>
          <span v-if="riwayatPasien" class="text-caption text-medium-emphasis ms-1">
            — {{ riwayatPasien.nama_pasien }}
          </span>
          <VSpacer />
          <VBtn icon="ri-close-line" variant="text" size="small" @click="dialogRiwayat = false" />
        </VCardTitle>
        <VDivider />

        <VCardText class="pa-3">
          <div v-if="loadingRiwayat" class="text-center py-8">
            <VProgressCircular indeterminate color="deep-purple" size="28" />
          </div>
          <div v-else-if="!filteredRiwayat.length" class="text-center py-10" style="color:#94a3b8">
            <VIcon icon="ri-inbox-line" size="36" class="mb-2 opacity-30" />
            <p class="text-body-2">Belum ada riwayat alasan untuk pasien ini</p>
          </div>
          <div v-else>
            <VTextField v-model="searchRiwayat" label="Cari..." density="compact" variant="outlined" hide-details
              clearable rounded="lg" class="mb-3" prepend-inner-icon="ri-search-line" />
            <div v-for="item in filteredRiwayat" :key="item.id"
              class="d-flex align-center gap-3 py-3 riwayat-row">
              <VChip :color="alasanColor(item.alasan)" size="small" variant="tonal" rounded="pill" label>
                {{ item.alasan }}
              </VChip>
              <div class="flex-grow-1 min-width-0">
                <p v-if="item.catatan" class="text-caption mb-0 text-truncate" style="max-width:220px">
                  {{ item.catatan }}
                </p>
                <p v-else class="text-caption mb-0 text-disabled">—</p>
              </div>
              <div class="text-end flex-shrink-0">
                <p class="text-caption mb-0" style="color:#64748b">{{ item.tanggal || '—' }}</p>
                <p class="text-caption mb-0 font-weight-medium">{{ item.petugas || '—' }}</p>
              </div>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- ── Snackbar ───────────────────────────────────────────────────────── -->
    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom right" rounded="pill">
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>

<style scoped>
/* ── Rows ── */
.alasan-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  transition: background 0.12s;
}

.alasan-row--bordered {
  border-bottom: 1px solid rgba(0, 0, 0, 0.07);
}

.alasan-row:hover {
  background: rgba(124, 58, 237, 0.04);
  border-left: 3px solid rgba(124, 58, 237, 0.35);
  padding-left: 13px;
}

.riwayat-row {
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.riwayat-row:last-child {
  border-bottom: none;
}

/* ── Dialog Banner ── */
.alasan-dlg-banner {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #6d28d9 0%, #7c3aed 55%, #a78bfa 100%);
  padding: 16px 18px 14px;
}

.adb-inner {
  display: flex;
  align-items: center;
  gap: 12px;
}

.adb-icon {
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.22);
  border: 1.5px solid rgba(255, 255, 255, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
}

.adb-text {
  flex: 1;
  min-width: 0;
}

.adb-sub {
  font-size: 0.6rem;
  color: rgba(255, 255, 255, 0.72);
  margin: 0 0 1px;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.adb-title {
  font-size: 0.95rem;
  font-weight: 800;
  color: #fff;
  margin: 0;
}

.adb-close {
  flex-shrink: 0;
  background: rgba(255, 255, 255, 0.2);
  border: none;
  cursor: pointer;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  transition: background 0.15s;
}

.adb-close:hover {
  background: rgba(255, 255, 255, 0.35);
}

/* ── Form sections ── */
.form-section {
  border-radius: 14px;
  border: 1.5px solid rgba(var(--v-border-color), var(--v-border-opacity));
  overflow: hidden;
}

.fs-header {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 9px 14px;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.fs-header--purple {
  background: rgba(124, 58, 237, 0.08);
  color: #7c3aed;
}

.fs-header--info {
  background: rgba(var(--v-theme-info), 0.07);
  color: rgb(var(--v-theme-info));
}

.fs-required-badge {
  margin-left: auto;
  font-size: 0.55rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 1px 6px;
  border-radius: 10px;
  background: rgba(124, 58, 237, 0.12);
  color: #7c3aed;
}

.fs-body {
  padding: 14px;
}

/* ── Info grid ── */
.info-chip-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1px;
  background: rgba(var(--v-border-color), var(--v-border-opacity));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  overflow: hidden;
}

.icg-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 9px 11px;
  background: rgba(var(--v-theme-surface), 1);
}

.icg-cell--span2 { grid-column: span 2; }

.icg-lbl {
  font-size: 0.58rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: rgba(var(--v-theme-on-surface), 0.38);
}

.icg-val {
  font-size: 0.82rem;
  font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.85);
}

.icg-val--accent { font-weight: 700; color: rgba(var(--v-theme-on-surface), 0.92); }
.icg-val--mono   { font-family: monospace; font-size: 0.78rem; }

/* ── Footer ── */
.dlg-footer {
  flex-shrink: 0;
  display: flex;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-surface-variant), 0.25);
}
</style>
