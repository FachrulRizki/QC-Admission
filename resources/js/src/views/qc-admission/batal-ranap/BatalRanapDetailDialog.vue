<script setup>
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'
import { useAuthStore }       from '@/stores/useAuthStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item:        { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'verified', 'edit'])

const store     = useBatalRanapStore()
const authStore = useAuthStore()

// ── Tab state ─────────────────────────────────────────────────────────────────
// 'detail' | 'closing' | 'verifikasi'
const tab = ref('detail')

// ── Closing state ─────────────────────────────────────────────────────────────
const closingStatus  = ref(null)
const savingClosing  = ref(false)
const closingErrMsg  = ref('')

// ── Verifikasi (status_ok) state ──────────────────────────────────────────────
const statusOkVal    = ref(null)
const savingVerif    = ref(false)
const verifNote      = ref('')
const verifErrMsg    = ref('')

// ── Bed History ───────────────────────────────────────────────────────────────
const bedHistory     = ref([])
const bedLoading     = ref(false)

const isAdmin = computed(() => ['admin', 'qc_admission'].includes(authStore.userRole))

// ── Init saat dialog buka ────────────────────────────────────────────────────
watch(() => props.modelValue, async (open) => {
  if (open && props.item) {
    tab.value           = 'detail'
    closingStatus.value = props.item.status_closing ?? null
    statusOkVal.value   = props.item.status_ok      ?? null
    verifNote.value     = ''
    closingErrMsg.value = ''
    verifErrMsg.value   = ''
    bedHistory.value    = []
    await fetchBedHistory()
  }
})

async function fetchBedHistory() {
  if (!props.item?.id) return
  bedLoading.value = true
  try {
    const { data } = await axios.get(`/api/batal-ranap/${props.item.id}/bed-history`)
    bedHistory.value = data.data ?? []
  } catch { bedHistory.value = [] }
  finally { bedLoading.value = false }
}

// ── Save Closing ──────────────────────────────────────────────────────────────
async function saveClosing() {
  closingErrMsg.value = ''
  if (!closingStatus.value) { closingErrMsg.value = 'Pilih status closing.'; return }
  savingClosing.value = true
  try {
    // Backend sekarang handle bed management update secara server-to-server
    const result = await store.konfirmasiClosing(props.item.id, closingStatus.value)
    if (!result?.success) { closingErrMsg.value = result?.message ?? 'Gagal menyimpan.'; return }
    emit('verified')
    close()
  } catch { closingErrMsg.value = 'Terjadi kesalahan.' }
  finally { savingClosing.value = false }
}

// ── Save Verifikasi (status_ok) ───────────────────────────────────────────────
async function saveVerifikasi() {
  verifErrMsg.value = ''
  if (!statusOkVal.value) { verifErrMsg.value = 'Pilih status OK terlebih dahulu.'; return }
  savingVerif.value = true
  try {
    const result = await store.verifikasi(props.item.id, statusOkVal.value, verifNote.value)
    if (!result?.success) { verifErrMsg.value = result?.message ?? 'Gagal menyimpan.'; return }
    emit('verified')
    close()
  } catch { verifErrMsg.value = 'Terjadi kesalahan.' }
  finally { savingVerif.value = false }
}

const statusOkColor = s => ({ Bedah: 'success', 'Non Bedah': 'info' }[s] ?? 'secondary')
const closingColor  = s => s === 'Siap Closing' ? 'success' : s === 'Belum Siap Closing' ? 'error' : 'secondary'

// Apakah boleh tampil tab Update Closing?
// → sudah diverifikasi (status_ok terisi) dan status_closing masih Belum Siap atau kosong
const canUpdateClosing = computed(() =>
  isAdmin.value && props.item?.status_ok
)

function close() { emit('update:modelValue', false) }
</script>

