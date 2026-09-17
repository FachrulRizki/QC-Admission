<script setup>
import axios from 'axios'
import { useAlasanStore } from '@/stores/useAlasanStore'
import { useMasterDataStore } from '@/stores/useMasterDataStore'
import { usePegawaiStore } from '@/stores/usePegawaiStore'
import { useAuthStore } from '@/stores/useAuthStore'
import PageHero from '@/components/PageHero.vue'

const auth         = useAuthStore()
const store        = useAlasanStore()
const masterStore  = useMasterDataStore()
const pegawaiStore = usePegawaiStore()

// ── Mapping no_reg → entries ────────────────────────────────────────────────
const recordsByNoReg = computed(() => {
  const map = {}
  for (const r of store.records) {
    if (!map[r.no_reg]) map[r.no_reg] = []
    map[r.no_reg].push(r)
  }
  return map
})
const filledNoRegs = computed(() => new Set(Object.keys(recordsByNoReg.value)))

// ── State umum ─────────────────────────────────────────────────────────────
const loadingPasien  = ref(false)
const pasienAktif    = ref([])
const searchPasien   = ref('')
const snackbar       = ref({ show: false, text: '', color: 'success' })
const activeTab      = ref('pendaftaran')

// ── Dialog DETAIL (sudah pernah isi) ───────────────────────────────────────
const dialogDetail   = ref(false)
const detailPasien   = ref(null)   // pasien object dari list
const detailRecords  = ref([])     // entries alasan pasien ini

// ── Dialog FORM (input / edit) ─────────────────────────────────────────────
const dialogForm     = ref(false)
const formPasien     = ref(null)
const formAlasan     = ref('')
const formCatatan    = ref('')
const formPetugas    = ref('')
const formError      = ref('')
const saving         = ref(false)
const editItem       = ref(null)

// ── Fetch pendaftaran ───────────────────────────────────────────────────────
async function fetchPendaftaranAktif() {
  loadingPasien.value = true
  try {
    const { data } = await axios.get('/api/alasan/pendaftaran-aktif', {
      params: searchPasien.value.trim() ? { search: searchPasien.value.trim() } : {},
    })
    pasienAktif.value = data.data ?? []
  } catch {
    notify('Gagal memuat data pendaftaran aktif', 'error')
  } finally {
    loadingPasien.value = false
  }
}

let searchTimer = null
watch(searchPasien, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(fetchPendaftaranAktif, 350)
})

// ── Klik baris pasien ───────────────────────────────────────────────────────
function handleRowClick(pasien) {
  if (filledNoRegs.value.has(pasien.no_reg)) {
    // Sudah diisi → buka modal detail
    detailPasien.value  = pasien
    detailRecords.value = recordsByNoReg.value[pasien.no_reg] ?? []
    dialogDetail.value  = true
  } else {
    // Belum diisi → langsung buka form
    if (!auth.hasPermission('alasan:write')) return
    openFormNew(pasien)
  }
}

// ── Buka form baru (dari baris kosong atau tombol "Isi Lagi" di detail) ─────
function openFormNew(pasien) {
  formPasien.value  = pasien
  formAlasan.value  = ''
  formCatatan.value = ''
  formPetugas.value = auth.user?.preferred_username ?? auth.user?.name ?? ''
  formError.value   = ''
  editItem.value    = null
  dialogForm.value  = true
  masterStore.fetch()
  pegawaiStore.fetch()
}

// Dari tombol "Isi Lagi" di detail modal
function isLagi() {
  const p = detailPasien.value
  dialogDetail.value = false
  nextTick(() => openFormNew(p))
}

// ── Buka form edit ──────────────────────────────────────────────────────────
function openEditDialog(item) {
  editItem.value    = item
  formPasien.value  = {
    no_reg:       item.no_reg,
    no_mr:        item.no_mr,
    nama_pasien:  item.nama_pasien,
    ket_bayar:    item.jaminan,
    tgl_daftar:   item.tgl_daftar,
    jam_daftar:   item.jam_daftar,
    nama_ruang:   item.nama_ruang,
    nama_bangsal: item.nama_bangsal,
  }
  formAlasan.value  = item.alasan  ?? ''
  formCatatan.value = item.catatan ?? ''
  formPetugas.value = item.petugas ?? ''
  formError.value   = ''
  dialogDetail.value = false
  nextTick(() => {
    dialogForm.value = true
    masterStore.fetch()
    pegawaiStore.fetch()
  })
}

