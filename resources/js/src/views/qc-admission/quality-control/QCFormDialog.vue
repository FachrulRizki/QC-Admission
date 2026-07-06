<script setup>
import { useQualityControlStore } from '@/stores/useQualityControlStore'
import { usePegawaiStore } from '@/stores/usePegawaiStore'
import { usePasienStore } from '@/stores/usePasienStore'
import { useMasterDataStore } from '@/stores/useMasterDataStore'
import SignaturePad from '@/components/SignaturePad.vue'

const props = defineProps({
modelValue: { type: Boolean, default: false },
editItem: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store = useQualityControlStore()
const pegawaiStore = usePegawaiStore()
const pasienStore = usePasienStore()
const masterStore = useMasterDataStore()

const form = ref(initialForm())
const errorMsg = ref('')
const saving = ref(false)

const nowDisplay = ref('')
let clockTimer = null

function tickClock() {
const d = new Date()
const p = n => String(n).padStart(2, '0')
nowDisplay.value = `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()}, ${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
}

// ── Pasien search ─────────────────────────────────────────────────────────────
const noRegSearch = ref('')
const noRegLoading = ref(false)
let _debounce = null

watch(noRegSearch, (val) => {
clearTimeout(_debounce)
if (!val || val.trim().length < 2) { pasienStore.clear(); return }
noRegLoading.value = true
_debounce = setTimeout(async () => {
await pasienStore.search(val.trim())
noRegLoading.value = false
}, 300)
})

watch(() => form.value.no_reg, async (val) => {
if (!val) return
const hit = pasienStore.results.find(p => p.no_reg === val)
?? await pasienStore.lookup(val)
if (hit) {
form.value.no_mr = hit.no_mr ?? ''
form.value.nama_pasien = hit.nama_pasien ?? ''
form.value.jaminan = hit.ket_bayar ?? ''
form.value.status_ket = hit.keterangan ?? ''
form.value.tgl_daftar = hit.tgl_daftar ?? ''
form.value.jam_daftar = hit.jam_daftar ?? ''
}
})

watch(() => props.modelValue, (open) => {
if (open) {
form.value = props.editItem ? { ...initialForm(), ...props.editItem } : initialForm()
errorMsg.value = ''
pasienStore.clear()
noRegSearch.value = ''
pegawaiStore.fetch()
masterStore.fetch()
tickClock()
clockTimer = setInterval(tickClock, 1000)
} else {
clearInterval(clockTimer)
}
})

function initialForm() {
return {
no_mr: '', no_reg: null, nama_pasien: '', jaminan: '',
status_ket: '', tgl_daftar: '', jam_daftar: '',
edukasi_kamar: '',
note: null, petugas: null,
keluarga_pasien: '', ttd_keluarga_pasien: '',
}
}

function fmt(d) {
const p = n => String(n).padStart(2, '0')
return `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()}, ${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
}
function fmtTime(d) {
const p = n => String(n).padStart(2, '0')
return `${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
}

async function handleSave() {
errorMsg.value = ''
if (!form.value.no_reg) { errorMsg.value = 'No. Registrasi wajib dipilih.'; return }
if (!form.value.petugas) { errorMsg.value = 'Petugas wajib dipilih.'; return }

saving.value = true
const now = new Date()
const payload = {
...form.value,
tanggal: fmt(now),
jam_input: fmtTime(now),
status: 'Edukasi',
}

const result = props.editItem
? await store.update(props.editItem.id, payload)
: await store.store(payload)

saving.value = false

if (result.success) {
emit('saved', payload)
close()
} else {
errorMsg.value = result.message ?? 'Gagal menyimpan data.'
}
}

function close() {
clearInterval(clockTimer)
emit('update:modelValue', false)
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
<VIcon icon="ri-shield-check-line" size="22" color="white" />
</div>
<div class="db-text">
<p class="db-sub">Quality Control · IGD</p>
<h3 class="db-title">{{ editItem ? 'Edit Quality Control' : 'Input Quality Control' }}</h3>
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

<!-- Auto-trigger info -->
<div v-if="!editItem" class="px-5 pt-4">
<div class="auto-trigger-banner">
<VIcon icon="ri-timer-flash-line" size="16" color="primary" />
<span class="text-caption">
Status awal <strong>Edukasi</strong> — otomatis pindah ke
<strong>Edukasi Lanjutan</strong> setelah <strong>2 jam</strong>
</span>
</div>
</div>

<div class="px-5 py-4 d-flex flex-column gap-4">

<!-- ── SECTION: Data Pasien IGD ──────────────────────────────────── -->
<div class="form-section form-section--primary">
<div class="fs-header fs-header--primary">
<VIcon icon="ri-user-heart-line" size="14" />
<span>Data Pasien IGD</span>
</div>
<div class="fs-body">
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
<VAvatar color="primary" variant="tonal" size="34" rounded="lg" class="me-2">
<span style="font-size:12px;font-weight:700">
{{ item.raw?.data?.nama_pasien?.charAt(0) ?? '?' }}
</span>
</VAvatar>
</template>
<VListItemTitle class="text-body-2 font-weight-semibold">
{{ item.raw?.data?.nama_pasien }}
</VListItemTitle>
<VListItemSubtitle class="d-flex flex-wrap gap-1 mt-1">
<VChip size="x-small" color="primary" variant="tonal" label>{{ item.raw?.data?.no_reg }}</VChip>
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
<span class="icg-lbl"><VIcon icon="ri-information-line" size="10" class="me-1" />Status Pasien</span>
<span class="icg-val">{{ form.status_ket || '—' }}</span>
</div>
<div class="icg-cell">
<span class="icg-lbl"><VIcon icon="ri-calendar-check-line" size="10" class="me-1" />Tgl. Daftar</span>
<span class="icg-val">{{ form.tgl_daftar || '—' }}</span>
</div>
<div class="icg-cell icg-cell--span2">
<span class="icg-lbl"><VIcon icon="ri-time-line" size="10" class="me-1" />Jam Daftar</span>
<span class="icg-val icg-val--mono">{{ form.jam_daftar || '—' }}</span>
</div>
</div>
</Transition>

<div v-if="!hasPasien" class="empty-pasien">
<VIcon icon="ri-user-search-line" size="28" class="mb-1 opacity-30" />
<p class="text-caption text-disabled mb-0">Cari pasien untuk mengisi data otomatis</p>
</div>
</div>
</div>

<!-- ── SECTION: Data QC ──────────────────────────────────────────── -->
<div class="form-section form-section--info">
<div class="fs-header fs-header--info">
<VIcon icon="ri-hospital-line" size="14" />
<span>Data Quality Control</span>
</div>
<div class="fs-body">
<VTextField
v-model="form.edukasi_kamar"
label="Edukasi Kamar / Ruangan"
variant="outlined" density="compact"
prepend-inner-icon="ri-hospital-line"
class="mb-3" hide-details="auto"
/>
<VRow dense class="mb-3">
<VCol cols="6">
<VSelect
v-model="form.note"
:items="masterStore.noteKamarList"
label="Note / Kamar"
variant="outlined" density="compact"
prepend-inner-icon="ri-sticky-note-line"
clearable hide-details="auto"
/>
</VCol>
<VCol cols="6">
<VAutocomplete
v-model="form.petugas"
:items="pegawaiStore.namaList"
label="Petugas *"
variant="outlined" density="compact"
prepend-inner-icon="ri-nurse-line"
clearable hide-details="auto"
:loading="pegawaiStore.loading"
no-data-text="Memuat petugas..."
/>
</VCol>
</VRow>

<!-- Status auto badge -->
<div class="status-auto-badge">
<div class="sab-icon">
<VIcon icon="ri-book-line" size="15" />
</div>
<div class="flex-grow-1">
<p class="sab-title mb-0">Status: <strong>Edukasi</strong></p>
<p class="sab-sub mb-0">Otomatis pindah ke Edukasi Lanjutan setelah 2 jam</p>
</div>
<VChip color="primary" variant="tonal" size="small" label>Edukasi</VChip>
</div>
</div>
</div>

<!-- ── SECTION: Keluarga & TTD ────────────────────────────────────── -->
<div class="form-section form-section--purple">
<div class="fs-header fs-header--purple">
<VIcon icon="ri-group-line" size="14" />
<span>Keluarga &amp; Tanda Tangan</span>
</div>
<div class="fs-body">
<VTextField
v-model="form.keluarga_pasien"
label="Nama Keluarga Pasien"
variant="outlined" density="compact"
prepend-inner-icon="ri-group-line"
class="mb-3" hide-details="auto"
/>
<SignaturePad v-model="form.ttd_keluarga_pasien" label="Tanda Tangan Keluarga Pasien" :height="140" />
</div>
</div>

</div>
</VCardText>

<!-- ── Footer Actions ───────────────────────────────────────────────── -->
<div class="dlg-footer">
<VBtn variant="outlined" rounded="lg" size="small" @click="close">Batal</VBtn>
<VBtn
color="primary" rounded="lg" class="flex-grow-1"
prepend-icon="ri-save-line"
:loading="saving || store.loading"
@click="handleSave"
>
{{ editItem ? 'Simpan Perubahan' : 'Simpan Data QC' }}
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
background: rgba(var(--v-theme-primary), 0.06);
border: 1px solid rgba(var(--v-theme-primary), 0.18);
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

/* ── Status auto badge ── */
.status-auto-badge {
display: flex; align-items: center; gap: 10px;
padding: 10px 12px; border-radius: 10px;
background: rgba(var(--v-theme-primary), 0.05);
border: 1px solid rgba(var(--v-theme-primary), 0.15);
}
.sab-icon {
width: 32px; height: 32px; flex-shrink: 0; border-radius: 8px;
background: rgba(var(--v-theme-primary), 0.12);
display: flex; align-items: center; justify-content: center;
color: rgb(var(--v-theme-primary));
}
.sab-title { font-size: 0.78rem; font-weight: 600; color: rgba(var(--v-theme-on-surface), 0.8); }
.sab-sub { font-size: 0.65rem; color: rgba(var(--v-theme-on-surface), 0.45); }

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
</style>