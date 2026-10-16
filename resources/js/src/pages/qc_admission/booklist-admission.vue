<script setup>
import { useBooklistAdmissionStore } from '@/stores/useBooklistAdmissionStore'
import BooklistAdmissionFormDialog from '@/views/qc-admission/booklist-admission/BooklistAdmissionFormDialog.vue'
import SummaryCards from '@/components/SummaryCards.vue'
import PageHero from '@/components/PageHero.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import { usePagination } from '@/composables/usePagination'

const store = useBooklistAdmissionStore()

const showForm   = ref(false)
const editItem   = ref(null)
const showDetail = ref(false)
const detailItem = ref(null)
const showDel    = ref(false)
const delTarget  = ref(null)
const loading    = ref(false)
const snackbar   = ref({ show: false, msg: '', color: 'success' })

const search        = ref('')
const filterStatus  = ref(null)  // null | 'Bedah' | 'Non Bedah'

// ── Data ─────────────────────────────────────────────────────────────────────
const records = computed(() => store.records ?? [])

const stats = computed(() => ({
  total:    records.value.length,
  bedah:    records.value.filter(r => r.status_pasien === 'Bedah').length,
  nonBedah: records.value.filter(r => r.status_pasien === 'Non Bedah').length,
  belumStatus: records.value.filter(r => !r.status_pasien).length,
}))

const filtered = computed(() => {
  let d = records.value

  if (filterStatus.value) {
    d = d.filter(r => r.status_pasien === filterStatus.value)
  }

  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    d = d.filter(r =>
      r.nama_pasien?.toLowerCase().includes(q) ||
      r.no_reg?.toLowerCase().includes(q)      ||
      r.no_mr?.toLowerCase().includes(q)       ||
      r.dpjp?.toLowerCase().includes(q)        ||
      r.diagnosa?.toLowerCase().includes(q)
    )
  }

  return d
})

// ── Helpers ──────────────────────────────────────────────────────────────────
function statusColor(s) {
  return { Bedah: 'error', 'Non Bedah': 'success' }[s] ?? 'secondary'
}
function statusIcon(s) {
  return { Bedah: 'ri-surgical-mask-line', 'Non Bedah': 'ri-hospital-line' }[s] ?? 'ri-question-line'
}