<template>
  <VDialog :model-value="modelValue" max-width="500" scrollable @update:model-value="close">
    <VCard v-if="item" rounded="xl" class="overflow-hidden">

      <!-- ── Banner ─────────────────────────────────────────────────────── -->
      <div class="modal-banner">
        <div class="blob blob-1" /><div class="blob blob-2" />
        <div class="banner-inner">
          <div class="banner-avatar">
            <span class="banner-avatar__letter">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
          </div>
          <div class="banner-info">
            <p class="banner-date">{{ item.tanggal || '—' }}</p>
            <h2 class="banner-name">{{ item.nama_pasien || item.no_reg }}</h2>
            <p class="banner-sub">{{ item.no_reg }} · MR: {{ item.no_mr || '—' }}</p>
          </div>
          <div class="banner-right">
            <div class="banner-badges">
              <span class="badge-pill" :class="`badge-pill--${statusOkColor(item.status_ok)}`">
                {{ item.status_ok || 'Belum Diverifikasi' }}
              </span>
              <span v-if="item.status_closing" class="badge-pill mt-1"
                :class="`badge-pill--${closingColor(item.status_closing)}`">
                {{ item.status_closing }}
              </span>
            </div>
            <button class="banner-close" @click="close">
              <VIcon icon="ri-close-line" size="16" />
            </button>
          </div>
        </div>
      </div>

      <!-- ── Tab Bar ────────────────────────────────────────────────────── -->
      <div class="modal-tab-bar">
        <button class="modal-tab" :class="tab==='detail'?'modal-tab--active':''" @click="tab='detail'">
          <VIcon icon="ri-file-list-3-line" size="14" class="me-1" />Detail
        </button>
        <button v-if="canUpdateClosing" class="modal-tab"
          :class="tab==='closing'?'modal-tab--active':''" @click="tab='closing'">
          <VIcon icon="ri-checkbox-circle-line" size="14" class="me-1" />Update Closing
        </button>
        <button v-if="isAdmin && !item.status_ok" class="modal-tab"
          :class="tab==='verifikasi'?'modal-tab--active':''" @click="tab='verifikasi'">
          <VIcon icon="ri-shield-check-line" size="14" class="me-1" />Verifikasi
        </button>
      </div>

      <VCardText class="pa-4">

        <!-- ══ DETAIL ══════════════════════════════════════════════════════ -->
        <template v-if="tab==='detail'">
          <div class="info-grid mb-3">
            <div class="info-cell"><span class="ic-lbl">Tgl. Input</span><span class="ic-val">{{ item.tanggal||'—' }}</span></div>
            <div class="info-cell"><span class="ic-lbl">Jam Input</span><span class="ic-val">{{ item.jam_input||'—' }}</span></div>
            <div class="info-cell"><span class="ic-lbl">Tgl. Daftar</span><span class="ic-val">{{ item.tgl_daftar||'—' }}</span></div>
            <div class="info-cell"><span class="ic-lbl">Jam Daftar</span><span class="ic-val">{{ item.jam_daftar||'—' }}</span></div>
          </div>

          <div class="highlight-box mb-3">
            <div class="hb-icon"><VIcon icon="ri-close-circle-line" size="18" color="error" /></div>
            <div>
              <p class="ic-lbl mb-0">Keterangan Batal</p>
              <p class="ic-val fw mb-0 text-error">{{ item.keterangan_batal||'—' }}</p>
            </div>
          </div>

          <div class="info-grid mb-3">
            <div class="info-cell">
              <span class="ic-lbl">Status OK</span>
              <VChip :color="statusOkColor(item.status_ok)" variant="tonal" size="x-small" class="mt-1">
                {{ item.status_ok || 'Belum Diverifikasi' }}
              </VChip>
            </div>
            <div class="info-cell">
              <span class="ic-lbl">Status Closing</span>
              <VChip v-if="item.status_closing" :color="closingColor(item.status_closing)" variant="tonal" size="x-small" class="mt-1">
                {{ item.status_closing }}
              </VChip>
              <span v-else class="ic-val text-disabled">—</span>
            </div>
            <div class="info-cell"><span class="ic-lbl">Ruangan</span><span class="ic-val">{{ item.ruangan||'—' }}</span></div>
            <div class="info-cell"><span class="ic-lbl">Petugas</span><span class="ic-val">{{ item.petugas||'—' }}</span></div>
            <div class="info-cell info-cell--full"><span class="ic-lbl">Ketersediaan Kamar</span><span class="ic-val">{{ item.ketersediaan_kamar||'—' }}</span></div>
            <div class="info-cell info-cell--full"><span class="ic-lbl">Diagnosa</span><span class="ic-val">{{ item.diagnosa||'—' }}</span></div>
            <div v-if="item.note" class="info-cell info-cell--full">
              <span class="ic-lbl">Note</span>
              <span class="ic-val" style="white-space:pre-wrap">{{ item.note }}</span>
            </div>
          </div>

          <!-- History Bed IGD -->
          <div class="sec-label mb-2">
            <VIcon icon="ri-hotel-bed-line" size="13" class="me-1" />History Bed IGD
          </div>
          <div v-if="bedLoading" class="text-center py-4">
            <VProgressCircular indeterminate size="24" color="primary" />
          </div>
          <div v-else-if="!bedHistory.length" class="empty-beds">
            <VIcon icon="ri-hotel-bed-line" size="22" class="opacity-30 me-2" />
            <span class="text-caption" style="color:var(--qc-text-2)">Tidak ada data bed</span>
          </div>
          <div v-else class="bed-list">
            <div v-for="(bed, i) in bedHistory" :key="i" class="bed-item">
              <div class="bed-item__icon">
                <VIcon icon="ri-hotel-bed-line" size="16" color="primary" />
              </div>
              <div class="flex-grow-1">
                <p class="bed-item__name">{{ bed.ruangan || bed.bangsal || '—' }}</p>
                <p class="bed-item__sub">
                  Bed: {{ bed.bed_code || bed.bed_id || '—' }}
                  <template v-if="bed.tgl_masuk"> · Masuk: {{ bed.tgl_masuk }}</template>
                  <template v-if="bed.tgl_keluar"> · Keluar: {{ bed.tgl_keluar }}</template>
                </p>
                <p v-if="bed.keterangan" class="bed-item__sub">{{ bed.keterangan }}</p>
              </div>
              <VChip :color="bed.status==='available'?'success':'warning'" size="x-small" variant="tonal">
                {{ bed.status === 'available' ? 'Kosong' : bed.status === 'occupied' ? 'Terisi' : (bed.status||'—') }}
              </VChip>
            </div>
          </div>
        </template>

        <!-- ══ UPDATE CLOSING ══════════════════════════════════════════════ -->
        <template v-else-if="tab==='closing'">
          <VAlert v-if="closingErrMsg" type="error" variant="tonal" density="compact" class="mb-3" closable @click:close="closingErrMsg=''">
            {{ closingErrMsg }}
          </VAlert>

          <!-- Bed yang akan dibebaskan -->
          <div v-if="bedHistory.length" class="bed-confirm-box mb-4">
            <p class="sec-label mb-2"><VIcon icon="ri-hotel-bed-line" size="13" class="me-1" />Bed yang akan dibebaskan</p>
            <div v-for="(bed, i) in bedHistory" :key="i" class="d-flex align-center gap-3 py-1">
              <VAvatar color="primary" variant="tonal" size="32" rounded="md">
                <VIcon icon="ri-hotel-bed-line" size="16" />
              </VAvatar>
              <div>
                <p class="text-body-2 font-weight-semibold mb-0">{{ bed.ruangan || bed.bangsal }}</p>
                <p class="text-caption mb-0" style="color:var(--qc-text-2)">Bed: {{ bed.bed_code || bed.bed_id }}</p>
              </div>
              <VChip :color="bed.status==='available'?'success':'warning'" size="x-small" variant="tonal" class="ms-auto">
                {{ bed.status === 'occupied' ? 'Terisi' : 'Kosong' }}
              </VChip>
            </div>
          </div>

          <p class="sec-label mb-3">PILIH STATUS CLOSING</p>
          <VRow dense class="mb-4">
            <VCol cols="6">
              <div class="opt-card text-center pa-4 rounded-xl cursor-pointer"
                :class="closingStatus==='Siap Closing'?'opt--success':''"
                @click="closingStatus='Siap Closing'">
                <div class="opt-icon-wrap opt-icon-wrap--success mb-2">
                  <VIcon icon="ri-checkbox-circle-line" size="26" />
                </div>
                <p class="text-subtitle-2 font-weight-bold mb-1"
                  :class="closingStatus==='Siap Closing'?'text-success':''">👍 Siap Closing</p>
                <p class="text-caption text-medium-emphasis mb-0">Bed akan dibebaskan</p>
              </div>
            </VCol>
            <VCol cols="6">
              <div class="opt-card text-center pa-4 rounded-xl cursor-pointer"
                :class="closingStatus==='Belum Siap Closing'?'opt--error':''"
                @click="closingStatus='Belum Siap Closing'">
                <div class="opt-icon-wrap opt-icon-wrap--error mb-2">
                  <VIcon icon="ri-close-circle-line" size="26" />
                </div>
                <p class="text-subtitle-2 font-weight-bold mb-1"
                  :class="closingStatus==='Belum Siap Closing'?'text-error':''">⏳ Belum Siap</p>
                <p class="text-caption text-medium-emphasis mb-0">Masih perlu tindakan</p>
              </div>
            </VCol>
          </VRow>

          <VAlert v-if="closingStatus==='Siap Closing' && bedHistory.length" type="success"
            variant="tonal" density="compact" class="mb-3 text-caption">
            <VIcon icon="ri-hotel-bed-line" size="14" class="me-1" />
            Bed <strong>{{ bedHistory[0]?.ruangan }}</strong> ({{ bedHistory[0]?.bed_code }})
            akan diset <strong>Tersedia</strong> di Bed Management IGD.
          </VAlert>
        </template>

        <!-- ══ VERIFIKASI STATUS OK ════════════════════════════════════════ -->
        <template v-else-if="tab==='verifikasi'">
          <VAlert v-if="verifErrMsg" type="error" variant="tonal" density="compact" class="mb-3" closable @click:close="verifErrMsg=''">
            {{ verifErrMsg }}
          </VAlert>

          <!-- History bed untuk referensi -->
          <div v-if="bedHistory.length" class="bed-confirm-box mb-4">
            <p class="sec-label mb-2"><VIcon icon="ri-hotel-bed-line" size="13" class="me-1" />History Bed Pasien</p>
            <div v-for="(bed, i) in bedHistory" :key="i" class="d-flex align-center gap-2 py-1">
              <VIcon icon="ri-hotel-bed-line" size="14" color="primary" />
              <span class="text-body-2">{{ bed.ruangan || bed.bangsal }}</span>
              <span class="text-caption" style="color:var(--qc-text-2)">Bed: {{ bed.bed_code || bed.bed_id }}</span>
              <VChip :color="bed.status==='available'?'success':'warning'" size="x-small" variant="tonal" class="ms-auto">
                {{ bed.status === 'occupied' ? 'Terisi' : 'Kosong' }}
              </VChip>
            </div>
          </div>

          <p class="sec-label mb-3">STATUS VERIFIKASI OK</p>
          <VRow dense class="mb-4">
            <VCol cols="6">
              <div class="opt-card text-center pa-3 rounded-xl cursor-pointer"
                :class="statusOkVal==='Bedah'?'opt--success':''"
                @click="statusOkVal='Bedah'">
                <div class="opt-icon-wrap opt-icon-wrap--success mb-2">
                  <VIcon icon="ri-surgical-mask-line" size="24" />
                </div>
                <p class="text-subtitle-2 font-weight-bold mb-0"
                  :class="statusOkVal==='Bedah'?'text-success':''">🏥 Bedah</p>
              </div>
            </VCol>
            <VCol cols="6">
              <div class="opt-card text-center pa-3 rounded-xl cursor-pointer"
                :class="statusOkVal==='Non Bedah'?'opt--info':''"
                @click="statusOkVal='Non Bedah'">
                <div class="opt-icon-wrap opt-icon-wrap--info mb-2">
                  <VIcon icon="ri-hospital-line" size="24" />
                </div>
                <p class="text-subtitle-2 font-weight-bold mb-0"
                  :class="statusOkVal==='Non Bedah'?'text-info':''">🏨 Non Bedah</p>
              </div>
            </VCol>
          </VRow>

          <VTextField v-model="verifNote" label="Catatan Verifikasi (opsional)"
            variant="outlined" density="compact" hide-details class="mb-3" />

          <VAlert type="info" variant="tonal" density="compact" class="text-caption">
            <VIcon icon="ri-information-line" size="14" class="me-1" />
            Setelah diverifikasi, tab "Update Closing" akan muncul untuk konfirmasi bed.
          </VAlert>
        </template>

      </VCardText>

      <!-- ── Actions ────────────────────────────────────────────────────── -->
      <VDivider />
      <div class="d-flex gap-2 px-4 py-3 flex-wrap">
        <VBtn variant="outlined" rounded="lg" size="small" @click="close">Tutup</VBtn>

        <template v-if="tab==='detail'">
          <VBtn v-if="isAdmin" color="primary" variant="tonal" rounded="lg" size="small"
            prepend-icon="ri-pencil-line" @click="emit('edit', item); close()">Edit</VBtn>
          <VBtn v-if="isAdmin && !item.status_ok" color="warning" variant="tonal" rounded="lg" size="small"
            class="flex-grow-1" prepend-icon="ri-shield-check-line" @click="tab='verifikasi'">
            Verifikasi
          </VBtn>
          <VBtn v-if="canUpdateClosing && item.status_closing !== 'Siap Closing'" color="success"
            variant="tonal" rounded="lg" size="small" class="flex-grow-1"
            prepend-icon="ri-checkbox-circle-line" @click="tab='closing'">
            Update Closing
          </VBtn>
        </template>

        <template v-else-if="tab==='closing'">
          <VBtn :color="closingStatus==='Siap Closing'?'success':closingStatus==='Belum Siap Closing'?'error':'secondary'"
            rounded="xl" class="flex-grow-1" prepend-icon="ri-save-line"
            :loading="savingClosing" :disabled="!closingStatus"
            @click="saveClosing">
            Simpan Closing
          </VBtn>
        </template>

        <template v-else-if="tab==='verifikasi'">
          <VBtn :color="statusOkVal==='Bedah'?'success':statusOkVal==='Non Bedah'?'info':'secondary'"
            rounded="xl" class="flex-grow-1" prepend-icon="ri-shield-check-line"
            :loading="savingVerif" :disabled="!statusOkVal"
            @click="saveVerifikasi">
            Simpan Verifikasi
          </VBtn>
        </template>
      </div>

    </VCard>
  </VDialog>
