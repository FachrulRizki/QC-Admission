<script setup>
import { useQualityControlStore } from '@/stores/useQualityControlStore'
import { usePegawaiStore }        from '@/stores/usePegawaiStore'
import { usePasienStore }         from '@/stores/usePasienStore'
import { useMasterDataStore }     from '@/stores/useMasterDataStore'
import SignaturePad               from '@/components/SignaturePad.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store        = useQualityControlStore()
const pegawaiStore = usePegawaiStore()
const pasienStore  = usePasienStore()
const masterStore  = useMasterDataStore()

const form     = ref(initialForm())
const errorMsg = ref('')
const saving   = ref(false)

// ── Live clock (hanya untuk display waktu input, bukan timer) ─────────────────
const nowDisplay = ref('')
let clockTimer   = null

function tickClock() {
  const d = new Date()
  const p = n => String(n).padStart(2, '0')
  nowDisplay.value = `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`
}

// ── Pasien search ─────────────────────────────────────────────────────────────
const noRegSearch  = ref('')
const noRegLoading = ref(false)
let   _debounce    = null

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
    form.value.no_mr       = hit.no_mr       ?? ''
    form.value.nama_pasien = hit.nama_pasien ?? ''
    form.value.jaminan     = hit.ket_bayar   ?? ''
    form.value.status_ket  = hit.keterangan  ?? ''
    form.value.tgl_daftar  = hit.tgl_daftar  ?? ''
    form.value.jam_daftar  = hit.jam_daftar  ?? ''
  }
})

// ── Master options (dari useMasterDataStore, bukan hardcoded) ─────────────────
// noteOptions diakses langsung via masterStore.noteKamarList di template