// ── Simpan ──────────────────────────────────────────────────────────────────
async function handleSave() {
  formError.value = ''
  if (!formAlasan.value) { formError.value = 'Alasan wajib dipilih.'; return }

  saving.value = true
  const now = new Date()
  const z   = n => String(n).padStart(2, '0')
  const jam = `${z(now.getHours())}.${z(now.getMinutes())}.${z(now.getSeconds())}`
  const tgl = `${z(now.getDate())}/${z(now.getMonth() + 1)}/${now.getFullYear()}, ${jam}`

  const payload = {
    tanggal:      tgl,
    jam_input:    jam,
    no_reg:       formPasien.value.no_reg,
    no_mr:        formPasien.value.no_mr        ?? '',
    nama_pasien:  formPasien.value.nama_pasien   ?? '',
    jaminan:      formPasien.value.ket_bayar     ?? '',
    tgl_daftar:   formPasien.value.tgl_daftar   ?? '',
    jam_daftar:   formPasien.value.jam_daftar   ?? '',
    nama_ruang:   formPasien.value.nama_ruang   ?? '',
    nama_bangsal: formPasien.value.nama_bangsal ?? '',
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
    dialogForm.value = false
    await store.fetchRecords({ per_page: 200 })
  } else {
    formError.value = result?.message ?? 'Gagal menyimpan.'
  }
}

// ── Hapus ───────────────────────────────────────────────────────────────────
async function handleDelete(item) {
  if (!confirm(`Hapus alasan "${item.alasan}" untuk ${item.nama_pasien}?`)) return
  const result = await store.destroy(item.id)
  if (result.success) {
    notify('Data berhasil dihapus.', 'success')
    await store.fetchRecords({ per_page: 200 })
    // update detail records jika detail dialog masih buka
    if (dialogDetail.value && detailPasien.value) {
      detailRecords.value = (recordsByNoReg.value[detailPasien.value.no_reg] ?? [])
      if (!detailRecords.value.length) dialogDetail.value = false
    }
  } else {
    notify(result.message, 'error')
  }
}

// ── Helpers ─────────────────────────────────────────────────────────────────
function notify(text, color = 'success') {
  snackbar.value = { show: true, text, color }
}

function alasanColor(a) {
  const map = {
    'Pelayanan':                 'primary',
    'Kelengkapan Alat & Dokter': 'warning',
    'Teman/Kerabat':             'info',
    'Rujukan':                   'success',
    'Marketing':                 'secondary',
    'Sosial Media':              'info',
  }
  return map[a] ?? 'secondary'
}

