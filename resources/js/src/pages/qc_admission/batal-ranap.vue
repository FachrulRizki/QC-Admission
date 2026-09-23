<script setup>
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'
import BatalRanapFormDialog from '@/views/qc-admission/batal-ranap/BatalRanapFormDialog.vue'
import BatalRanapDetailDialog from '@/views/qc-admission/batal-ranap/BatalRanapDetailDialog.vue'
import SummaryCards from '@/components/SummaryCards.vue'
import PageHero from '@/components/PageHero.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import { usePagination } from '@/composables/usePagination'

const store = useBatalRanapStore()

const showForm   = ref(false)
const editItem   = ref(null)
const showDetail = ref(false)
const detailItem = ref(null)
const loading    = ref(false)
const snackbar   = ref({ show: false, msg: '', color: 'success' })

// ── Tab aktif ─────────────────────────────────────────────────────────────────
// 'pending'  = belum konfirmasi siap closing
// 'closing'  = sudah siap closing
const activeTab = ref('pending')

// ── Search per-tab ────────────────────────────────────────────────────────────
const searchPending = ref('')
const searchClosing = ref('')

// ── Data dari store ───────────────────────────────────────────────────────────
const records = computed(() => store.records ?? [])

// Tab Pending: status_closing != 'Siap Closing' (null / 'Belum Siap Closing')
const pendingRecords = computed(() => {
  let d = records.value.filter(r => r.status_closing !== 'Siap Closing')
  if (searchPending.value.trim()) {
    const q = searchPending.value.toLowerCase()
    d = d.filter(r =>
      r.nama_pasien?.toLowerCase().includes(q) ||
      r.no_reg?.toLowerCase().includes(q) ||
      r.no_mr?.toLowerCase().includes(q) ||
      r.petugas?.toLowerCase().includes(q) ||
      r.keterangan_batal?.toLowerCase().includes(q)
    )
  }
  return d
})

// Tab Closing: status_closing === 'Siap Closing'
const closingRecords = computed(() => {
  let d = records.value.filter(r => r.status_closing === 'Siap Closing')
  if (searchClosing.value.trim()) {
    const q = searchClosing.value.toLowerCase()
    d = d.filter(r =>
      r.nama_pasien?.toLowerCase().includes(q) ||
      r.no_reg?.toLowerCase().includes(q) ||
      r.no_mr?.toLowerCase().includes(q) ||
      r.petugas?.toLowerCase().includes(q) ||
      r.keterangan_batal?.toLowerCase().includes(q)
    )
  }
  return d
})

const stats = computed(() => ({
  total:     records.value.length,
  pending:   records.value.filter(r => r.status_closing !== 'Siap Closing').length,
  closing:   records.value.filter(r => r.status_closing === 'Siap Closing').length,
  belumSiap: records.value.filter(r => r.status_closing === 'Belum Siap Closing').length,
}))

// ── Helpers warna ─────────────────────────────────────────────────────────────
function statusOkColor(s) {
  return { Bedah: 'success', 'Non Bedah': 'info' }[s] ?? 'secondary'
}
function statusOkLabel(s) { return s || 'Belum Verifikasi' }
function closingColor(s) {
  return s === 'Siap Closing' ? 'success' : s === 'Belum Siap Closing' ? 'error' : 'secondary'
}

// ── Actions ───────────────────────────────────────────────────────────────────
function openRow(item) { detailItem.value = item; showDetail.value = true }
function openAdd()     { editItem.value = null; showForm.value = true }
function openEdit(item) {
  if (item.status_closing === 'Siap Closing') {
    snackbar.value = { show: true, msg: 'Data sudah "Siap Closing" dan tidak dapat diedit.', color: 'warning' }
    return
  }
  editItem.value = { ...item }; showDetail.value = false; showForm.value = true
}

function toast(msg, color = 'success') { snackbar.value = { show: true, msg, color } }

async function onSaved() {
  showForm.value = false
  toast('Data disimpan.')
  await load()
}