// ── Lifecycle ─────────────────────────────────────────────────────────────────
watch(() => props.modelValue, (open) => {
  if (open) {
    form.value     = props.editItem ? { ...initialForm(), ...props.editItem } : initialForm()
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
  if (!form.value.no_reg)  { errorMsg.value = 'No. Registrasi wajib dipilih.'; return }
  if (!form.value.petugas) { errorMsg.value = 'Petugas wajib dipilih.'; return }

  saving.value = true
  const now = new Date()
  const payload = {
    ...form.value,
    tanggal:   fmt(now),
    jam_input: fmtTime(now),
    status:    'Edukasi', // selalu Edukasi — pindah ke Edukasi Lanjutan via scheduler setelah >= 2 jam
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
</script>

<template>
  <VDialog :model-value="modelValue" max-width="600" persistent scrollable @update:model-value="close">
    <VCard rounded="xl">

      <!-- ── Header ─────────────────────────────────────────────────────────── -->
      <div class="dlg-header d-flex align-center gap-3 px-5 py-4">
        <VAvatar color="primary" variant="tonal" size="44" rounded="lg">
          <VIcon icon="ri-shield-check-line" size="22" />
        </VAvatar>
        <div class="flex-grow-1 min-width-0">
          <p class="text-subtitle-1 font-weight-bold mb-0">
            {{ editItem ? 'Edit Quality Control' : 'Input Quality Control' }}
          </p>
          <p class="text-caption text-medium-emphasis mb-0">
            Setelah disimpan, pasien otomatis masuk Edukasi Lanjutan setelah 2 jam
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

        <!-- Info auto-trigger -->
        <VAlert v-if="!editItem" type="info" variant="tonal" density="compact" border="start" class="mb-4">
          <div class="text-caption d-flex align-center gap-1 flex-wrap">
            <VIcon icon="ri-timer-flash-line" size="14" />
            <span>
              Pasien dicatat dengan status <strong>Edukasi</strong>.
              Sistem otomatis memindahkan ke <strong>Edukasi Lanjutan</strong> setelah <strong>2 jam</strong> sejak data disimpan.
            </span>
          </div>
        </VAlert>

        <!-- ── Waktu Input ──────────────────────────────────────────────────── -->
        <div class="fsec fsec--muted mb-4">
          <p class="fsec-label">Waktu Input</p>
          <VTextField
            :model-value="nowDisplay"
            label="Tanggal & Jam Input (otomatis)"
            variant="outlined" density="compact" readonly
            prepend-inner-icon="ri-calendar-line"
            bg-color="rgba(var(--v-theme-on-surface), 0.03)"
            hide-details
          />
        </div>

        <!-- ── Data Pasien IGD ──────────────────────────────────────────────── -->
        <div class="fsec mb-4">
          <p class="fsec-label">
            <VIcon icon="ri-user-heart-line" size="13" class="me-1" />Data Pasien IGD
          </p>

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

          <VRow dense>
            <VCol cols="4">
              <VTextField
                v-model="form.no_mr" label="No. MR"
                variant="outlined" density="compact" readonly hide-details
                bg-color="rgba(var(--v-theme-on-surface), 0.03)"
              />
            </VCol>
            <VCol cols="8">
              <VTextField
                v-model="form.nama_pasien" label="Nama Pasien"
                variant="outlined" density="compact" readonly hide-details
                prepend-inner-icon="ri-user-3-line"
                bg-color="rgba(var(--v-theme-on-surface), 0.03)"
              />
            </VCol>
          </VRow>

          <VRow dense class="mt-2">
            <VCol cols="5">
              <VTextField
                v-model="form.jaminan" label="Jaminan"
                variant="outlined" density="compact" readonly hide-details
                bg-color="rgba(var(--v-theme-on-surface), 0.03)"
              />
            </VCol>
            <VCol cols="7">
              <VTextField
                v-model="form.status_ket" label="Status Pasien"
                variant="outlined" density="compact" readonly hide-details
                prepend-inner-icon="ri-information-line"
                bg-color="rgba(var(--v-theme-on-surface), 0.03)"
              />
            </VCol>
          </VRow>

          <VRow dense class="mt-2">
            <VCol cols="6">
              <VTextField
                v-model="form.tgl_daftar" label="Tgl. Daftar Pasien"
                variant="outlined" density="compact" readonly hide-details
                prepend-inner-icon="ri-calendar-check-line"
                bg-color="rgba(var(--v-theme-on-surface), 0.03)"
              />
            </VCol>
            <VCol cols="6">
              <VTextField
                v-model="form.jam_daftar" label="Jam Daftar"
                variant="outlined" density="compact" readonly hide-details
                prepend-inner-icon="ri-time-line"
                bg-color="rgba(var(--v-theme-on-surface), 0.03)"
              />
            </VCol>
          </VRow>
        </div>

        <!-- ── Data QC ─────────────────────────────────────────────────────── -->
        <div class="fsec mb-4">
          <p class="fsec-label">
            <VIcon icon="ri-hospital-line" size="13" class="me-1" />Data Quality Control
          </p>

          <VTextField
            v-model="form.edukasi_kamar"
            label="Edukasi Kamar / Ruangan"
            variant="outlined" density="compact"
            prepend-inner-icon="ri-hospital-line"
            class="mb-3" hide-details="auto"
          />

          <VRow dense>
            <VCol cols="6">
              <VSelect
                v-model="form.note"
                :items="masterStore.noteKamarList"
                label="Note / Kamar"
                variant="outlined" density="compact"
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

          <!-- Status info — hanya Edukasi, tidak ada pilihan -->
          <div class="mt-3 d-flex align-center gap-2 px-3 py-2 rounded-lg" style="background:rgba(var(--v-theme-primary),0.05)">
            <VIcon icon="ri-book-line" size="16" color="primary" />
            <span class="text-caption text-medium-emphasis">Status QC:</span>
            <VChip color="primary" variant="tonal" size="small" label>Edukasi</VChip>
            <span class="text-caption text-disabled">· auto pindah ke Edukasi Lanjutan setelah 2 jam</span>
          </div>
        </div>

        <!-- ── Keluarga & TTD ──────────────────────────────────────────────── -->
        <div class="fsec">
          <p class="fsec-label">
            <VIcon icon="ri-group-line" size="13" class="me-1" />Keluarga & Tanda Tangan
          </p>
          <VTextField
            v-model="form.keluarga_pasien"
            label="Nama Keluarga Pasien"
            variant="outlined" density="compact"
            prepend-inner-icon="ri-group-line"
            class="mb-3" hide-details="auto"
          />
          <SignaturePad v-model="form.ttd_keluarga_pasien" label="Tanda Tangan Keluarga Pasien" :height="140" />
        </div>

      </VCardText>

      <VDivider />
      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" rounded="lg" @click="close">Batal</VBtn>
        <VBtn
          color="primary" rounded="xl" class="flex-grow-1"
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
.dlg-header {
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.06), rgba(var(--v-theme-primary), 0.02));
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
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgba(var(--v-theme-on-surface), 0.45);
  margin-bottom: 10px;
  display: flex;
  align-items: center;
}
</style>
