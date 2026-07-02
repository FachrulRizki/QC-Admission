<script setup>
import SignaturePad                from '@/components/SignaturePad.vue'
import { useEdukasiLanjutanStore } from '@/stores/useEdukasiLanjutanStore'
import { usePegawaiStore }         from '@/stores/usePegawaiStore'
import { useMasterDataStore }      from '@/stores/useMasterDataStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  patient:    { type: Object, default: null },
  mode:       { type: String, default: 'view' },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store        = useEdukasiLanjutanStore()
const pegawaiStore = usePegawaiStore()
const masterStore  = useMasterDataStore()

const tabView    = ref('riwayat')
const form       = ref({})
const history    = ref([])
const errorMsg   = ref('')
const successMsg = ref('')

const nowMs = ref(Date.now())
let clockTimer = null

function getWaktuMenunggu(createdAt) {
  if (!createdAt) return null
  const s = Math.floor((nowMs.value - new Date(createdAt).getTime()) / 1000)
  if (s < 0) return null
  const day = Math.floor(s / 86400)
  const h   = Math.floor((s % 86400) / 3600)
  const m   = Math.floor((s % 3600) / 60)
  if (day >= 1) return `${day}h ${h}j`
  if (h >= 1)   return `${h}j ${String(m).padStart(2,'0')}m`
  return `${m} mnt`
}

function getWaktuColor(createdAt) {
  if (!createdAt) return 'secondary'
  const min = Math.floor((nowMs.value - new Date(createdAt).getTime()) / 60000)
  if (min >= 240) return 'error'
  if (min >= 120) return 'warning'
  return 'success'
}

watch(() => props.modelValue, (open) => {
  if (open && props.patient) {
    tabView.value    = props.mode === 'edit' ? 'baru' : 'riwayat'
    errorMsg.value   = ''
    successMsg.value = ''
    resetForm()
    pegawaiStore.fetch()
    masterStore.fetch()
    loadHistory()
    clockTimer = setInterval(() => { nowMs.value = Date.now() }, 30_000)
  } else {
    clearInterval(clockTimer)
  }
})
watch(() => props.mode, m => { if (m === 'edit') tabView.value = 'baru' })

function resetForm() {
  const now = new Date()
  const bulanMap = ['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER']
  const p = n => String(n).padStart(2,'0')
  form.value = {
    no_mr:               props.patient?.no_mr        ?? '',
    no_reg:              props.patient?.no_reg        ?? '',
    nama_pasien:         props.patient?.nama_pasien   ?? '',
    jaminan:             props.patient?.jaminan       ?? '',
    tanggal:             `${p(now.getDate())}/${p(now.getMonth()+1)}/${now.getFullYear()}, ${p(now.getHours())}.${p(now.getMinutes())}.${p(now.getSeconds())}`,
    jam_input:           `${p(now.getHours())}.${p(now.getMinutes())}.${p(now.getSeconds())}`,
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
  history.value = [...(store.records ?? []).filter(r => r.no_mr === props.patient.no_mr)]
    .sort((a, b) => new Date(b.created_at ?? 0) - new Date(a.created_at ?? 0))
}

const sesiCount = computed(() => history.value.length)

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.petugas)                 { errorMsg.value = 'Petugas wajib dipilih.'; return }
  if (!form.value.keluarga_pasien?.trim()) { errorMsg.value = 'Nama keluarga pasien wajib diisi.'; return }
  const result = await store.store(form.value)
  if (result?.success) {
    successMsg.value = 'Sesi berhasil dicatat!'
    emit('saved')
    setTimeout(() => close(), 600)
  } else {
    errorMsg.value = result?.message ?? 'Gagal menyimpan.'
  }
}

function close() { emit('update:modelValue', false) }

function statusColor(s) { return { Selesai: 'success', Menunggu: 'warning' }[s] ?? 'secondary' }
function fmtDate(d) {
  if (!d) return '—'
  try { return new Date(d).toLocaleString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) }
  catch { return d }
}
</script>