async function onVerified(result) {
  if (result?.bedTriggered === true) {
    toast(`✅ Siap Closing tersimpan. Bed ${result.kodeBed} berhasil dibebaskan via ${result.source}.`, 'success')
    // Pindah ke tab closing otomatis
    activeTab.value = 'closing'
  } else if (result?.bedTriggered === false) {
    toast('✅ Siap Closing tersimpan. Catatan: bed IGD belum dapat dibebaskan otomatis, harap informasikan ke petugas IT.', 'warning')
    activeTab.value = 'closing'
  } else if (result?.bedTriggered === null) {
    toast('✅ Siap Closing tersimpan. Pasien tidak memiliki bed IGD (menunggu di rumah).', 'success')
    activeTab.value = 'closing'
  } else {
    toast('Verifikasi disimpan.')
  }
  await load()
}

async function load() {
  loading.value = true
  try {
    await store.fetchRecords({ per_page: 500 })
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

// ── Pagination — dua tab terpisah ─────────────────────────────────────────────
const { page: pagePending, pageCount: pageCountPending, paginated: paginatedPending, setPage: setPagePending }
  = usePagination(pendingRecords, 10)
const { page: pageClosing, pageCount: pageCountClosing, paginated: paginatedClosing, setPage: setPageClosing }
  = usePagination(closingRecords, 10)

onMounted(load)
</script>

<template>
  <div>
    <!-- ── Hero ─────────────────────────────────────────────────────────────── -->
    <PageHero
      icon="ri-close-circle-line"
      badge="Batal Ranap"
      title="Batal Ranap"
      subtitle="Kelola pembatalan rawat inap & konfirmasi siap closing"
      color-from="#0EA5E9"
      color-to="#0369A1"
      :pills="[
        { icon: 'ri-database-line',      text: `${stats.total} total` },
        { icon: 'ri-time-line',          text: `${stats.pending} menunggu` },
        { icon: 'ri-checkbox-circle-line', text: `${stats.closing} siap closing` },
      ]"
    >
      <template #actions>
        <VBtn
          color="white" variant="elevated" rounded="pill" size="small"
          style="color:#0369A1;font-weight:700"
          @click="openAdd"
        >
          <VIcon icon="ri-add-line" size="16" class="me-1" />Input Batal Ranap
        </VBtn>
      </template>
    </PageHero>

    <!-- ── Summary cards ─────────────────────────────────────────────────────── -->
    <SummaryCards
      :model-value="null"
      :cards="[
        { value: stats.total,     label: 'Total',              color: 'primary', icon: 'ri-close-circle-line' },
        { value: stats.pending,   label: 'Belum Konfirmasi',   color: 'warning', icon: 'ri-time-line' },
        { value: stats.closing,   label: 'Siap Closing',       color: 'success', icon: 'ri-checkbox-circle-line' },
        { value: stats.belumSiap, label: 'Belum Siap Closing', color: 'error',   icon: 'ri-close-circle-line' },
      ]"
    />

    <!-- ── Tab bar ────────────────────────────────────────────────────────────── -->
    <div class="br-tabs">
      <button
        class="br-tab"
        :class="{ 'br-tab--active': activeTab === 'pending' }"
        @click="activeTab = 'pending'"
      >
        <VIcon icon="ri-time-line" size="15" />
        Belum Konfirmasi
        <span class="br-tab__badge" :class="{ 'br-tab__badge--active': activeTab === 'pending' }">
          {{ stats.pending }}
        </span>
      </button>
      <button
        class="br-tab"
        :class="{ 'br-tab--active': activeTab === 'closing' }"
        @click="activeTab = 'closing'"
      >
        <VIcon icon="ri-checkbox-circle-line" size="15" />
        Siap Closing
        <span class="br-tab__badge br-tab__badge--green" :class="{ 'br-tab__badge--active-green': activeTab === 'closing' }">
          {{ stats.closing }}
        </span>
      </button>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         TAB: BELUM KONFIRMASI
    ═══════════════════════════════════════════════════════════════ -->
    <div v-show="activeTab === 'pending'">
      <!-- Toolbar -->
      <div class="br-toolbar">
        <div class="br-search-wrap">
          <VIcon icon="ri-search-line" size="16" class="br-search-icon" />
          <input
            v-model="searchPending"
            class="br-search-input"
            placeholder="Cari nama / no reg / keterangan..."
          />
          <button v-if="searchPending" class="br-search-clear" @click="searchPending = ''">
            <VIcon icon="ri-close-line" size="14" />
          </button>
        </div>
        <button class="br-icon-btn" :disabled="loading" title="Refresh" @click="load">
          <VIcon
            :icon="loading ? 'ri-loader-4-line' : 'ri-refresh-line'"
            size="16"
            :class="{ spin: loading }"
          />
        </button>
      </div>

      <!-- Info count -->
      <div class="d-flex align-center gap-3 mb-3 flex-wrap">
        <VChip size="small" color="warning" variant="tonal" rounded="pill">
          {{ pendingRecords.length }} data
        </VChip>
        <span class="text-caption" style="color:var(--qc-text-2)">
          Klik pasien untuk detail & konfirmasi siap closing
        </span>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="br-state-box">
        <VProgressCircular indeterminate color="warning" size="30" />
        <span>Memuat data...</span>
      </div>

      <!-- Empty -->
      <div v-else-if="!pendingRecords.length" class="br-state-box">
        <VIcon icon="ri-checkbox-circle-line" size="48" style="color:#10B981;opacity:.5" />
        <span class="br-state-box__title">Semua sudah dikonfirmasi</span>
        <span class="br-state-box__sub">Tidak ada data yang menunggu konfirmasi closing</span>
        <VBtn color="error" variant="tonal" rounded="lg" size="small" class="mt-2" @click="openAdd">
          + Input Batal Ranap
        </VBtn>
      </div>

      <!-- List -->
      <div v-else class="br-list">
        <div
          v-for="item in paginatedPending"
          :key="item.id"
          class="br-row"
          @click="openRow(item)"
        >
          <!-- Avatar -->
          <div class="br-row__av">
            {{ item.nama_pasien?.charAt(0) ?? '?' }}
          </div>

          <!-- Info -->
          <div class="br-row__info">
            <div class="br-row__nameline">
              <span class="br-row__name">{{ item.nama_pasien }}</span>
              <VChip :color="statusOkColor(item.status_ok)" size="x-small" variant="tonal">
                {{ statusOkLabel(item.status_ok) }}
              </VChip>
              <VChip
                v-if="item.status_closing"
                :color="closingColor(item.status_closing)"
                size="x-small"
                variant="tonal"
              >
                {{ item.status_closing }}
              </VChip>
            </div>
            <div class="br-row__meta">
              <span>{{ item.no_reg }}</span>
              <span v-if="item.jaminan">{{ item.jaminan }}</span>
              <span v-if="item.keterangan_batal">
                <VIcon icon="ri-error-warning-line" size="10" />{{ item.keterangan_batal }}
              </span>
              <span v-if="item.bed_id">
                <VIcon icon="ri-hotel-bed-line" size="10" />Bed: {{ item.bed_id }}
              </span>
            </div>
          </div>

          <!-- Right -->
          <div class="br-row__right">
            <span class="br-row__petugas">
              <VIcon icon="ri-user-3-line" size="11" />{{ item.petugas || '—' }}
            </span>
            <span class="br-row__tgl">{{ item.tanggal }}</span>
          </div>
        </div>
        <PaginationBar :page="pagePending" :page-count="pageCountPending" :total="pendingRecords.length" :per-page="10"
          @update:page="setPagePending" />
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         TAB: SIAP CLOSING
    ═══════════════════════════════════════════════════════════════ -->
    <div v-show="activeTab === 'closing'">
      <!-- Toolbar -->
      <div class="br-toolbar">
        <div class="br-search-wrap">
          <VIcon icon="ri-search-line" size="16" class="br-search-icon" />
          <input
            v-model="searchClosing"
            class="br-search-input"
            placeholder="Cari nama / no reg / keterangan..."
          />
          <button v-if="searchClosing" class="br-search-clear" @click="searchClosing = ''">
            <VIcon icon="ri-close-line" size="14" />
          </button>
        </div>
        <button class="br-icon-btn" :disabled="loading" title="Refresh" @click="load">
          <VIcon
            :icon="loading ? 'ri-loader-4-line' : 'ri-refresh-line'"
            size="16"
            :class="{ spin: loading }"
          />
        </button>
      </div>

      <!-- Info count -->
      <div class="d-flex align-center gap-3 mb-3 flex-wrap">
        <VChip size="small" color="success" variant="tonal" rounded="pill">
          {{ closingRecords.length }} data
        </VChip>
        <span class="text-caption" style="color:var(--qc-text-2)">
          Data pasien yang sudah dikonfirmasi siap closing
        </span>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="br-state-box">
        <VProgressCircular indeterminate color="success" size="30" />
        <span>Memuat data...</span>
      </div>

      <!-- Empty -->
      <div v-else-if="!closingRecords.length" class="br-state-box">
        <VIcon icon="ri-inbox-line" size="48" style="opacity:.18" />
        <span class="br-state-box__title">Belum ada data siap closing</span>
        <span class="br-state-box__sub">Data akan muncul setelah dikonfirmasi dari tab Belum Konfirmasi</span>
      </div>

      <!-- List -->
      <div v-else class="br-list br-list--closing">
        <div
          v-for="item in paginatedClosing"
          :key="item.id"
          class="br-row br-row--closing"
          @click="openRow(item)"
        >
          <!-- Avatar -->
          <div class="br-row__av br-row__av--closing">
            {{ item.nama_pasien?.charAt(0) ?? '?' }}
            <span class="br-row__dot-ok" />
          </div>

          <!-- Info -->
          <div class="br-row__info">
            <div class="br-row__nameline">
              <span class="br-row__name">{{ item.nama_pasien }}</span>
              <VChip color="success" size="x-small" variant="tonal">
                <VIcon icon="ri-lock-line" size="10" class="me-1" />Siap Closing
              </VChip>
              <VChip :color="statusOkColor(item.status_ok)" size="x-small" variant="tonal">
                {{ statusOkLabel(item.status_ok) }}
              </VChip>
            </div>
            <div class="br-row__meta">
              <span>{{ item.no_reg }}</span>
              <span v-if="item.jaminan">{{ item.jaminan }}</span>
              <span v-if="item.keterangan_batal">
                <VIcon icon="ri-error-warning-line" size="10" />{{ item.keterangan_batal }}
              </span>
              <span v-if="item.bed_id">
                <VIcon icon="ri-hotel-bed-line" size="10" />Bed: {{ item.bed_id }}
              </span>
            </div>
          </div>

          <!-- Right -->
          <div class="br-row__right">
            <span class="br-row__petugas">
              <VIcon icon="ri-user-3-line" size="11" />{{ item.petugas || '—' }}
            </span>
            <span class="br-row__tgl">{{ item.tanggal }}</span>
          </div>
        </div>
        <PaginationBar :page="pageClosing" :page-count="pageCountClosing" :total="closingRecords.length" :per-page="10"
          @update:page="setPageClosing" />
      </div>
    </div>

    <!-- ── Dialogs ─────────────────────────────────────────────────────────── -->
    <BatalRanapFormDialog v-model="showForm" :edit-item="editItem" @saved="onSaved" />
    <BatalRanapDetailDialog v-model="showDetail" :item="detailItem" @verified="onVerified" @edit="openEdit" />

    <!-- Snackbar -->
    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000" location="bottom right" rounded="xl">
      {{ snackbar.msg }}
      <template #actions>
        <VBtn variant="text" size="small" @click="snackbar.show = false">✕</VBtn>
      </template>
    </VSnackbar>
  </div>