// ── Riwayat tab ─────────────────────────────────────────────────────────────
const searchRiwayatAll = ref('')
const filteredRecords  = computed(() => {
  if (!searchRiwayatAll.value.trim()) return store.records
  const q = searchRiwayatAll.value.toLowerCase()
  return store.records.filter(r =>
    Object.values(r).some(v => String(v ?? '').toLowerCase().includes(q))
  )
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
    <!-- ── Hero ───────────────────────────────────────────────────────────── -->
    <PageHero
      icon="ri-question-answer-line"
      badge="Alasan Kunjungan"
      title="Alasan Kunjungan"
      subtitle="Input alasan pasien memilih RS · data pendaftaran aktif"
      color-from="#0369A1"
      color-to="#0EA5E9"
      :pills="[
        { icon: 'ri-calendar-line',        text: todayFormatted },
        { icon: 'ri-user-heart-line',      text: `${pasienAktif.length} pasien aktif` },
        { icon: 'ri-checkbox-circle-line', text: `${filledNoRegs.size} sudah diisi` },
      ]"
    />

    <!-- ── Tab bar ─────────────────────────────────────────────────────────── -->
    <div class="al-tabs">
      <button
        class="al-tab"
        :class="{ 'al-tab--active': activeTab === 'pendaftaran' }"
        @click="activeTab = 'pendaftaran'"
      >
        <VIcon icon="ri-user-heart-line" size="15" />
        Pasien Aktif
        <span class="al-tab__badge" :class="{ 'al-tab__badge--active': activeTab === 'pendaftaran' }">
          {{ pasienAktif.length }}
        </span>
      </button>
      <button
        v-if="auth.hasPermission('alasan:view')"
        class="al-tab"
        :class="{ 'al-tab--active': activeTab === 'riwayat' }"
        @click="activeTab = 'riwayat'"
      >
        <VIcon icon="ri-history-line" size="15" />
        Riwayat Input
        <span class="al-tab__badge" :class="{ 'al-tab__badge--active': activeTab === 'riwayat' }">
          {{ store.records.length }}
        </span>
      </button>
    </div>

    <!-- ══════════════════════════════════
         TAB: PASIEN AKTIF
    ═══════════════════════════════════ -->
    <div v-show="activeTab === 'pendaftaran'">
      <div class="al-toolbar">
        <div class="al-search-wrap">
          <VIcon icon="ri-search-line" size="16" class="al-search-icon" />
          <input v-model="searchPasien" class="al-search-input" placeholder="Cari No. Reg / MR / Nama Pasien..." />
          <button v-if="searchPasien" class="al-search-clear" @click="searchPasien = ''">
            <VIcon icon="ri-close-line" size="14" />
          </button>
        </div>
        <button class="al-icon-btn" :disabled="loadingPasien" title="Refresh" @click="fetchPendaftaranAktif">
          <VIcon :icon="loadingPasien ? 'ri-loader-4-line' : 'ri-refresh-line'" size="16"
            :class="{ spin: loadingPasien }" />
        </button>
      </div>

      <div v-if="loadingPasien" class="al-state-box">
        <VProgressCircular indeterminate color="primary" size="30" />
        <span>Memuat data...</span>
      </div>

      <div v-else-if="!pasienAktif.length" class="al-state-box">
        <VIcon icon="ri-user-search-line" size="48" style="opacity:.18" />
        <span class="al-state-box__title">Tidak ada pasien aktif</span>
        <span class="al-state-box__sub">Status aktif &amp; belum pulang</span>
      </div>

      <div v-else class="al-list">
        <div
          v-for="(p, idx) in pasienAktif"
          :key="p.no_reg + idx"
          class="al-row"
          :class="{ 'al-row--filled': filledNoRegs.has(p.no_reg) }"
          @click="handleRowClick(p)"
        >
          <!-- Avatar -->
          <div class="al-row__av" :class="filledNoRegs.has(p.no_reg) ? 'al-row__av--done' : 'al-row__av--new'">
            {{ p.nama_pasien?.charAt(0) ?? '?' }}
            <span v-if="filledNoRegs.has(p.no_reg)" class="al-row__dot" />
          </div>

          <!-- Info -->
          <div class="al-row__info">
            <div class="al-row__nameline">
              <span class="al-row__name">{{ p.nama_pasien }}</span>
              <span v-if="p.ket_bayar" class="al-chip al-chip--blue">{{ p.ket_bayar }}</span>
              <span v-if="p.nama_bangsal" class="al-chip al-chip--gray">{{ p.nama_bangsal }}</span>
              <span v-if="filledNoRegs.has(p.no_reg)" class="al-chip al-chip--green">
                <VIcon icon="ri-checkbox-circle-fill" size="10" />
                {{ recordsByNoReg[p.no_reg]?.length }} alasan
              </span>
            </div>
            <div class="al-row__meta">
              <span><VIcon icon="ri-hashtag" size="10" />{{ p.no_reg }}</span>
              <span v-if="p.no_mr">MR: {{ p.no_mr }}</span>
              <span v-if="p.tgl_daftar">
                <VIcon icon="ri-time-line" size="10" />{{ p.tgl_daftar }}<template v-if="p.jam_daftar"> {{ p.jam_daftar }}</template>
              </span>
            </div>
          </div>

          <!-- Right hint -->
          <div class="al-row__hint">
            <VIcon
              :icon="filledNoRegs.has(p.no_reg) ? 'ri-eye-line' : 'ri-add-circle-line'"
              size="18"
              :style="{ color: filledNoRegs.has(p.no_reg) ? 'rgb(var(--v-theme-success))' : 'rgb(var(--v-theme-primary))' }"
              style="opacity:0.5"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════
         TAB: RIWAYAT INPUT
    ═══════════════════════════════════ -->
    <div v-if="activeTab === 'riwayat' && auth.hasPermission('alasan:view')">
      <div class="al-toolbar">
        <div class="al-search-wrap">
          <VIcon icon="ri-search-line" size="16" class="al-search-icon" />
          <input v-model="searchRiwayatAll" class="al-search-input" placeholder="Cari riwayat..." />
          <button v-if="searchRiwayatAll" class="al-search-clear" @click="searchRiwayatAll = ''">
            <VIcon icon="ri-close-line" size="14" />
          </button>
        </div>
        <span class="al-count-chip">{{ filteredRecords.length }} data</span>
      </div>

      <div v-if="store.loading" class="al-state-box">
        <VProgressCircular indeterminate color="primary" size="28" />
      </div>

      <div v-else-if="!filteredRecords.length" class="al-state-box">
        <VIcon icon="ri-inbox-line" size="48" style="opacity:.18" />
        <span class="al-state-box__title">Belum ada riwayat</span>
      </div>

      <div v-else class="al-list">
        <div v-for="item in filteredRecords" :key="item.id" class="al-rw-row">
          <div class="al-rw-bar" :class="`al-rw-bar--${alasanColor(item.alasan)}`" />
          <div class="al-row__info" style="flex:1">
            <div class="al-row__nameline">
              <span class="al-row__name">{{ item.nama_pasien }}</span>
              <span class="al-chip" :class="`al-chip--${alasanColor(item.alasan)}`">{{ item.alasan }}</span>
            </div>
            <div class="al-row__meta">
              <span>{{ item.no_reg }}</span>
              <span v-if="item.jaminan">{{ item.jaminan }}</span>
              <span v-if="item.catatan" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <VIcon icon="ri-chat-3-line" size="10" />"{{ item.catatan }}"
              </span>
            </div>
          </div>
          <div class="al-rw-right">
            <span class="al-rw-right__petugas"><VIcon icon="ri-user-3-line" size="11" />{{ item.petugas || '—' }}</span>
            <span class="al-rw-right__tgl">{{ item.tanggal || '—' }}</span>
            <div v-if="auth.hasPermission('alasan:write')" class="al-rw-right__acts">
              <button class="al-mini-btn al-mini-btn--edit" @click="openEditDialog(item)">
                <VIcon icon="ri-edit-line" size="14" />
                <span>Edit</span>
              </button>
              <button v-if="auth.hasPermission('alasan:delete')" class="al-mini-btn al-mini-btn--del"
                @click="handleDelete(item)">
                <VIcon icon="ri-delete-bin-line" size="14" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         MODAL DETAIL — sudah pernah isi
    ═══════════════════════════════════════════════════════════════════ -->
    <VDialog v-model="dialogDetail" max-width="580" scrollable>
      <VCard rounded="xl" class="overflow-hidden">
        <!-- Header pasien -->
        <div class="det-header">
          <div class="det-header__av">
            {{ detailPasien?.nama_pasien?.charAt(0) ?? '?' }}
          </div>
          <div class="det-header__info">
            <p class="det-header__name">{{ detailPasien?.nama_pasien }}</p>
            <div class="det-header__meta">
              <span v-if="detailPasien?.no_reg">
                <VIcon icon="ri-hashtag" size="11" />{{ detailPasien.no_reg }}
              </span>
              <span v-if="detailPasien?.no_mr">MR: {{ detailPasien.no_mr }}</span>
              <span v-if="detailPasien?.ket_bayar">{{ detailPasien.ket_bayar }}</span>
              <span v-if="detailPasien?.nama_bangsal">{{ detailPasien.nama_bangsal }}</span>
            </div>
          </div>
          <VBtn icon="ri-close-line" variant="text" color="white" size="small" @click="dialogDetail = false" />
        </div>

        <!-- Daftar entri alasan -->
        <VCardText class="pa-0">
          <div class="det-entries">
            <div v-for="(rec, i) in detailRecords" :key="rec.id" class="det-entry">
              <!-- Nomor urut -->
              <div class="det-entry__num">{{ i + 1 }}</div>

              <!-- Konten -->
              <div class="det-entry__body">
                <!-- Alasan -->
                <div class="det-entry__alasan-row">
                  <span class="det-entry__alasan" :class="`det-entry__alasan--${alasanColor(rec.alasan)}`">
                    <VIcon icon="ri-chat-check-line" size="13" />
                    {{ rec.alasan }}
                  </span>
                </div>
                <!-- Catatan -->
                <p v-if="rec.catatan" class="det-entry__catatan">
                  <VIcon icon="ri-double-quotes-l" size="11" class="det-entry__quote" />
                  {{ rec.catatan }}
                </p>
                <!-- Meta: petugas + waktu -->
                <div class="det-entry__meta">
                  <span><VIcon icon="ri-user-3-line" size="11" />{{ rec.petugas || '—' }}</span>
                  <span><VIcon icon="ri-time-line" size="11" />{{ rec.tanggal || '—' }}</span>
                </div>
              </div>

              <!-- Aksi edit/hapus -->
              <div v-if="auth.hasPermission('alasan:write')" class="det-entry__acts">
                <button class="al-mini-btn al-mini-btn--edit" title="Edit" @click="openEditDialog(rec)">
                  <VIcon icon="ri-edit-line" size="14" />
                  <span>Edit</span>
                </button>
                <button v-if="auth.hasPermission('alasan:delete')"
                  class="al-mini-btn al-mini-btn--del" title="Hapus" @click="handleDelete(rec)">
                  <VIcon icon="ri-delete-bin-line" size="14" />
                </button>
              </div>
            </div>
          </div>
        </VCardText>

        <!-- Footer: tutup + isi lagi -->
        <div class="det-footer">
          <VBtn variant="outlined" color="primary" rounded="lg" @click="dialogDetail = false">Tutup</VBtn>
          <VBtn
            v-if="auth.hasPermission('alasan:write')"
            color="primary"
            variant="flat"
            rounded="lg"
            class="flex-grow-1"
            prepend-icon="ri-add-line"
            @click="isLagi"
          >
            Isi Lagi
          </VBtn>
        </div>
      </VCard>
    </VDialog>

    <!-- ══════════════════════════════════════════════════════════════════
         MODAL FORM — input / edit alasan
    ═══════════════════════════════════════════════════════════════════ -->
    <VDialog v-model="dialogForm" max-width="540" persistent scrollable>
      <VCard rounded="xl" class="overflow-hidden">
        <!-- Banner -->
        <div class="frm-banner">
          <div class="frm-banner__icon">
            <VIcon icon="ri-question-answer-line" size="20" color="white" />
          </div>
          <div class="frm-banner__text">
            <p class="frm-banner__label">{{ editItem ? 'Edit Alasan' : 'Input Alasan' }}</p>
            <p class="frm-banner__name">{{ formPasien?.nama_pasien }}</p>
          </div>
          <button class="frm-banner__close" @click="dialogForm = false">
            <VIcon icon="ri-close-line" size="16" />
          </button>
        </div>

        <!-- Strip info pasien -->
        <div v-if="formPasien" class="frm-strip">
          <div class="frm-strip__cell">
            <span class="frm-strip__lbl">No. Reg</span>
            <span class="frm-strip__val">{{ formPasien.no_reg }}</span>
          </div>
          <div v-if="formPasien.ket_bayar || formPasien.jaminan" class="frm-strip__cell">
            <span class="frm-strip__lbl">Jaminan</span>
            <span class="frm-strip__val">{{ formPasien.ket_bayar || formPasien.jaminan }}</span>
          </div>
          <div v-if="formPasien.nama_bangsal || formPasien.nama_ruang" class="frm-strip__cell">
            <span class="frm-strip__lbl">Ruangan</span>
            <span class="frm-strip__val">{{ formPasien.nama_bangsal || formPasien.nama_ruang }}</span>
          </div>
          <div v-if="formPasien.tgl_daftar" class="frm-strip__cell">
            <span class="frm-strip__lbl">Tgl. Daftar</span>
            <span class="frm-strip__val">{{ formPasien.tgl_daftar }}</span>
          </div>
        </div>

        <VCardText class="pa-4">
          <VAlert v-if="formError" type="error" variant="tonal" density="compact" closable class="mb-3"
            @click:close="formError = ''">{{ formError }}</VAlert>

          <div class="d-flex flex-column gap-3">
            <VSelect
              v-model="formAlasan"
              :items="masterStore.alasanPilihList"
              label="Alasan Memilih RS *"
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
              rows="2"
              auto-grow
              prepend-inner-icon="ri-chat-3-line"
              hide-details="auto"
              placeholder="Keterangan tambahan..."
            />
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
        </VCardText>

        <div class="frm-footer">
          <VBtn variant="text" color="default" @click="dialogForm = false">Batal</VBtn>
          <VBtn color="primary" variant="flat" class="flex-grow-1" prepend-icon="ri-save-line"
            :loading="saving || store.loading" @click="handleSave">
            {{ editItem ? 'Simpan Perubahan' : 'Simpan' }}
          </VBtn>
        </div>
      </VCard>
    </VDialog>

    <!-- Snackbar -->
    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom right" rounded="pill">
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>

<style scoped>
/* ═══════════════════════════════════
   TAB BAR — putih, underline biru
══════════════════════════════════ */
.al-tabs {
  display: flex;
  background: #fff;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 14px 14px 0 0;
  border-bottom: none;
  overflow: hidden;
}

.al-tab {
  display: flex;
  align-items: center;
  gap: 7px;
  flex: 1;
  justify-content: center;
  padding: 12px 20px;
  border: none;
  background: transparent;
  font-size: 0.84rem;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.45);
  cursor: pointer;
  border-bottom: 2.5px solid transparent;
  transition: color 0.15s, border-color 0.15s, background 0.12s;
}

