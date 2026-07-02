<script setup>
import { useUpSellingStore }   from '@/stores/useUpSellingStore'
import { usePegawaiStore }     from '@/stores/usePegawaiStore'
import { usePasienStore }      from '@/stores/usePasienStore'
import { useMasterDataStore }  from '@/stores/useMasterDataStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store        = useUpSellingStore()
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

// ── Master options (dari useMasterDataStore) ──────────────────────────────────
// ketUpSellingList diakses via masterStore.ketUpSellingList di template

// ── Search pasien ─────────────────────────────────────────────────────────────
const noRegSearch  = ref('')
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
    form.value.no_mr        = hit.no_mr        ?? ''
    form.value.nama_pasien  = hit.nama_pasien  ?? ''
    form.value.ket_bayar    = hit.ket_bayar    ?? ''
    form.value.nama_ruang   = hit.nama_ruang   ?? ''
    form.value.nama_bangsal = hit.nama_bangsal ?? ''
    form.value.kelas        = hit.nama_kelas   ?? ''
    form.value.tgl_daftar   = hit.tgl_daftar   ?? ''
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
    no_reg:         null,
    no_mr:          '',
    nama_pasien:    '',
    ket_bayar:      '',
    nama_ruang:     '',
    nama_bangsal:   '',
    kelas:          '',
    tgl_daftar:     '',
    ket_up_selling: null,
    notes:          '',
    nama_petugas:   null,
  }
}

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.no_reg)         { errorMsg.value = 'NoReg wajib dipilih.'; return }
  if (!form.value.nama_petugas)   { errorMsg.value = 'nama_petugas wajib dipilih.'; return }
  if (!form.value.ket_up_selling) { errorMsg.value = 'Ket_Up_Selling wajib dipilih.'; return }

  saving.value = true
  const now = new Date()
  const p   = n => String(n).padStart(2, '0')
  const jam = `${p(now.getHours())}.${p(now.getMinutes())}.${p(now.getSeconds())}`
  const tgl = `${p(now.getDate())}/${p(now.getMonth()+1)}/${now.getFullYear()}, ${jam}`

  const payload = {
    tanggal:           tgl,
    jam_input:         jam,
    no_reg:            form.value.no_reg,
    no_mr:             form.value.no_mr,
    tgl_daftar:        form.value.tgl_daftar,
    nama_pasien:       form.value.nama_pasien,
    jaminan:           form.value.ket_bayar,
    nama_ruang:        form.value.nama_ruang,
    nama_bangsal:      form.value.nama_bangsal,
    kelas:             form.value.kelas,
    rekomendasi_kelas: form.value.kelas,
    kelas_diambil:     form.value.kelas,
    alasan:            form.value.ket_up_selling,
    petugas:           form.value.nama_petugas,
    note:              form.value.notes,
    status:            'Pending',
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
</script>

<template>
  <VDialog :model-value="modelValue" max-width="520" persistent scrollable @update:model-value="close">
    <VCard rounded="xl">

      <!-- Header -->
      <div class="dlg-header d-flex align-center gap-3 px-5 py-4">
        <VAvatar color="success" variant="tonal" size="44" rounded="lg">
          <VIcon icon="ri-arrow-up-circle-line" size="22" />
        </VAvatar>
        <div class="flex-grow-1 min-width-0">
          <p class="text-subtitle-1 font-weight-bold mb-0">
            {{ editItem ? 'Edit Up Selling' : 'Input Up Selling' }}
          </p>
          <p class="text-caption text-medium-emphasis mb-0">Penawaran upgrade kelas kamar rawat inap</p>
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

        <!-- updateAt -->
        <VTextField
          :model-value="nowDisplay"
          label="updateAt"
          variant="outlined" density="compact" readonly
          prepend-inner-icon="ri-calendar-line"
          bg-color="rgba(var(--v-theme-on-surface), 0.03)"
          class="mb-4" hide-details
        />

        <!-- tgldaftar -->
        <VTextField
          v-model="form.tgl_daftar"
          label="tgldaftar"
          variant="outlined" density="compact" readonly
          prepend-inner-icon="ri-calendar-check-line"
          bg-color="rgba(var(--v-theme-on-surface), 0.03)"
          class="mb-3" hide-details
        />

        <!-- NoMR -->
        <VTextField
          v-model="form.no_mr"
          label="NoMR"
          variant="outlined" density="compact" readonly
          bg-color="rgba(var(--v-theme-on-surface), 0.03)"
          class="mb-3" hide-details
        />

        <!-- NoReg — search -->
        <VAutocomplete
          v-model="form.no_reg"
          v-model:search="noRegSearch"
          :items="pasienStore.optionList"
          item-title="title"
          item-value="value"
          label="NoReg *"
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

        <!-- NamaPasien -->
        <VTextField
          v-model="form.nama_pasien"
          label="NamaPasien"
          variant="outlined" density="compact" readonly
          prepend-inner-icon="ri-user-3-line"
          bg-color="rgba(var(--v-theme-on-surface), 0.03)"
          class="mb-3" hide-details
        />

        <!-- ketBayar -->
        <VTextField
          v-model="form.ket_bayar"
          label="ketBayar"
          variant="outlined" density="compact" readonly
          bg-color="rgba(var(--v-theme-on-surface), 0.03)"
          class="mb-3" hide-details
        />

        <!-- NamaRuang -->
        <VTextField
          v-model="form.nama_ruang"
          label="NamaRuang"
          variant="outlined" density="compact" readonly
          bg-color="rgba(var(--v-theme-on-surface), 0.03)"
          class="mb-3" hide-details
        />

        <!-- NamaBangsal -->
        <VTextField
          v-model="form.nama_bangsal"
          label="NamaBangsal"
          variant="outlined" density="compact" readonly
          bg-color="rgba(var(--v-theme-on-surface), 0.03)"
          class="mb-3" hide-details
        />

        <!-- Kelas -->
        <VTextField
          v-model="form.kelas"
          label="Kelas"
          variant="outlined" density="compact" readonly
          bg-color="rgba(var(--v-theme-on-surface), 0.03)"
          class="mb-3" hide-details
        />

        <!-- Ket_Up_Selling — dari master data -->
        <VSelect
          v-model="form.ket_up_selling"
          :items="masterStore.ketUpSellingList"
          label="Ket_Up_Selling *"
          variant="outlined" density="compact"
          clearable class="mb-3" hide-details="auto"
        />

        <!-- notes -->
        <VTextarea
          v-model="form.notes"
          label="notes"
          variant="outlined" density="compact"
          rows="3" auto-grow
          class="mb-3" hide-details="auto"
        />

        <!-- nama_petugas -->
        <VAutocomplete
          v-model="form.nama_petugas"
          :items="pegawaiStore.namaList"
          label="nama_petugas *"
          variant="outlined" density="compact"
          prepend-inner-icon="ri-nurse-line"
          clearable hide-details="auto"
          :loading="pegawaiStore.loading"
          no-data-text="Memuat petugas..."
        />
      </VCardText>

      <VDivider />
      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" rounded="lg" @click="close">Cancel</VBtn>
        <VBtn
          color="success" rounded="xl" class="flex-grow-1"
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
  background: linear-gradient(135deg, rgba(var(--v-theme-success), 0.06), rgba(var(--v-theme-success), 0.02));
}
</style>
