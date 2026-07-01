<script setup>
/**
 * BatalRanapDetailDialog
 * - Tab Detail: info lengkap pasien batal ranap
 * - Tab Verifikasi: set status_ok → Bedah | Non Bedah (sesuai requirement — tidak ada Pending)
 */
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item:       { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'verified', 'edit'])

const store      = useBatalRanapStore()
const tabView    = ref('detail')   // 'detail' | 'verifikasi'
const verifStatus = ref(null)
const verifNote   = ref('')
const saving      = ref(false)
const errorMsg    = ref('')

watch(() => props.modelValue, (open) => {
  if (open && props.item) {
    tabView.value    = 'detail'
    verifStatus.value = props.item.status_ok ?? null
    verifNote.value   = props.item.note ?? ''
    errorMsg.value    = ''
  }
})

function statusColor(s) {
  return { Bedah: 'success', 'Non Bedah': 'info' }[s] ?? 'secondary'
}
function statusLabel(s) {
  if (!s) return 'Belum Diverifikasi'
  return s
}
function statusIcon(s) {
  return { Bedah: 'ri-surgical-mask-line', 'Non Bedah': 'ri-hospital-line' }[s] ?? 'ri-time-line'
}
function fmtDate(d) {
  if (!d) return '—'
  try { return new Date(d).toLocaleString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) }
  catch { return d }
}

async function saveVerifikasi() {
  if (!verifStatus.value) { errorMsg.value = 'Status wajib dipilih.'; return }
  saving.value   = true
  errorMsg.value = ''
  try {
    const result = await store.verifikasi(props.item.id, {
      status_ok: verifStatus.value,
      note:      verifNote.value,
    })
    if (result?.success) {
      emit('verified')
      close()
    } else {
      errorMsg.value = result?.message ?? 'Gagal menyimpan verifikasi.'
    }
  } catch {
    errorMsg.value = 'Terjadi kesalahan.'
  } finally {
    saving.value = false
  }
}

function close() { emit('update:modelValue', false) }
</script>