.al-tab + .al-tab {
  border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.al-tab:hover:not(.al-tab--active) {
  background: rgba(var(--v-theme-primary), 0.03);
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.al-tab--active {
  color: rgb(var(--v-theme-primary));
  font-weight: 700;
  border-bottom-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.03);
}

.al-tab__badge {
  padding: 1px 7px;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 700;
  background: rgba(var(--v-theme-on-surface), 0.07);
  color: rgba(var(--v-theme-on-surface), 0.45);
  transition: background 0.15s, color 0.15s;
}

.al-tab__badge--active {
  background: rgba(var(--v-theme-primary), 0.12);
  color: rgb(var(--v-theme-primary));
}

/* ═══════════════════════════════════
   TOOLBAR
══════════════════════════════════ */
.al-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 0 8px;
}

.al-search-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  background: #fff;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  padding: 0 10px;
  height: 38px;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.al-search-wrap:focus-within {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.08);
}

.al-search-icon { color: rgba(var(--v-theme-on-surface), 0.3); flex-shrink: 0; }

.al-search-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-size: 0.84rem;
  color: rgba(var(--v-theme-on-surface), 0.87);
  padding: 0 8px;
}

.al-search-input::placeholder { color: rgba(var(--v-theme-on-surface), 0.3); }

.al-search-clear {
  background: none; border: none; cursor: pointer;
  color: rgba(var(--v-theme-on-surface), 0.3);
  padding: 2px; display: flex; align-items: center;
}