// Format YYYY-MM-DD → DD/MM/YYYY untuk display
function fmtTanggal(val) {
  if (!val) return '—'
  // Jika sudah format lama (DD/MM/YYYY), tampilkan langsung
  if (val.includes('/')) return val
  // Format YYYY-MM-DD dari date picker
  const parts = val.split('-')
  if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`
  return val
}

// ── Actions ───────────────────────────────────────────────────────────────────
function openRow(item)   { detailItem.value = item; showDetail.value = true }
function openAdd()       { editItem.value = null; showForm.value = true }
function openEdit(item)  { editItem.value = { ...item }; showDetail.value = false; showForm.value = true }

function toast(msg, color = 'success') { snackbar.value = { show: true, msg, color } }

async function onSaved() {
  showForm.value = false
  toast('Data booklist berhasil disimpan.')
  await load()
}

async function doDel() {
  if (!delTarget.value) return
  loading.value = true
  const result = await store.destroy(delTarget.value.id)
  loading.value = false
  showDel.value    = false
  showDetail.value = false
  delTarget.value  = null
  if (result?.success !== false) {
    toast('Data dihapus.')
  } else {
    toast(result.message ?? 'Gagal menghapus.', 'error')
  }
}

async function load() {
  loading.value = true
  try {
    await store.fetchRecords({ per_page: 500 })
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

// ── Pagination ────────────────────────────────────────────────────────────────
const { page, pageCount, paginated, setPage } = usePagination(filtered, 10)

onMounted(load)
</script>

<template>
  <div>
    <!-- ── Hero ───────────────────────────────────────────────────────────── -->
    <PageHero
      icon="ri-book-mark-line"
      badge="Booklist"
      title="Booklist Admission"
      subtitle="Daftar pasien yang akan masuk rawat inap tanpa menunggu di IGD"
      color-from="#0EA5E9"
      color-to="#0369A1"
      :pills="[
        { icon: 'ri-database-line',           text: `${stats.total} total` },
        { icon: 'ri-surgical-mask-line',      text: `${stats.bedah} Bedah` },
        { icon: 'ri-hospital-line',           text: `${stats.nonBedah} Non Bedah` },
      ]"
    >
      <template #actions>
        <VBtn
          color="white" variant="elevated" rounded="pill" size="small"
          style="color:#0369A1;font-weight:700"
          @click="openAdd"
        >
          <VIcon icon="ri-add-line" size="16" class="me-1" />Tambah Pasien
        </VBtn>
      </template>
    </PageHero>

    <!-- ── Summary cards ─────────────────────────────────────────────────── -->
    <SummaryCards
      v-model="filterStatus"
      :cards="[
        { value: stats.total,       label: 'Total Booklist',  color: 'primary', icon: 'ri-book-mark-line' },
        { value: stats.bedah,       label: 'Bedah',           color: 'error',   icon: 'ri-surgical-mask-line', filterValue: 'Bedah' },
        { value: stats.nonBedah,    label: 'Non Bedah',       color: 'success', icon: 'ri-hospital-line',      filterValue: 'Non Bedah' },
        { value: stats.belumStatus, label: 'Belum ada Status',color: 'warning', icon: 'ri-question-line' },
      ]"
    />

    <!-- ── Toolbar ────────────────────────────────────────────────────────── -->
    <div class="bl-toolbar">
      <div class="bl-search-wrap">
        <VIcon icon="ri-search-line" size="16" class="bl-search-icon" />
        <input
          v-model="search"
          class="bl-search-input"
          placeholder="Cari nama / no reg / no mr / DPJP / diagnosa..."
        />
        <button v-if="search" class="bl-search-clear" @click="search = ''">
          <VIcon icon="ri-close-line" size="14" />
        </button>
      </div>

      <!-- Filter status chip -->
      <VChip
        v-if="filterStatus"
        :color="statusColor(filterStatus)"
        size="small"
        variant="tonal"
        closable
        rounded="pill"
        @click:close="filterStatus = null"
      >
        <VIcon :icon="statusIcon(filterStatus)" size="12" class="me-1" />
        {{ filterStatus }}
      </VChip>

      <button class="bl-icon-btn" :disabled="loading" title="Refresh" @click="load">
        <VIcon
          :icon="loading ? 'ri-loader-4-line' : 'ri-refresh-line'"
          size="16"
          :class="{ spin: loading }"
        />
      </button>
    </div>

    <!-- Info count -->
    <div class="d-flex align-center gap-3 mb-3 flex-wrap">
      <VChip size="small" color="primary" variant="tonal" rounded="pill">
        {{ filtered.length }} data
      </VChip>
      <span class="text-caption" style="color:var(--qc-text-2)">
        Klik baris untuk detail &amp; edit
      </span>
    </div>

    <!-- ── List ──────────────────────────────────────────────────────────── -->
    <div class="bl-list-wrap">

      <!-- Loading -->
      <div v-if="loading" class="bl-state">
        <VProgressCircular indeterminate color="primary" size="30" />
        <span>Memuat data...</span>
      </div>

      <!-- Empty -->
      <div v-else-if="!filtered.length" class="bl-state">
        <VIcon icon="ri-book-mark-line" size="48" style="opacity:.2" />
        <span class="bl-state__title">Belum ada data booklist</span>
        <span class="bl-state__sub">Klik "Tambah Pasien" untuk mendaftarkan pasien ke booklist</span>
        <VBtn color="primary" variant="tonal" rounded="lg" size="small" class="mt-2" @click="openAdd">
          <VIcon icon="ri-add-line" size="15" class="me-1" />Tambah Pasien
        </VBtn>
      </div>

      <!-- Rows -->
      <div v-else>
        <div
          v-for="item in paginated"
          :key="item.id"
          class="bl-row"
          @click="openRow(item)"
        >
          <!-- Avatar -->
          <div class="bl-row__av" :class="item.status_pasien === 'Bedah' ? 'bl-row__av--bedah' : 'bl-row__av--nonbedah'">
            {{ item.nama_pasien?.charAt(0) ?? '?' }}
          </div>

          <!-- Info -->
          <div class="bl-row__info">
            <div class="bl-row__nameline">
              <span class="bl-row__name">{{ item.nama_pasien || '—' }}</span>
              <VChip
                v-if="item.status_pasien"
                :color="statusColor(item.status_pasien)"
                size="x-small"
                variant="tonal"
              >
                <VIcon :icon="statusIcon(item.status_pasien)" size="10" class="me-1" />
                {{ item.status_pasien }}
              </VChip>
            </div>
            <div class="bl-row__meta">
              <span v-if="item.no_mr">No.RM: {{ item.no_mr }}</span>
              <span v-if="item.no_reg">No.Reg: {{ item.no_reg }}</span>
              <span v-if="item.jaminan">{{ item.jaminan }}</span>
              <span v-if="item.kelas">
                <VIcon icon="ri-hotel-bed-line" size="10" />{{ item.kelas }}
              </span>
              <span v-if="item.dpjp">
                <VIcon icon="ri-user-star-line" size="10" />{{ item.dpjp }}
              </span>
              <span v-if="item.diagnosa" class="bl-row__diagnosa">
                <VIcon icon="ri-stethoscope-line" size="10" />{{ item.diagnosa }}
              </span>
            </div>
          </div>

          <!-- Right -->
          <div class="bl-row__right">
            <span v-if="item.tanggal_op" class="bl-row__op">
              <VIcon icon="ri-calendar-event-line" size="11" class="me-1" />{{ fmtTanggal(item.tanggal_op) }}
            </span>
            <span class="bl-row__petugas">
              <VIcon icon="ri-user-3-line" size="11" />{{ item.petugas || '—' }}
            </span>
            <span class="bl-row__tgl">{{ item.tanggal_booklist }}</span>
          </div>
        </div>

        <PaginationBar
          :page="page"
          :page-count="pageCount"
          :total="filtered.length"
          :per-page="10"
          @update:page="setPage"
        />
      </div>
    </div>

    <!-- ── Detail Dialog ─────────────────────────────────────────────────── -->
    <VDialog v-model="showDetail" max-width="460">
      <VCard v-if="detailItem" rounded="xl" class="bl-detail-card overflow-hidden">

        <!-- Banner -->
        <div class="bl-detail-banner">
          <div class="bl-db-blob bl-db-blob--1" />
          <div class="bl-db-blob bl-db-blob--2" />
          <div class="d-flex align-center gap-3" style="position:relative;z-index:2">
            <div class="bl-detail-av">{{ detailItem.nama_pasien?.charAt(0) ?? '?' }}</div>
            <div class="flex-grow-1 min-width-0">
              <p class="bl-detail-name text-truncate">{{ detailItem.nama_pasien }}</p>
              <p class="bl-detail-sub mb-0">
                {{ detailItem.no_reg }}{{ detailItem.no_mr ? ` · MR ${detailItem.no_mr}` : '' }}
              </p>
            </div>
            <button class="bl-detail-close" @click="showDetail = false">
              <VIcon icon="ri-close-line" size="16" />
            </button>
          </div>
          <!-- Chips -->
          <div class="d-flex gap-2 mt-3 flex-wrap" style="position:relative;z-index:2">
            <span v-if="detailItem.status_pasien" class="bl-pill"
              :class="detailItem.status_pasien === 'Bedah' ? 'bl-pill--bedah' : 'bl-pill--nonbedah'">
              <VIcon :icon="statusIcon(detailItem.status_pasien)" size="12" class="me-1" />
              {{ detailItem.status_pasien }}
            </span>
            <span v-if="detailItem.jaminan" class="bl-pill bl-pill--neutral">
              <VIcon icon="ri-shield-user-line" size="12" class="me-1" />{{ detailItem.jaminan }}
            </span>
            <span v-if="detailItem.kelas" class="bl-pill bl-pill--neutral">
              <VIcon icon="ri-hotel-bed-line" size="12" class="me-1" />{{ detailItem.kelas }}
            </span>
          </div>
        </div>

        <!-- Body -->
        <div class="bl-detail-body">
          <div class="bl-info-grid-detail">
            <div class="bl-dc">
              <span class="bl-dc-lbl">No. MR</span>
              <span class="bl-dc-val font-weight-bold">{{ detailItem.no_mr || '—' }}</span>
            </div>
            <div class="bl-dc">
              <span class="bl-dc-lbl">No. Reg</span>
              <span class="bl-dc-val bl-dc-val--mono">{{ detailItem.no_reg || '—' }}</span>
            </div>
            <div class="bl-dc">
              <span class="bl-dc-lbl">No. HP</span>
              <span class="bl-dc-val bl-dc-val--mono">{{ detailItem.no_hp || '—' }}</span>
            </div>
            <div class="bl-dc">
              <span class="bl-dc-lbl">Jaminan</span>
              <span class="bl-dc-val">{{ detailItem.jaminan || '—' }}</span>
            </div>
            <div class="bl-dc">
              <span class="bl-dc-lbl">Kelas</span>
              <span class="bl-dc-val">{{ detailItem.kelas || '—' }}</span>
            </div>
            <div class="bl-dc">
              <span class="bl-dc-lbl">Tgl. Booklist</span>
              <span class="bl-dc-val">{{ detailItem.tanggal_booklist || '—' }}</span>
            </div>
            <div class="bl-dc bl-dc--full">
              <span class="bl-dc-lbl"><VIcon icon="ri-user-star-line" size="10" class="me-1" />DPJP</span>
              <span class="bl-dc-val">{{ detailItem.dpjp || '—' }}</span>
            </div>
            <div class="bl-dc bl-dc--full">
              <span class="bl-dc-lbl"><VIcon icon="ri-stethoscope-line" size="10" class="me-1" />Diagnosa</span>
              <span class="bl-dc-val" style="white-space:pre-wrap">{{ detailItem.diagnosa || '—' }}</span>
            </div>
            <div class="bl-dc">
              <span class="bl-dc-lbl"><VIcon icon="ri-calendar-event-line" size="10" class="me-1" />Tgl. OP / Masuk</span>
              <span class="bl-dc-val font-weight-semibold">{{ fmtTanggal(detailItem.tanggal_op) }}</span>
            </div>
            <div class="bl-dc">
              <span class="bl-dc-lbl">Status Pasien</span>
              <span class="bl-dc-val">
                <VChip
                  v-if="detailItem.status_pasien"
                  :color="statusColor(detailItem.status_pasien)"
                  size="x-small"
                  variant="tonal"
                >
                  {{ detailItem.status_pasien }}
                </VChip>
                <span v-else>—</span>
              </span>
            </div>
            <div class="bl-dc bl-dc--full">
              <span class="bl-dc-lbl"><VIcon icon="ri-sticky-note-line" size="10" class="me-1" />Catatan</span>
              <span class="bl-dc-val" style="white-space:pre-wrap">{{ detailItem.catatan || '—' }}</span>
            </div>
            <div class="bl-dc bl-dc--full">
              <span class="bl-dc-lbl">Petugas</span>
              <span class="bl-dc-val">{{ detailItem.petugas || '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="bl-detail-actions">
          <VBtn variant="outlined" rounded="lg" class="bl-act-btn" @click="showDetail = false">Tutup</VBtn>
          <VBtn color="primary" variant="tonal" rounded="lg" class="bl-act-btn bl-act-btn--grow"
            @click="openEdit(detailItem)">
            <VIcon icon="ri-pencil-line" size="15" class="me-1" />Edit
          </VBtn>
          <VBtn color="error" variant="tonal" rounded="lg" class="bl-act-btn"
            @click="delTarget = detailItem; showDel = true">
            <VIcon icon="ri-delete-bin-line" size="15" />
          </VBtn>
        </div>
      </VCard>
    </VDialog>

    <!-- ── Delete Confirm ────────────────────────────────────────────────── -->
    <VDialog v-model="showDel" max-width="340">
      <VCard rounded="lg">
        <VCardText class="pa-6 text-center">
          <VAvatar color="error" variant="tonal" size="56" rounded="lg" class="mb-3">
            <VIcon icon="ri-delete-bin-2-line" size="26" />
          </VAvatar>
          <p class="text-h6 font-weight-bold mb-1">Hapus Data?</p>
          <p class="text-body-2" style="color:var(--qc-text-2)">
            Data booklist <strong>{{ delTarget?.nama_pasien }}</strong> akan dihapus permanen.
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

    <!-- ── Form Dialog ───────────────────────────────────────────────────── -->
    <BooklistAdmissionFormDialog v-model="showForm" :edit-item="editItem" @saved="onSaved" />

    <!-- Snackbar -->
    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3500" location="bottom right" rounded="xl">
      {{ snackbar.msg }}
      <template #actions>
        <VBtn variant="text" size="small" @click="snackbar.show = false">✕</VBtn>
      </template>
    </VSnackbar>
  </div>
</template>

<style scoped>
/* ── Toolbar ── */
.bl-toolbar {
  display: flex; align-items: center; gap: 8px; padding: 10px 0 8px;
}

.bl-search-wrap {
  flex: 1; display: flex; align-items: center;
  background: #fff;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px; padding: 0 10px; height: 38px;
  transition: border-color .15s, box-shadow .15s;
}
.bl-search-wrap:focus-within {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), .08);
}
.bl-search-icon  { color: rgba(var(--v-theme-on-surface), .3); flex-shrink: 0; }
.bl-search-input {
  flex: 1; border: none; outline: none; background: transparent;
  font-size: .84rem; color: rgba(var(--v-theme-on-surface), .87); padding: 0 8px;
}
.bl-search-input::placeholder { color: rgba(var(--v-theme-on-surface), .3); }
.bl-search-clear {
  background: none; border: none; cursor: pointer;
  color: rgba(var(--v-theme-on-surface), .3);
  padding: 2px; display: flex; align-items: center;
}

.bl-icon-btn {
  width: 38px; height: 38px; flex-shrink: 0;
  border-radius: 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: #fff; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  color: rgba(var(--v-theme-on-surface), .45);
  transition: background .12s, color .12s;
}
.bl-icon-btn:hover:not(:disabled) {
  background: rgba(var(--v-theme-primary), .05);
  color: rgb(var(--v-theme-primary));
}
.spin { animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── List wrapper ── */
.bl-list-wrap {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 14px; background: #fff; overflow: hidden;
}

/* ── State box ── */
.bl-state {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  padding: 56px 16px;
  color: rgba(var(--v-theme-on-surface), .35); font-size: .84rem;
}
.bl-state__title { font-size: .88rem; font-weight: 600; color: rgba(var(--v-theme-on-surface), .5); }
.bl-state__sub   { font-size: .76rem; text-align: center; }

/* ── Row ── */
.bl-row {
  display: flex; align-items: center; gap: 12px;
  padding: 13px 16px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  cursor: pointer;
  transition: background .1s, border-left .1s, padding-left .1s;
}
.bl-row:last-child { border-bottom: none; }
.bl-row:hover {
  background: rgba(15,118,110,.04);
  border-left: 3px solid rgba(15,118,110,.3);
  padding-left: 13px;
}

/* Avatar */
.bl-row__av {
  flex-shrink: 0; width: 40px; height: 40px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 700;
}
.bl-row__av--bedah    { background: rgba(var(--v-theme-error), .1);   color: rgb(var(--v-theme-error)); }
.bl-row__av--nonbedah { background: rgba(var(--v-theme-success), .1); color: rgb(var(--v-theme-success)); }

/* Info */
.bl-row__info    { flex: 1; min-width: 0; }
.bl-row__nameline {
  display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 4px;
}
.bl-row__name {
  font-size: .875rem; font-weight: 600; color: rgba(var(--v-theme-on-surface), .87);
}
.bl-row__meta {
  display: flex; align-items: center; flex-wrap: wrap; gap: 6px;
  font-size: .72rem; color: rgba(var(--v-theme-on-surface), .4);
}
.bl-row__meta span { display: inline-flex; align-items: center; gap: 3px; }
.bl-row__diagnosa  { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Right */
.bl-row__right {
  flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 2px;
}
.bl-row__op {
  display: inline-flex; align-items: center;
  font-size: .72rem; font-weight: 600;
  color: rgb(var(--v-theme-primary));
}
.bl-row__petugas {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: .72rem; color: rgba(var(--v-theme-on-surface), .5);
}
.bl-row__tgl {
  font-size: .7rem; color: rgba(var(--v-theme-on-surface), .35);
}

/* ── Detail dialog ── */
.bl-detail-card { display: flex; flex-direction: column; max-height: 92dvh; }
.bl-detail-body {
  flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain;
}

.bl-detail-banner {
  position: relative; overflow: hidden;
  background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 60%, #60a5fa 100%);
  padding: 20px 20px 16px;
  border-radius: 8px 8px 0 0;
}
.bl-db-blob { position: absolute; border-radius: 50%; background: rgba(255,255,255,.08); }
.bl-db-blob--1 { width: 180px; height: 180px; top: -60px; right: -30px; }
.bl-db-blob--2 { width: 90px;  height: 90px;  bottom: -35px; right: 100px; }

.bl-detail-av {
  width: 52px; height: 52px; border-radius: 10px; flex-shrink: 0;
  background: rgba(255,255,255,.2); border: 2px solid rgba(255,255,255,.35);
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; font-weight: 800; color: #fff;
}
.bl-detail-name { font-size: 1.05rem; font-weight: 700; color: #fff; margin: 0 0 2px; }
.bl-detail-sub  { font-size: .75rem; color: rgba(255,255,255,.75); }
.bl-detail-close {
  background: rgba(255,255,255,.18); border: none; cursor: pointer;
  width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  color: #fff; transition: background .15s;
}
.bl-detail-close:hover { background: rgba(255,255,255,.32); }

.bl-pill {
  display: inline-flex; align-items: center;
  padding: 3px 10px; border-radius: 20px;
  font-size: .72rem; font-weight: 700;
}
.bl-pill--bedah    { background: rgba(239,68,68,.28);  color: #fecaca; }
.bl-pill--nonbedah { background: rgba(34,197,94,.28);  color: #bbf7d0; }
.bl-pill--neutral  { background: rgba(255,255,255,.18); color: rgba(255,255,255,.9); }

/* ── Info grid detail ── */
.bl-info-grid-detail {
  display: grid; grid-template-columns: 1fr 1fr;
  padding: 4px 0;
}
.bl-dc {
  display: flex; flex-direction: column;
  padding: 11px 20px;
  border-bottom: 1px solid var(--qc-border, rgba(0,0,0,.07));
  border-right: 1px solid var(--qc-border, rgba(0,0,0,.07));
}
.bl-dc:nth-child(even) { border-right: none; }
.bl-dc--full { grid-column: span 2; border-right: none; }

.bl-dc-lbl {
  font-size: .63rem; text-transform: uppercase; letter-spacing: .07em;
  color: rgba(var(--v-theme-on-surface), .4); margin-bottom: 2px; font-weight: 600;
  display: flex; align-items: center;
}
.bl-dc-val      { font-size: .875rem; font-weight: 500; color: rgba(var(--v-theme-on-surface), .87); }
.bl-dc-val--mono { font-family: monospace; letter-spacing: .03em; }

/* ── Actions ── */
.bl-detail-actions {
  display: flex; gap: 8px;
  padding: 14px 16px;
  border-top: 1px solid var(--qc-border, rgba(0,0,0,.07));
  flex-wrap: wrap;
}
.bl-act-btn      { min-height: 40px; font-size: .85rem; font-weight: 600; }
.bl-act-btn--grow { flex: 1; }

@media (max-width: 400px) {
  .bl-detail-actions { flex-direction: column; }
  .bl-act-btn { width: 100%; }
}
</style>