</template>

<style scoped>
/* ═══════════════════════════════════
   TAB BAR
══════════════════════════════════ */
.br-tabs {
  display: flex;
  background: #fff;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 14px 14px 0 0;
  border-bottom: none;
  overflow: hidden;
}

.br-tab {
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

.br-tab + .br-tab {
  border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.br-tab:hover:not(.br-tab--active) {
  background: rgba(var(--v-theme-on-surface), 0.03);
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.br-tab--active {
  color: rgb(var(--v-theme-warning));
  font-weight: 700;
  border-bottom-color: rgb(var(--v-theme-warning));
  background: rgba(var(--v-theme-warning), 0.03);
}

.br-tab:last-child.br-tab--active {
  color: rgb(var(--v-theme-success));
  border-bottom-color: rgb(var(--v-theme-success));
  background: rgba(var(--v-theme-success), 0.03);
}

.br-tab__badge {
  padding: 1px 7px;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 700;
  background: rgba(var(--v-theme-on-surface), 0.07);
  color: rgba(var(--v-theme-on-surface), 0.45);
  transition: background 0.15s, color 0.15s;
}

.br-tab__badge--active {
  background: rgba(var(--v-theme-warning), 0.15);
  color: rgb(var(--v-theme-warning));
}

.br-tab__badge--green {
  background: rgba(16, 185, 129, 0.08);
  color: #059669;
}

.br-tab__badge--active-green {
  background: rgba(16, 185, 129, 0.2);
  color: #059669;
}

/* ═══════════════════════════════════
   TOOLBAR
══════════════════════════════════ */
.br-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 0 8px;
}

.br-search-wrap {
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

.br-search-wrap:focus-within {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.08);
}

.br-search-icon  { color: rgba(var(--v-theme-on-surface), 0.3); flex-shrink: 0; }

.br-search-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-size: 0.84rem;
  color: rgba(var(--v-theme-on-surface), 0.87);
  padding: 0 8px;
}
.br-search-input::placeholder { color: rgba(var(--v-theme-on-surface), 0.3); }

.br-search-clear {
  background: none; border: none; cursor: pointer;
  color: rgba(var(--v-theme-on-surface), 0.3);
  padding: 2px; display: flex; align-items: center;
}

.br-icon-btn {
  width: 38px; height: 38px; flex-shrink: 0;
  border-radius: 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: #fff; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  color: rgba(var(--v-theme-on-surface), 0.45);
  transition: background 0.12s, color 0.12s;
}
.br-icon-btn:hover:not(:disabled) {
  background: rgba(var(--v-theme-primary), 0.05);
  color: rgb(var(--v-theme-primary));
}

.spin { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ═══════════════════════════════════
   STATE BOX
══════════════════════════════════ */
.br-state-box {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  padding: 56px 16px;
  background: #fff;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-top: none;
  border-radius: 0 0 14px 14px;
  color: rgba(var(--v-theme-on-surface), 0.35);
  font-size: 0.84rem;
}
.br-state-box__title { font-size: 0.88rem; font-weight: 600; color: rgba(var(--v-theme-on-surface), 0.5); }
.br-state-box__sub   { font-size: 0.76rem; text-align: center; }

/* ═══════════════════════════════════
   LIST
══════════════════════════════════ */
.br-list {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-top: none;
  border-radius: 0 0 14px 14px;
  background: #fff;
  overflow: hidden;
}

.br-list--closing {
  border-color: rgba(16, 185, 129, 0.2);
}

/* ── Row ── */
.br-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 13px 16px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  cursor: pointer;
  transition: background 0.1s, padding-left 0.1s, border-left 0.1s;
}
.br-row:last-child { border-bottom: none; }
.br-row:hover {
  background: rgba(239, 68, 68, 0.04);
  border-left: 3px solid rgba(239, 68, 68, 0.3);
  padding-left: 13px;
}

.br-row--closing { background: rgba(16, 185, 129, 0.02); }
.br-row--closing:hover {
  background: rgba(16, 185, 129, 0.06) !important;
  border-left-color: rgba(16, 185, 129, 0.4) !important;
}

/* Avatar */
.br-row__av {
  position: relative;
  flex-shrink: 0;
  width: 40px; height: 40px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 700;
  background: rgba(var(--v-theme-error), 0.1);
  color: rgb(var(--v-theme-error));
}

.br-row__av--closing {
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
}

.br-row__dot-ok {
  position: absolute; bottom: -2px; right: -2px;
  width: 11px; height: 11px; border-radius: 50%;
  background: #10B981;
  border: 2px solid #fff;
}

/* Info */
.br-row__info    { flex: 1; min-width: 0; }

.br-row__nameline {
  display: flex; align-items: center; gap: 6px;
  flex-wrap: wrap; margin-bottom: 4px;
}
.br-row__name {
  font-size: 0.875rem; font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.87);
}

.br-row__meta {
  display: flex; align-items: center; flex-wrap: wrap; gap: 6px;
  font-size: 0.72rem; color: rgba(var(--v-theme-on-surface), 0.4);
}
.br-row__meta span { display: inline-flex; align-items: center; gap: 3px; }

/* Right */
.br-row__right  {
  flex-shrink: 0;
  display: flex; flex-direction: column; align-items: flex-end; gap: 2px;
}
.br-row__petugas {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 0.72rem; color: rgba(var(--v-theme-on-surface), 0.5);
}
.br-row__tgl {
  font-size: 0.7rem; color: rgba(var(--v-theme-on-surface), 0.35);
}
</style>