.al-icon-btn {
  width: 38px; height: 38px; flex-shrink: 0;
  border-radius: 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: #fff; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  color: rgba(var(--v-theme-on-surface), 0.45);
  transition: background 0.12s, color 0.12s, border-color 0.12s;
}

.al-icon-btn:hover:not(:disabled) {
  background: rgba(var(--v-theme-primary), 0.05);
  color: rgb(var(--v-theme-primary));
  border-color: rgba(var(--v-theme-primary), 0.25);
}

.al-count-chip {
  padding: 4px 10px; border-radius: 999px;
  font-size: 0.72rem; font-weight: 600;
  background: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
}

.spin { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ═══════════════════════════════════
   STATE BOX (loading / empty)
══════════════════════════════════ */
.al-state-box {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  padding: 56px 16px;
  background: #fff;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-top: none;
  border-radius: 0 0 14px 14px;
  color: rgba(var(--v-theme-on-surface), 0.35);
  font-size: 0.84rem;
}

.al-state-box__title { font-size: 0.88rem; font-weight: 600; color: rgba(var(--v-theme-on-surface), 0.45); }
.al-state-box__sub   { font-size: 0.76rem; }

/* ═══════════════════════════════════
   LIST
══════════════════════════════════ */
.al-list {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-top: none;
  border-radius: 0 0 14px 14px;
  background: #fff;
  overflow: hidden;
}

/* ─── Pasien row ─── */
.al-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 13px 16px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  cursor: pointer;
  transition: background 0.1s;
  position: relative;
}

