<script setup>
import { useBatalRanapStore }  from '@/stores/useBatalRanapStore'
import { usePegawaiStore }     from '@/stores/usePegawaiStore'
import { usePasienStore }      from '@/stores/usePasienStore'
import { useMasterDataStore }  from '@/stores/useMasterDataStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store        = useBatalRanapStore()
const pegawaiStore = usePegawaiStore()
const pasienStore  = usePasienStore()
const masterStore  = useMasterDataStore()

const form     = ref(initialForm())
const errorMsg = ref('')
const saving   = ref(false)

// Live clock
const nowDisplay = ref('')
let clockTimer = null
function tickClock() {
  const d = new Date()
  const p = n => String(n).padStart(2, '0')
  nowDisplay.value = `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()}, ${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
}

// ── Opsi ─────────────────────────────────────────────────────────────────────
// keteranganOpts & statusOkOpts diambil dari masterStore (bukan hardcoded)
const statusClosingOpts = [
  { title: '👍 Siap Closing',        value: 'Siap Closing',        color: 'success' },
  { title: '⏳ Belum Siap Closing',  value: 'Belum Siap Closing',  color: 'error'   },
]

// ── Pasien search ─────────────────────────────────────────────────────────────
const noRegSearch  = ref('')
const noRegLoading = ref(false)
let   searchTimer  = null

watch(noRegSearch, (val) => {
  clearTimeout(searchTimer)
  if (!val || val.trim().length < 2) { pasienStore.clear(); return }
  noRegLoading.value = true
  searchTimer = setTimeout(async () => {
    await pasienStore.search(val.trim())
    noRegLoading.value = false
  }, 300)
})

// Autofill saat pasien dipilih
watch(() => form.value.no_reg, async (val) => {
  if (!val) return
  const hit = pasienStore.results.find(p => p.no_reg === val)
    ?? await pasienStore.lookup(val)
  if (hit) {
    form.value.no_mr       = hit.no_mr        ?? ''
    form.value.nama_pasien = hit.nama_pasien  ?? ''
    form.value.tgl_daftar  = hit.tgl_daftar   ?? ''
    form.value.jam_daftar  = hit.jam_daftar   ?? ''
    form.value.diagnosa    = hit.diagnosa     ?? ''
    form.value.ruangan     = hit.nama_bangsal ?? hit.nama_ruang ?? ''
  }
})

watch(() => props.modelValue, (open) => {
  if (open) {
    form.value        = props.editItem ? { ...initialForm(), ...props.editItem } : initialForm()
    errorMsg.value    = ''
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
    no_reg:             null,
    no_mr:              '',
    nama_pasien:        '',
    tgl_daftar:         '',
    jam_daftar:         '',
    keterangan_batal:   null,
    status_ok:          null,
    status_closing:     null,
    ketersediaan_kamar: '',
    diagnosa:           '',
    note:               '',
    petugas:            null,
    ruangan:            '',
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
  if (!form.value.no_reg)           { errorMsg.value = 'Pasien wajib dipilih.'; return }
  if (!form.value.keterangan_batal) { errorMsg.value = 'Keterangan batal wajib dipilih.'; return }
  if (!form.value.petugas)          { errorMsg.value = 'Petugas wajib dipilih.'; return }

  saving.value = true
  const now = new Date()
  const payload = {
    ...form.value,
    tanggal:   fmtTgl(now),
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
</script>

<template>
  <VDialog :model-value="modelValue" max-width="600" persistent scrollable @update:model-value="close">
    <VCard rounded="xl">

      <!-- ── Header ─────────────────────────────────────────────────────────── -->
      <div class="dlg-header d-flex align-center gap-3 px-5 py-4">
        <VAvatar color="error" variant="tonal" size="44" rounded="lg">
          <VIcon icon="ri-close-circle-line" size="22" />
        </VAvatar>
        <div class="flex-grow-1 min-width-0">
          <p class="text-subtitle-1 font-weight-bold mb-0">
            {{ editItem ? 'Edit Batal Ranap' : 'Input Pasien Batal Ranap' }}
          </p>
          <p class="text-caption text-medium-emphasis mb-0">
            Pencatatan pembatalan rawat inap pasien IGD
          </p>
        </div>
        <VBtn icon variant="text" size="small" @click="close">
          <VIcon icon="ri-close-line" />
        </VBtn>
      </div>
      <VDivider />

      <VCardText class="pa-5">
        <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg=''">
          {{ errorMsg }}
        </VAlert>

        <!-- ── Waktu Input ──────────────────────────────────────────────────── -->
        <div class="fsec fsec--muted mb-4">
          <p class="fsec-label">
            <VIcon icon="ri-time-line" size="13" class="me-1" />Waktu Input
          </p>
          <VRow dense>
            <VCol cols="7">
              <VTextField
                :model-value="nowDisplay"
                label="Tanggal"
                variant="outlined" density="compact" readonly hide-details
                prepend-inner-icon="ri-calendar-line"
                bg-color="rgba(var(--v-theme-on-surface), 0.03)"
              />
            </VCol>
            <VCol cols="5">
              <VTextField
                :model-value="nowDisplay.split(', ')[1] ?? ''"
                label="Jam Input"
                variant="outlined" density="compact" readonly hide-details
                prepend-inner-icon="ri-time-line"
                bg-color="rgba(var(--v-theme-on-surface), 0.03)"
              />
            </VCol>
          </VRow>
        </div>

        <!-- ── Data Pasien IGD (autofill dari search) ──────────────────────── -->
        <div class="fsec mb-4">
          <p class="fsec-label">
            <VIcon icon="ri-user-heart-line" size="13" class="me-1" />Data Pasien
          </p>

          <!-- Search -->
          <VAutocomplete
            v-model="form.no_reg"
            v-model:search="noRegSearch"
            :items="pasienStore.optionList"
            item-title="title"
            item-value="value"
            label="NoReg (cari No. MR / No. Reg / Nama Pasien) *"
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

          <!-- tgldaftar & jamdaftar -->
          <VRow dense class="mb-2">
            <VCol cols="7">
              <VTextField
                v-model="form.tgl_daftar"
                label="tgldaftar"
                variant="outlined" density="compact" readonly hide-details
                prepend-inner-icon="ri-calendar-check-line"
                bg-color="rgba(var(--v-theme-on-surface), 0.03)"
              />
            </VCol>
            <VCol cols="5">
              <VTextField
                v-model="form.jam_daftar"
                label="jamdaftar"
                variant="outlined" density="compact" readonly hide-details
                prepend-inner-icon="ri-time-line"
                bg-color="rgba(var(--v-theme-on-surface), 0.03)"
              />
            </VCol>
          </VRow>

          <!-- NoMR + NamaPasien -->
          <VRow dense>
            <VCol cols="4">
              <VTextField
                v-model="form.no_mr"
                label="NoMR"
                variant="outlined" density="compact" readonly hide-details
                bg-color="rgba(var(--v-theme-on-surface), 0.03)"
              />
            </VCol>
            <VCol cols="8">
              <VTextField
                v-model="form.nama_pasien"
                label="NamaPasien"
                variant="outlined" density="compact" readonly hide-details
                prepend-inner-icon="ri-user-3-line"
                bg-color="rgba(var(--v-theme-on-surface), 0.03)"
              />
            </VCol>
          </VRow>
        </div>

        <!-- ── Detail Batal ─────────────────────────────────────────────────── -->
        <div class="fsec mb-4">
          <p class="fsec-label">
            <VIcon icon="ri-close-circle-line" size="13" class="me-1" />Keterangan Batal
          </p>

          <!-- Keterangan_Batal -->
          <VSelect
            v-model="form.keterangan_batal"
            :items="masterStore.keteranganBatalList"
            label="Keterangan_Batal *"
            variant="outlined" density="compact"
            prepend-inner-icon="ri-close-circle-line"
            clearable class="mb-3" hide-details="auto"
          />

          <!-- Status OK -->
          <VSelect
            v-model="form.status_ok"
            :items="masterStore.statusOkList"
            label="Status OK"
            variant="outlined" density="compact"
            clearable class="mb-3" hide-details="auto"
            :prepend-inner-icon="form.status_ok === 'Bedah' ? 'ri-surgical-mask-line' : form.status_ok === 'Non Bedah' ? 'ri-hospital-line' : 'ri-question-line'"
          />

          <!-- Ketersediaan Kamar -->
          <VTextField
            v-model="form.ketersediaan_kamar"
            label="Ketersediaan Kamar Saat Ini"
            variant="outlined" density="compact"
            prepend-inner-icon="ri-door-line"
            class="mb-3" hide-details="auto"
          />

          <!-- Diagnosa -->
          <VTextField
            v-model="form.diagnosa"
            label="Diagnosa"
            variant="outlined" density="compact"
            prepend-inner-icon="ri-stethoscope-line"
            class="mb-3" hide-details="auto"
          />
        </div>

        <!-- ── Petugas & Note ──────────────────────────────────────────────── -->
        <div class="fsec mb-4">
          <p class="fsec-label">
            <VIcon icon="ri-nurse-line" size="13" class="me-1" />Petugas & Catatan
          </p>

          <!-- Note — textarea -->
          <VTextarea
            v-model="form.note"
            label="Note"
            variant="outlined" density="compact"
            rows="3" auto-grow
            class="mb-3" hide-details="auto"
          />

          <!-- Petugas -->
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
        </div>

        <!-- ── Status Closing ──────────────────────────────────────────────── -->
        <div class="fsec">
          <p class="fsec-label">
            <VIcon icon="ri-checkbox-circle-line" size="13" class="me-1" />status_Closing
          </p>

          <!-- Visual selector seperti AppSheet -->
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

        <!-- Info verifikasi -->
        <div v-if="!editItem" class="mt-4 d-flex align-start gap-2 px-3 py-2 rounded-lg"
          style="background:rgba(var(--v-theme-info),0.06);border:1px solid rgba(var(--v-theme-info),0.2)">
          <VIcon icon="ri-information-line" size="15" color="info" />
          <span class="text-caption text-medium-emphasis">
            Setelah disimpan, gunakan aksi <strong>Verifikasi</strong> pada data untuk
            konfirmasi dan update status bed management IGD.
          </span>
        </div>
      </VCardText>

      <VDivider />
      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" rounded="lg" @click="close">Cancel</VBtn>
        <VBtn
          color="error" rounded="xl" class="flex-grow-1"
          prepend-icon="ri-save-line"
          :loading="saving || store.loading"
          @click="handleSave"
        >
          Save
        </VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dlg-header {
  background: linear-gradient(135deg, rgba(var(--v-theme-error), 0.06), rgba(var(--v-theme-error), 0.02));
}
.fsec {
  padding: 14px;
  border-radius: 12px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.fsec--muted {
  background: rgba(var(--v-theme-surface-variant), 0.3);
  border-color: transparent;
}
.fsec-label {
  font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.08em; color: rgba(var(--v-theme-on-surface), 0.45);
  margin-bottom: 10px; display: flex; align-items: center;
}

/* Status Closing selector */
.closing-option {
  border: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
  transition: all 0.18s;
}
.closing-option:hover {
  border-color: rgba(var(--v-theme-primary), 0.35);
  background: rgba(var(--v-theme-primary), 0.04);
}
.closing-option--active  { border-width: 2px !important; }
.closing-option--success {
  border-color: rgb(var(--v-theme-success)) !important;
  background: rgba(var(--v-theme-success), 0.07) !important;
}
.closing-option--error {
  border-color: rgb(var(--v-theme-error)) !important;
  background: rgba(var(--v-theme-error), 0.07) !important;
}
</style>
