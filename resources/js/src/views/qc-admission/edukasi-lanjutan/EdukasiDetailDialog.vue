<script setup>
import SignaturePad                from '@/components/SignaturePad.vue'
import { useEdukasiLanjutanStore } from '@/stores/useEdukasiLanjutanStore'
import { usePegawaiStore }         from '@/stores/usePegawaiStore'
import { usePasienStore }          from '@/stores/usePasienStore'
import { useMasterDataStore }      from '@/stores/useMasterDataStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  patient:    { type: Object, default: null },
  mode:       { type: String, default: 'view' },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store        = useEdukasiLanjutanStore()
const pegawaiStore = usePegawaiStore()
const pasienStore  = usePasienStore()
const masterStore  = useMasterDataStore()

const tabView    = ref('riwayat')
const form       = ref({})
const history    = ref([])
const errorMsg   = ref('')
const successMsg = ref('')

watch(() => props.modelValue, async (open) => {
  if (open && props.patient) {
    tabView.value    = props.mode === 'edit' ? 'baru' : 'riwayat'
    errorMsg.value   = ''
    successMsg.value = ''
    resetForm()
    pegawaiStore.fetch()
    masterStore.fetch()
    loadHistory()
  }
})
watch(() => props.mode, m => { if (m === 'edit') tabView.value = 'baru' })

