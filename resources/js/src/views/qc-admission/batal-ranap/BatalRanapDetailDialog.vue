<script setup>
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'
import axios from 'axios'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item:       { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'verified', 'edit'])

const store = useBatalRanapStore()

const tab            = ref('detail')  // 'detail' | 'verifikasi'
const closingStatus  = ref(null)
const saving         = ref(false)
const bedUpdating    = ref(false)
const errMsg         = ref('')

watch(() => props.modelValue, (open) => {
  if (open && props.item) {
    tab.value           = 'detail'
    closingStatus.value = props.item.status_closing ?? null
    errMsg.value        = ''
  }
})

// helpers
const statusOkColor = s => ({ Bedah: 'success', 'Non Bedah': 'info' }[s] ?? 'secondary')
const statusOkIcon  = s => ({ Bedah: 'ri-surgical-mask-line', 'Non Bedah': 'ri-hospital-line' }[s] ?? 'ri-time-line')
const statusOkLabel = s => s || 'Belum Diverifikasi'
const closingColor  = s => s === 'Siap Closing' ? 'success' : s === 'Belum Siap Closing' ? 'error' : 'secondary'
const closingIcon   = s => s === 'Siap Closing' ? 'ri-checkbox-circle-line' : s === 'Belum Siap Closing' ? 'ri-close-circle-line' : 'ri-question-line'

/**
 * Verifikasi = konfirmasi status_closing
 * Jika "Siap Closing" → update bed IGD tersedia
 */