<template>
  <VDialog :model-value="modelValue" max-width="500" scrollable @update:model-value="close">
    <VCard v-if="item" rounded="xl">
      <!-- Header -->
      <div class="dialog-header d-flex align-center gap-3 px-5 py-4">
        <VAvatar color="error" variant="tonal" size="42" rounded="lg">
          <VIcon icon="ri-close-circle-line" size="22" />
        </VAvatar>
        <div class="flex-grow-1 min-width-0">
          <p class="text-subtitle-1 font-weight-bold mb-0 text-truncate">{{ item.nama_pasien }}</p>
          <p class="text-caption text-medium-emphasis mb-0">{{ item.no_reg }} · {{ item.keterangan_batal }}</p>
        </div>
        <!-- Status chip -->
        <VChip
          :color="statusColor(item.status_ok)"
          variant="tonal" size="small"
          :prepend-icon="statusIcon(item.status_ok)"
          class="flex-shrink-0 me-1"
        >
          {{ statusLabel(item.status_ok) }}
        </VChip>
        <VBtn icon variant="text" size="small" @click="close"><VIcon icon="ri-close-line" /></VBtn>
      </div>
      <VDivider />

      <!-- Tabs -->
      <VTabs v-model="tabView" color="error" density="compact" class="px-4 pt-2">
        <VTab value="detail">
          <VIcon icon="ri-file-list-3-line" size="15" class="me-1" />Detail
        </VTab>
        <VTab value="verifikasi">
          <VIcon icon="ri-checkbox-circle-line" size="15" class="me-1" />Verifikasi Status
        </VTab>
      </VTabs>
      <VDivider />

      <VCardText class="pa-5">

        <!-- ══ TAB: DETAIL ════════════════════════════════════════════════════ -->
        <template v-if="tabView === 'detail'">
          <!-- Grid info -->
          <VRow dense>
            <VCol cols="6">
              <div class="info-field">
                <p class="field-label">Tanggal Input</p>
                <p class="field-value">{{ item.tanggal }}</p>
              </div>
            </VCol>
            <VCol cols="6">
              <div class="info-field">
                <p class="field-label">No. Reg</p>
                <p class="field-value font-weight-bold">{{ item.no_reg }}</p>
              </div>
            </VCol>
            <VCol cols="12">
              <div class="info-field">
                <p class="field-label">Nama Pasien</p>
                <p class="field-value font-weight-semibold text-body-1">{{ item.nama_pasien || '—' }}</p>
              </div>
            </VCol>
            <VCol cols="6">
              <div class="info-field">
                <p class="field-label">Keterangan Batal</p>
                <p class="field-value">{{ item.keterangan_batal || '—' }}</p>
              </div>
            </VCol>
            <VCol cols="6">
              <div class="info-field">
                <p class="field-label">Status</p>
                <VChip :color="statusColor(item.status_ok)" size="small" variant="tonal" :prepend-icon="statusIcon(item.status_ok)">
                  {{ statusLabel(item.status_ok) }}
                </VChip>
              </div>
            </VCol>
            <VCol cols="6">
              <div class="info-field">
                <p class="field-label">Ruangan</p>
                <p class="field-value">{{ item.ruangan || '—' }}</p>
              </div>
            </VCol>
            <VCol cols="6">
              <div class="info-field">
                <p class="field-label">No. Bed</p>
                <p class="field-value">{{ item.bed_id || '—' }}</p>
              </div>
            </VCol>
            <VCol cols="6">
              <div class="info-field">
                <p class="field-label">Diagnosa</p>
                <p class="field-value">{{ item.diagnosa || '—' }}</p>
              </div>
            </VCol>
            <VCol cols="6">
              <div class="info-field">
                <p class="field-label">Ketersediaan Kamar</p>
                <p class="field-value">{{ item.ketersediaan_kamar || '—' }}</p>
              </div>
            </VCol>
            <VCol cols="6">
              <div class="info-field">
                <p class="field-label">Petugas</p>
                <p class="field-value d-flex align-center gap-1">
                  <VIcon icon="ri-user-3-line" size="14" />{{ item.petugas || '—' }}
                </p>
              </div>
            </VCol>
            <VCol cols="6">
              <div class="info-field">
                <p class="field-label">Waktu Input</p>
                <p class="field-value text-caption">{{ fmtDate(item.created_at) }}</p>
              </div>
            </VCol>
            <VCol v-if="item.note" cols="12">
              <div class="info-field">
                <p class="field-label">Catatan</p>
                <p class="field-value">{{ item.note }}</p>
              </div>
            </VCol>
          </VRow>
        </template>

        <!-- ══ TAB: VERIFIKASI ════════════════════════════════════════════════ -->
        <template v-else>
          <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg=''">
            {{ errorMsg }}
          </VAlert>

          <!-- Pasien info ringkas -->
          <div class="info-box pa-3 rounded-xl mb-4">
            <div class="d-flex align-center gap-3">
              <VAvatar color="error" variant="tonal" size="40" rounded="lg">
                <VIcon icon="ri-user-heart-line" size="20" />
              </VAvatar>
              <div>
                <p class="text-body-2 font-weight-bold mb-0">{{ item.nama_pasien }}</p>
                <p class="text-caption text-medium-emphasis mb-0">{{ item.no_reg }} · {{ item.tanggal }}</p>
              </div>
            </div>
          </div>

          <!-- Status pilihan: HANYA Bedah / Non Bedah -->
          <p class="text-caption font-weight-semibold text-medium-emphasis mb-3">Status Verifikasi</p>

          <VRow dense class="mb-4">
            <!-- Bedah -->
            <VCol cols="6">
              <div
                class="status-option pa-4 rounded-xl cursor-pointer text-center"
                :class="verifStatus === 'Bedah' ? 'status-option--active status-option--bedah' : ''"
                @click="verifStatus = 'Bedah'"
              >
                <VAvatar
                  :color="verifStatus === 'Bedah' ? 'success' : 'secondary'"
                  variant="tonal" size="44" rounded="lg"
                  class="mb-2"
                >
                  <VIcon icon="ri-surgical-mask-line" size="22" />
                </VAvatar>
                <p class="text-subtitle-2 font-weight-bold mb-0" :class="verifStatus === 'Bedah' ? 'text-success' : ''">Bedah</p>
                <p class="text-caption text-medium-emphasis mb-0">Pasien butuh tindakan bedah</p>
              </div>
            </VCol>
            <!-- Non Bedah -->
            <VCol cols="6">
              <div
                class="status-option pa-4 rounded-xl cursor-pointer text-center"
                :class="verifStatus === 'Non Bedah' ? 'status-option--active status-option--nonbedah' : ''"
                @click="verifStatus = 'Non Bedah'"
              >
                <VAvatar
                  :color="verifStatus === 'Non Bedah' ? 'info' : 'secondary'"
                  variant="tonal" size="44" rounded="lg"
                  class="mb-2"
                >
                  <VIcon icon="ri-hospital-line" size="22" />
                </VAvatar>
                <p class="text-subtitle-2 font-weight-bold mb-0" :class="verifStatus === 'Non Bedah' ? 'text-info' : ''">Non Bedah</p>
                <p class="text-caption text-medium-emphasis mb-0">Rawat inap tanpa bedah</p>
              </div>
            </VCol>
          </VRow>

          <VAlert v-if="verifStatus === 'Bedah'" type="success" variant="tonal" density="compact" class="mb-4 text-caption">
            <VIcon icon="ri-information-line" class="me-1" size="14" />
            Status bed di ruangan <strong>{{ item.ruangan || '—' }}</strong> akan diupdate ke "Tersedia".
          </VAlert>

          <VTextField
            v-model="verifNote"
            label="Catatan Verifikasi (opsional)"
            variant="outlined" density="compact"
            prepend-inner-icon="ri-sticky-note-line"
          />
        </template>
      </VCardText>

      <VDivider />
      <div class="d-flex gap-3 px-5 py-4 flex-wrap">
        <VBtn variant="outlined" rounded="lg" @click="close">Tutup</VBtn>

        <template v-if="tabView === 'detail'">
          <VBtn color="primary" variant="tonal" rounded="lg" prepend-icon="ri-pencil-line" @click="emit('edit', item); close()">Edit</VBtn>
          <VBtn color="success" rounded="lg" class="flex-grow-1" prepend-icon="ri-checkbox-circle-line" @click="tabView = 'verifikasi'">
            Verifikasi Status
          </VBtn>
        </template>
        <template v-else>
          <VBtn
            :color="verifStatus === 'Bedah' ? 'success' : verifStatus === 'Non Bedah' ? 'info' : 'secondary'"
            rounded="xl" class="flex-grow-1"
            prepend-icon="ri-save-line"
            :loading="saving"
            :disabled="!verifStatus"
            @click="saveVerifikasi"
          >
            Simpan Verifikasi
          </VBtn>
        </template>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dialog-header { background: rgba(var(--v-theme-error), 0.04); }
.info-box { background: rgba(var(--v-theme-on-surface), 0.03); border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.info-field { padding: 6px 0; }
.field-label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em; color: rgba(var(--v-theme-on-surface), 0.5); margin-bottom: 2px; }
.field-value { font-size: 0.875rem; font-weight: 500; margin-bottom: 0; }

.status-option {
  border: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
  transition: all 0.2s;
}
.status-option:hover { border-color: rgba(var(--v-theme-primary), 0.4); background: rgba(var(--v-theme-primary), 0.04); }
.status-option--active { border-width: 2px !important; }
.status-option--bedah { border-color: rgb(var(--v-theme-success)) !important; background: rgba(var(--v-theme-success), 0.06) !important; }
.status-option--nonbedah { border-color: rgb(var(--v-theme-info)) !important; background: rgba(var(--v-theme-info), 0.06) !important; }
</style>