</template>

<style scoped>
/* ── Banner ──────────────────────────────────────────────────────────── */
.modal-banner {
  position: relative;
  background: linear-gradient(135deg, #0369A1 0%, #0EA5E9 55%, #0EA5E9 100%);
  padding: 16px 16px 14px; overflow: hidden;
}
.blob { position: absolute; border-radius: 50%; opacity: 0.14; background: #fff; }
.blob-1 { width: 150px; height: 150px; top: -40px; right: -30px; }
.blob-2 { width: 90px;  height: 90px;  bottom: -28px; right: 60px; }

.banner-inner { position: relative; z-index: 2; display: flex; align-items: center; gap: 12px; }
.banner-avatar {
  width: 46px; height: 46px; flex-shrink: 0; border-radius: 50%;
  background: rgba(255,255,255,0.25); border: 2.5px solid rgba(255,255,255,0.5);
  display: flex; align-items: center; justify-content: center;
}
.banner-avatar__letter { font-size: 18px; font-weight: 800; color: #fff; }
.banner-info { flex: 1; min-width: 0; overflow: hidden; }
.banner-date { font-size: 0.62rem; color: rgba(255,255,255,0.72); margin: 0 0 1px; }
.banner-name {
  font-size: 0.92rem; font-weight: 800; color: #fff; margin: 0 0 2px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.banner-sub { font-size: 0.65rem; color: rgba(255,255,255,0.78); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.banner-right { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
.banner-badges { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; }
.badge-pill {
  font-size: 0.6rem; font-weight: 700;
  padding: 2px 7px; border-radius: 20px;
  background: rgba(255,255,255,0.2); color: #fff;
  display: inline-block; white-space: nowrap;
}
.badge-pill--success   { background: rgba(16,185,129,0.85); }
.badge-pill--info      { background: rgba(59,130,246,0.85); }
.badge-pill--error     { background: rgba(239,68,68,0.85); }
.badge-pill--secondary { background: rgba(255,255,255,0.2); }
.banner-close {
  background: rgba(255,255,255,0.18); border: none; cursor: pointer;
  width: 26px; height: 26px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff; transition: background 0.15s;
}
.banner-close:hover { background: rgba(255,255,255,0.3); }

/* ── Tab Bar ─────────────────────────────────────────────────────────── */
.modal-tab-bar {
  display: flex;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  overflow-x: auto; scrollbar-width: none;
}
.modal-tab-bar::-webkit-scrollbar { display: none; }
.modal-tab {
  flex: 1; min-width: 0;
  display: flex; align-items: center; justify-content: center;
  padding: 11px 10px; gap: 5px;
  font-size: 0.8rem; font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.5);
  background: transparent; border: none;
  border-bottom: 2.5px solid transparent;
  cursor: pointer; transition: all 0.15s;
  white-space: nowrap;
}
.modal-tab:hover { color: rgba(var(--v-theme-on-surface), 0.8); }
.modal-tab--active {
  color: rgb(var(--v-theme-error));
  border-bottom-color: rgb(var(--v-theme-error));
  background: rgba(var(--v-theme-error), 0.04);
}

/* ── Info grid ───────────────────────────────────────────────────────── */
.info-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px; overflow: hidden;
}
.info-cell {
  display: flex; flex-direction: column; padding: 10px 12px;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.info-cell:nth-child(even) { border-right: none; }
.info-cell:last-child, .info-cell:nth-last-child(2):nth-child(odd) { border-bottom: none; }
.info-cell--full { grid-column: span 2; border-right: none; }
.ic-lbl {
  font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.07em;
  color: rgba(var(--v-theme-on-surface), 0.45); margin-bottom: 2px; font-weight: 600;
}
.ic-val { font-size: 0.85rem; font-weight: 500; color: rgba(var(--v-theme-on-surface), 0.87); }
.ic-val.fw { font-weight: 700; }

/* ── Highlight box ───────────────────────────────────────────────────── */
.highlight-box {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 12px 14px;
  background: rgba(var(--v-theme-error), 0.06);
  border: 1px solid rgba(var(--v-theme-error), 0.2);
  border-radius: 12px;
}
.hb-icon {
  width: 34px; height: 34px; flex-shrink: 0; border-radius: 10px;
  background: rgba(var(--v-theme-error), 0.12);
  display: flex; align-items: center; justify-content: center;
}

/* ── Bed list ────────────────────────────────────────────────────────── */
.empty-beds {
  display: flex; align-items: center; justify-content: center;
  padding: 14px; border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
}
.bed-list {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px; overflow: hidden;
}
.bed-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.bed-item:last-child { border-bottom: none; }
.bed-item__icon {
  width: 32px; height: 32px; flex-shrink: 0; border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.08);
  display: flex; align-items: center; justify-content: center;
}
.bed-item__name { font-size: 0.85rem; font-weight: 600; margin: 0; color: rgba(var(--v-theme-on-surface), 0.87); }
.bed-item__sub  { font-size: 0.7rem; color: rgba(var(--v-theme-on-surface), 0.5); margin: 0; }

/* ── Bed confirm box ─────────────────────────────────────────────────── */
.bed-confirm-box {
  padding: 12px 14px; border-radius: 12px;
  background: rgba(var(--v-theme-primary), 0.04);
  border: 1px solid rgba(var(--v-theme-primary), 0.15);
}

/* ── Option cards ────────────────────────────────────────────────────── */
.sec-label {
  font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.08em; color: rgba(var(--v-theme-on-surface), 0.45);
  display: flex; align-items: center;
}
.opt-card {
  border: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
  transition: all 0.18s; border-radius: 12px;
}
.opt-card:hover { border-color: rgba(var(--v-theme-primary), 0.3); }
.opt--success { border-color: rgb(var(--v-theme-success)) !important; background: rgba(var(--v-theme-success), 0.06) !important; }
.opt--error   { border-color: rgb(var(--v-theme-error)) !important;   background: rgba(var(--v-theme-error), 0.06) !important; }
.opt--info    { border-color: rgb(var(--v-theme-info)) !important;    background: rgba(var(--v-theme-info), 0.06) !important; }

.opt-icon-wrap {
  width: 52px; height: 52px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center; margin: 0 auto;
}
.opt-icon-wrap--success { background: rgba(var(--v-theme-success), 0.12); color: rgb(var(--v-theme-success)); }
.opt-icon-wrap--error   { background: rgba(var(--v-theme-error), 0.12);   color: rgb(var(--v-theme-error)); }
.opt-icon-wrap--info    { background: rgba(var(--v-theme-info), 0.12);    color: rgb(var(--v-theme-info)); }
</style>