async function saveVerifikasi() {
  errMsg.value = ''
  if (!closingStatus.value) { errMsg.value = 'Pilih status closing terlebih dahulu.'; return }

  saving.value = true
  try {
    // Simpan status_closing
    const result = await store.konfirmasiClosing(props.item.id, closingStatus.value)
    if (!result?.success) { errMsg.value = result?.message ?? 'Gagal menyimpan.'; return }

    // Jika Siap Closing → update bed management IGD
    if (closingStatus.value === 'Siap Closing' && props.item.ruangan) {
      bedUpdating.value = true
      try {
        await axios.post('/api/bed-management/update-status', {
          bed_id:  props.item.bed_id || props.item.ruangan,
          ruangan: props.item.ruangan,
          status:  'available',
        })
      } catch (e) {
        console.warn('Bed update (non-critical):', e.message)
      } finally {
        bedUpdating.value = false
      }
    }

    emit('verified')
    close()
  } catch {
    errMsg.value = 'Terjadi kesalahan.'
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
      <div class="dlg-header d-flex align-center gap-3 px-5 py-4">
        <VAvatar color="error" variant="tonal" size="44" rounded="lg">
          <span style="font-size:15px;font-weight:700">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
        </VAvatar>
        <div class="flex-grow-1 min-width-0">
          <p class="text-subtitle-2 font-weight-bold mb-0 text-truncate">{{ item.nama_pasien || item.no_reg }}</p>
          <p class="text-caption text-medium-emphasis mb-0">
            NoReg: <strong>{{ item.no_reg }}</strong>
            <template v-if="item.no_mr"> · NoMR: <strong>{{ item.no_mr }}</strong></template>
          </p>
        </div>
        <!-- Status badges -->
        <div class="d-flex flex-column gap-1 align-end flex-shrink-0 me-1">
          <VChip :color="statusOkColor(item.status_ok)" variant="tonal" size="x-small" :prepend-icon="statusOkIcon(item.status_ok)">
            {{ statusOkLabel(item.status_ok) }}
          </VChip>
          <VChip v-if="item.status_closing" :color="closingColor(item.status_closing)" variant="tonal" size="x-small" :prepend-icon="closingIcon(item.status_closing)">
            {{ item.status_closing }}
          </VChip>
        </div>
        <VBtn icon variant="text" size="small" @click="close"><VIcon icon="ri-close-line" /></VBtn>
      </div>

      <!-- Tabs — hanya Detail & Verifikasi -->
      <VTabs v-model="tab" color="error" density="compact" class="px-4 pt-1 border-b">
        <VTab value="detail">
          <VIcon icon="ri-file-list-3-line" size="14" class="me-1" />Detail
        </VTab>
        <VTab value="verifikasi">
          <VIcon icon="ri-checkbox-circle-line" size="14" class="me-1" />Verifikasi
        </VTab>
      </VTabs>

      <VCardText class="pa-5">

        <!-- ══ DETAIL ════════════════════════════════════════════════════════ -->
        <template v-if="tab === 'detail'">
          <VRow dense>
            <VCol cols="6"><p class="fl">Tanggal</p><p class="fv">{{ item.tanggal || '—' }}</p></VCol>
            <VCol cols="6"><p class="fl">Jam Input</p><p class="fv">{{ item.jam_input || '—' }}</p></VCol>
            <VCol cols="6"><p class="fl">tgldaftar</p><p class="fv">{{ item.tgl_daftar || '—' }}</p></VCol>
            <VCol cols="6"><p class="fl">jamdaftar</p><p class="fv">{{ item.jam_daftar || '—' }}</p></VCol>
            <VCol cols="4"><p class="fl">NoMR</p><p class="fv font-weight-bold">{{ item.no_mr || '—' }}</p></VCol>
            <VCol cols="8"><p class="fl">NamaPasien</p><p class="fv font-weight-semibold">{{ item.nama_pasien || '—' }}</p></VCol>
            <VCol cols="12">
              <p class="fl">Keterangan_Batal</p>
              <VChip color="error" variant="tonal" size="small" label class="mb-2">{{ item.keterangan_batal || '—' }}</VChip>
            </VCol>
            <VCol cols="6">
              <p class="fl">Status OK</p>
              <VChip :color="statusOkColor(item.status_ok)" variant="tonal" size="small" :prepend-icon="statusOkIcon(item.status_ok)">
                {{ statusOkLabel(item.status_ok) }}
              </VChip>
            </VCol>
            <VCol cols="6">
              <p class="fl">status_Closing</p>
              <VChip v-if="item.status_closing" :color="closingColor(item.status_closing)" variant="tonal" size="small" :prepend-icon="closingIcon(item.status_closing)">
                {{ item.status_closing }}
              </VChip>
              <span v-else class="fv text-disabled">—</span>
            </VCol>
            <VCol cols="12"><p class="fl">Ketersediaan Kamar</p><p class="fv">{{ item.ketersediaan_kamar || '—' }}</p></VCol>
            <VCol cols="12"><p class="fl">Diagnosa</p><p class="fv">{{ item.diagnosa || '—' }}</p></VCol>
            <VCol cols="6"><p class="fl">Ruangan</p><p class="fv">{{ item.ruangan || '—' }}</p></VCol>
            <VCol cols="6"><p class="fl">Petugas</p><p class="fv d-flex align-center gap-1"><VIcon icon="ri-user-3-line" size="13" />{{ item.petugas || '—' }}</p></VCol>
            <VCol v-if="item.note" cols="12"><p class="fl">Note</p><p class="fv" style="white-space:pre-wrap">{{ item.note }}</p></VCol>
          </VRow>
        </template>

        <!-- ══ VERIFIKASI — status_closing ═══════════════════════════════════ -->
        <template v-else>
          <VAlert v-if="errMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errMsg=''">
            {{ errMsg }}
          </VAlert>

          <!-- Pasien info ringkas -->
          <div class="info-box pa-3 rounded-xl mb-4">
            <div class="d-flex align-center gap-3">
              <VAvatar color="error" variant="tonal" size="40" rounded="lg">
                <span style="font-size:14px;font-weight:700">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
              </VAvatar>
              <div class="flex-grow-1">
                <p class="text-body-2 font-weight-bold mb-0">{{ item.nama_pasien }}</p>
                <p class="text-caption text-medium-emphasis mb-0">{{ item.no_reg }} · {{ item.tanggal }}</p>
                <p v-if="item.keterangan_batal" class="text-caption text-error mb-0">{{ item.keterangan_batal }}</p>
              </div>
            </div>
          </div>

          <p class="sec-label mb-3">STATUS CLOSING</p>

          <VRow dense class="mb-4">
            <!-- Siap Closing -->
            <VCol cols="6">
              <div class="opt-card text-center pa-4 rounded-xl cursor-pointer"
                :class="closingStatus === 'Siap Closing' ? 'opt--success' : ''"
                @click="closingStatus = 'Siap Closing'">
                <VAvatar :color="closingStatus === 'Siap Closing' ? 'success' : 'secondary'" variant="tonal" size="48" rounded="lg" class="mb-2">
                  <VIcon icon="ri-checkbox-circle-line" size="24" />
                </VAvatar>
                <p class="text-subtitle-2 font-weight-bold mb-1" :class="closingStatus === 'Siap Closing' ? 'text-success' : ''">
                  👍 Siap Closing
                </p>
                <p class="text-caption text-medium-emphasis mb-0">Pasien siap selesai</p>
              </div>
            </VCol>
            <!-- Belum Siap Closing -->
            <VCol cols="6">
              <div class="opt-card text-center pa-4 rounded-xl cursor-pointer"
                :class="closingStatus === 'Belum Siap Closing' ? 'opt--error' : ''"
                @click="closingStatus = 'Belum Siap Closing'">
                <VAvatar :color="closingStatus === 'Belum Siap Closing' ? 'error' : 'secondary'" variant="tonal" size="48" rounded="lg" class="mb-2">
                  <VIcon icon="ri-close-circle-line" size="24" />
                </VAvatar>
                <p class="text-subtitle-2 font-weight-bold mb-1" :class="closingStatus === 'Belum Siap Closing' ? 'text-error' : ''">
                  ⏳ Belum Siap
                </p>
                <p class="text-caption text-medium-emphasis mb-0">Masih perlu tindakan</p>
              </div>
            </VCol>
          </VRow>

          <!-- Info bed update jika Siap Closing -->
          <VAlert
            v-if="closingStatus === 'Siap Closing' && item.ruangan"
            type="success" variant="tonal" density="compact"
            class="mb-3 text-caption"
          >
            <VIcon icon="ri-hotel-bed-line" size="14" class="me-1" />
            Bed di ruangan <strong>{{ item.ruangan }}</strong> akan diupdate → <strong>Tersedia</strong> di Bed Management IGD.
          </VAlert>

          <VAlert type="info" variant="tonal" density="compact" class="text-caption">
            <VIcon icon="ri-information-line" size="14" class="me-1" />
            Status ini dilihat Kasir untuk konfirmasi kesiapan closing pasien.
          </VAlert>
        </template>

      </VCardText>

      <VDivider />
      <div class="d-flex gap-2 px-5 py-4 flex-wrap">
        <VBtn variant="outlined" rounded="lg" @click="close">Tutup</VBtn>

        <template v-if="tab === 'detail'">
          <VBtn color="primary" variant="tonal" rounded="lg" prepend-icon="ri-pencil-line"
            @click="emit('edit', item); close()">
            Edit
          </VBtn>
          <VBtn color="success" rounded="lg" class="flex-grow-1" prepend-icon="ri-checkbox-circle-line"
            @click="tab = 'verifikasi'">
            Verifikasi
          </VBtn>
        </template>

        <template v-else>
          <VBtn
            :color="closingStatus === 'Siap Closing' ? 'success' : closingStatus === 'Belum Siap Closing' ? 'error' : 'secondary'"
            rounded="xl" class="flex-grow-1"
            prepend-icon="ri-save-line"
            :loading="saving || bedUpdating"
            :disabled="!closingStatus"
            @click="saveVerifikasi"
          >
            {{ bedUpdating ? 'Update Bed...' : 'Simpan Verifikasi' }}
          </VBtn>
        </template>
      </div>

    </VCard>
  </VDialog>
</template>

<style scoped>
.dlg-header { background: rgba(var(--v-theme-error), 0.04); }
.border-b { border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.info-box { background: rgba(var(--v-theme-on-surface), 0.03); border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 12px; }
.fl { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em; color: rgba(var(--v-theme-on-surface), 0.45); margin-bottom: 2px; }
.fv { font-size: 0.875rem; font-weight: 500; margin-bottom: 8px; }
.sec-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(var(--v-theme-on-surface), 0.45); }

.opt-card {
  border: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
  transition: all 0.18s;
}
.opt-card:hover { border-color: rgba(var(--v-theme-primary), 0.3); background: rgba(var(--v-theme-primary), 0.03); }
.opt--success { border-color: rgb(var(--v-theme-success)) !important; background: rgba(var(--v-theme-success), 0.06) !important; }
.opt--error   { border-color: rgb(var(--v-theme-error)) !important;   background: rgba(var(--v-theme-error), 0.06) !important; }
</style>