function resetForm() {
  const now      = new Date()
  const bulanMap = ['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI',
    'JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER']
  const p       = n => String(n).padStart(2, '0')
  const fmt     = d => `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()}, ${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
  const fmtTime = d => `${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`

  form.value = {
    no_mr:               props.patient?.no_mr        ?? '',
    no_reg:              props.patient?.no_reg        ?? '',
    nama_pasien:         props.patient?.nama_pasien   ?? '',
    jaminan:             props.patient?.jaminan       ?? '',
    tanggal:             fmt(now),
    jam_input:           fmtTime(now),
    bulan:               bulanMap[now.getMonth()],
    edukasi_kamar:       props.patient?.edukasi_kamar ?? '',
    note:                null,
    petugas:             null,
    keluarga_pasien:     props.patient?.keluarga_pasien ?? '',
    ttd_keluarga_pasien: '',
    status:              'Menunggu',
    quality_control_id:  props.patient?.quality_control_id ?? null,
  }
}

function loadHistory() {
  if (!props.patient?.no_mr) { history.value = []; return }
  const all = store.records ?? []
  history.value = [...all.filter(r => r.no_mr === props.patient.no_mr)]
    .sort((a, b) => new Date(b.created_at ?? 0) - new Date(a.created_at ?? 0))
}

const sesiCount = computed(() => history.value.length)

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.petugas)                 { errorMsg.value = 'Petugas wajib dipilih.'; return }
  if (!form.value.keluarga_pasien?.trim()) { errorMsg.value = 'Nama keluarga pasien wajib diisi.'; return }

  const result = await store.store(form.value)
  if (result?.success) {
    successMsg.value = 'Edukasi lanjutan baru berhasil dicatat!'
    emit('saved')
    setTimeout(() => close(), 700)
  } else {
    errorMsg.value = result?.message ?? 'Gagal menyimpan.'
  }
}

function close() { emit('update:modelValue', false) }

function statusColor(s) {
  return { Selesai: 'success', Menunggu: 'warning' }[s] ?? 'secondary'
}
function fmtDate(d) {
  if (!d) return '—'
  try { return new Date(d).toLocaleString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) }
  catch { return d }
}
</script>

<template>
  <VDialog :model-value="modelValue" max-width="620" persistent scrollable @update:model-value="close">
    <VCard v-if="patient" rounded="xl" class="overflow-hidden">

      <!-- ── Gradient Banner ──────────────────────────────────────────────── -->
      <div class="modal-banner">
        <div class="blob blob-1" />
        <div class="blob blob-2" />
        <div class="blob blob-3" />
        <div class="banner-inner">
          <!-- Avatar -->
          <div class="banner-avatar">
            <span class="banner-avatar__letter">{{ patient.nama_pasien?.charAt(0) ?? '?' }}</span>
          </div>
          <!-- Info -->
          <div class="banner-info">
            <p class="banner-date">{{ patient.tanggal || '—' }}</p>
            <h2 class="banner-name">{{ patient.nama_pasien }}</h2>
            <p class="banner-sub">MR: {{ patient.no_mr }} · {{ patient.jaminan || '—' }}</p>
          </div>
          <!-- Badges di kanan atas, close di pojok -->
          <div class="banner-right">
            <div class="banner-badges">
              <span class="badge-pill badge-pill--sesi">{{ sesiCount }} sesi</span>
              <span class="badge-pill mt-1" :class="patient.status === 'Selesai' ? 'badge-pill--success' : 'badge-pill--muted'">
                {{ patient.status || 'Menunggu' }}
              </span>
            </div>
            <button class="banner-close" @click="close">
              <VIcon icon="ri-close-line" size="16" />
            </button>
          </div>
        </div>
      </div>

      <!-- ── Tab bar responsif — custom (bukan VTabs) ─────────────────────── -->
      <div class="modal-tab-bar">
        <button
          class="modal-tab"
          :class="tabView === 'riwayat' ? 'modal-tab--active' : ''"
          @click="tabView = 'riwayat'"
        >
          <VIcon icon="ri-history-line" size="14" class="me-1" />
          <span>Riwayat</span>
          <span class="modal-tab__badge">{{ sesiCount }}</span>
        </button>
        <button
          class="modal-tab"
          :class="tabView === 'baru' ? 'modal-tab--active' : ''"
          @click="tabView = 'baru'"
        >
          <VIcon icon="ri-add-circle-line" size="14" class="me-1" />
          <span>Edukasi Baru</span>
        </button>
      </div>

      <VCardText class="pa-5">
        <VAlert v-if="errorMsg"   type="error"   variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg=''">{{ errorMsg }}</VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">{{ successMsg }}</VAlert>

        <!-- ═══ TAB: RIWAYAT ═══════════════════════════════════════════════ -->
        <template v-if="tabView === 'riwayat'">
          <!-- Info pasien ringkas -->
          <div class="info-grid mb-4">
            <div class="info-cell">
              <span class="ic-lbl">No. MR</span>
              <span class="ic-val fw text-primary">{{ patient.no_mr || '—' }}</span>
            </div>
            <div class="info-cell">
              <span class="ic-lbl">No. Reg</span>
              <span class="ic-val">{{ patient.no_reg || '—' }}</span>
            </div>
            <div class="info-cell">
              <span class="ic-lbl">Jaminan</span>
              <span class="ic-val">{{ patient.jaminan || '—' }}</span>
            </div>
            <div class="info-cell">
              <span class="ic-lbl">Total Sesi</span>
              <VChip color="warning" variant="tonal" size="x-small" class="mt-1">{{ sesiCount }} kali</VChip>
            </div>
          </div>

          <!-- Timeline riwayat -->
          <template v-if="history.length">
            <p class="sec-label mb-3">Riwayat Edukasi Lanjutan</p>
            <div
              v-for="(h, idx) in history" :key="h.id"
              class="history-item pa-3 rounded-xl mb-3"
              :class="idx === 0 ? 'history-item--latest' : ''"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <div class="d-flex align-center gap-2 flex-wrap">
                  <VChip color="warning" variant="tonal" size="x-small">#{{ history.length - idx }}</VChip>
                  <span class="text-caption font-weight-semibold">{{ h.tanggal }}</span>
                  <span class="text-caption text-medium-emphasis">· {{ h.bulan }}</span>
                </div>
                <VChip :color="statusColor(h.status)" size="x-small" variant="tonal">{{ h.status }}</VChip>
              </div>
              <div class="ig-grid">
                <div class="ig-cell">
                  <span class="ic-lbl">Petugas</span>
                  <span class="ic-val">{{ h.petugas || '—' }}</span>
                </div>
                <div class="ig-cell">
                  <span class="ic-lbl">Ruangan Edukasi</span>
                  <span class="ic-val">{{ h.edukasi_kamar || '—' }}</span>
                </div>
                <div class="ig-cell ig-cell--full">
                  <span class="ic-lbl">Note</span>
                  <span class="ic-val">{{ h.note || '—' }}</span>
                </div>
                <div class="ig-cell">
                  <span class="ic-lbl">Keluarga Pasien</span>
                  <span class="ic-val">{{ h.keluarga_pasien || '—' }}</span>
                </div>
                <div class="ig-cell">
                  <span class="ic-lbl">TTD</span>
                  <img v-if="h.ttd_keluarga_pasien" :src="h.ttd_keluarga_pasien" alt="TTD"
                    style="height:32px;border:1px solid #eee;border-radius:4px;margin-top:2px" />
                  <span v-else class="ic-val text-medium-emphasis">—</span>
                </div>
                <div class="ig-cell ig-cell--full">
                  <span class="ic-lbl">Waktu Input</span>
                  <span class="ic-val text-caption text-medium-emphasis">{{ fmtDate(h.created_at) }}</span>
                </div>
              </div>
            </div>
          </template>

          <div v-else class="text-center py-8 text-medium-emphasis">
            <VIcon icon="ri-book-open-line" size="44" class="mb-2 opacity-40" />
            <p class="text-body-2 mb-3">Belum ada riwayat edukasi lanjutan</p>
            <VBtn color="warning" variant="tonal" size="small" rounded="lg" @click="tabView = 'baru'">
              <VIcon icon="ri-add-line" class="me-1" />Mulai Edukasi Lanjutan
            </VBtn>
          </div>
        </template>

        <!-- ═══ TAB: FORM BARU ═════════════════════════════════════════════ -->
        <template v-else>
          <VAlert type="info" variant="tonal" density="compact" border="start" class="mb-4">
            <div class="text-caption">
              Setiap klik <strong>Simpan</strong> akan membuat <strong>catatan sesi baru</strong>.
              Riwayat semua sesi bisa dilihat di tab Riwayat.
            </div>
          </VAlert>

          <div class="form-section mb-4">
            <p class="sec-label mb-2">Waktu Input</p>
            <VRow dense>
              <VCol cols="7">
                <VTextField v-model="form.tanggal" label="Tanggal" variant="outlined" density="compact"
                  readonly bg-color="grey-lighten-5" prepend-inner-icon="ri-calendar-line" />
              </VCol>
              <VCol cols="5">
                <VTextField v-model="form.jam_input" label="Jam" variant="outlined" density="compact"
                  readonly bg-color="grey-lighten-5" prepend-inner-icon="ri-time-line" />
              </VCol>
            </VRow>
          </div>

          <div class="form-section mb-4">
            <p class="sec-label mb-2">Data Pasien (dari QC)</p>
            <VRow dense>
              <VCol cols="4">
                <VTextField v-model="form.no_mr" label="No. MR" variant="outlined" density="compact"
                  readonly bg-color="grey-lighten-5" />
              </VCol>
              <VCol cols="8">
                <VTextField v-model="form.nama_pasien" label="Nama Pasien" variant="outlined" density="compact"
                  readonly bg-color="grey-lighten-5" />
              </VCol>
            </VRow>
            <VRow dense class="mt-2">
              <VCol cols="5">
                <VTextField v-model="form.jaminan" label="Jaminan" variant="outlined" density="compact"
                  readonly bg-color="grey-lighten-5" />
              </VCol>
              <VCol cols="7">
                <VTextField v-model="form.bulan" label="Bulan" variant="outlined" density="compact"
                  readonly bg-color="grey-lighten-5" />
              </VCol>
            </VRow>
          </div>

          <div class="form-section mb-4">
            <p class="sec-label mb-2">Data Edukasi Lanjutan</p>
            <VTextField
              v-model="form.edukasi_kamar"
              label="Edukasi Kamar / Ruangan"
              variant="outlined" density="compact"
              prepend-inner-icon="ri-hospital-line"
              class="mb-3"
            />
            <VRow dense>
              <VCol cols="6">
                <VSelect
                  v-model="form.note"
                  :items="masterStore.noteKamarList"
                  label="Note Kamar"
                  variant="outlined" density="compact"
                  clearable
                />
              </VCol>
              <VCol cols="6">
                <VAutocomplete
                  v-model="form.petugas"
                  :items="pegawaiStore.namaList"
                  label="Petugas *"
                  variant="outlined" density="compact"
                  prepend-inner-icon="ri-nurse-line"
                  clearable
                  :loading="pegawaiStore.loading"
                  no-data-text="Memuat petugas..."
                />
              </VCol>
            </VRow>
            <div class="mt-3 d-flex align-center gap-2 px-3 py-2 rounded-lg"
              style="background:rgba(var(--v-theme-warning),0.06);border:1px solid rgba(var(--v-theme-warning),0.25)">
              <VIcon icon="ri-book-open-line" size="16" color="warning" />
              <span class="text-caption text-medium-emphasis">Status:</span>
              <VChip color="warning" variant="tonal" size="small" label>Lanjut Edukasi</VChip>
              <span class="text-caption text-disabled">· Menunggu hingga dapat bed</span>
            </div>
          </div>

          <div class="form-section">
            <p class="sec-label mb-2">Keluarga & Tanda Tangan</p>
            <VTextField
              v-model="form.keluarga_pasien"
              label="Nama Keluarga Pasien *"
              variant="outlined" density="compact"
              prepend-inner-icon="ri-group-line"
              class="mb-3"
            />
            <SignaturePad v-model="form.ttd_keluarga_pasien" label="Tanda Tangan Keluarga Pasien" :height="150" />
          </div>
        </template>
      </VCardText>

      <!-- ── Actions ──────────────────────────────────────────────────────── -->
      <VDivider />
      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="close">Tutup</VBtn>
        <template v-if="tabView === 'riwayat'">
          <VBtn color="warning" variant="tonal" rounded="lg" prepend-icon="ri-add-circle-line" @click="tabView = 'baru'">
            Tambah Sesi
          </VBtn>
        </template>
        <template v-else>
          <VBtn color="warning" rounded="xl" class="flex-grow-1" prepend-icon="ri-save-line"
            :loading="store.loading" @click="handleSave">
            Simpan Sesi Edukasi Baru
          </VBtn>
        </template>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
/* ── Gradient Banner ──────────────────────────────────────────────────── */
.modal-banner {
  position: relative;
  background: linear-gradient(135deg, #0EA5E9 0%, #0EA5E9 55%, #0EA5E9 100%);
  padding: 16px 16px 14px;
  overflow: hidden;
}
.blob { position: absolute; border-radius: 50%; background: #fff; }
.blob-1 { width: 160px; height: 160px; opacity: 0.12; top: -50px; right: -20px; }
.blob-2 { width: 80px;  height: 80px;  opacity: 0.10; bottom: -25px; right: 70px; }
.blob-3 { width: 50px;  height: 50px;  opacity: 0.07; top: 8px; left: 160px; }

/* Banner row: avatar | info | badges+close */
.banner-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center; gap: 12px;
}
.banner-avatar {
  width: 46px; height: 46px; flex-shrink: 0; border-radius: 50%;
  background: rgba(255,255,255,0.25); border: 2.5px solid rgba(255,255,255,0.5);
  display: flex; align-items: center; justify-content: center;
}
.banner-avatar__letter { font-size: 18px; font-weight: 800; color: #fff; }

.banner-info { flex: 1; min-width: 0; overflow: hidden; }
.banner-date  { font-size: 0.62rem; color: rgba(255,255,255,0.72); margin: 0 0 1px; }
.banner-name  {
  font-size: 0.92rem; font-weight: 800; color: #fff; margin: 0 0 2px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.banner-sub   { font-size: 0.65rem; color: rgba(255,255,255,0.78); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Kanan: badges di atas, tombol close di bawah */
.banner-right {
  flex-shrink: 0;
  display: flex; flex-direction: column; align-items: flex-end; gap: 5px;
}
.banner-badges { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; }
.badge-pill {
  font-size: 0.6rem; font-weight: 700;
  padding: 2px 7px; border-radius: 20px;
  background: rgba(255,255,255,0.22); color: #fff;
  display: inline-flex; align-items: center; white-space: nowrap;
}
.badge-pill--success { background: rgba(16,185,129,0.85); }
.badge-pill--muted   { background: rgba(0,0,0,0.18); }
.badge-pill--sesi    { background: rgba(0,0,0,0.14); }

.banner-close {
  background: rgba(255,255,255,0.18); border: none; cursor: pointer;
  width: 26px; height: 26px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff; transition: background 0.15s;
}
.banner-close:hover { background: rgba(255,255,255,0.35); }

/* ── Custom Tab Bar — responsif, tidak terpotong ────────────────────── */
.modal-tab-bar {
  display: flex;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  overflow-x: auto;
  scrollbar-width: none;
}
.modal-tab-bar::-webkit-scrollbar { display: none; }
.modal-tab {
  flex: 1; min-width: 0;
  display: flex; align-items: center; justify-content: center;
  padding: 10px 12px; gap: 5px;
  font-size: 0.8rem; font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.5);
  background: transparent; border: none;
  border-bottom: 2.5px solid transparent;
  cursor: pointer; transition: all 0.15s;
  white-space: nowrap;
}
.modal-tab:hover { color: rgba(var(--v-theme-on-surface), 0.8); }
.modal-tab--active {
  color: rgb(var(--v-theme-warning));
  border-bottom-color: rgb(var(--v-theme-warning));
  background: rgba(var(--v-theme-warning), 0.04);
}
.modal-tab__badge {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 18px; height: 18px; padding: 0 5px; border-radius: 10px;
  font-size: 0.65rem; font-weight: 700;
  background: rgba(var(--v-theme-warning), 0.15);
  color: rgb(var(--v-theme-warning)); flex-shrink: 0;
}
.modal-tab--active .modal-tab__badge {
  background: rgb(var(--v-theme-warning)); color: #fff;
}

/* ── Info grid (2-col bordered) ─────────────────────────────────────── */
.info-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px; overflow: hidden; margin-bottom: 12px;
}
.info-cell {
  display: flex; flex-direction: column; padding: 10px 12px;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.info-cell:nth-child(even) { border-right: none; }
.info-cell:nth-last-child(-n+2) { border-bottom: none; }
.info-cell--full { grid-column: span 2; border-right: none; }

/* ── Inline grid inside history ─────────────────────────────────────── */
.ig-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 12px; margin-top: 8px; }
.ig-cell { display: flex; flex-direction: column; }
.ig-cell--full { grid-column: span 2; }

/* ── Label / value ──────────────────────────────────────────────────── */
.ic-lbl {
  font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.07em;
  color: rgba(var(--v-theme-on-surface), 0.45); margin-bottom: 2px; font-weight: 600;
}
.ic-val { font-size: 0.85rem; font-weight: 500; color: rgba(var(--v-theme-on-surface), 0.87); }
.ic-val.fw { font-weight: 700; }

/* ── Sec label & form section ───────────────────────────────────────── */
.sec-label {
  font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.08em; color: rgba(var(--v-theme-on-surface), 0.45);
}
.form-section {
  padding: 12px; border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.history-item {
  background: rgba(var(--v-theme-on-surface), 0.02);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.history-item--latest {
  border-color: rgba(var(--v-theme-warning), 0.45) !important;
  background: rgba(var(--v-theme-warning), 0.04) !important;
}
</style>
