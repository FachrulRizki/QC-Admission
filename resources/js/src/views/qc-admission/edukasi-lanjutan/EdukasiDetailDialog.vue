<script setup>
/**
 * EdukasiDetailDialog — form edit/view untuk Edukasi Lanjutan.
 * Data pasien di-trigger dari QC (no_mr, no_reg, nama, jaminan, dll).
 * Mirip QCFormDialog tapi field yang bisa diisi: edukasi_kamar, note,
 * petugas, keluarga_pasien, ttd_keluarga_pasien, status.
 */
import SignaturePad from '@/components/SignaturePad.vue'
import { useEdukasiLanjutanStore } from '@/stores/useEdukasiLanjutanStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  patient:    { type: Object, default: null },   // data dari card (sudah ter-populate dari QC)
  mode:       { type: String, default: 'view' }, // 'view' | 'edit'
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store     = useEdukasiLanjutanStore()
const isEdit    = ref(false)
const form      = ref({})
const errorMsg  = ref('')
const successMsg = ref('')

const petugasOptions = ['Nurul', 'AYU Putri Anisa', 'Reskim', 'Mulbagus Koyum', 'Abdul Hayyi']
const noteOptions    = ['Pasien mengerti', 'Keluarga hadir', 'Sudah menjelaskan kelas', 'Dirujuk', 'Menunggu kamar']
const statusOptions  = ['Menunggu', 'Selesai']

// ── Buka dialog ───────────────────────────────────────────────────────────────
watch(() => props.modelValue, open => {
  if (open && props.patient) {
    form.value       = { ...props.patient }
    isEdit.value     = props.mode === 'edit'
    errorMsg.value   = ''
    successMsg.value = ''
  }
})
watch(() => props.mode, m => { isEdit.value = m === 'edit' })

// ── Simpan ────────────────────────────────────────────────────────────────────
async function handleSave() {
  errorMsg.value = ''
  if (!form.value.petugas)               { errorMsg.value = 'Petugas wajib dipilih.'; return }
  if (!form.value.keluarga_pasien?.trim()) { errorMsg.value = 'Nama keluarga pasien wajib diisi.'; return }

  // Simulasi simpan (ganti dengan store.update saat API ready)
  successMsg.value = 'Data berhasil disimpan!'
  emit('saved', { ...form.value })
  setTimeout(() => close(), 500)
}