<template>
  <!-- scrollable=false — kita atur sendiri inner scroll agar banner sticky -->
  <VDialog :model-value="modelValue" max-width="580" persistent @update:model-value="close">
    <VCard v-if="patient" rounded="xl" class="edu-dialog overflow-hidden">

      <!-- ── Banner — STICKY, tidak ikut scroll ──────────────────────────── -->
      <div class="edu-banner">
        <div class="eb-blob eb-blob--1" /><div class="eb-blob eb-blob--2" />
        <div class="eb-inner">
          <div class="eb-avatar">{{ patient.nama_pasien?.charAt(0) ?? '?' }}</div>
          <div class="eb-info">
            <p class="eb-date">{{ patient.tanggal || '—' }}</p>
            <h2 class="eb-name">{{ patient.nama_pasien }}</h2>
            <p class="eb-sub">MR: {{ patient.no_mr }} · {{ patient.jaminan || '—' }}</p>
          </div>
          <div class="eb-right">
            <button class="eb-close" @click="close"><VIcon icon="ri-close-line" size="16" /></button>
            <div class="eb-badges">
              <span class="eb-pill eb-pill--count">{{ sesiCount }} sesi</span>
              <span class="eb-pill" :class="patient.status==='Selesai'?'eb-pill--ok':'eb-pill--muted'">
                {{ patient.status || 'Menunggu' }}
              </span>
              <span v-if="patient.status!=='Selesai' && getWaktuMenunggu(patient.created_at)"
                class="eb-pill"
                :class="{
                  'eb-pill--danger':  getWaktuColor(patient.created_at)==='error',
                  'eb-pill--warn':    getWaktuColor(patient.created_at)==='warning',
                  'eb-pill--success': getWaktuColor(patient.created_at)==='success',
                }">
                <VIcon icon="ri-time-line" size="10" class="me-1" />{{ getWaktuMenunggu(patient.created_at) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Tab bar — STICKY di bawah banner ──────────────────────────────── -->
      <div class="edu-tab-bar">
        <button class="edu-tab" :class="tabView==='riwayat'?'edu-tab--active':''" @click="tabView='riwayat'">
          <VIcon icon="ri-history-line" size="13" class="me-1" />Riwayat
          <span class="edu-tab-badge" :class="tabView==='riwayat'?'edu-tab-badge--active':''">{{ sesiCount }}</span>
        </button>
        <button class="edu-tab" :class="tabView==='baru'?'edu-tab--active':''" @click="tabView='baru'">
          <VIcon icon="ri-add-circle-line" size="13" class="me-1" />Tambah Sesi
        </button>
      </div>

      <!-- ── Scrollable content ─────────────────────────────────────────────── -->
      <div class="edu-body">
        <VAlert v-if="errorMsg"   type="error"   variant="tonal" density="compact" class="mb-3" closable @click:close="errorMsg=''">{{ errorMsg }}</VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-3">{{ successMsg }}</VAlert>

        <!-- ═══ RIWAYAT ════════════════════════════════════════════════════ -->
        <template v-if="tabView==='riwayat'">
          <!-- Info ringkas pasien -->
          <div class="info-grid mb-3">
            <div class="info-cell"><span class="ic-lbl">No. MR</span><span class="ic-val fw">{{ patient.no_mr||'—' }}</span></div>
            <div class="info-cell"><span class="ic-lbl">No. Reg</span><span class="ic-val">{{ patient.no_reg||'—' }}</span></div>
            <div class="info-cell"><span class="ic-lbl">Jaminan</span><span class="ic-val">{{ patient.jaminan||'—' }}</span></div>
            <div class="info-cell">
              <span class="ic-lbl">⏱ Menunggu</span>
              <VChip v-if="patient.status==='Menunggu'&&getWaktuMenunggu(patient.created_at)"
                :color="getWaktuColor(patient.created_at)" variant="tonal" size="x-small" class="mt-1">
                {{ getWaktuMenunggu(patient.created_at) }}
              </VChip>
              <VChip v-else-if="patient.status==='Selesai'" color="success" variant="tonal" size="x-small" class="mt-1">Dapat bed</VChip>
              <span v-else class="ic-val text-disabled">—</span>
            </div>
            <div class="info-cell">
              <span class="ic-lbl">Status</span>
              <VChip :color="patient.status==='Selesai'?'success':'warning'" variant="tonal" size="x-small" class="mt-1">
                {{ patient.status||'Menunggu' }}
              </VChip>
            </div>
            <div class="info-cell"><span class="ic-lbl">Total Sesi</span>
              <VChip color="warning" variant="tonal" size="x-small" class="mt-1">{{ sesiCount }} kali</VChip>
            </div>
            <div v-if="patient.status_ranap" class="info-cell info-cell--full">
              <span class="ic-lbl">Status Ranap</span>
              <VChip color="purple" variant="tonal" size="x-small" class="mt-1" prepend-icon="ri-hospital-fill">{{ patient.status_ranap }}</VChip>
            </div>
          </div>

          <!-- Timeline riwayat sesi -->
          <template v-if="history.length">
            <p class="sec-label mb-2">Riwayat Sesi</p>
            <div v-for="(h, idx) in history" :key="h.id" class="sesi-card mb-2"
              :class="idx===0?'sesi-card--latest':''">
              <div class="d-flex align-center justify-space-between mb-2">
                <div class="d-flex align-center gap-2 flex-wrap">
                  <VChip color="warning" variant="tonal" size="x-small">#{{ history.length - idx }}</VChip>
                  <span class="text-caption font-weight-semibold">{{ h.tanggal }}</span>
                </div>
                <VChip :color="statusColor(h.status)" size="x-small" variant="tonal">{{ h.status }}</VChip>
              </div>
              <div class="ig-grid">
                <div class="ig-cell"><span class="ic-lbl">Petugas</span><span class="ic-val">{{ h.petugas||'—' }}</span></div>
                <div class="ig-cell"><span class="ic-lbl">Ruangan</span><span class="ic-val">{{ h.edukasi_kamar||'—' }}</span></div>
                <div class="ig-cell ig-cell--full"><span class="ic-lbl">Note</span><span class="ic-val">{{ h.note||'—' }}</span></div>
                <div class="ig-cell"><span class="ic-lbl">Keluarga</span><span class="ic-val">{{ h.keluarga_pasien||'—' }}</span></div>
                <div class="ig-cell">
                  <span class="ic-lbl">TTD</span>
                  <img v-if="h.ttd_keluarga_pasien" :src="h.ttd_keluarga_pasien" alt="TTD"
                    style="height:28px;border:1px solid #eee;border-radius:4px;margin-top:2px" />
                  <span v-else class="ic-val text-disabled">—</span>
                </div>
                <div class="ig-cell ig-cell--full"><span class="ic-lbl">Waktu Input</span><span class="ic-val">{{ fmtDate(h.created_at) }}</span></div>
              </div>
            </div>
          </template>
          <div v-else class="text-center py-8" style="color:rgba(var(--v-theme-on-surface),0.4)">
            <VIcon icon="ri-book-open-line" size="40" class="mb-2 opacity-40" />
            <p class="text-body-2 mb-3">Belum ada riwayat</p>
            <VBtn color="warning" variant="tonal" size="small" rounded="lg" @click="tabView='baru'">
              <VIcon icon="ri-add-line" class="me-1" />Mulai Edukasi
            </VBtn>
          </div>
        </template>

        <!-- ═══ FORM BARU ════════════════════════════════════════════════ -->
        <template v-else>
          <VAlert type="info" variant="tonal" density="compact" border="start" class="mb-3">
            <span class="text-caption">Setiap simpan = <strong>sesi baru</strong>. Riwayat di tab Riwayat.</span>
          </VAlert>

          <div class="fsec mb-3">
            <p class="sec-label mb-2">Data Pasien</p>
            <VRow dense>
              <VCol cols="4"><VTextField v-model="form.no_mr" label="No. MR" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" hide-details /></VCol>
              <VCol cols="8"><VTextField v-model="form.nama_pasien" label="Nama Pasien" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" hide-details /></VCol>
            </VRow>
            <VRow dense class="mt-2">
              <VCol cols="6"><VTextField v-model="form.jaminan" label="Jaminan" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" hide-details /></VCol>
              <VCol cols="6"><VTextField v-model="form.bulan" label="Bulan" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" hide-details /></VCol>
            </VRow>
          </div>

          <div class="fsec mb-3">
            <p class="sec-label mb-2">Edukasi Lanjutan</p>
            <VTextField v-model="form.edukasi_kamar" label="Edukasi Kamar / Ruangan"
              variant="outlined" density="compact" prepend-inner-icon="ri-hospital-line" class="mb-2" hide-details />
            <VRow dense>
              <VCol cols="6">
                <VSelect v-model="form.note" :items="masterStore.noteKamarList" label="Note Kamar"
                  variant="outlined" density="compact" clearable hide-details />
              </VCol>
              <VCol cols="6">
                <VAutocomplete v-model="form.petugas" :items="pegawaiStore.namaList" label="Petugas *"
                  variant="outlined" density="compact" prepend-inner-icon="ri-nurse-line"
                  clearable :loading="pegawaiStore.loading" no-data-text="Memuat..." hide-details />
              </VCol>
            </VRow>
          </div>

          <div class="fsec">
            <p class="sec-label mb-2">Keluarga & TTD</p>
            <VTextField v-model="form.keluarga_pasien" label="Nama Keluarga Pasien *"
              variant="outlined" density="compact" prepend-inner-icon="ri-group-line" class="mb-3" hide-details />
            <SignaturePad v-model="form.ttd_keluarga_pasien" label="Tanda Tangan Keluarga" :height="130" />
          </div>
        </template>
      </div>

      <!-- ── Actions — FIXED di bawah ──────────────────────────────────── -->
      <div class="edu-actions">
        <VBtn variant="outlined" rounded="lg" size="small" @click="close">Tutup</VBtn>
        <template v-if="tabView==='riwayat'">
          <VBtn color="warning" variant="tonal" rounded="lg" size="small"
            prepend-icon="ri-add-circle-line" class="flex-grow-1" @click="tabView='baru'">
            Tambah Sesi
          </VBtn>
        </template>
        <template v-else>
          <VBtn color="warning" rounded="lg" size="small" class="flex-grow-1"
            prepend-icon="ri-save-line" :loading="store.loading" @click="handleSave">
            Simpan Sesi Baru
          </VBtn>
        </template>
      </div>

    </VCard>
  </VDialog>
</template>

<style scoped>
/* ── Dialog wrapper ── */
.edu-dialog {
  display: flex;
  flex-direction: column;
  max-height: 90dvh;        /* batas total tinggi dialog */
  height: auto;
}

/* ── Banner — fixed height, tidak stretch ── */
.edu-banner {
  flex-shrink: 0;           /* tidak ikut compress */
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #0369A1 0%, #0EA5E9 55%, #38BDF8 100%);
  padding: 14px 16px 12px;
  min-height: 80px;
}
.eb-blob { position: absolute; border-radius: 50%; background: #fff; }
.eb-blob--1 { width: 160px; height: 160px; opacity: 0.1; top: -50px; right: -20px; }
.eb-blob--2 { width: 70px;  height: 70px;  opacity: 0.08; bottom: -22px; right: 80px; }

.eb-inner { position: relative; z-index: 2; display: flex; align-items: center; gap: 10px; }
.eb-avatar {
  width: 42px; height: 42px; flex-shrink: 0; border-radius: 50%;
  background: rgba(255,255,255,0.25); border: 2px solid rgba(255,255,255,0.45);
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; font-weight: 800; color: #fff;
}
.eb-info   { flex: 1; min-width: 0; overflow: hidden; }
.eb-date   { font-size: 0.6rem; color: rgba(255,255,255,0.7); margin: 0 0 1px; }
.eb-name   { font-size: 0.88rem; font-weight: 800; color: #fff; margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.eb-sub    { font-size: 0.62rem; color: rgba(255,255,255,0.75); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.eb-right  { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.eb-close  {
  background: rgba(255,255,255,0.2); border: none; cursor: pointer;
  width: 24px; height: 24px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff; transition: background 0.15s; flex-shrink: 0;
}
.eb-close:hover { background: rgba(255,255,255,0.35); }

.eb-badges { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; }
.eb-pill {
  font-size: 0.58rem; font-weight: 700; padding: 2px 7px; border-radius: 20px;
  background: rgba(255,255,255,0.2); color: #fff;
  display: inline-flex; align-items: center; white-space: nowrap;
}
.eb-pill--count   { background: rgba(0,0,0,0.15); }
.eb-pill--ok      { background: rgba(16,185,129,0.85); }
.eb-pill--muted   { background: rgba(0,0,0,0.18); }
.eb-pill--success { background: rgba(16,185,129,0.8); }
.eb-pill--warn    { background: rgba(245,158,11,0.85); }
.eb-pill--danger  { background: rgba(239,68,68,0.85); }

/* ── Tab bar — fixed, tidak scroll ── */
.edu-tab-bar {
  flex-shrink: 0;
  display: flex;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
}
.edu-tab {
  flex: 1;
  display: flex; align-items: center; justify-content: center; gap: 4px;
  padding: 10px 8px;
  font-size: 0.8rem; font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.45);
  background: transparent; border: none;
  border-bottom: 2.5px solid transparent;
  cursor: pointer; transition: all 0.15s;
  white-space: nowrap;
}
.edu-tab:hover { color: rgba(var(--v-theme-on-surface), 0.75); }
.edu-tab--active {
  color: rgb(var(--v-theme-warning));
  border-bottom-color: rgb(var(--v-theme-warning));
  background: rgba(var(--v-theme-warning), 0.04);
}
.edu-tab-badge {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 18px; height: 18px; padding: 0 4px; border-radius: 9px;
  font-size: 0.65rem; font-weight: 700;
  background: rgba(var(--v-theme-warning), 0.15);
  color: rgb(var(--v-theme-warning));
}
.edu-tab-badge--active { background: rgb(var(--v-theme-warning)); color: #fff; }

/* ── Scrollable body ── */
.edu-body {
  flex: 1 1 auto;
  overflow-y: auto;
  padding: 14px 16px;
  overscroll-behavior: contain;
}

/* ── Actions — fixed bottom ── */
.edu-actions {
  flex-shrink: 0;
  display: flex; gap: 8px;
  padding: 12px 16px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
}

/* ── Info grid ── */
.info-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px; overflow: hidden;
}
.info-cell {
  display: flex; flex-direction: column; padding: 9px 11px;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.info-cell:nth-child(even) { border-right: none; }
.info-cell:nth-last-child(-n+2) { border-bottom: none; }
.info-cell--full { grid-column: span 2; border-right: none; }

/* ── Sesi card ── */
.sesi-card {
  padding: 10px 12px; border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.sesi-card--latest {
  border-color: rgba(var(--v-theme-warning), 0.4) !important;
  background: rgba(var(--v-theme-warning), 0.04) !important;
}

/* ── Inline grid ── */
.ig-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 10px; margin-top: 6px; }
.ig-cell { display: flex; flex-direction: column; }
.ig-cell--full { grid-column: span 2; }

/* ── Labels ── */
.ic-lbl {
  font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.07em;
  color: rgba(var(--v-theme-on-surface), 0.4); margin-bottom: 1px; font-weight: 600;
}
.ic-val { font-size: 0.83rem; font-weight: 500; color: rgba(var(--v-theme-on-surface), 0.85); }
.ic-val.fw { font-weight: 700; }

.sec-label {
  font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.08em; color: rgba(var(--v-theme-on-surface), 0.4);
}
.fsec {
  padding: 11px; border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
