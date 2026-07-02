<script setup>
import axios from 'axios'
import PageHero from '@/components/PageHero.vue'

const loading      = ref(false)
const saving       = ref(false)
const masterData   = ref({})
const activeCategory = ref('')
const snackbar     = ref({ show: false, message: '', color: 'success' })

// Dialog untuk tambah item
const showAddDialog  = ref(false)
const newItem        = ref('')
const addLoading     = ref(false)

// Dialog untuk hapus item
const showDeleteDialog = ref(false)
const deleteTarget     = ref({ category: '', index: -1, item: '' })

// Inline edit per kategori (replace seluruh list)
const editMode    = ref({})
const editBuffer  = ref({})

// Konfigurasi tampilan tiap kategori
const CATEGORY_CONFIG = {
  ket_bayar:        { label: 'Keterangan Bayar / Jaminan', icon: 'ri-money-cny-circle-line', color: 'success',   group: 'Pasien' },
  jaminan:          { label: 'Jenis Jaminan',               icon: 'ri-shield-line',            color: 'teal',      group: 'Pasien' },
  cara_masuk:       { label: 'Cara Masuk',                  icon: 'ri-door-open-line',          color: 'info',      group: 'Pasien' },
  diagnosa:         { label: 'Diagnosa',                    icon: 'ri-heart-pulse-line',        color: 'error',     group: 'Pasien' },
  ruangan:          { label: 'Ruangan',                     icon: 'ri-building-2-line',         color: 'primary',   group: 'Fasilitas' },
  kelas:            { label: 'Kelas Kamar',                 icon: 'ri-hotel-line',              color: 'purple',    group: 'Fasilitas' },
  bangsal:          { label: 'Bangsal',                     icon: 'ri-map-pin-line',            color: 'indigo',    group: 'Fasilitas' },
  note_kamar:       { label: 'Opsi Kamar (Note)',           icon: 'ri-home-4-line',             color: 'blue',      group: 'Fasilitas' },
  keterangan_batal: { label: 'Keterangan Batal Ranap',      icon: 'ri-close-circle-line',       color: 'error',     group: 'Operasional' },
  status_ok:        { label: 'Status Batal Ranap',          icon: 'ri-checkbox-circle-line',    color: 'warning',   group: 'Operasional' },
  ket_up_selling:   { label: 'Keterangan Up Selling',       icon: 'ri-arrow-up-circle-line',    color: 'success',   group: 'Operasional' },
  status_ket_qc:    { label: 'Status Keterangan QC',        icon: 'ri-shield-check-line',       color: 'primary',   group: 'Operasional' },
}

const groups = computed(() => {
  const g = {}
  Object.entries(CATEGORY_CONFIG).forEach(([key, cfg]) => {
    if (!g[cfg.group]) g[cfg.group] = []
    g[cfg.group].push({ key, ...cfg, items: masterData.value[key] ?? [] })
  })
  return g
})

const todayFormatted = computed(() =>
  new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
)

const searchCat   = ref('')
const activeGroup = ref('All')

const groupNames = computed(() => ['All', ...new Set(Object.values(CATEGORY_CONFIG).map(c => c.group))])

const visibleGroups = computed(() => {
  const filtered = {}
  Object.entries(groups.value).forEach(([name, items]) => {
    if (activeGroup.value !== 'All' && name !== activeGroup.value) return
    const q = searchCat.value.toLowerCase()
    const filtItems = q ? items.filter(i => i.label.toLowerCase().includes(q) || i.key.includes(q)) : items
    if (filtItems.length) filtered[name] = filtItems
  })
  return filtered
})

function notify(message, color = 'success') {
  snackbar.value = { show: true, message, color }
}

async function loadMasterData() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/master-data')
    masterData.value = data
  } catch (e) {
    notify('Gagal memuat master data.', 'error')
  } finally {
    loading.value = false
  }
}

function openAddDialog(category) {
  activeCategory.value = category
  newItem.value        = ''
  showAddDialog.value  = true
}

async function addItem() {
  if (!newItem.value.trim()) return
  addLoading.value = true
  try {
    const { data } = await axios.post('/api/master-data', {
      category: activeCategory.value,
      item:     newItem.value.trim(),
    })
    masterData.value = data.data
    notify(`"${newItem.value}" ditambahkan.`)
    showAddDialog.value = false
    newItem.value = ''
  } catch (e) {
    notify(e.response?.data?.message ?? 'Gagal menambahkan item.', 'error')
  } finally {
    addLoading.value = false
  }
}

function openDeleteDialog(category, index, item) {
  deleteTarget.value = { category, index, item }
  showDeleteDialog.value = true
}