function close() { emit('update:modelValue', false) }
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="560"
    persistent
    scrollable
    @update:model-value="close"
  >
    <VCard v-if="patient" rounded="lg">

      <!-- ── Header ─────────────────────────────────────────────────────── -->
      <div class="dialog-header d-flex align-center gap-3 px-5 py-4">
        <VAvatar color="warning" variant="tonal" size="40" rounded="lg">
          <VIcon icon="ri-book-open-line" size="20" />
        </VAvatar>
        <div class="flex-grow-1 min-width-0">
          <p class="text-subtitle-1 font-weight-bold mb-0 text-truncate">
            {{ isEdit ? 'Edit Edukasi Lanjutan' : 'Detail Edukasi Lanjutan' }}
          </p>
          <p class="text-caption text-medium-emphasis mb-0">
            Trigger dari data QC · {{ patient.bulan }}
          </p>
        </div>
        <VBtn icon variant="text" size="small" @click="close">
          <VIcon icon="ri-close-line" />
        </VBtn>
      </div>

      <VDivider />

      <VCardText class="pa-5">
        <!-- Alerts -->
        <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg=''">{{ errorMsg }}</VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">
          <VIcon icon="ri-check-line" class="me-1" />{{ successMsg }}
        </VAlert>

        <!-- ── Info pasien (readonly, dari QC) ────────────────────────── -->
        <div class="info-box pa-3 rounded-lg mb-4">
          <p class="text-caption font-weight-bold text-medium-emphasis text-uppercase mb-2">
            <VIcon icon="ri-user-heart-line" size="13" class="me-1" />
            Data Pasien (dari Quality Control)
          </p>
          <VRow dense>
            <VCol cols="6">
              <p class="field-label">Tanggal</p>
              <p class="field-value">{{ form.tanggal || '—' }}</p>
            </VCol>
            <VCol cols="6">
              <p class="field-label">Bulan</p>
              <p class="field-value">{{ form.bulan || '—' }}</p>
            </VCol>
            <VCol cols="4">
              <p class="field-label">No. MR</p>
              <p class="field-value font-weight-bold text-primary">{{ form.no_mr || '—' }}</p>
            </VCol>
            <VCol cols="8">
              <p class="field-label">No. Reg</p>
              <p class="field-value">{{ form.no_reg || '—' }}</p>
            </VCol>
            <VCol cols="12">
              <p class="field-label">Nama Pasien</p>
              <p class="field-value font-weight-semibold">{{ form.nama_pasien || '—' }}</p>
            </VCol>
            <VCol cols="6">
              <p class="field-label">Jaminan</p>
              <p class="field-value">{{ form.jaminan || '—' }}</p>
            </VCol>
            <VCol cols="6">
              <p class="field-label">Status</p>
              <VChip
                :color="form.status === 'Selesai' ? 'success' : 'warning'"
                size="x-small"
                variant="tonal"
              >{{ form.status || 'Menunggu' }}</VChip>
            </VCol>
          </VRow>
        </div>

        <VForm @submit.prevent="handleSave">

          <!-- Edukasi Kamar -->
          <div class="mb-3">
            <VTextField
              v-model="form.edukasi_kamar"
              label="Edukasi Kamar / Ruangan"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-hospital-line"
              :readonly="!isEdit"
            />
          </div>

          <!-- Note -->
          <div class="mb-3">
            <VSelect
              v-model="form.note"
              :items="noteOptions"
              label="Note"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-sticky-note-line"
              clearable
              :readonly="!isEdit"
            />
          </div>

          <!-- Petugas -->
          <div class="mb-3">
            <VAutocomplete
              v-model="form.petugas"
              :items="petugasOptions"
              label="Petugas *"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-nurse-line"
              clearable
              :readonly="!isEdit"
            />
          </div>

          <!-- Status toggle (edit only) -->
          <div v-if="isEdit" class="mb-4">
            <p class="text-caption font-weight-semibold text-medium-emphasis mb-2">Status Edukasi</p>
            <VBtnToggle
              v-model="form.status"
              mandatory
              rounded="lg"
              color="warning"
              density="compact"
              class="w-100"
            >
              <VBtn value="Menunggu" class="flex-grow-1" variant="outlined">
                <VIcon icon="ri-time-line" size="14" class="me-1" />Edukasi
              </VBtn>
              <VBtn value="Selesai" class="flex-grow-1" variant="outlined">
                <VIcon icon="ri-check-double-line" size="14" class="me-1" />Edukasi Lanjutan
              </VBtn>
            </VBtnToggle>
          </div>

          <VDivider class="mb-4" />

          <!-- Keluarga Pasien -->
          <div class="mb-3">
            <VTextField
              v-model="form.keluarga_pasien"
              label="Nama Keluarga Pasien *"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-group-line"
              :readonly="!isEdit"
            />
          </div>

          <!-- TTD Keluarga Pasien -->
          <template v-if="isEdit">
            <SignaturePad
              v-model="form.ttd_keluarga_pasien"
              label="Tanda Tangan Keluarga Pasien"
              :height="160"
            />
          </template>
          <template v-else>
            <div class="ttd-section pa-3 rounded-lg">
              <p class="text-caption font-weight-semibold text-medium-emphasis mb-2">
                <VIcon icon="ri-pen-nib-line" size="13" class="me-1" />
                Tanda Tangan Keluarga Pasien
              </p>
              <div v-if="form.ttd_keluarga_pasien" class="rounded-lg overflow-hidden border">
                <img
                  :src="form.ttd_keluarga_pasien"
                  alt="TTD"
                  style="width:100%; max-height:120px; object-fit:contain; background:#fff;"
                />
              </div>
              <div v-else class="ttd-empty text-center py-4">
                <VIcon icon="ri-pen-nib-line" size="28" color="secondary" class="mb-1" />
                <p class="text-caption text-medium-emphasis mb-0">Belum ada tanda tangan</p>
              </div>
            </div>
          </template>

        </VForm>
      </VCardText>

      <VDivider />

      <!-- ── Footer actions ────────────────────────────────────────────── -->
      <div class="d-flex gap-3 px-5 py-4">
        <template v-if="isEdit">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="isEdit = false">Batal</VBtn>
          <VBtn color="warning" rounded="lg" class="flex-grow-1" prepend-icon="ri-save-line" :loading="store.loading" @click="handleSave">
            Simpan
          </VBtn>
        </template>
        <template v-else>
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="close">Tutup</VBtn>
          <VBtn color="warning" rounded="lg" class="flex-grow-1" prepend-icon="ri-pencil-line" @click="isEdit = true">
            Edit / Isi TTD
          </VBtn>
        </template>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dialog-header { background: rgba(var(--v-theme-warning), 0.05); }

.info-box { background: rgba(var(--v-theme-on-surface), 0.03); border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }

.field-label {
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  margin-bottom: 1px;
}
.field-value { font-size: 0.875rem; margin-bottom: 0; }

.ttd-section { background: rgba(var(--v-theme-on-surface), 0.03); }
.ttd-empty {
  border: 1.5px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}
</style>