.al-row:last-child { border-bottom: none; }

.al-row:hover { background: rgba(var(--v-theme-primary), 0.04); }

.al-row--filled { background: rgba(16, 185, 129, 0.025); }
.al-row--filled:hover { background: rgba(16, 185, 129, 0.06) !important; }

/* Avatar */
.al-row__av {
  position: relative; flex-shrink: 0;
  width: 42px; height: 42px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; font-weight: 700;
}

.al-row__av--new  { background: rgba(var(--v-theme-primary), 0.1);  color: rgb(var(--v-theme-primary)); }
.al-row__av--done { background: rgba(16, 185, 129, 0.13); color: #059669; }

.al-row__dot {
  position: absolute; bottom: -2px; right: -2px;
  width: 11px; height: 11px; border-radius: 50%;
  background: #10B981;
  border: 2px solid #fff;
}

/* Info */
.al-row__info { flex: 1; min-width: 0; }

.al-row__nameline {
  display: flex; align-items: center; gap: 6px;
  flex-wrap: wrap; margin-bottom: 5px;
}

.al-row__name { font-size: 0.875rem; font-weight: 600; color: rgba(var(--v-theme-on-surface), 0.87); }

.al-row__meta {
  display: flex; align-items: center; flex-wrap: wrap; gap: 6px;
  font-size: 0.72rem; color: rgba(var(--v-theme-on-surface), 0.4);
}

.al-row__meta span { display: inline-flex; align-items: center; gap: 3px; }

.al-row__hint { flex-shrink: 0; }

/* Chips */
.al-chip {
  display: inline-flex; align-items: center; gap: 3px;
  padding: 2px 7px; border-radius: 6px;
  font-size: 0.68rem; font-weight: 600; white-space: nowrap;
}

.al-chip--blue    { background: rgba(var(--v-theme-info), 0.1);    color: rgb(var(--v-theme-info)); }
.al-chip--gray    { background: rgba(var(--v-theme-on-surface), 0.07); color: rgba(var(--v-theme-on-surface), 0.5); }
.al-chip--green   { background: rgba(16, 185, 129, 0.12); color: #059669; }
.al-chip--primary { background: rgba(var(--v-theme-primary), 0.1); color: rgb(var(--v-theme-primary)); }
.al-chip--warning { background: rgba(var(--v-theme-warning), 0.1); color: rgb(var(--v-theme-warning)); }
.al-chip--success { background: rgba(var(--v-theme-success), 0.1); color: rgb(var(--v-theme-success)); }
.al-chip--info    { background: rgba(var(--v-theme-info), 0.1);    color: rgb(var(--v-theme-info)); }
.al-chip--secondary { background: rgba(var(--v-theme-on-surface), 0.07); color: rgba(var(--v-theme-on-surface), 0.5); }

/* ─── Riwayat row ─── */
.al-rw-row {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 16px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.al-rw-row:last-child { border-bottom: none; }

.al-rw-bar { width: 4px; height: 36px; flex-shrink: 0; border-radius: 3px; }
.al-rw-bar--primary   { background: rgb(var(--v-theme-primary)); }
.al-rw-bar--warning   { background: rgb(var(--v-theme-warning)); }
.al-rw-bar--info      { background: rgb(var(--v-theme-info)); }
.al-rw-bar--success   { background: rgb(var(--v-theme-success)); }
.al-rw-bar--secondary { background: rgba(var(--v-theme-on-surface), 0.18); }

.al-rw-right { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }

.al-rw-right__petugas {
  display: flex; align-items: center; gap: 4px;
  font-size: 0.75rem; font-weight: 600; color: rgba(var(--v-theme-on-surface), 0.65);
}

.al-rw-right__tgl { font-size: 0.68rem; color: rgba(var(--v-theme-on-surface), 0.38); }

.al-rw-right__acts { display: flex; gap: 4px; margin-top: 4px; }

/* Mini action buttons */
.al-mini-btn {
  height: 32px;
  padding: 0 10px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.75rem;
  font-weight: 600;
  transition: background 0.12s;
  white-space: nowrap;
}

.al-mini-btn--edit { background: rgba(var(--v-theme-warning), 0.1); color: rgb(var(--v-theme-warning)); }
.al-mini-btn--edit:hover { background: rgba(var(--v-theme-warning), 0.22); }
.al-mini-btn--del  { width: 32px; padding: 0; justify-content: center; background: rgba(var(--v-theme-error), 0.1); color: rgb(var(--v-theme-error)); }
.al-mini-btn--del:hover  { background: rgba(var(--v-theme-error), 0.22); }

/* ═══════════════════════════════════
   MODAL DETAIL
══════════════════════════════════ */
.det-header {
  display: flex; align-items: center; gap: 12px;
  padding: 18px 20px;
  background: linear-gradient(135deg, #059669 0%, #10B981 55%, #34D399 100%);
}

.det-header__av {
  width: 44px; height: 44px; flex-shrink: 0; border-radius: 13px;
  background: rgba(255,255,255,0.22);
  border: 1.5px solid rgba(255,255,255,0.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; font-weight: 800; color: #fff;
}

.det-header__info { flex: 1; min-width: 0; }

.det-header__name {
  font-size: 1rem; font-weight: 800; color: #fff;
  margin: 0 0 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

.det-header__meta {
  display: flex; flex-wrap: wrap; gap: 10px;
  font-size: 0.73rem; color: rgba(255,255,255,0.78);
}

.det-header__meta span { display: inline-flex; align-items: center; gap: 3px; }

/* Entries */
.det-entries { padding: 12px 16px; display: flex; flex-direction: column; gap: 8px; }

.det-entry {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 14px;
  background: #fff;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  transition: border-color 0.12s, box-shadow 0.12s;
}

.det-entry:hover {
  border-color: rgba(var(--v-theme-primary), 0.25);
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.det-entry__num {
  flex-shrink: 0; width: 22px; height: 22px;
  border-radius: 7px;
  background: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
  font-size: 0.7rem; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  margin-top: 2px;
}

.det-entry__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 5px; }

.det-entry__alasan-row { display: flex; align-items: center; gap: 6px; }

.det-entry__alasan {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: 8px;
  font-size: 0.8rem; font-weight: 700;
}

.det-entry__alasan--primary   { background: rgba(var(--v-theme-primary), 0.1); color: rgb(var(--v-theme-primary)); }
.det-entry__alasan--warning   { background: rgba(var(--v-theme-warning), 0.1); color: rgb(var(--v-theme-warning)); }
.det-entry__alasan--info      { background: rgba(var(--v-theme-info), 0.1);    color: rgb(var(--v-theme-info)); }
.det-entry__alasan--success   { background: rgba(var(--v-theme-success), 0.1); color: rgb(var(--v-theme-success)); }
.det-entry__alasan--secondary { background: rgba(var(--v-theme-on-surface), 0.07); color: rgba(var(--v-theme-on-surface), 0.6); }

.det-entry__catatan {
  font-size: 0.82rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
  display: flex;
  align-items: flex-start;
  gap: 5px;
  line-height: 1.55;
  margin: 0;
  word-break: break-word;
}

.det-entry__quote { opacity: 0.35; flex-shrink: 0; margin-top: 2px; }

.det-entry__meta {
  display: flex; flex-wrap: wrap; gap: 10px;
  font-size: 0.72rem; color: rgba(var(--v-theme-on-surface), 0.42);
}

.det-entry__meta span { display: inline-flex; align-items: center; gap: 3px; }

.det-entry__acts {
  flex-shrink: 0;
  display: flex;
  flex-direction: row;
  gap: 6px;
  align-items: flex-start;
  padding-top: 2px;
}

.det-footer {
  display: flex; gap: 8px; padding: 12px 16px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: #fff;
}

/* ═══════════════════════════════════
   MODAL FORM
══════════════════════════════════ */
.frm-banner {
  display: flex; align-items: center; gap: 12px;
  padding: 16px 18px;
  background: linear-gradient(135deg, #0369A1 0%, #0EA5E9 60%, #38BDF8 100%);
}

.frm-banner__icon {
  width: 40px; height: 40px; flex-shrink: 0; border-radius: 12px;
  background: rgba(255,255,255,0.2);
  border: 1.5px solid rgba(255,255,255,0.28);
  display: flex; align-items: center; justify-content: center;
}

.frm-banner__text { flex: 1; min-width: 0; }

.frm-banner__label {
  font-size: 0.65rem; font-weight: 600; text-transform: uppercase;
  letter-spacing: 0.07em; color: rgba(255,255,255,0.72); margin: 0 0 2px;
}

.frm-banner__name {
  font-size: 0.95rem; font-weight: 800; color: #fff; margin: 0;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

.frm-banner__close {
  flex-shrink: 0; width: 28px; height: 28px; border-radius: 50%;
  background: rgba(255,255,255,0.18); border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  color: #fff; transition: background 0.15s;
}

.frm-banner__close:hover { background: rgba(255,255,255,0.3); }

.frm-strip {
  display: flex; flex-wrap: wrap;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: #f8fafc;
}

.frm-strip__cell {
  flex: 1; min-width: 80px;
  display: flex; flex-direction: column;
  padding: 8px 14px;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.frm-strip__cell:last-child { border-right: none; }

.frm-strip__lbl {
  font-size: 0.56rem; font-weight: 600; text-transform: uppercase;
  letter-spacing: 0.06em; color: rgba(var(--v-theme-on-surface), 0.35); margin-bottom: 2px;
}

.frm-strip__val { font-size: 0.8rem; font-weight: 600; color: rgba(var(--v-theme-on-surface), 0.85); }

.frm-footer {
  display: flex; gap: 8px; padding: 12px 16px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: #fff;
}

/* ═══════════════════════════════════
   RESPONSIVE
══════════════════════════════════ */
/* Mobile: modal jadi bottom sheet */
@media (max-width: 600px) {
  /* Override Vuetify dialog positioning */
  :deep(.v-overlay__content) {
    align-items: flex-end !important;
    margin: 0 !important;
  }

  :deep(.v-dialog > .v-overlay__content > .v-card) {
    border-radius: 20px 20px 0 0 !important;
    max-height: 92dvh !important;
    width: 100% !important;
    max-width: 100% !important;
  }

  /* Entry lebih longgar di HP */
  .det-entry {
    flex-direction: column;
    gap: 10px;
  }

  .det-entry__acts {
    flex-direction: row;
    width: 100%;
    justify-content: flex-end;
  }

  .det-header__meta { gap: 6px; font-size: 0.7rem; }
  .frm-strip { flex-direction: row; flex-wrap: wrap; }
  .frm-strip__cell { min-width: calc(50% - 1px); }
}
</style>