async function deleteItem() {
  const { category, index } = deleteTarget.value
  saving.value = true
  try {
    const { data } = await axios.delete(`/api/master-data/${category}/${index}`)
    masterData.value = data.data
    notify(`"${deleteTarget.value.item}" dihapus.`)
    showDeleteDialog.value = false
  } catch (e) {
    notify(e.response?.data?.message ?? 'Gagal menghapus item.', 'error')
  } finally {
    saving.value = false
  }
}

function startEdit(category) {
  editBuffer.value[category] = [...(masterData.value[category] ?? [])]
  editMode.value[category]   = true
}

function cancelEdit(category) {
  editMode.value[category]  = false
  editBuffer.value[category] = []
}

async function saveEdit(category) {
  saving.value = true
  try {
    const { data } = await axios.put(`/api/master-data/${category}`, {
      items: editBuffer.value[category],
    })
    masterData.value = data.data
    editMode.value[category]  = false
    notify('Perubahan disimpan.')
  } catch (e) {
    notify('Gagal menyimpan perubahan.', 'error')
  } finally {
    saving.value = false
  }
}

function removeFromBuffer(category, index) {
  editBuffer.value[category]?.splice(index, 1)
}

onMounted(() => loadMasterData())
</script>

<template>
  <div>
    <!-- Hero -->
    <PageHero
      icon="ri-database-2-line"
      badge="Admin · Master Data"
      title="Master Data"
      subtitle="Kelola data referensi untuk semua dropdown aplikasi QC Admission"
      color-from="#0EA5E9"
      color-to="#0369A1"
      :pills="[
        { icon: 'ri-calendar-line', text: todayFormatted },
        { icon: 'ri-list-check', text: `${Object.keys(CATEGORY_CONFIG).length} kategori` },
      ]"
    >
    </PageHero>

    <!-- Info -->
    <VAlert type="info" variant="tonal" border="start" density="compact" class="mb-4" closable>
      <div class="text-caption">
        <strong>Master Data</strong> adalah data referensi yang digunakan pada dropdown di seluruh formulir.
        Perubahan akan langsung berlaku di semua form yang menggunakan data tersebut.
      </div>
    </VAlert>

    <!-- Category filter bar -->
    <div class="d-flex gap-2 mb-4 flex-wrap align-center">
      <VTextField v-model="searchCat" label="Cari kategori..." prepend-inner-icon="ri-search-line"
        variant="outlined" density="compact" hide-details clearable rounded="lg" style="max-width:220px" />
      <VChip
        v-for="g in groupNames" :key="g"
        :color="activeGroup === g ? 'primary' : 'default'"
        :variant="activeGroup === g ? 'elevated' : 'outlined'"
        size="small" class="cursor-pointer"
        @click="activeGroup = g"
      >{{ g }}</VChip>
    </div>

    <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-4" rounded />

    <!-- Groups -->
    <div v-for="(groupItems, groupName) in groups" :key="groupName" class="mb-6">
      <div class="d-flex align-center gap-2 mb-3">
        <VDivider />
        <VChip color="primary" variant="tonal" size="small" class="text-caption font-weight-bold text-uppercase flex-shrink-0">
          {{ groupName }}
        </VChip>
        <VDivider />
      </div>

      <VRow>
        <VCol
          v-for="cat in groupItems"
          :key="cat.key"
          cols="12" sm="6" lg="4"
        >
          <VCard elevation="0" border rounded="xl" class="h-100">
            <!-- Card header -->
            <VCardTitle class="pa-4 pb-2">
              <div class="d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-2">
                  <VAvatar :color="cat.color" variant="tonal" size="32" rounded="lg">
                    <VIcon :icon="cat.icon" size="16" />
                  </VAvatar>
                  <div>
                    <p class="text-body-2 font-weight-semibold mb-0">{{ cat.label }}</p>
                    <p class="text-caption text-medium-emphasis mb-0">{{ (masterData[cat.key] ?? []).length }} item</p>
                  </div>
                </div>
                <div class="d-flex gap-1">
                  <template v-if="!editMode[cat.key]">
                    <VTooltip text="Tambah Item">
                      <template #activator="{ props }">
                        <VBtn v-bind="props" icon size="x-small" variant="text" :color="cat.color" @click="openAddDialog(cat.key)">
                          <VIcon icon="ri-add-line" size="16" />
                        </VBtn>
                      </template>
                    </VTooltip>
                    <VTooltip text="Edit Semua">
                      <template #activator="{ props }">
                        <VBtn v-bind="props" icon size="x-small" variant="text" color="warning" @click="startEdit(cat.key)">
                          <VIcon icon="ri-pencil-line" size="16" />
                        </VBtn>
                      </template>
                    </VTooltip>
                  </template>
                  <template v-else>
                    <VBtn size="x-small" color="success" variant="tonal" rounded="lg" :loading="saving" @click="saveEdit(cat.key)">
                      Simpan
                    </VBtn>
                    <VBtn size="x-small" variant="text" color="secondary" @click="cancelEdit(cat.key)">
                      Batal
                    </VBtn>
                  </template>
                </div>
              </div>
            </VCardTitle>

            <VDivider />

            <!-- Items list — normal mode -->
            <VCardText v-if="!editMode[cat.key]" class="pa-3">
              <div v-if="(masterData[cat.key] ?? []).length === 0" class="text-center py-4 text-medium-emphasis">
                <VIcon icon="ri-inbox-line" size="24" class="mb-1 opacity-40" />
                <p class="text-caption mb-0">Belum ada item</p>
              </div>
              <div v-else class="d-flex flex-wrap gap-1">
                <VChip
                  v-for="(item, idx) in (masterData[cat.key] ?? [])"
                  :key="idx"
                  :color="cat.color"
                  size="small"
                  variant="tonal"
                  closable
                  @click:close="openDeleteDialog(cat.key, idx, item)"
                >
                  {{ item }}
                </VChip>
              </div>
            </VCardText>

            <!-- Items list — edit mode -->
            <VCardText v-else class="pa-3">
              <div class="d-flex flex-wrap gap-1 mb-2">
                <VChip
                  v-for="(item, idx) in (editBuffer[cat.key] ?? [])"
                  :key="idx"
                  :color="cat.color"
                  size="small"
                  variant="tonal"
                  closable
                  @click:close="removeFromBuffer(cat.key, idx)"
                >
                  {{ item }}
                </VChip>
              </div>
              <p class="text-caption text-medium-emphasis mb-0">
                Klik × di chip untuk hapus item. Klik "Simpan" untuk konfirmasi.
              </p>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>

    <!-- Add Item Dialog -->
    <VDialog v-model="showAddDialog" max-width="420">
      <VCard rounded="xl">
        <VCardTitle class="pa-5 pb-2">
          <div class="d-flex align-center gap-3">
            <VAvatar :color="CATEGORY_CONFIG[activeCategory]?.color ?? 'primary'" variant="tonal" size="40" rounded="lg">
              <VIcon :icon="CATEGORY_CONFIG[activeCategory]?.icon ?? 'ri-add-line'" size="18" />
            </VAvatar>
            <div>
              <h6 class="text-subtitle-1 font-weight-bold mb-0">Tambah Item</h6>
              <p class="text-caption text-medium-emphasis mb-0">{{ CATEGORY_CONFIG[activeCategory]?.label }}</p>
            </div>
          </div>
        </VCardTitle>
        <VCardText class="pa-5 pt-3">
          <VTextField
            v-model="newItem"
            label="Nama Item"
            placeholder="Masukkan nama item baru..."
            variant="outlined"
            density="compact"
            autofocus
            @keyup.enter="addItem"
          />
        </VCardText>
        <VCardActions class="pa-4 pt-0 d-flex gap-2">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showAddDialog = false">Batal</VBtn>
          <VBtn
            :color="CATEGORY_CONFIG[activeCategory]?.color ?? 'primary'"
            rounded="lg" class="flex-grow-1"
            :loading="addLoading"
            :disabled="!newItem.trim()"
            @click="addItem"
          >
            Tambah
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Confirm Dialog -->
    <VDialog v-model="showDeleteDialog" max-width="360">
      <VCard rounded="lg">
        <VCardText class="pa-6 text-center">
          <VAvatar color="error" variant="tonal" size="52" rounded="xl" class="mb-3">
            <VIcon icon="ri-delete-bin-2-line" size="24" />
          </VAvatar>
          <h6 class="text-subtitle-1 font-weight-bold mb-2">Hapus Item?</h6>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Item <VChip size="x-small" color="error" variant="tonal">{{ deleteTarget.item }}</VChip>
            akan dihapus dari <strong>{{ CATEGORY_CONFIG[deleteTarget.category]?.label }}</strong>.
          </p>
        </VCardText>
        <VCardActions class="px-5 pb-5 pt-0 d-flex gap-2">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showDeleteDialog = false">Batal</VBtn>
          <VBtn color="error" rounded="lg" class="flex-grow-1" :loading="saving" @click="deleteItem">Hapus</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Snackbar -->
    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom right" rounded="lg">
      {{ snackbar.message }}
      <template #actions>
        <VBtn variant="text" @click="snackbar.show = false">Tutup</VBtn>
      </template>
    </VSnackbar>
  </div>
</template>