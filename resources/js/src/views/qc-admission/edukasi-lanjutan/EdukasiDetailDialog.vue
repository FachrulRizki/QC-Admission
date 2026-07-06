<script setup>
import SignaturePad from '@/components/SignaturePad.vue'
import { useEdukasiLanjutanStore } from '@/stores/useEdukasiLanjutanStore'
import { usePegawaiStore } from '@/stores/usePegawaiStore'
import { useMasterDataStore } from '@/stores/useMasterDataStore'

const props = defineProps({
modelValue: { type: Boolean, default: false },
patient: { type: Object, default: null },
mode: { type: String, default: 'view' },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store = useEdukasiLanjutanStore()
const pegawaiStore = usePegawaiStore()
const masterStore = useMasterDataStore()

const tabView = ref('riwayat')
const form = ref({})
const history = ref([])
const errorMsg = ref('')
const successMsg = ref('')

const nowMs = ref(Date.now())
let clockTimer = null

function getWaktuMenunggu(createdAt) {
if (!createdAt) return null
const s = Math.floor((nowMs.value - new Date(createdAt).getTime()) / 1000)
if (s < 0) return null
const day = Math.floor(s / 86400)
const h = Math.floor((s % 86400) / 3600)
const m = Math.floor((s % 3600) / 60)
if (day >= 1) return `${day}h ${h}j`
if (h >= 1) return `${h}j ${String(m).padStart(2,'0')}m`
return `${m} mnt`
}

function getWaktuColor(createdAt) {
if (!createdAt) return 'secondary'
const min = Math.floor((nowMs.value - new Date(createdAt).getTime()) / 60000)
if (min >= 240) return 'error'
if (min >= 120) return 'warning'
return 'success'
}

// Hitung lama waktu tunggu antara sesi idx dengan sesi sebelumnya (atau mulai QC)
// history sudah urut DESC (terbaru idx=0)
function getWaktuTungguSesi(idx) {
const curr = history.value[idx]
if (!curr?.created_at) return null
// Sesi terlama (idx = history.length-1) = waktu dari awal QC tidak bisa dihitung antar sesi
// Hitung selisih antara sesi ini (created_at) dan sesi SEBELUMNYA (idx+1, lebih lama)
const prev = history.value[idx + 1]
const startMs = prev?.created_at ? new Date(prev.created_at).getTime() : null
const endMs = new Date(curr.created_at).getTime()
if (!startMs) return null // sesi pertama tidak ada pembanding
const s = Math.floor((endMs - startMs) / 1000)
if (s <= 0) return null
const day = Math.floor(s / 86400)
const h = Math.floor((s % 86400) / 3600)
const m = Math.floor((s % 3600) / 60)
if (day >= 1) return `${day}h ${h}j`
if (h >= 1) return `${h}j ${String(m).padStart(2,'0')}m`
return `${m} mnt`
}

function getWaktuTungguSesiColor(idx) {
const curr = history.value[idx]
const prev = history.value[idx + 1]
if (!curr?.created_at || !prev?.created_at) return 'secondary'
const min = Math.floor((new Date(curr.created_at) - new Date(prev.created_at)) / 60000)
if (min >= 240) return 'error'
if (min >= 120) return 'warning'
return 'info'
}

watch(() => props.modelValue, (open) => {
if (open && props.patient) {
tabView.value = props.mode === 'edit' ? 'baru' : 'riwayat'
errorMsg.value = ''
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
no_mr: props.patient?.no_mr ?? '',
no_reg: props.patient?.no_reg ?? '',
nama_pasien: props.patient?.nama_pasien ?? '',
jaminan: props.patient?.jaminan ?? '',
tanggal: `${p(now.getDate())}/${p(now.getMonth()+1)}/${now.getFullYear()}, ${p(now.getHours())}.${p(now.getMinutes())}.${p(now.getSeconds())}`,
jam_input: `${p(now.getHours())}.${p(now.getMinutes())}.${p(now.getSeconds())}`,
bulan: bulanMap[now.getMonth()],
edukasi_kamar: props.patient?.edukasi_kamar ?? '',
note: null,
petugas: null,
keluarga_pasien: props.patient?.keluarga_pasien ?? '',
ttd_keluarga_pasien: '',
status: 'Menunggu',
quality_control_id: props.patient?.quality_control_id ?? null,
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
if (!form.value.petugas) { errorMsg.value = 'Petugas wajib dipilih.'; return }
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

// ── Report Timeline Sesi ─────────────────────────────────────────────────────
// history urut DESC (idx=0 = terbaru). Kita balik ke ASC untuk kalkulasi.
const reportTimeline = computed(() => {
if (history.value.length < 1) return null
// Urutkan ASC (terlama dulu)
const asc = [...history.value].sort((a, b) => new Date(a.created_at ?? 0) - new Date(b.created_at ?? 0))

// Titik awal = created_at pasien masuk edukasi lanjutan (dari props.patient)
const masukMs = props.patient?.created_at ? new Date(props.patient.created_at).getTime() : null

const rows = []

// Baris 0: Masuk Edukasi Lanjutan → Sesi 1
if (masukMs && asc[0]?.created_at) {
const endMs = new Date(asc[0].created_at).getTime()
const diff = Math.max(0, endMs - masukMs)
rows.push({
label: 'Masuk Edukasi → Sesi 1',
from: fmtDate(props.patient.created_at),
to: fmtDate(asc[0].created_at),
durMs: diff,
durStr: fmtDurasi(diff),
color: durColor(diff),
isFirst: true,
})
}

// Baris antar sesi
for (let i = 0; i < asc.length - 1; i++) {
const startMs = new Date(asc[i].created_at).getTime()
const endMs = new Date(asc[i + 1].created_at).getTime()
const diff = Math.max(0, endMs - startMs)
rows.push({
label: `Sesi ${i + 1} → Sesi ${i + 2}`,
from: fmtDate(asc[i].created_at),
to: fmtDate(asc[i + 1].created_at),
durMs: diff,
durStr: fmtDurasi(diff),
color: durColor(diff),
})
}

// Baris total: Masuk Edukasi Lanjutan → Sesi terakhir
const lastMs = new Date(asc[asc.length - 1].created_at).getTime()
const totalMs = masukMs ? Math.max(0, lastMs - masukMs) : null

return {
rows,
totalMs,
totalStr: totalMs !== null ? fmtDurasi(totalMs) : null,
sesiCount: asc.length,
masukAt: fmtDate(props.patient?.created_at),
lastAt: fmtDate(asc[asc.length - 1].created_at),
}
})

function fmtDurasi(ms) {
if (!ms && ms !== 0) return '—'
const s = Math.floor(ms / 1000)
const day = Math.floor(s / 86400)
const h = Math.floor((s % 86400) / 3600)
const m = Math.floor((s % 3600) / 60)
if (day >= 1) return `${day} hari ${h} jam`
if (h >= 1) return `${h} jam ${m} menit`
if (m >= 1) return `${m} menit`
return `< 1 menit`
}

function durColor(ms) {
const min = ms / 60000
if (min >= 240) return 'error'
if (min >= 120) return 'warning'
if (min >= 30) return 'info'
return 'success'
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
'eb-pill--danger': getWaktuColor(patient.created_at)==='error',
'eb-pill--warn': getWaktuColor(patient.created_at)==='warning',
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
<VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-3" closable @click:close="errorMsg=''">{{ errorMsg }}</VAlert>
<VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-3">{{ successMsg }}</VAlert>

<!-- ═══ RIWAYAT ════════════════════════════════════════════════════ -->
<template v-if="tabView==='riwayat'">
<!-- Info ringkas pasien -->
<div class="info-chip-grid mb-3">
<div class="icg-cell"><span class="icg-lbl">No. MR</span><span class="icg-val icg-val--mono">{{ patient.no_mr||'—' }}</span></div>
<div class="icg-cell"><span class="icg-lbl">No. Reg</span><span class="icg-val">{{ patient.no_reg||'—' }}</span></div>
<div class="icg-cell"><span class="icg-lbl">Jaminan</span><span class="icg-val">{{ patient.jaminan||'—' }}</span></div>
<div class="icg-cell">
<span class="icg-lbl">⏱ Menunggu</span>
<VChip v-if="patient.status==='Menunggu'&&getWaktuMenunggu(patient.created_at)"
:color="getWaktuColor(patient.created_at)" variant="tonal" size="x-small" class="mt-1 font-weight-bold">
{{ getWaktuMenunggu(patient.created_at) }}
</VChip>
<VChip v-else-if="patient.status==='Selesai'" color="success" variant="tonal" size="x-small" class="mt-1 font-weight-bold">Dapat bed</VChip>
<span v-else class="icg-val text-disabled">—</span>
</div>
<div class="icg-cell">
<span class="icg-lbl">Status</span>
<VChip :color="patient.status==='Selesai'?'success':'warning'" variant="tonal" size="x-small" class="mt-1 font-weight-bold">
{{ patient.status||'Menunggu' }}
</VChip>
</div>
<div class="icg-cell"><span class="icg-lbl">Total Sesi</span>
<VChip color="warning" variant="tonal" size="x-small" class="mt-1 font-weight-bold">{{ sesiCount }} kali</VChip>
</div>
<div v-if="patient.status_ranap" class="icg-cell icg-cell--span2">
<span class="icg-lbl">Status Ranap</span>
<VChip color="purple" variant="tonal" size="x-small" class="mt-1 font-weight-bold" prepend-icon="ri-hospital-fill">{{ patient.status_ranap }}</VChip>
</div>
</div>

<!-- ── Report Timeline Grid ──────────────────────────────────────── -->
<template v-if="reportTimeline && reportTimeline.rows.length">
<p class="sec-label mb-2">
<VIcon icon="ri-bar-chart-grouped-line" size="13" class="me-1" />Laporan Waktu Edukasi
</p>

<!-- Summary bar -->
<div class="timeline-summary mb-2">
<div class="ts-item">
<span class="ts-lbl">Total Edukasi</span>
<span class="ts-val ts-val--big">{{ reportTimeline.sesiCount }}x Sesi</span>
</div>
<div class="ts-divider" />
<div class="ts-item">
<span class="ts-lbl">Durasi Total</span>
<span class="ts-val ts-val--big" :class="reportTimeline.totalMs >= 7200000 ? 'text-error' : reportTimeline.totalMs >= 3600000 ? 'text-warning' : 'text-success'">
{{ reportTimeline.totalStr ?? '—' }}
</span>
</div>
<div class="ts-divider" />
<div class="ts-item">
<span class="ts-lbl">Masuk Edukasi</span>
<span class="ts-val">{{ reportTimeline.masukAt }}</span>
</div>
</div>

<!-- Grid baris per selang waktu -->
<div class="tl-grid mb-3">
<div class="tl-head">
<span>Tahap</span>
<span>Dari</span>
<span>Ke</span>
<span class="text-end">Durasi</span>
</div>
<div v-for="(row, i) in reportTimeline.rows" :key="i"
class="tl-row" :class="row.isFirst ? 'tl-row--entry' : ''">
<span class="tl-label">
<VIcon :icon="row.isFirst ? 'ri-login-circle-line' : 'ri-arrow-right-line'" size="11" class="me-1" />
{{ row.label }}
</span>
<span class="tl-time">{{ row.from }}</span>
<span class="tl-time">{{ row.to }}</span>
<span class="text-end">
<VChip :color="row.color" variant="tonal" size="x-small" class="font-weight-bold">
{{ row.durStr }}
</VChip>
</span>
</div>
<!-- Total row -->
<div v-if="reportTimeline.totalStr" class="tl-row tl-row--total">
<span class="tl-label font-weight-bold">
<VIcon icon="ri-timer-flash-line" size="11" class="me-1" />Total Keseluruhan
</span>
<span class="tl-time">{{ reportTimeline.masukAt }}</span>
<span class="tl-time">{{ reportTimeline.lastAt }}</span>
<span class="text-end">
<VChip
:color="reportTimeline.totalMs >= 7200000 ? 'error' : reportTimeline.totalMs >= 3600000 ? 'warning' : 'success'"
variant="flat" size="x-small" class="font-weight-bold"
>
{{ reportTimeline.totalStr }}
</VChip>
</span>
</div>
</div>
</template>

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
<div class="d-flex align-center gap-1">
<!-- Lama waktu tunggu antar sesi -->
<VChip
v-if="getWaktuTungguSesi(idx)"
:color="getWaktuTungguSesiColor(idx)"
variant="tonal" size="x-small"
:title="`Jarak waktu dari sesi sebelumnya`"
>
<VIcon icon="ri-timer-line" size="10" class="me-1" />
{{ getWaktuTungguSesi(idx) }}
</VChip>
<VChip :color="statusColor(h.status)" size="x-small" variant="tonal">{{ h.status }}</VChip>
</div>
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
<div class="auto-trigger-banner mb-4">
<VIcon icon="ri-information-line" size="16" color="info" />
<span class="text-caption">
Setiap simpan akan menambah <strong>sesi baru</strong>. Riwayat dapat dilihat di tab Riwayat.
</span>
</div>

<!-- ── SECTION: Data Pasien ──────────────────────────────────────── -->
<div class="form-section form-section--primary mb-4">
<div class="fs-header fs-header--primary">
<VIcon icon="ri-user-heart-line" size="14" />
<span>Data Pasien</span>
</div>
<div class="fs-body px-0 py-0 pb-1">
<div class="info-chip-grid" style="border:none; border-radius:0;">
<div class="icg-cell icg-cell--span2">
<span class="icg-lbl"><VIcon icon="ri-user-3-line" size="10" class="me-1" />Nama Pasien</span>
<span class="icg-val icg-val--accent">{{ form.nama_pasien || '—' }}</span>
</div>
<div class="icg-cell">
<span class="icg-lbl"><VIcon icon="ri-id-card-line" size="10" class="me-1" />No. MR</span>
<span class="icg-val icg-val--mono">{{ form.no_mr || '—' }}</span>
</div>
<div class="icg-cell">
<span class="icg-lbl"><VIcon icon="ri-calendar-event-line" size="10" class="me-1" />Bulan</span>
<span class="icg-val">{{ form.bulan || '—' }}</span>
</div>
<div class="icg-cell icg-cell--span2">
<span class="icg-lbl"><VIcon icon="ri-shield-user-line" size="10" class="me-1" />Jaminan</span>
<span class="icg-val">{{ form.jaminan || '—' }}</span>
</div>
</div>
</div>
</div>

<!-- ── SECTION: Edukasi Lanjutan ─────────────────────────────────── -->
<div class="form-section form-section--info mb-4">
<div class="fs-header fs-header--info">
<VIcon icon="ri-hospital-line" size="14" />
<span>Edukasi Lanjutan</span>
<span class="fs-required-badge">Wajib Diisi</span>
</div>
<div class="fs-body">
<VTextField v-model="form.edukasi_kamar" label="Edukasi Kamar / Ruangan"
variant="outlined" density="compact" prepend-inner-icon="ri-hospital-line" class="mb-3" hide-details />
<VRow dense>
<VCol cols="6">
<VSelect v-model="form.note" :items="masterStore.noteKamarList" label="Note Kamar"
variant="outlined" density="compact" prepend-inner-icon="ri-sticky-note-line" clearable hide-details />
</VCol>
<VCol cols="6">
<VAutocomplete v-model="form.petugas" :items="pegawaiStore.namaList" label="Petugas *"
variant="outlined" density="compact" prepend-inner-icon="ri-nurse-line"
clearable :loading="pegawaiStore.loading" no-data-text="Memuat..." hide-details />
</VCol>
</VRow>
</div>
</div>

<!-- ── SECTION: Keluarga & TTD ───────────────────────────────────── -->
<div class="form-section form-section--purple mb-4">
<div class="fs-header fs-header--purple">
<VIcon icon="ri-group-line" size="14" />
<span>Keluarga &amp; Tanda Tangan</span>
</div>
<div class="fs-body">
<VTextField v-model="form.keluarga_pasien" label="Nama Keluarga Pasien *"
variant="outlined" density="compact" prepend-inner-icon="ri-group-line" class="mb-3" hide-details />
<SignaturePad v-model="form.ttd_keluarga_pasien" label="Tanda Tangan Keluarga" :height="130" />
</div>
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
max-height: 90dvh; /* batas total tinggi dialog */
height: auto;
}

/* ── Banner — fixed height, tidak stretch ── */
.edu-banner {
flex-shrink: 0; /* tidak ikut compress */
position: relative;
overflow: hidden;
background: linear-gradient(135deg, #0369A1 0%, #0EA5E9 55%, #38BDF8 100%);
padding: 14px 16px 12px;
min-height: 80px;
}
.eb-blob { position: absolute; border-radius: 50%; background: #fff; }
.eb-blob--1 { width: 160px; height: 160px; opacity: 0.1; top: -50px; right: -20px; }
.eb-blob--2 { width: 70px; height: 70px; opacity: 0.08; bottom: -22px; right: 80px; }

.eb-inner { position: relative; z-index: 2; display: flex; align-items: center; gap: 10px; }
.eb-avatar {
width: 42px; height: 42px; flex-shrink: 0; border-radius: 50%;
background: rgba(255,255,255,0.25); border: 2px solid rgba(255,255,255,0.45);
display: flex; align-items: center; justify-content: center;
font-size: 16px; font-weight: 800; color: #fff;
}
.eb-info { flex: 1; min-width: 0; overflow: hidden; }
.eb-date { font-size: 0.6rem; color: rgba(255,255,255,0.7); margin: 0 0 1px; }
.eb-name { font-size: 0.88rem; font-weight: 800; color: #fff; margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.eb-sub { font-size: 0.62rem; color: rgba(255,255,255,0.75); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.eb-right { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.eb-close {
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
.eb-pill--count { background: rgba(0,0,0,0.15); }
.eb-pill--ok { background: rgba(16,185,129,0.85); }
.eb-pill--muted { background: rgba(0,0,0,0.18); }
.eb-pill--success { background: rgba(16,185,129,0.8); }
.eb-pill--warn { background: rgba(245,158,11,0.85); }
.eb-pill--danger { background: rgba(239,68,68,0.85); }

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

/* ── Auto trigger banner ── */
.auto-trigger-banner {
display: flex; align-items: center; gap: 10px;
padding: 10px 14px; border-radius: 10px;
background: rgba(var(--v-theme-info), 0.06);
border: 1px solid rgba(var(--v-theme-info), 0.18);
color: rgba(var(--v-theme-on-surface), 0.75);
}

/* ── Form sections ── */
.form-section {
border-radius: 14px;
border: 1.5px solid rgba(var(--v-border-color), var(--v-border-opacity));
overflow: hidden;
transition: box-shadow 0.2s;
}
.form-section:focus-within {
border-color: rgba(var(--v-theme-primary), 0.3);
box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.07);
}
.form-section--primary:focus-within { border-color: rgba(var(--v-theme-primary), 0.35); box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.08); }
.form-section--info:focus-within { border-color: rgba(var(--v-theme-info), 0.35); box-shadow: 0 0 0 3px rgba(var(--v-theme-info), 0.08); }
.form-section--purple:focus-within { border-color: rgba(128,90,213, 0.3); box-shadow: 0 0 0 3px rgba(128,90,213, 0.07); }

.fs-header {
display: flex; align-items: center; gap: 7px;
padding: 9px 14px;
font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.fs-header--primary { background: rgba(var(--v-theme-primary), 0.07); color: rgb(var(--v-theme-primary)); }
.fs-header--info { background: rgba(var(--v-theme-info), 0.07); color: rgb(var(--v-theme-info)); }
.fs-header--purple { background: rgba(128,90,213,0.08); color: rgb(128,90,213); }

.fs-required-badge {
margin-left: auto;
font-size: 0.55rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;
padding: 1px 6px; border-radius: 10px;
background: rgba(var(--v-theme-error), 0.12);
color: rgb(var(--v-theme-error));
}

.fs-body { padding: 14px; }

/* ── Info Chip Grid ── */
.info-chip-grid {
display: grid; grid-template-columns: 1fr 1fr;
gap: 1px;
background: rgba(var(--v-border-color), var(--v-border-opacity));
border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
border-radius: 10px; overflow: hidden;
}
.icg-cell {
display: flex; flex-direction: column; gap: 2px;
padding: 9px 11px;
background: rgba(var(--v-theme-surface), 1);
transition: background 0.12s;
}
.icg-cell:hover { background: rgba(var(--v-theme-on-surface), 0.018); }
.icg-cell--span2 { grid-column: span 2; }
.icg-lbl {
font-size: 0.58rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.07em;
color: rgba(var(--v-theme-on-surface), 0.38);
display: flex; align-items: center;
}
.icg-val { font-size: 0.82rem; font-weight: 600; color: rgba(var(--v-theme-on-surface), 0.85); word-break: break-word; }
.icg-val--accent { font-weight: 700; color: rgba(var(--v-theme-on-surface), 0.92); }
.icg-val--mono { font-family: monospace; font-size: 0.78rem; letter-spacing: 0.03em; }

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


/* ── Timeline Summary Bar ── */
.timeline-summary {
display: flex; align-items: stretch;
border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
border-radius: 10px; overflow: hidden;
background: rgba(var(--v-theme-on-surface), 0.015);
}
.ts-item {
flex: 1; display: flex; flex-direction: column;
align-items: center; justify-content: center;
padding: 9px 8px; gap: 2px; text-align: center;
}
.ts-divider {
width: 1px;
background: rgba(var(--v-border-color), var(--v-border-opacity));
flex-shrink: 0;
}
.ts-lbl {
font-size: 0.58rem; text-transform: uppercase; letter-spacing: 0.07em;
color: rgba(var(--v-theme-on-surface), 0.4); font-weight: 600;
}
.ts-val {
font-size: 0.78rem; font-weight: 600;
color: rgba(var(--v-theme-on-surface), 0.85);
}
.ts-val--big { font-size: 0.88rem; font-weight: 800; }

/* ── Timeline Grid Table ── */
.tl-grid {
border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
border-radius: 10px; overflow: hidden;
font-size: 0.72rem;
}
.tl-head {
display: grid;
grid-template-columns: 2fr 1.6fr 1.6fr 1.1fr;
gap: 0 6px;
padding: 6px 10px;
background: rgba(var(--v-theme-on-surface), 0.04);
font-size: 0.6rem; font-weight: 700; text-transform: uppercase;
letter-spacing: 0.07em; color: rgba(var(--v-theme-on-surface), 0.45);
border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.tl-row {
display: grid;
grid-template-columns: 2fr 1.6fr 1.6fr 1.1fr;
gap: 0 6px; align-items: center;
padding: 7px 10px;
border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
transition: background 0.12s;
}
.tl-row:last-child { border-bottom: none; }
.tl-row:hover { background: rgba(var(--v-theme-on-surface), 0.025); }
.tl-row--entry {
background: rgba(var(--v-theme-info), 0.04);
}
.tl-row--entry:hover { background: rgba(var(--v-theme-info), 0.07); }
.tl-row--total {
background: rgba(var(--v-theme-warning), 0.06);
font-weight: 700;
}
.tl-row--total:hover { background: rgba(var(--v-theme-warning), 0.1); }
.tl-label {
font-size: 0.7rem; font-weight: 600;
color: rgba(var(--v-theme-on-surface), 0.8);
display: flex; align-items: center;
}
.tl-time {
font-size: 0.65rem;
color: rgba(var(--v-theme-on-surface), 0.5);
line-height: 1.3;
}
</style>