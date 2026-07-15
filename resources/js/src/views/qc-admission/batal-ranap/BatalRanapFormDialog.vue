<script setup>
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'
import { usePegawaiStore } from '@/stores/usePegawaiStore'
import { usePasienStore } from '@/stores/usePasienStore'
import { useMasterDataStore } from '@/stores/useMasterDataStore'
import { ref, computed, watch } from 'vue'

const props = defineProps({
modelValue: { type: Boolean, default: false },
editItem: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store = useBatalRanapStore()
const pegawaiStore = usePegawaiStore()
const pasienStore = usePasienStore()
const masterStore = useMasterDataStore()

const form = ref(initialForm())
const errorMsg = ref('')
const saving = ref(false)

const nowDisplay = ref('')
let clockTimer = null

// ── Bed IGD state ─────────────────────────────────────────────────────────────
const bedList    = ref([])
const bedLoading = ref(false)

function tickClock() {
const d = new Date()
const p = n => String(n).padStart(2, '0')
nowDisplay.value = `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()}, ${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
}

const statusClosingOpts = [
{ title: '👍 Siap Closing', value: 'Siap Closing', color: 'success' },
{ title: '⏳ Belum Siap Closing', value: 'Belum Siap Closing', color: 'error' },
]

// ── Pasien search ─────────────────────────────────────────────────────────────
const noRegSearch = ref('')
const noRegLoading = ref(false)
let searchTimer = null

watch(noRegSearch, (val) => {
clearTimeout(searchTimer)
if (!val || val.trim().length < 2) { pasienStore.clear(); return }
noRegLoading.value = true
searchTimer = setTimeout(async () => {
await pasienStore.search(val.trim())
noRegLoading.value = false
}, 300)
})

watch(() => form.value.no_reg, async (val) => {
if (!val) {
bedList.value = []
return
}
const hit = pasienStore.results.find(p => p.no_reg === val)
?? await pasienStore.lookup(val)
if (hit) {
form.value.no_mr = hit.no_mr ?? ''
form.value.nama_pasien = hit.nama_pasien ?? ''
form.value.jaminan = hit.ket_bayar ?? ''
form.value.tgl_daftar = hit.tgl_daftar ?? ''
form.value.jam_daftar = hit.jam_daftar ?? ''
form.value.diagnosa = hit.diagnosa ?? ''
form.value.ruangan = hit.nama_bangsal ?? hit.nama_ruang ?? ''

// Jika query pasien sudah join BI_Bed_Igd, gunakan langsung
if (hit.kode_bed) {
  form.value.bed_id = hit.kode_bed
  bedList.value = [{
    kode_bed:   hit.kode_bed,
    bed_id:     hit.kode_bed,
    status:     hit.bed_status ?? 'TERISI',
    no_reg:     val,
    tanggal:    null,
  }]
  bedLoading.value = false
  return
}
}
// Fallback: fetch bed terpisah jika tidak dapat dari query pasien
await fetchBedList(val)
})

// Ambil daftar bed IGD berdasarkan No_Reg (sama seperti di DetailDialog)
async function fetchBedList(noReg) {
if (!noReg) return
bedLoading.value = true
bedList.value = []
try {
const { data } = await axios.get('/api/bed-management/beds', {
params: { no_reg: noReg }
})
bedList.value = data.beds ?? []
// Auto-pilih bed pertama yang TERISI jika belum ada pilihan
if (bedList.value.length && !form.value.bed_id) {
const aktif = bedList.value.find(b => (b.status ?? '').toUpperCase() !== 'KOSONG')
if (aktif) form.value.bed_id = aktif.kode_bed ?? aktif.bed_id
}
} catch {
bedList.value = []
} finally {
bedLoading.value = false
}
}

function bedStatusColor(s) {
return (s ?? '').toUpperCase() === 'KOSONG' ? 'success' : 'warning'
}

watch(() => props.modelValue, (open) => {
if (open) {
form.value = props.editItem ? { ...initialForm(), ...props.editItem } : initialForm()
errorMsg.value = ''
pasienStore.clear()
noRegSearch.value = ''
bedList.value = []
pegawaiStore.fetch()
masterStore.fetch()
tickClock()
clockTimer = setInterval(tickClock, 1000)
// Jika edit dan sudah ada no_reg, fetch bed langsung
if (props.editItem?.no_reg) fetchBedList(props.editItem.no_reg)
} else {
clearInterval(clockTimer)
}
})

function initialForm() {
return {
no_reg: null,
no_mr: '',
nama_pasien: '',
jaminan: '',
tgl_daftar: '',
jam_daftar: '',
keterangan_batal: null,
status_ok: null,
status_closing: null,
ketersediaan_kamar: '',
diagnosa: '',
note: '',
petugas: null,
ruangan: '',
bed_id: null,
}
}

function fmtJam(d) {
const p = n => String(n).padStart(2, '0')
return `${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
}
function fmtTgl(d) {
const p = n => String(n).padStart(2, '0')
return `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()}, ${fmtJam(d)}`
}

async function handleSave() {
errorMsg.value = ''
if (!form.value.no_reg) { errorMsg.value = 'Pasien wajib dipilih.'; return }
if (!form.value.keterangan_batal) { errorMsg.value = 'Keterangan batal wajib dipilih.'; return }
if (!form.value.petugas) { errorMsg.value = 'Petugas wajib dipilih.'; return }

saving.value = true
const now = new Date()
const payload = {
...form.value,
tanggal: fmtTgl(now),
jam_input: fmtJam(now),
}

const result = props.editItem
? await store.update(props.editItem.id, payload)
: await store.store(payload)

saving.value = false

if (result?.success !== false) {
emit('saved', payload)
close()
} else {
errorMsg.value = result?.message ?? 'Gagal menyimpan.'
}
}

function close() {
clearInterval(clockTimer)
emit('update:modelValue', false)
}

function statusClosingColor(v) {
return v === 'Siap Closing' ? 'success' : v === 'Belum Siap Closing' ? 'error' : 'secondary'
}

const hasPasien = computed(() => !!form.value.no_reg && !!form.value.nama_pasien)
</script>

<template>
<VDialog :model-value="modelValue" max-width="600" persistent scrollable @update:model-value="close">
<VCard rounded="xl" class="dlg-card overflow-hidden">

<!-- ── Gradient Banner Header ───────────────────────────────────────── -->
<div class="dlg-banner">
<div class="db-blob db-blob--1" /><div class="db-blob db-blob--2" />
<div class="db-inner">
<div class="db-icon">
<VIcon icon="ri-close-circle-line" size="22" color="white" />
</div>
<div class="db-text">
<p class="db-sub">Batal Ranap · IGD</p>
<h3 class="db-title">{{ editItem ? 'Edit Batal Ranap' : 'Input Batal Ranap' }}</h3>
</div>
<div class="db-clock">
<VIcon icon="ri-time-line" size="11" class="me-1 opacity-70" />
<span>{{ nowDisplay }}</span>
</div>
<button class="db-close" @click="close">
<VIcon icon="ri-close-line" size="16" />
</button>
</div>
</div>

<VCardText class="pa-0">
<div v-if="errorMsg" class="px-5 pt-4">
<VAlert type="error" variant="tonal" density="compact" closable @click:close="errorMsg=''">
{{ errorMsg }}
</VAlert>
</div>

<div class="px-5 py-4 d-flex flex-column gap-4">

<!-- Info verifikasi -->
<div v-if="!editItem" class="auto-trigger-banner">
<VIcon icon="ri-information-line" size="16" color="info" />
<span class="text-caption">
Setelah disimpan, gunakan aksi <strong>Verifikasi</strong> pada data untuk konfirmasi dan update status bed management IGD.
</span>
</div>

<!-- ── SECTION: Data Pasien IGD ──────────────────────────────────── -->
<div class="form-section form-section--error">
<div class="fs-header fs-header--error">
<VIcon icon="ri-user-heart-line" size="14" />
<span>Data Pasien</span>
</div>

<div class="fs-body">
<!-- Search -->
<VAutocomplete
v-model="form.no_reg"
v-model:search="noRegSearch"
:items="pasienStore.optionList"
item-title="title"
item-value="value"
label="Cari No. Reg / No. MR / Nama Pasien *"
variant="outlined" density="compact"
prepend-inner-icon="ri-search-line"
clearable no-filter
:loading="noRegLoading || pasienStore.loading"
no-data-text="Ketik min. 2 karakter..."
class="mb-3" hide-details="auto"
>
<template #item="{ item, props: iProps }">
<VListItem v-bind="iProps" class="py-2">
<template #prepend>
<VAvatar color="error" variant="tonal" size="34" rounded="lg" class="me-2">
<span style="font-size:12px;font-weight:700">
{{ item.raw?.data?.nama_pasien?.charAt(0) ?? '?' }}
</span>
</VAvatar>
</template>
<VListItemTitle class="text-body-2 font-weight-semibold">
{{ item.raw?.data?.nama_pasien }}
</VListItemTitle>
<VListItemSubtitle class="d-flex flex-wrap gap-1 mt-1">
<VChip size="x-small" color="error" variant="tonal" label>{{ item.raw?.data?.no_reg }}</VChip>
<VChip size="x-small" color="secondary" variant="tonal" label>{{ item.raw?.data?.ket_bayar }}</VChip>
<span class="text-caption text-medium-emphasis">{{ item.raw?.data?.nama_bangsal }}</span>
</VListItemSubtitle>
</VListItem>
</template>
</VAutocomplete>

<!-- Autofill info chips -->
<Transition name="slide-down">
<div v-if="hasPasien" class="info-chip-grid">
<div class="icg-cell icg-cell--span2">
<span class="icg-lbl"><VIcon icon="ri-user-3-line" size="10" class="me-1" />Nama Pasien</span>
<span class="icg-val icg-val--accent">{{ form.nama_pasien || '—' }}</span>
</div>
<div class="icg-cell">
<span class="icg-lbl"><VIcon icon="ri-id-card-line" size="10" class="me-1" />No. MR</span>
<span class="icg-val icg-val--mono">{{ form.no_mr || '—' }}</span>
</div>
<div class="icg-cell">
<span class="icg-lbl"><VIcon icon="ri-shield-user-line" size="10" class="me-1" />Jaminan</span>
<span class="icg-val">{{ form.jaminan || '—' }}</span>
</div>
<div class="icg-cell">
<span class="icg-lbl"><VIcon icon="ri-door-line" size="10" class="me-1" />Ruangan</span>
<span class="icg-val">{{ form.ruangan || '—' }}</span>
</div>
<div class="icg-cell icg-cell--span2">
<span class="icg-lbl"><VIcon icon="ri-stethoscope-line" size="10" class="me-1" />Diagnosa</span>
<span class="icg-val">{{ form.diagnosa || '—' }}</span>
</div>
<div class="icg-cell">
<span class="icg-lbl"><VIcon icon="ri-calendar-check-line" size="10" class="me-1" />Tgl. Daftar</span>
<span class="icg-val">{{ form.tgl_daftar || '—' }}</span>
</div>
<div class="icg-cell">
<span class="icg-lbl"><VIcon icon="ri-time-line" size="10" class="me-1" />Jam Daftar</span>
<span class="icg-val icg-val--mono">{{ form.jam_daftar || '—' }}</span>
</div>
</div>
</Transition>

<!-- Empty state -->
<div v-if="!hasPasien" class="empty-pasien">
<VIcon icon="ri-user-search-line" size="28" class="mb-1 opacity-30" />
<p class="text-caption text-disabled mb-0">Cari pasien untuk mengisi data otomatis</p>
</div>
</div>
</div>

<!-- ── SECTION: Bed IGD ───────────────────────────────────────────── -->
<div v-if="hasPasien" class="form-section form-section--primary">
<div class="fs-header fs-header--primary">
<VIcon icon="ri-hotel-bed-line" size="14" />
<span>Bed IGD</span>
<VProgressCircular v-if="bedLoading" indeterminate size="12" width="2" class="ms-2" />
<VChip v-else-if="bedList.length" size="x-small" color="primary" variant="tonal" class="ms-auto">
{{ bedList.length }} bed ditemukan
</VChip>
</div>
<div class="fs-body">
<div v-if="bedLoading" class="text-center py-3">
<VProgressCircular indeterminate size="20" color="primary" />
<p class="text-caption mt-2 text-disabled">Mencari bed IGD...</p>
</div>

<!-- Ada bed — tampilkan info saja, bed_id di-set otomatis -->
<div v-else-if="bedList.length" class="d-flex flex-column gap-2">
<div v-for="bed in bedList" :key="bed.kode_bed ?? bed.bed_id" class="d-flex align-center gap-3 py-1">
<VAvatar color="primary" variant="tonal" size="30" rounded="md">
<VIcon icon="ri-hotel-bed-line" size="14" />
</VAvatar>
<div class="flex-grow-1">
<span class="text-body-2 font-weight-bold">{{ bed.kode_bed ?? bed.bed_id }}</span>
<span class="text-caption ms-2" style="color:var(--qc-text-2)">No. Reg: {{ bed.no_reg }}</span>
</div>
<VChip :color="bedStatusColor(bed.status)" size="x-small" variant="tonal">
{{ (bed.status ?? '').toUpperCase() || '—' }}
</VChip>
</div>
<VAlert type="success" variant="tonal" density="compact" class="mt-1 text-caption">
<VIcon icon="ri-checkbox-circle-line" size="13" class="me-1" />
Kode bed akan otomatis dibebaskan saat status "Siap Closing"
</VAlert>
</div>

<!-- Tidak ada bed — info saja, backend tetap akan auto-lookup saat closing -->
<div v-else class="empty-beds">
<VIcon icon="ri-hotel-bed-line" size="20" class="opacity-30 me-2" />
<div>
<p class="text-caption text-disabled mb-0">Tidak ada bed aktif ditemukan untuk No. Reg ini</p>
</div>
</div>
</div>
</div>

<!-- ── SECTION: Detail Batal ──────────────────────────────────────── -->
<div class="form-section form-section--warning">
<div class="fs-header fs-header--warning">
<VIcon icon="ri-close-circle-line" size="14" />
<span>Detail Pembatalan</span>
<span class="fs-required-badge">Wajib Diisi</span>
</div>

<div class="fs-body">
<VSelect
v-model="form.keterangan_batal"
:items="masterStore.keteranganBatalList"
label="Keterangan Batal *"
variant="outlined" density="compact"
prepend-inner-icon="ri-close-circle-line"
clearable class="mb-3" hide-details="auto"
/>
<VSelect
v-model="form.status_ok"
:items="masterStore.statusOkList"
label="Status OK (Opsional)"
variant="outlined" density="compact"
clearable class="mb-3" hide-details="auto"
:prepend-inner-icon="form.status_ok === 'Bedah' ? 'ri-surgical-mask-line' : form.status_ok === 'Non Bedah' ? 'ri-hospital-line' : 'ri-question-line'"
/>
<VTextField
v-model="form.ketersediaan_kamar"
label="Ketersediaan Kamar Saat Ini"
variant="outlined" density="compact"
prepend-inner-icon="ri-door-line"
class="mb-3" hide-details="auto"
/>
<VTextField
v-model="form.diagnosa"
label="Diagnosa"
variant="outlined" density="compact"
prepend-inner-icon="ri-stethoscope-line"
hide-details="auto"
/>
</div>
</div>

<!-- ── SECTION: Petugas & Note ────────────────────────────────────── -->
<div class="form-section form-section--info">
<div class="fs-header fs-header--info">
<VIcon icon="ri-nurse-line" size="14" />
<span>Petugas &amp; Catatan</span>
</div>

<div class="fs-body">
<VAutocomplete
v-model="form.petugas"
:items="pegawaiStore.namaList"
label="Petugas *"
variant="outlined" density="compact"
prepend-inner-icon="ri-nurse-line"
clearable class="mb-3" hide-details="auto"
:loading="pegawaiStore.loading"
no-data-text="Memuat petugas..."
/>
<VTextarea
v-model="form.note"
label="Catatan Tambahan"
variant="outlined" density="compact"
rows="2" auto-grow
prepend-inner-icon="ri-sticky-note-line"
hide-details="auto"
/>
</div>
</div>

<!-- ── SECTION: Status Closing ────────────────────────────────────── -->
<div class="form-section form-section--primary">
<div class="fs-header fs-header--primary">
<VIcon icon="ri-checkbox-circle-line" size="14" />
<span>Status Closing</span>
</div>

<div class="fs-body">
<div class="d-flex gap-3">
<div
v-for="opt in statusClosingOpts"
:key="opt.value"
class="closing-option flex-grow-1 pa-3 rounded-xl cursor-pointer text-center"
:class="form.status_closing === opt.value ? `closing-option--active closing-option--${opt.color}` : ''"
@click="form.status_closing = form.status_closing === opt.value ? null : opt.value"
>
<p class="text-body-2 font-weight-bold mb-0"
:class="form.status_closing === opt.value ? `text-${opt.color}` : 'text-medium-emphasis'">
{{ opt.title }}
</p>
</div>
</div>

<div v-if="form.status_closing" class="mt-2 d-flex align-center gap-2">
<VChip :color="statusClosingColor(form.status_closing)" variant="tonal" size="small">
{{ form.status_closing }}
</VChip>
<span class="text-caption text-disabled">terpilih</span>
</div>
</div>
</div>

</div>
</VCardText>

<div class="dlg-footer">
<VBtn variant="outlined" rounded="lg" size="small" @click="close">Batal</VBtn>
<VBtn
color="error" rounded="lg" class="flex-grow-1"
prepend-icon="ri-save-line"
:loading="saving || store.loading"
@click="handleSave"
>
{{ editItem ? 'Simpan Perubahan' : 'Simpan Batal Ranap' }}
</VBtn>
</div>
</VCard>
</VDialog>
</template>

<style scoped>
/* ── Dialog card ── */
.dlg-card { display: flex; flex-direction: column; max-height: 92dvh; }
.v-card-text { flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; }

/* ── Gradient Banner ── */
.dlg-banner {
flex-shrink: 0; position: relative; overflow: hidden;
background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 55%, #60a5fa 100%);
padding: 16px 18px 14px;
}
.db-blob { position: absolute; border-radius: 50%; background: #fff; }
.db-blob--1 { width: 180px; height: 180px; opacity: 0.08; top: -60px; right: -40px; }
.db-blob--2 { width: 80px; height: 80px; opacity: 0.06; bottom: -30px; right: 90px; }
.db-inner { position: relative; z-index: 2; display: flex; align-items: center; gap: 12px; }
.db-icon {
width: 40px; height: 40px; flex-shrink: 0; border-radius: 12px;
background: rgba(255,255,255,0.22); border: 1.5px solid rgba(255,255,255,0.35);
display: flex; align-items: center; justify-content: center;
}
.db-text { flex: 1; min-width: 0; }
.db-sub { font-size: 0.6rem; color: rgba(255,255,255,0.72); margin: 0 0 1px; letter-spacing: 0.05em; text-transform: uppercase; }
.db-title { font-size: 0.95rem; font-weight: 800; color: #fff; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.db-clock {
font-size: 0.58rem; color: rgba(255,255,255,0.75); flex-shrink: 0;
background: rgba(0,0,0,0.15); border-radius: 20px; padding: 3px 8px;
display: flex; align-items: center; white-space: nowrap;
}
.db-close {
flex-shrink: 0; background: rgba(255,255,255,0.2); border: none; cursor: pointer;
width: 28px; height: 28px; border-radius: 50%;
display: flex; align-items: center; justify-content: center;
color: #fff; transition: background 0.15s;
}
.db-close:hover { background: rgba(255,255,255,0.35); }

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
.form-section--error:focus-within { border-color: rgba(var(--v-theme-error), 0.35); box-shadow: 0 0 0 3px rgba(var(--v-theme-error), 0.08); }
.form-section--primary:focus-within { border-color: rgba(var(--v-theme-primary), 0.35); box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.08); }
.form-section--warning:focus-within { border-color: rgba(var(--v-theme-warning), 0.35); box-shadow: 0 0 0 3px rgba(var(--v-theme-warning), 0.08); }
.form-section--info:focus-within { border-color: rgba(var(--v-theme-info), 0.35); box-shadow: 0 0 0 3px rgba(var(--v-theme-info), 0.08); }

.fs-header {
display: flex; align-items: center; gap: 7px;
padding: 9px 14px;
font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.fs-header--error { background: rgba(var(--v-theme-error), 0.07); color: rgb(var(--v-theme-error)); }
.fs-header--primary { background: rgba(var(--v-theme-primary), 0.07); color: rgb(var(--v-theme-primary)); }
.fs-header--warning { background: rgba(var(--v-theme-warning), 0.07); color: rgb(var(--v-theme-warning)); }
.fs-header--info { background: rgba(var(--v-theme-info), 0.07); color: rgb(var(--v-theme-info)); }

.fs-required-badge {
margin-left: auto;
font-size: 0.55rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;
padding: 1px 6px; border-radius: 10px;
background: rgba(var(--v-theme-error), 0.12);
color: rgb(var(--v-theme-error));
}

.fs-body { padding: 14px; }

/* ── Info chip grid ── */
.info-chip-grid {
display: grid; grid-template-columns: 1fr 1fr;
gap: 1px;
background: rgba(var(--v-border-color), var(--v-border-opacity));
border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
border-radius: 10px; overflow: hidden;
margin-top: 2px;
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

/* ── Status Closing selector ── */
.closing-option {
border: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
transition: all 0.18s;
}
.closing-option:hover {
border-color: rgba(var(--v-theme-primary), 0.35);
background: rgba(var(--v-theme-primary), 0.04);
}
.closing-option--active { border-width: 2px !important; }
.closing-option--success {
border-color: rgb(var(--v-theme-success)) !important;
background: rgba(var(--v-theme-success), 0.07) !important;
}
.closing-option--error {
border-color: rgb(var(--v-theme-error)) !important;
background: rgba(var(--v-theme-error), 0.07) !important;
}

/* ── Empty pasien ── */
.empty-pasien {
display: flex; flex-direction: column; align-items: center; justify-content: center;
padding: 20px 10px; gap: 4px;
border: 1.5px dashed rgba(var(--v-border-color), var(--v-border-opacity));
border-radius: 10px; margin-top: 2px;
}

/* ── Footer ── */
.dlg-footer {
flex-shrink: 0;
display: flex; gap: 10px;
padding: 14px 20px;
border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
background: rgba(var(--v-theme-surface-variant), 0.25);
}

/* ── Transitions ── */
.slide-down-enter-active { transition: all 0.25s cubic-bezier(0.4,0,0.2,1); }
.slide-down-leave-active { transition: all 0.18s ease; }
.slide-down-enter-from { opacity: 0; transform: translateY(-8px); }
.slide-down-leave-to { opacity: 0; transform: translateY(-4px); }

/* ── Bed select items ── */
/* dihapus — bed dipilih otomatis oleh backend dari BI_Bed_Igd */

/* ── Empty beds ── */
.empty-beds {
display: flex; align-items: flex-start; gap: 10px;
padding: 12px 14px; border-radius: 10px;
background: rgba(var(--v-theme-on-surface), 0.02);
border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>