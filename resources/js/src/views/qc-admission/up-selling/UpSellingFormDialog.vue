<script setup>
import { useUpSellingStore } from '@/stores/useUpSellingStore'
import { usePegawaiStore } from '@/stores/usePegawaiStore'
import { usePasienStore } from '@/stores/usePasienStore'
import { useMasterDataStore } from '@/stores/useMasterDataStore'

const props = defineProps({
modelValue: { type: Boolean, default: false },
editItem: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store = useUpSellingStore()
const pegawaiStore = usePegawaiStore()
const pasienStore = usePasienStore()
const masterStore = useMasterDataStore()

const form = ref(initialForm())
const errorMsg = ref('')
const saving = ref(false)

// Live clock
const nowDisplay = ref('')
let clockTimer = null
function tickClock() {
const d = new Date()
const p = n => String(n).padStart(2, '0')
nowDisplay.value = `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()}, ${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
}

// ── Search pasien ─────────────────────────────────────────────────────────────
const noRegSearch = ref('')
const noRegLoading = ref(false)
let searchTimer = null

watch(noRegSearch, (val) => {
clearTimeout(searchTimer)
if (!val || val.length < 2) { pasienStore.clear(); return }
noRegLoading.value = true
searchTimer = setTimeout(async () => {
await pasienStore.search(val)
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
form.value.ket_bayar = hit.ket_bayar ?? ''
form.value.nama_ruang = hit.nama_ruang ?? ''
form.value.nama_bangsal = hit.nama_bangsal ?? ''
form.value.kelas = hit.nama_kelas ?? ''
form.value.tgl_daftar = hit.tgl_daftar ?? ''
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
no_reg: null,
no_mr: '',
nama_pasien: '',
ket_bayar: '',
nama_ruang: '',
nama_bangsal: '',
kelas: '',
tgl_daftar: '',
ket_up_selling: null,
notes: '',
nama_petugas: null,
}
}

async function handleSave() {
errorMsg.value = ''
if (!form.value.no_reg) { errorMsg.value = 'No. Registrasi wajib dipilih.'; return }
if (!form.value.nama_petugas) { errorMsg.value = 'Petugas wajib dipilih.'; return }
if (!form.value.ket_up_selling) { errorMsg.value = 'Keterangan Up Selling wajib dipilih.'; return }

saving.value = true
const now = new Date()
const p = n => String(n).padStart(2, '0')
const jam = `${p(now.getHours())}.${p(now.getMinutes())}.${p(now.getSeconds())}`
const tgl = `${p(now.getDate())}/${p(now.getMonth()+1)}/${now.getFullYear()}, ${jam}`

const payload = {
tanggal: tgl,
jam_input: jam,
no_reg: form.value.no_reg,
no_mr: form.value.no_mr,
tgl_daftar: form.value.tgl_daftar,
nama_pasien: form.value.nama_pasien,
jaminan: form.value.ket_bayar,
nama_ruang: form.value.nama_ruang,
nama_bangsal: form.value.nama_bangsal,
kelas: form.value.kelas,
rekomendasi_kelas: form.value.kelas,
kelas_diambil: form.value.kelas,
alasan: form.value.ket_up_selling,
petugas: form.value.nama_petugas,
note: form.value.notes,
status: 'Pending',
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

const hasPasien = computed(() => !!form.value.no_reg && !!form.value.nama_pasien)
</script>

<template>
<VDialog :model-value="modelValue" max-width="560" persistent scrollable @update:model-value="close">
<VCard rounded="xl" class="dlg-card overflow-hidden">

<!-- ── Gradient Banner Header ───────────────────────────────────────── -->
<div class="dlg-banner">
<div class="db-blob db-blob--1" /><div class="db-blob db-blob--2" />
<div class="db-inner">
<div class="db-icon">
<VIcon icon="ri-arrow-up-circle-line" size="22" color="white" />
</div>
<div class="db-text">
<p class="db-sub">Up Selling · Rawat Inap</p>
<h3 class="db-title">{{ editItem ? 'Edit Data Up Selling' : 'Input Up Selling' }}</h3>
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

<!-- Error -->
<div v-if="errorMsg" class="px-5 pt-4">
<VAlert type="error" variant="tonal" density="compact" closable @click:close="errorMsg=''">
{{ errorMsg }}
</VAlert>
</div>

<div class="px-5 py-4 d-flex flex-column gap-4">

<!-- ── SECTION: Data Pasien ──────────────────────────────────────── -->
<div class="form-section form-section--primary">
<div class="fs-header fs-header--primary">
<VIcon icon="ri-user-heart-line" size="14" />
<span>Data Pasien</span>
</div>

<!-- Search autocomplete -->
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
<VAvatar color="success" variant="tonal" size="32" rounded="lg" class="me-2">
<span style="font-size:11px;font-weight:700">{{ item.raw?.data?.nama_pasien?.charAt(0) ?? '?' }}</span>
</VAvatar>
</template>
<VListItemTitle class="text-body-2 font-weight-semibold">{{ item.raw?.data?.nama_pasien }}</VListItemTitle>
<VListItemSubtitle class="d-flex gap-1 mt-1 flex-wrap">
<VChip size="x-small" color="success" variant="tonal" label>{{ item.raw?.data?.no_reg }}</VChip>
<span class="text-caption text-medium-emphasis">{{ item.raw?.data?.ket_bayar }} · {{ item.raw?.data?.nama_bangsal }}</span>
</VListItemSubtitle>
</VListItem>
</template>
</VAutocomplete>

<!-- Info chips grid (readonly autofill) -->
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
<span class="icg-val">{{ form.ket_bayar || '—' }}</span>
</div>
<div class="icg-cell">
<span class="icg-lbl"><VIcon icon="ri-door-line" size="10" class="me-1" />Ruang / Bangsal</span>
<span class="icg-val">{{ [form.nama_ruang, form.nama_bangsal].filter(Boolean).join(' / ') || '—' }}</span>
</div>
<div class="icg-cell">
<span class="icg-lbl"><VIcon icon="ri-hotel-bed-line" size="10" class="me-1" />Kelas</span>
<span class="icg-val icg-val--chip">{{ form.kelas || '—' }}</span>
</div>
<div class="icg-cell icg-cell--span2">
<span class="icg-lbl"><VIcon icon="ri-calendar-check-line" size="10" class="me-1" />Tgl. Daftar</span>
<span class="icg-val">{{ form.tgl_daftar || '—' }}</span>
</div>
</div>
</Transition>

<!-- Empty state -->
<div v-if="!hasPasien" class="empty-pasien">
<VIcon icon="ri-user-search-line" size="28" class="mb-1 opacity-30" />
<p class="text-caption text-disabled mb-0">Cari pasien untuk mengisi data otomatis</p>
</div>
</div>

<!-- ── SECTION: Penawaran Up Selling ────────────────────────────── -->
<div class="form-section form-section--success">
<div class="fs-header fs-header--success">
<VIcon icon="ri-arrow-up-circle-line" size="14" />
<span>Penawaran Up Selling</span>
<span class="fs-required-badge">Wajib Diisi</span>
</div>

<VSelect
v-model="form.ket_up_selling"
:items="masterStore.ketUpSellingList"
label="Keterangan Up Selling *"
variant="outlined" density="compact"
prepend-inner-icon="ri-medal-line"
clearable class="mb-3" hide-details="auto"
/>

<VTextarea
v-model="form.notes"
label="Catatan / Keterangan Tambahan"
variant="outlined" density="compact"
rows="2" auto-grow
prepend-inner-icon="ri-sticky-note-line"
hide-details="auto"
/>
</div>

<!-- ── SECTION: Petugas ──────────────────────────────────────────── -->
<div class="form-section form-section--warning">
<div class="fs-header fs-header--warning">
<VIcon icon="ri-nurse-line" size="14" />
<span>Petugas</span>
<span class="fs-required-badge">Wajib Diisi</span>
</div>
<VAutocomplete
v-model="form.nama_petugas"
:items="pegawaiStore.namaList"
label="Nama Petugas *"
variant="outlined" density="compact"
prepend-inner-icon="ri-nurse-line"
clearable hide-details="auto"
:loading="pegawaiStore.loading"
no-data-text="Memuat petugas..."
/>
</div>

</div>
</VCardText>

<!-- ── Footer Actions ───────────────────────────────────────────────── -->
<div class="dlg-footer">
<VBtn variant="outlined" rounded="lg" size="small" @click="close">Batal</VBtn>
<VBtn
color="success" rounded="lg" class="flex-grow-1"
prepend-icon="ri-save-line"
:loading="saving || store.loading"
@click="handleSave"
>
{{ editItem ? 'Simpan Perubahan' : 'Simpan Up Selling' }}
</VBtn>
</div>

</VCard>
</VDialog>
</template>

<style scoped>
/* ── Dialog card ── */
.dlg-card { display: flex; flex-direction: column; max-height: 92dvh; }

/* ── Gradient Banner ── */
.dlg-banner {
flex-shrink: 0; position: relative; overflow: hidden;
background: linear-gradient(135deg, #0369A1 0%, #0EA5E9 55%, #38BDF8 100%);
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

/* ── Form sections ── */
.form-section {
border-radius: 14px;
border: 1.5px solid rgba(var(--v-border-color), var(--v-border-opacity));
overflow: hidden;
transition: box-shadow 0.2s;
}
.form-section:focus-within { box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.08); }
.form-section--primary:focus-within { box-shadow: 0 0 0 3px rgba(var(--v-theme-success), 0.1); }
.form-section--success:focus-within { box-shadow: 0 0 0 3px rgba(var(--v-theme-success), 0.12); }
.form-section--warning:focus-within { box-shadow: 0 0 0 3px rgba(var(--v-theme-warning), 0.12); }

/* Section header strip */
.fs-header {
display: flex; align-items: center; gap: 7px;
padding: 9px 14px;
font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.fs-header--primary { background: rgba(var(--v-theme-success), 0.07); color: rgb(var(--v-theme-success)); }
.fs-header--success { background: rgba(var(--v-theme-success), 0.06); color: rgb(var(--v-theme-success)); }
.fs-header--warning { background: rgba(var(--v-theme-warning), 0.08); color: rgb(var(--v-theme-warning)); }

.fs-required-badge {
margin-left: auto;
font-size: 0.55rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;
padding: 1px 6px; border-radius: 10px;
background: rgba(var(--v-theme-error), 0.12);
color: rgb(var(--v-theme-error));
}

/* Section body */
.form-section > :not(.fs-header) {
padding: 14px;
}

/* ── Info Chip Grid (readonly autofill) ── */
.info-chip-grid {
display: grid;
grid-template-columns: 1fr 1fr;
gap: 1px;
background: rgba(var(--v-border-color), var(--v-border-opacity));
border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
border-radius: 10px;
overflow: hidden;
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
.icg-val {
font-size: 0.82rem; font-weight: 600;
color: rgba(var(--v-theme-on-surface), 0.85);
word-break: break-word;
}
.icg-val--accent { font-weight: 700; color: rgba(var(--v-theme-on-surface), 0.92); }
.icg-val--mono { font-family: monospace; font-size: 0.78rem; letter-spacing: 0.03em; }
.icg-val--chip {
display: inline-flex; align-items: center;
padding: 1px 8px; border-radius: 6px;
background: rgba(var(--v-theme-success), 0.12);
color: rgb(var(--v-theme-success));
font-size: 0.75rem; font-weight: 700; width: fit-content;
}

/* ── Empty state ── */
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

/* ── Scrollable card text ── */
.v-card-text { flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; }

/* ── Transitions ── */
.slide-down-enter-active { transition: all 0.25s cubic-bezier(0.4,0,0.2,1); }
.slide-down-leave-active { transition: all 0.18s ease; }
.slide-down-enter-from { opacity: 0; transform: translateY(-8px); }
.slide-down-leave-to { opacity: 0; transform: translateY(-4px); }
</style>