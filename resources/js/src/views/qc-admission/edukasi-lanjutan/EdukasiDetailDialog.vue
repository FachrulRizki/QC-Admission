<script setup>
import SignaturePad               from '@/components/SignaturePad.vue'
import { useEdukasiLanjutanStore } from '@/stores/useEdukasiLanjutanStore'
import { usePegawaiStore }         from '@/stores/usePegawaiStore'
import { usePasienStore }          from '@/stores/usePasienStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  patient:    { type: Object, default: null },
  mode:       { type: String, default: 'view' },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store        = useEdukasiLanjutanStore()
const pegawaiStore = usePegawaiStore()
const pasienStore  = usePasienStore()

const tabView = ref('riwayat')   // 'riwayat' | 'baru'
const form    = ref({})
const history = ref([])
const errorMsg   = ref('')
const successMsg = ref('')

// ── Opsi sama persis dengan QCFormDialog ──────────────────────────────────────
const noteOptions = [
  'Kelas 1 Bedah Laki-laki','Kelas 2 Bedah Laki-laki','Kelas 3 Bedah Laki-laki',
  'Kelas 1 Bedah Perempuan','Kelas 2 Bedah Perempuan','Kelas 3 Bedah Perempuan',
  'Kelas 1 Internis Laki-laki','Kelas 2 Internis Laki-laki','Kelas 3 Internis Laki-laki',
  'Kelas 1 Internis Perempuan','Kelas 2 Internis Perempuan','Kelas 3 Internis Perempuan',
  'Kelas 1 Onkologi Laki-laki','Kelas 2 Onkologi Laki-laki','Kelas 3 Onkologi Laki-laki',
  'Kelas 1 Onkologi Perempuan','Kelas 2 Onkologi Perempuan','Kelas 3 Onkologi Perempuan',
  'Kelas 1 Kebidanan','Kelas 2 Kebidanan','Kelas 3 Kebidanan',
  'Kelas 1 Anak','Kelas 2 Anak','Kelas 3 Anak','Kelas VIP',
]

watch(() => props.modelValue, async (open) => {
  if (open && props.patient) {
    tabView.value    = props.mode === 'edit' ? 'baru' : 'riwayat'
    errorMsg.value   = ''
    successMsg.value = ''
    resetForm()
    pegawaiStore.fetch()
    loadHistory()
  }
})
watch(() => props.mode, m => { if (m === 'edit') tabView.value = 'baru' })

