<script setup>
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'
import { useAuthStore }       from '@/stores/useAuthStore'
import axios from 'axios'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item:        { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'verified', 'edit'])

const store    = useBatalRanapStore()
const authStore = useAuthStore()

const tab           = ref('detail')   // 'detail' | 'verifikasi'
const closingStatus = ref(null)
const saving        = ref(false)
const bedUpdating   = ref(false)
const errMsg        = ref('')

const isAdmin = computed(() => ['admin','qc_admission'].includes(authStore.userRole))

watch(() => props.modelValue, (open) => {
  if (open && props.item) {
    tab.value           = 'detail'
    closingStatus.value = props.item.status_closing ?? null
    errMsg.value        = ''
  }
})

const statusOkColor = s => ({ Bedah: 'success', 'Non Bedah': 'info' }[s] ?? 'secondary')
const statusOkIcon  = s => ({ Bedah: 'ri-surgical-mask-line', 'Non Bedah': 'ri-hospital-line' }[s] ?? 'ri-time-line')
const closingColor  = s => s === 'Siap Closing' ? 'success' : s === 'Belum Siap Closing' ? 'error' : 'secondary'

async function saveVerifikasi() {
  errMsg.value = ''
  if (!closingStatus.value) { errMsg.value = 'Pilih status closing terlebih dahulu.'; return }

  saving.value = true
  try {
    const result = await store.konfirmasiClosing(props.item.id, closingStatus.value)
    if (!result?.success) { errMsg.value = result?.message ?? 'Gagal menyimpan.'; return }

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
  <VDialog :model-value="modelValue" max-width="480" scrollable @update:model-value="close">
    <VCard v-if="item" rounded="xl" class="overflow-hidden">

      <!-- ── Gradient Banner ────────────────────────────────────────────── -->
      <div class="modal-banner">
        <div class="blob blob-1" />
        <div class="blob blob-2" />

        <div class="banner-inner">
          <div class="banner-avatar">
            <span class="banner-avatar__letter">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
          </div>

          <div class="banner-info">
            <p class="banner-date">{{ item.tanggal || '—' }}</p>
            <h2 class="banner-name">{{ item.nama_pasien || item.no_reg }}</h2>
            <p class="banner-sub">{{ item.no_reg }} · MR: {{ item.no_mr || '—' }}</p>
          </div>

          <!-- Badges + close di kanan -->
          <div class="banner-right">
            <div class="banner-badges">
              <span class="badge-pill" :class="`badge-pill--${statusOkColor(item.status_ok)}`">
                {{ item.status_ok || 'Belum Diverifikasi' }}
              </span>
              <span v-if="item.status_closing" class="badge-pill mt-1" :class="`badge-pill--${closingColor(item.status_closing)}`">
                {{ item.status_closing }}
              </span>
            </div>
            <button class="banner-close" @click="close">
              <VIcon icon="ri-close-line" size="16" />
            </button>
          </div>
        </div>
      </div>

      <!-- ── Custom Tab Bar — responsif ───────────────────────────────────── -->
      <div class="modal-tab-bar">
        <button
          class="modal-tab"
          :class="tab === 'detail' ? 'modal-tab--active-error' : ''"
          @click="tab = 'detail'"
        >
          <VIcon icon="ri-file-list-3-line" size="14" class="me-1" />
          <span>Detail</span>
        </button>
        <button
          v-if="isAdmin"
          class="modal-tab"
          :class="tab === 'verifikasi' ? 'modal-tab--active-error' : ''"
          @click="tab = 'verifikasi'"
        >
          <VIcon icon="ri-checkbox-circle-line" size="14" class="me-1" />
          <span>Verifikasi</span>
        </button>
      </div>

      <VCardText class="pa-5">

        <!-- ══ DETAIL ══════════════════════════════════════════════════════ -->
        <template v-if="tab === 'detail'">
          <!-- Info grid 2-col -->
          <div class="info-grid">
            <div class="info-cell">
              <span class="ic-lbl">Tanggal Input</span>
              <span class="ic-val">{{ item.tanggal || '—' }}</span>
            </div>
            <div class="info-cell">
              <span class="ic-lbl">Jam Input</span>
              <span class="ic-val">{{ item.jam_input || '—' }}</span>
            </div>
            <div class="info-cell">
              <span class="ic-lbl">Tgl. Daftar</span>
              <span class="ic-val">{{ item.tgl_daftar || '—' }}</span>
            </div>
            <div class="info-cell">
              <span class="ic-lbl">Jam Daftar</span>
              <span class="ic-val">{{ item.jam_daftar || '—' }}</span>
            </div>
          </div>

          <!-- Keterangan Batal highlight -->
          <div class="highlight-box mb-3">
            <div class="hb-icon">
              <VIcon icon="ri-close-circle-line" size="18" color="error" />
            </div>
            <div>
              <p class="ic-lbl mb-0">Keterangan Batal</p>
              <p class="ic-val fw mb-0 text-error">{{ item.keterangan_batal || '—' }}</p>
            </div>
          </div>

          <!-- Info grid baris berikut -->
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
            <div class="info-cell">
              <span class="ic-lbl">Ruangan</span>
              <span class="ic-val">{{ item.ruangan || '—' }}</span>
            </div>
            <div class="info-cell">
              <span class="ic-lbl">Petugas</span>
              <span class="ic-val">{{ item.petugas || '—' }}</span>
            </div>
          </div>

          <div class="info-grid">
            <div class="info-cell info-cell--full">
              <span class="ic-lbl">Ketersediaan Kamar</span>
              <span class="ic-val">{{ item.ketersediaan_kamar || '—' }}</span>
            </div>
            <div class="info-cell info-cell--full">
              <span class="ic-lbl">Diagnosa</span>
              <span class="ic-val">{{ item.diagnosa || '—' }}</span>
            </div>
            <div v-if="item.note" class="info-cell info-cell--full">
              <span class="ic-lbl">Note</span>
              <span class="ic-val" style="white-space:pre-wrap">{{ item.note }}</span>
            </div>
          </div>
        </template>

        <!-- ══ VERIFIKASI ══════════════════════════════════════════════════ -->
        <template v-else>
          <VAlert v-if="errMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errMsg=''">
            {{ errMsg }}
          </VAlert>

          <p class="sec-label mb-3">PILIH STATUS CLOSING</p>

          <VRow dense class="mb-4">
            <VCol cols="6">
              <div
                class="opt-card text-center pa-4 rounded-xl cursor-pointer"
                :class="closingStatus === 'Siap Closing' ? 'opt--success' : ''"
                @click="closingStatus = 'Siap Closing'"
              >
                <div class="opt-icon-wrap opt-icon-wrap--success mb-2">
                  <VIcon icon="ri-checkbox-circle-line" size="26" />
                </div>
                <p class="text-subtitle-2 font-weight-bold mb-1" :class="closingStatus === 'Siap Closing' ? 'text-success' : ''">
                  👍 Siap Closing
                </p>
                <p class="text-caption text-medium-emphasis mb-0">Pasien siap selesai</p>
              </div>
            </VCol>
            <VCol cols="6">
              <div
                class="opt-card text-center pa-4 rounded-xl cursor-pointer"
                :class="closingStatus === 'Belum Siap Closing' ? 'opt--error' : ''"
                @click="closingStatus = 'Belum Siap Closing'"
              >
                <div class="opt-icon-wrap opt-icon-wrap--error mb-2">
                  <VIcon icon="ri-close-circle-line" size="26" />
                </div>
                <p class="text-subtitle-2 font-weight-bold mb-1" :class="closingStatus === 'Belum Siap Closing' ? 'text-error' : ''">
                  ⏳ Belum Siap
                </p>
                <p class="text-caption text-medium-emphasis mb-0">Masih perlu tindakan</p>
              </div>
            </VCol>
          </VRow>

          <VAlert
            v-if="closingStatus === 'Siap Closing' && item.ruangan"
            type="success" variant="tonal" density="compact"
            class="mb-3 text-caption"
          >
            <VIcon icon="ri-hotel-bed-line" size="14" class="me-1" />
            Bed <strong>{{ item.ruangan }}</strong> → <strong>Tersedia</strong> di Bed Management IGD.
          </VAlert>

          <VAlert type="info" variant="tonal" density="compact" class="text-caption">
            <VIcon icon="ri-information-line" size="14" class="me-1" />
            Status ini dilihat Kasir untuk konfirmasi kesiapan closing pasien.
          </VAlert>
        </template>

      </VCardText>

      <!-- ── Actions ────────────────────────────────────────────────────── -->
      <VDivider />
      <div class="d-flex gap-2 px-5 py-4 flex-wrap">
        <VBtn variant="outlined" rounded="lg" @click="close">Tutup</VBtn>

        <template v-if="tab === 'detail'">
          <VBtn v-if="isAdmin" color="primary" variant="tonal" rounded="lg" prepend-icon="ri-pencil-line"
            @click="emit('edit', item); close()">
            Edit
          </VBtn>
          <VBtn v-if="isAdmin" color="success" rounded="lg" class="flex-grow-1" prepend-icon="ri-checkbox-circle-line"
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
/* ── Banner gradient ──────────────────────────────────────────────── */
.modal-banner {
  position: relative;
  background: linear-gradient(135deg, #0EA5E9 0%, #0EA5E9 55%, #0EA5E9 100%);
  padding: 16px 16px 14px;
  overflow: hidden;
}
.blob { position: absolute; border-radius: 50%; opacity: 0.16; background: #fff; }
.blob-1 { width: 150px; height: 150px; top: -40px; right: -30px; }
.blob-2 { width: 90px;  height: 90px;  bottom: -28px; right: 60px; }

/* Banner row: avatar | info | badges+close */
.banner-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center; gap: 12px;
}
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

/* Kanan: badges atas, close bawah */
.banner-right {
  flex-shrink: 0;
  display: flex; flex-direction: column; align-items: flex-end; gap: 5px;
}
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

/* ── Custom Tab Bar — responsif ─────────────────────────────────────── */
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
  padding: 10px 12px; gap: 5px;
  font-size: 0.8rem; font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.5);
  background: transparent; border: none;
  border-bottom: 2.5px solid transparent;
  cursor: pointer; transition: all 0.15s;
  white-space: nowrap;
}
.modal-tab:hover { color: rgba(var(--v-theme-on-surface), 0.8); }
.modal-tab--active-error {
  color: rgb(var(--v-theme-error));
  border-bottom-color: rgb(var(--v-theme-error));
  background: rgba(var(--v-theme-error), 0.04);
}

/* ── Info grid ────────────────────────────────────────────────────── */
.info-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 0;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px; overflow: hidden; margin-bottom: 12px;
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

/* ── Highlight box ────────────────────────────────────────────────── */
.highlight-box {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 12px 14px; margin-bottom: 12px;
  background: rgba(var(--v-theme-error), 0.06);
  border: 1px solid rgba(var(--v-theme-error), 0.2);
  border-radius: 12px;
}
.hb-icon {
  width: 34px; height: 34px; flex-shrink: 0; border-radius: 10px;
  background: rgba(var(--v-theme-error), 0.12);
  display: flex; align-items: center; justify-content: center;
}

/* ── Sec label ───────────────────────────────────────────────────── */
.sec-label {
  font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.08em; color: rgba(var(--v-theme-on-surface), 0.45);
}

/* ── Option cards ─────────────────────────────────────────────────── */
.opt-card {
  border: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
  transition: all 0.18s; border-radius: 12px;
}
.opt-card:hover { border-color: rgba(var(--v-theme-primary), 0.3); background: rgba(var(--v-theme-primary), 0.03); }
.opt--success { border-color: rgb(var(--v-theme-success)) !important; background: rgba(var(--v-theme-success), 0.06) !important; }
.opt--error   { border-color: rgb(var(--v-theme-error)) !important;   background: rgba(var(--v-theme-error), 0.06) !important; }

.opt-icon-wrap {
  width: 52px; height: 52px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center; margin: 0 auto;
}
.opt-icon-wrap--success { background: rgba(var(--v-theme-success), 0.12); color: rgb(var(--v-theme-success)); }
.opt-icon-wrap--error   { background: rgba(var(--v-theme-error), 0.12);   color: rgb(var(--v-theme-error)); }
</style>
