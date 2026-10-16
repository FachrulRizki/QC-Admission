<script setup>
import { useBooklistAdmissionStore } from '@/stores/useBooklistAdmissionStore'
import { usePegawaiStore } from '@/stores/usePegawaiStore'
import { usePasienStore } from '@/stores/usePasienStore'
import { useDokterStore } from '@/stores/useDokterStore'
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store        = useBooklistAdmissionStore()
const pegawaiStore = usePegawaiStore()
const pasienStore  = usePasienStore()
const dokterStore  = useDokterStore()

const form     = ref(initialForm())
const errorMsg = ref('')
const saving   = ref(false)

const nowDisplay = ref('')
let clockTimer   = null

function tickClock() {
  const d = new Date()
  const p = n => String(n).padStart(2, '0')
  nowDisplay.value = `${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()}, ${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
}

// ── Pasien search ─────────────────────────────────────────────────────────────
const pasienSearch  = ref('')
const pasienLoading = ref(false)
let searchTimer     = null

watch(pasienSearch, (val) => {
  clearTimeout(searchTimer)
  if (!val || val.trim().length < 2) { pasienStore.clear(); return }
  pasienLoading.value = true
  searchTimer = setTimeout(async () => {
    await pasienStore.search(val.trim())
    pasienLoading.value = false
  }, 300)
})

// ── Dokter search ─────────────────────────────────────────────────────────────
const dokterSearch  = ref('')
let dokterTimer     = null

watch(dokterSearch, (val) => {
  clearTimeout(dokterTimer)
  if (!val || val.trim().length < 2) { dokterStore.clear(); return }
  dokterTimer = setTimeout(() => dokterStore.search(val.trim()), 300)
})

watch(() => form.value.no_reg, async (val) => {
  if (!val) return
  const hit = pasienStore.results.find(p => p.no_reg === val)
    ?? await pasienStore.lookup(val)
  if (hit) {
    form.value.no_mr        = hit.no_mr        ?? ''
    form.value.nama_pasien  = hit.nama_pasien  ?? ''
    form.value.jaminan      = hit.ket_bayar    ?? ''
    form.value.diagnosa     = hit.diagnosa     ?? ''
    form.value.kelas        = hit.nama_kelas   ?? ''
    form.value.no_hp        = hit.no_telp      ?? ''
  }
})

watch(() => props.modelValue, (open) => {
  if (open) {
    form.value = props.editItem ? { ...initialForm(), ...props.editItem } : initialForm()
    errorMsg.value = ''
    pasienStore.clear()
    pasienSearch.value = ''
    dokterStore.clear()
    dokterSearch.value = ''
    pegawaiStore.fetch()
    tickClock()
    clockTimer = setInterval(tickClock, 1000)
  } else {
    clearInterval(clockTimer)
  }
})

function initialForm() {
  return {
    no_reg:           null,
    no_mr:            '',
    nama_pasien:      '',
    no_hp:            '',
    jaminan:          '',
    kelas:            '',
    dpjp:             '',
    diagnosa:         '',
    tanggal_op:       '',
    status_pasien:    null,
    catatan:          '',
    petugas:          null,
  }
}

function fmtTanggal() {
  const d = new Date()
  const p = n => String(n).padStart(2, '0')
  return `${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()}`
}

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.nama_pasien && !form.value.no_reg) {
    errorMsg.value = 'Nama pasien atau No. Reg wajib diisi.'
    return
  }

  saving.value = true
  const payload = {
    ...form.value,
    tanggal_booklist: fmtTanggal(),
  }

  const result = props.editItem
    ? await store.update(props.editItem.id, payload)
    : await store.store(payload)

  saving.value = false

  if (result?.success !== false) {
    emit('saved')
    close()
  } else {
    errorMsg.value = result?.message ?? 'Gagal menyimpan.'
  }
}

function close() {
  clearInterval(clockTimer)
  emit('update:modelValue', false)
}

const hasPasien = computed(() => !!form.value.no_reg || !!form.value.nama_pasien)

const statusOpts = [
  { title: '🔪 Bedah',     value: 'Bedah' },
  { title: '🏥 Non Bedah', value: 'Non Bedah' },
]
</script>

<template>
  <VDialog :model-value="modelValue" max-width="600" persistent @update:model-value="close">
    <VCard rounded="xl" class="bl-dlg-card overflow-hidden">

      <!-- ── Banner Header ───────────────────────────────────────────────── -->
      <div class="bl-banner">
        <div class="bl-blob bl-blob--1" />
        <div class="bl-blob bl-blob--2" />
        <div class="bl-inner">
          <div class="bl-icon">
            <VIcon icon="ri-book-mark-line" size="22" color="white" />
          </div>
          <div class="bl-text">
            <p class="bl-sub">Booklist Admission · IGD</p>
            <h3 class="bl-title">{{ editItem ? 'Edit Booklist' : 'Tambah Booklist' }}</h3>
          </div>
          <div class="bl-clock">
            <VIcon icon="ri-time-line" size="11" class="me-1 opacity-70" />
            <span>{{ nowDisplay }}</span>
          </div>
          <button class="bl-close" @click="close">
            <VIcon icon="ri-close-line" size="16" />
          </button>
        </div>
      </div>

      <!-- ── Body ──────────────────────────────────────────────────────── -->
      <div class="bl-body">
        <div v-if="errorMsg" class="px-5 pt-4">
          <VAlert type="error" variant="tonal" density="compact" closable @click:close="errorMsg = ''">
            {{ errorMsg }}
          </VAlert>
        </div>

        <div class="px-5 py-4 d-flex flex-column gap-4">

          <!-- ── SECTION: Data Pasien ──────────────────────────────────── -->
          <div class="bl-section bl-section--primary">
            <div class="bl-sec-hd bl-sec-hd--primary">
              <VIcon icon="ri-user-heart-line" size="14" />
              <span>Data Pasien</span>
              <span class="bl-sec-req">Wajib Diisi</span>
            </div>
            <div class="bl-sec-body">

              <!-- Search pasien -->
              <VAutocomplete
                v-model="form.no_reg"
                v-model:search="pasienSearch"
                :items="pasienStore.optionList"
                item-title="title"
                item-value="value"
                label="Cari No. Reg / No. MR / Nama Pasien *"
                variant="outlined"
                density="compact"
                prepend-inner-icon="ri-search-line"
                clearable
                no-filter
                :loading="pasienLoading || pasienStore.loading"
                no-data-text="Ketik min. 2 karakter..."
                class="mb-3"
                hide-details="auto"
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

              <!-- Autofill info grid -->
              <Transition name="slide-down">
                <div v-if="hasPasien" class="bl-info-grid">
                  <div class="bl-ig-cell bl-ig-cell--span2">
                    <span class="bl-ig-lbl"><VIcon icon="ri-user-3-line" size="10" class="me-1" />Nama Pasien</span>
                    <span class="bl-ig-val bl-ig-val--accent">{{ form.nama_pasien || '—' }}</span>
                  </div>
                  <div class="bl-ig-cell">
                    <span class="bl-ig-lbl"><VIcon icon="ri-id-card-line" size="10" class="me-1" />No. MR</span>
                    <span class="bl-ig-val bl-ig-val--mono">{{ form.no_mr || '—' }}</span>
                  </div>
                  <div class="bl-ig-cell">
                    <span class="bl-ig-lbl"><VIcon icon="ri-shield-user-line" size="10" class="me-1" />Jaminan</span>
                    <span class="bl-ig-val">{{ form.jaminan || '—' }}</span>
                  </div>
                  <div class="bl-ig-cell">
                    <span class="bl-ig-lbl"><VIcon icon="ri-hotel-bed-line" size="10" class="me-1" />Kelas</span>
                    <span class="bl-ig-val">{{ form.kelas || '—' }}</span>
                  </div>
                  <div class="bl-ig-cell bl-ig-cell--span2">
                    <span class="bl-ig-lbl"><VIcon icon="ri-stethoscope-line" size="10" class="me-1" />Diagnosa</span>
                    <span class="bl-ig-val">{{ form.diagnosa || '—' }}</span>
                  </div>
                  <div class="bl-ig-cell">
                    <span class="bl-ig-lbl"><VIcon icon="ri-phone-line" size="10" class="me-1" />No. HP</span>
                    <span class="bl-ig-val bl-ig-val--mono">{{ form.no_hp || '—' }}</span>
                  </div>
                  <div class="bl-ig-cell">
                    <span class="bl-ig-lbl"><VIcon icon="ri-map-pin-line" size="10" class="me-1" />Ruang / Bangsal</span>
                    <span class="bl-ig-val">{{ form.kelas || '—' }}</span>
                  </div>
                </div>
              </Transition>

              <div v-if="!hasPasien" class="bl-empty-pasien">
                <VIcon icon="ri-user-search-line" size="28" class="mb-1 opacity-30" />
                <p class="text-caption text-disabled mb-0">Cari pasien untuk mengisi data otomatis</p>
              </div>
            </div>
          </div>

          <!-- ── SECTION: Rencana Masuk & Status ──────────────────────── -->
          <div class="bl-section bl-section--warning">
            <div class="bl-sec-hd bl-sec-hd--warning">
              <VIcon icon="ri-calendar-event-line" size="14" />
              <span>Rencana Masuk &amp; Status</span>
            </div>
            <div class="bl-sec-body d-flex flex-column gap-3">

              <VAutocomplete
                v-model="form.dpjp"
                v-model:search="dokterSearch"
                :items="dokterStore.optionList"
                item-title="title"
                item-value="value"
                label="DPJP (Dokter Penanggung Jawab)"
                variant="outlined"
                density="compact"
                prepend-inner-icon="ri-user-star-line"
                clearable
                no-filter
                :loading="dokterStore.loading"
                no-data-text="Ketik min. 2 karakter nama dokter..."
                hide-details="auto"
              >
                <template #item="{ item, props: iProps }">
                  <VListItem v-bind="iProps" class="py-2">
                    <template #prepend>
                      <VAvatar color="info" variant="tonal" size="30" rounded="lg" class="me-2">
                        <VIcon icon="ri-user-star-line" size="14" />
                      </VAvatar>
                    </template>
                    <VListItemTitle class="text-body-2 font-weight-semibold">
                      {{ item.raw?.data?.nama }}
                    </VListItemTitle>
                    <VListItemSubtitle v-if="item.raw?.data?.spesialis" class="text-caption">
                      {{ item.raw.data.spesialis }}
                    </VListItemSubtitle>
                  </VListItem>
                </template>
              </VAutocomplete>

              <VTextField
                v-model="form.tanggal_op"
                label="Tanggal Rencana Masuk / Operasi"
                type="date"
                variant="outlined"
                density="compact"
                prepend-inner-icon="ri-calendar-2-line"
                hide-details="auto"
              />

              <VSelect
                v-model="form.status_pasien"
                :items="statusOpts"
                item-title="title"
                item-value="value"
                label="Status Pasien"
                variant="outlined"
                density="compact"
                clearable
                hide-details="auto"
                :prepend-inner-icon="
                  form.status_pasien === 'Bedah'     ? 'ri-surgical-mask-line' :
                  form.status_pasien === 'Non Bedah' ? 'ri-hospital-line'       :
                  'ri-question-line'
                "
              />

              <VTextarea
                v-model="form.catatan"
                label="Catatan"
                variant="outlined"
                density="compact"
                rows="2"
                auto-grow
                prepend-inner-icon="ri-sticky-note-line"
                hide-details="auto"
              />

            </div>
          </div>

          <!-- ── SECTION: Petugas ──────────────────────────────────────── -->
          <div class="bl-section bl-section--secondary">
            <div class="bl-sec-hd bl-sec-hd--secondary">
              <VIcon icon="ri-nurse-line" size="14" />
              <span>Petugas</span>
            </div>
            <div class="bl-sec-body">
              <VAutocomplete
                v-model="form.petugas"
                :items="pegawaiStore.namaList"
                label="Nama Petugas"
                variant="outlined"
                density="compact"
                prepend-inner-icon="ri-nurse-line"
                clearable
                hide-details="auto"
                :loading="pegawaiStore.loading"
                no-data-text="Memuat petugas..."
              />
            </div>
          </div>

        </div>
      </div>

      <!-- ── Footer ─────────────────────────────────────────────────────── -->
      <div class="bl-footer">
        <VBtn variant="outlined" rounded="lg" size="small" @click="close">Batal</VBtn>
        <VBtn
          color="primary"
          rounded="lg"
          class="flex-grow-1"
          prepend-icon="ri-save-line"
          :loading="saving || store.loading"
          @click="handleSave"
        >
          {{ editItem ? 'Simpan Perubahan' : 'Simpan Booklist' }}
        </VBtn>
      </div>

    </VCard>
  </VDialog>
</template>

<style scoped>
/* ── Dialog card ── */
.bl-dlg-card {
  display: flex;
  flex-direction: column;
  max-height: 92dvh;
}

.bl-body {
  flex: 1 1 auto;
  overflow-y: auto;
  overscroll-behavior: contain;
}

/* ── Banner ── */
.bl-banner {
  flex-shrink: 0;
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 55%, #60a5fa 100%);
  padding: 16px 18px 14px;
}

.bl-blob {
  position: absolute;
  border-radius: 50%;
  background: #fff;
}
.bl-blob--1 { width: 180px; height: 180px; opacity: .08; top: -60px; right: -40px; }
.bl-blob--2 { width: 80px;  height: 80px;  opacity: .06; bottom: -30px; right: 90px; }

.bl-inner {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 12px;
}

.bl-icon {
  width: 40px; height: 40px; flex-shrink: 0;
  border-radius: 12px;
  background: rgba(255,255,255,.22);
  border: 1.5px solid rgba(255,255,255,.35);
  display: flex; align-items: center; justify-content: center;
}

.bl-text { flex: 1; min-width: 0; }
.bl-sub  { font-size: .6rem; color: rgba(255,255,255,.72); margin: 0 0 1px; letter-spacing: .05em; text-transform: uppercase; }
.bl-title { font-size: .95rem; font-weight: 800; color: #fff; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.bl-clock {
  font-size: .58rem;
  color: rgba(255,255,255,.75);
  flex-shrink: 0;
  background: rgba(0,0,0,.15);
  border-radius: 20px;
  padding: 3px 8px;
  display: flex; align-items: center; white-space: nowrap;
}

.bl-close {
  flex-shrink: 0;
  background: rgba(255,255,255,.2);
  border: none; cursor: pointer;
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff; transition: background .15s;
}
.bl-close:hover { background: rgba(255,255,255,.35); }

/* ── Sections ── */
.bl-section {
  border-radius: 14px;
  border: 1.5px solid rgba(var(--v-border-color), var(--v-border-opacity));
  overflow: hidden;
}
.bl-section:focus-within {
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), .07);
}
.bl-section--primary:focus-within  { border-color: rgba(var(--v-theme-primary), .35); box-shadow: 0 0 0 3px rgba(var(--v-theme-primary),.08); }
.bl-section--info:focus-within     { border-color: rgba(var(--v-theme-info), .35);    box-shadow: 0 0 0 3px rgba(var(--v-theme-info),.08); }
.bl-section--warning:focus-within  { border-color: rgba(var(--v-theme-warning), .35); box-shadow: 0 0 0 3px rgba(var(--v-theme-warning),.08); }
.bl-section--secondary:focus-within{ border-color: rgba(var(--v-theme-secondary),.35);box-shadow: 0 0 0 3px rgba(var(--v-theme-secondary),.08); }

.bl-sec-hd {
  display: flex; align-items: center; gap: 7px;
  padding: 9px 14px;
  font-size: .68rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .07em;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.bl-sec-hd--primary   { background: rgba(var(--v-theme-primary), .07);   color: rgb(var(--v-theme-primary)); }
.bl-sec-hd--info      { background: rgba(var(--v-theme-info), .07);      color: rgb(var(--v-theme-info)); }
.bl-sec-hd--warning   { background: rgba(var(--v-theme-warning), .07);   color: rgb(var(--v-theme-warning)); }
.bl-sec-hd--secondary { background: rgba(var(--v-theme-secondary), .07); color: rgb(var(--v-theme-secondary)); }

.bl-sec-req {
  margin-left: auto;
  font-size: .55rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;
  padding: 1px 6px; border-radius: 10px;
  background: rgba(var(--v-theme-primary), .12);
  color: rgb(var(--v-theme-primary));
}

.bl-sec-body { padding: 14px; }

/* ── Info grid ── */
.bl-info-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 1px;
  background: rgba(var(--v-border-color), var(--v-border-opacity));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px; overflow: hidden; margin-top: 2px;
}
.bl-ig-cell {
  display: flex; flex-direction: column; gap: 2px;
  padding: 9px 11px;
  background: rgba(var(--v-theme-surface), 1);
}
.bl-ig-cell--span2 { grid-column: span 2; }
.bl-ig-lbl {
  font-size: .58rem; font-weight: 600; text-transform: uppercase;
  letter-spacing: .07em; color: rgba(var(--v-theme-on-surface), .38);
  display: flex; align-items: center;
}
.bl-ig-val { font-size: .82rem; font-weight: 600; color: rgba(var(--v-theme-on-surface), .85); word-break: break-word; }
.bl-ig-val--accent { font-weight: 700; color: rgba(var(--v-theme-on-surface), .92); }
.bl-ig-val--mono   { font-family: monospace; font-size: .78rem; letter-spacing: .03em; }

/* ── Empty pasien ── */
.bl-empty-pasien {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 20px 10px; gap: 4px;
  border: 1.5px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px; margin-top: 2px;
}

/* ── Footer ── */
.bl-footer {
  flex-shrink: 0;
  display: flex; gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-surface-variant), .25);
}

/* ── Transition ── */
.slide-down-enter-active { transition: all .25s cubic-bezier(.4,0,.2,1); }
.slide-down-leave-active { transition: all .18s ease; }
.slide-down-enter-from  { opacity: 0; transform: translateY(-8px); }
.slide-down-leave-to    { opacity: 0; transform: translateY(-4px); }
</style>