function resetForm() {
  const now = new Date()
  const bulanMap = ['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI',
    'JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER']
  const p = n => String(n).padStart(2, '0')
  const fmt = d => `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()}, ${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
  const fmtTime = d => `${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`

  form.value = {
    no_mr:        props.patient?.no_mr        ?? '',
    no_reg:       props.patient?.no_reg       ?? '',
    nama_pasien:  props.patient?.nama_pasien  ?? '',
    jaminan:      props.patient?.jaminan      ?? '',
    tanggal:      fmt(now),
    jam_input:    fmtTime(now),
    bulan:        bulanMap[now.getMonth()],
    edukasi_kamar: props.patient?.edukasi_kamar ?? '',
    note:          null,
    petugas:       null,
    keluarga_pasien:     props.patient?.keluarga_pasien ?? '',
    ttd_keluarga_pasien: '',
    status:        'Menunggu', // selalu Menunggu — berubah ke Selesai hanya via Sync SIMRS
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

  // Selalu CREATE baris baru — setiap edukasi lanjutan = sesi baru
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
    <VCard v-if="patient" rounded="xl">
      <!-- Header -->
      <div class="dialog-header d-flex align-center gap-3 px-5 py-4">
        <VAvatar color="warning" variant="tonal" size="42" rounded="lg">
          <VIcon icon="ri-book-open-line" size="22" />
        </VAvatar>
        <div class="flex-grow-1 min-width-0">
          <p class="text-subtitle-1 font-weight-bold mb-0 text-truncate">Edukasi Lanjutan</p>
          <p class="text-caption text-medium-emphasis mb-0">
            {{ patient.nama_pasien }} · No. MR {{ patient.no_mr }}
          </p>
        </div>
        <VChip color="warning" variant="tonal" size="x-small" class="me-2 flex-shrink-0">
          {{ sesiCount }} sesi
        </VChip>
        <VBtn icon variant="text" size="small" @click="close"><VIcon icon="ri-close-line" /></VBtn>
      </div>
      <VDivider />

      <!-- Tabs -->
      <VTabs v-model="tabView" color="warning" density="compact" class="px-4 pt-2">
        <VTab value="riwayat">
          <VIcon icon="ri-history-line" size="15" class="me-1" />Riwayat ({{ sesiCount }})
        </VTab>
        <VTab value="baru">
          <VIcon icon="ri-add-circle-line" size="15" class="me-1" />Edukasi Lanjutan Baru
        </VTab>
      </VTabs>
      <VDivider />

      <VCardText class="pa-5">
        <VAlert v-if="errorMsg"   type="error"   variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg=''">{{ errorMsg }}</VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">{{ successMsg }}</VAlert>

        <!-- ══ TAB: RIWAYAT ══════════════════════════════════════════════════ -->
        <template v-if="tabView === 'riwayat'">
          <!-- Info pasien -->
          <div class="info-box pa-3 rounded-xl mb-4">
            <p class="sec-label mb-2">Data Pasien</p>
            <VRow dense>
              <VCol cols="4" sm="3">
                <p class="field-label">No. MR</p>
                <p class="field-value font-weight-bold text-primary">{{ patient.no_mr || '—' }}</p>
              </VCol>
              <VCol cols="4" sm="3">
                <p class="field-label">No. Reg</p>
                <p class="field-value">{{ patient.no_reg || '—' }}</p>
              </VCol>
              <VCol cols="12" sm="6">
                <p class="field-label">Nama Pasien</p>
                <p class="field-value font-weight-semibold">{{ patient.nama_pasien || '—' }}</p>
              </VCol>
              <VCol cols="6">
                <p class="field-label">Jaminan</p>
                <p class="field-value">{{ patient.jaminan || '—' }}</p>
              </VCol>
              <VCol cols="6">
                <p class="field-label">Total Sesi Edukasi</p>
                <VChip color="warning" variant="tonal" size="x-small">{{ sesiCount }} kali diedukasi</VChip>
              </VCol>
            </VRow>
          </div>

          <!-- Timeline riwayat -->
          <template v-if="history.length">
            <p class="sec-label mb-3">Riwayat Edukasi Lanjutan</p>
            <div
              v-for="(h, idx) in history"
              :key="h.id"
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
              <VRow dense>
                <VCol cols="6">
                  <p class="field-label">Petugas</p>
                  <p class="field-value">{{ h.petugas || '—' }}</p>
                </VCol>
                <VCol cols="6">
                  <p class="field-label">Ruangan Edukasi</p>
                  <p class="field-value">{{ h.edukasi_kamar || '—' }}</p>
                </VCol>
                <VCol cols="12">
                  <p class="field-label">Note</p>
                  <p class="field-value">{{ h.note || '—' }}</p>
                </VCol>
                <VCol cols="6">
                  <p class="field-label">Keluarga Pasien</p>
                  <p class="field-value">{{ h.keluarga_pasien || '—' }}</p>
                </VCol>
                <VCol cols="6">
                  <p class="field-label">TTD</p>
                  <img v-if="h.ttd_keluarga_pasien" :src="h.ttd_keluarga_pasien" alt="TTD" style="height:32px;border:1px solid #eee;border-radius:4px;margin-top:2px" />
                  <p v-else class="field-value text-medium-emphasis">—</p>
                </VCol>
                <VCol cols="12">
                  <p class="field-label">Waktu Input</p>
                  <p class="field-value text-caption text-medium-emphasis">{{ fmtDate(h.created_at) }}</p>
                </VCol>
              </VRow>
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

        <!-- ══ TAB: FORM BARU — identik dengan QCFormDialog ════════════════ -->
        <template v-else>
          <VAlert type="info" variant="tonal" density="compact" border="start" class="mb-4">
            <div class="text-caption">
              Setiap klik <strong>Simpan</strong> akan membuat <strong>catatan sesi baru</strong>.
              Riwayat semua sesi bisa dilihat di tab Riwayat.
            </div>
          </VAlert>

          <!-- Waktu -->
          <div class="form-section mb-4">
            <p class="sec-label mb-2">Waktu Input</p>
            <VRow dense>
              <VCol cols="7">
                <VTextField v-model="form.tanggal" label="Tanggal" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" prepend-inner-icon="ri-calendar-line" />
              </VCol>
              <VCol cols="5">
                <VTextField v-model="form.jam_input" label="Jam" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" prepend-inner-icon="ri-time-line" />
              </VCol>
            </VRow>
          </div>

          <!-- Data Pasien (readonly, dari QC) -->
          <div class="form-section mb-4">
            <p class="sec-label mb-2">Data Pasien (dari QC)</p>
            <VRow dense>
              <VCol cols="4">
                <VTextField v-model="form.no_mr" label="No. MR" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" />
              </VCol>
              <VCol cols="8">
                <VTextField v-model="form.nama_pasien" label="Nama Pasien" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" />
              </VCol>
            </VRow>
            <VRow dense class="mt-2">
              <VCol cols="5">
                <VTextField v-model="form.jaminan" label="Jaminan" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" />
              </VCol>
              <VCol cols="7">
                <VTextField v-model="form.bulan" label="Bulan" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" />
              </VCol>
            </VRow>
          </div>

          <!-- Data Edukasi — SAMA PERSIS dengan QCFormDialog -->
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
                <!-- Note dropdown persis seperti QCFormDialog -->
                <VSelect
                  v-model="form.note"
                  :items="noteOptions"
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

            <!-- Status toggle — hanya Lanjut Edukasi (tidak ada pilihan Edukasi) -->
            <div class="mt-3">
              <p class="text-caption font-weight-semibold text-medium-emphasis mb-2">Status Edukasi Lanjutan</p>
              <div class="d-flex align-center gap-2 px-3 py-2 rounded-lg" style="background:rgba(var(--v-theme-warning),0.06);border:1px solid rgba(var(--v-theme-warning),0.25)">
                <VIcon icon="ri-book-open-line" size="16" color="warning" />
                <span class="text-caption text-medium-emphasis">Status:</span>
                <VChip color="warning" variant="tonal" size="small" label>Lanjut Edukasi</VChip>
                <span class="text-caption text-disabled">· status tetap Menunggu hingga dapat bed</span>
              </div>
            </div>
          </div>

          <!-- Keluarga & TTD — sama dengan QCFormDialog -->
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

      <VDivider />
      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="close">Tutup</VBtn>
        <template v-if="tabView === 'riwayat'">
          <VBtn color="warning" variant="tonal" rounded="lg" prepend-icon="ri-add-circle-line" @click="tabView = 'baru'">
            Tambah Sesi
          </VBtn>
        </template>
        <template v-else>
          <VBtn
            color="warning" rounded="xl" class="flex-grow-1"
            prepend-icon="ri-save-line"
            :loading="store.loading"
            @click="handleSave"
          >
            Simpan Sesi Edukasi Baru
          </VBtn>
        </template>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dialog-header { background: rgba(var(--v-theme-warning), 0.05); }
.info-box { background: rgba(var(--v-theme-on-surface), 0.03); border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.form-section { padding: 12px; border-radius: 10px; background: rgba(var(--v-theme-on-surface), 0.02); border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.sec-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(var(--v-theme-on-surface), 0.5); margin-bottom: 4px; }
.field-label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em; color: rgba(var(--v-theme-on-surface), 0.5); margin-bottom: 1px; }
.field-value { font-size: 0.875rem; margin-bottom: 0; }
.history-item { background: rgba(var(--v-theme-on-surface), 0.02); border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.history-item--latest { border-color: rgba(var(--v-theme-warning), 0.45) !important; background: rgba(var(--v-theme-warning), 0.04) !important; }
</style>
