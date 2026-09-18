<script setup>
import axios from 'axios'
import PageHero from '@/components/PageHero.vue'

const loading  = ref(false)
const saving   = ref(false)
const masterData = ref({})   // { category_key: [item, ...] }
const snackbar = ref({ show: false, message: '', color: 'success' })

// ── Tambah Item ───────────────────────────────────────────────────────────────
const showAddDialog    = ref(false)
const activeCategory   = ref('')
const newItem          = ref('')
const addLoading       = ref(false)

// ── Hapus Item ────────────────────────────────────────────────────────────────
const showDeleteDialog = ref(false)
const deleteTarget     = ref({ category: '', index: -1, item: '' })

// ── Tambah Kategori ───────────────────────────────────────────────────────────
const showAddCategoryDialog = ref(false)
const newCategoryLabel      = ref('')
const addCategoryLoading    = ref(false)
// Kategori baru yang dibuat sesi ini (belum punya item, tidak ada di DB)
const pendingCategories     = ref([])   // [{ key, label }]

// ── Hapus Kategori ────────────────────────────────────────────────────────────
const showDeleteCategoryDialog = ref(false)
const deleteCategoryTarget     = ref({ key: '', label: '' })

// ── Edit mode ─────────────────────────────────────────────────────────────────
const editMode   = ref({})
const editBuffer = ref({})

// ── Konfigurasi tampilan kategori bawaan ─────────────────────────────────────
const CATEGORY_CONFIG = {
  ket_bayar:        { label: 'Keterangan Bayar / Jaminan',  icon: 'ri-money-cny-circle-line',  color: 'success', group: 'Pasien' },
  jaminan:          { label: 'Jenis Jaminan',               icon: 'ri-shield-line',             color: 'teal',    group: 'Pasien' },
  cara_masuk:       { label: 'Cara Masuk',                  icon: 'ri-door-open-line',          color: 'info',    group: 'Pasien' },
  diagnosa:         { label: 'Diagnosa',                    icon: 'ri-heart-pulse-line',        color: 'error',   group: 'Pasien' },
  ruangan:          { label: 'Ruangan',                     icon: 'ri-building-2-line',         color: 'primary', group: 'Fasilitas' },
  kelas:            { label: 'Kelas Kamar',                 icon: 'ri-hotel-line',              color: 'purple',  group: 'Fasilitas' },
  bangsal:          { label: 'Bangsal',                     icon: 'ri-map-pin-line',            color: 'indigo',  group: 'Fasilitas' },
  note_kamar:       { label: 'Opsi Kamar (Note)',           icon: 'ri-home-4-line',             color: 'blue',    group: 'Fasilitas' },
  keterangan_batal: { label: 'Keterangan Batal Ranap',      icon: 'ri-close-circle-line',       color: 'error',   group: 'Operasional' },
  status_ok:        { label: 'Status Batal Ranap',          icon: 'ri-checkbox-circle-line',    color: 'warning', group: 'Operasional' },
  ket_up_selling:   { label: 'Keterangan Up Selling',       icon: 'ri-arrow-up-circle-line',    color: 'success', group: 'Operasional' },
  status_ket_qc:    { label: 'Status Keterangan QC',        icon: 'ri-shield-check-line',       color: 'primary', group: 'Operasional' },
  alasan_pilih:     { label: 'Alasan Memilih RS',           icon: 'ri-questionnaire-line',      color: 'orange',  group: 'Pasien' },
}

// Warna & icon default untuk kategori custom (rotasi)
const CUSTOM_COLORS = ['secondary','deep-purple','cyan','amber','pink','lime','brown']
const CUSTOM_ICONS  = [
  'ri-folder-line','ri-list-check','ri-apps-line','ri-bookmark-line',
  'ri-price-tag-3-line','ri-archive-line','ri-file-list-line',
]

function customConfig(key, idx = 0) {
  return {
    label: key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
    icon:  CUSTOM_ICONS[idx % CUSTOM_ICONS.length],
    color: CUSTOM_COLORS[idx % CUSTOM_COLORS.length],
    group: 'Lainnya',
  }
}

// ── Semua kategori (bawaan + DB + pending) ─────────────────────────────────
const allCategories = computed(() => {
  const result = {}

  // 1. Kategori bawaan — tampilkan walau kosong
  Object.entries(CATEGORY_CONFIG).forEach(([key, cfg]) => {
    result[key] = { ...cfg, key, items: masterData.value[key] ?? [] }
  })

  // 2. Kategori dari DB yang tidak ada di CATEGORY_CONFIG (custom)
  let customIdx = 0
  Object.keys(masterData.value).forEach(key => {
    if (!result[key]) {
      result[key] = { ...customConfig(key, customIdx++), key, items: masterData.value[key] ?? [] }
    }
  })

  // 3. Kategori baru yang baru dibuat sesi ini, belum ada item → tampil sebagai kartu kosong
  pendingCategories.value.forEach((pc, i) => {
    if (!result[pc.key]) {
      result[pc.key] = {
        label: pc.label,
        icon:  CUSTOM_ICONS[(customIdx + i) % CUSTOM_ICONS.length],
        color: CUSTOM_COLORS[(customIdx + i) % CUSTOM_COLORS.length],
        group: 'Lainnya',
        key:   pc.key,
        items: [],
      }
    }
  })

  return result
})

// ── Filter & Group ────────────────────────────────────────────────────────────
const searchCat   = ref('')
const activeGroup = ref('All')

const groupNames = computed(() => {
  const names = [...new Set(Object.values(allCategories.value).map(c => c.group))]
  return ['All', ...names]
})

const visibleGroups = computed(() => {
  const g = {}
  Object.values(allCategories.value).forEach(cat => {
    if (activeGroup.value !== 'All' && cat.group !== activeGroup.value) return
    const q = searchCat.value.toLowerCase()
    if (q && !cat.label.toLowerCase().includes(q) && !cat.key.includes(q)) return
    if (!g[cat.group]) g[cat.group] = []
    g[cat.group].push(cat)
  })
  return g
})

const totalItems = computed(() =>
  Object.values(masterData.value).reduce((s, arr) => s + arr.length, 0)
)

const todayFormatted = computed(() =>
  new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
)

// ── Helpers ───────────────────────────────────────────────────────────────────
function notify(message, color = 'success') {
  snackbar.value = { show: true, message, color }
}

// ── Load ──────────────────────────────────────────────────────────────────────
async function loadMasterData() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/master-data')
    masterData.value = data
  } catch {
    notify('Gagal memuat master data.', 'error')
  } finally {
    loading.value = false
  }
}

// ── Tambah Kategori ───────────────────────────────────────────────────────────
function openAddCategoryDialog() {
  newCategoryLabel.value = ''
  showAddCategoryDialog.value = true
}

async function addCategory() {
  if (!newCategoryLabel.value.trim()) return
  addCategoryLoading.value = true
  try {
    const { data } = await axios.post('/api/master-data/category', {
      label: newCategoryLabel.value.trim(),
    })
    // Hapus dari pending jika ada, lalu update masterData
    masterData.value = data.data
    // Tambahkan ke pending agar kartu langsung muncul (kategori belum punya item)
    const key = data.category.key
    pendingCategories.value = pendingCategories.value.filter(p => p.key !== key)
    pendingCategories.value.push({ key, label: newCategoryLabel.value.trim() })
    notify(`Kategori "${data.category.label}" berhasil dibuat.`)
    showAddCategoryDialog.value = false
    newCategoryLabel.value = ''
  } catch (e) {
    notify(e.response?.data?.message ?? 'Gagal membuat kategori.', 'error')
  } finally {
    addCategoryLoading.value = false
  }
}

// ── Hapus Kategori ────────────────────────────────────────────────────────────
function openDeleteCategoryDialog(cat) {
  deleteCategoryTarget.value = { key: cat.key, label: cat.label }
  showDeleteCategoryDialog.value = true
}

async function deleteCategory() {
  saving.value = true
  const { key, label } = deleteCategoryTarget.value
  try {
    const { data } = await axios.delete(`/api/master-data/category/${key}`)
    masterData.value = data.data
    pendingCategories.value = pendingCategories.value.filter(p => p.key !== key)
    notify(`Kategori "${label}" dihapus.`)
    showDeleteCategoryDialog.value = false
  } catch (e) {
    notify(e.response?.data?.message ?? 'Gagal menghapus kategori.', 'error')
  } finally {
    saving.value = false
  }
}

// ── Tambah Item ───────────────────────────────────────────────────────────────
function openAddDialog(category) {
  activeCategory.value = category
  newItem.value = ''
  showAddDialog.value = true
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
    // Hapus dari pending karena sudah punya item di DB
    pendingCategories.value = pendingCategories.value.filter(p => p.key !== activeCategory.value)
    notify(`"${newItem.value}" ditambahkan.`)
    showAddDialog.value = false
    newItem.value = ''
  } catch (e) {
    notify(e.response?.data?.message ?? 'Gagal menambahkan item.', 'error')
  } finally {
    addLoading.value = false
  }
}

// ── Hapus Item ────────────────────────────────────────────────────────────────
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

// ── Edit mode ─────────────────────────────────────────────────────────────────
function startEdit(category) {
  editBuffer.value[category] = [...(masterData.value[category] ?? [])]
  editMode.value[category]   = true
}

function cancelEdit(category) {
  editMode.value[category]   = false
  editBuffer.value[category] = []
}

async function saveEdit(category) {
  saving.value = true
  try {
    const { data } = await axios.put(`/api/master-data/${category}`, {
      items: editBuffer.value[category],
    })
    masterData.value = data.data
    editMode.value[category] = false
    notify('Perubahan disimpan.')
  } catch {
    notify('Gagal menyimpan perubahan.', 'error')
  } finally {
    saving.value = false
  }
}

function removeFromBuffer(category, index) {
  editBuffer.value[category]?.splice(index, 1)
}

// ── Computed label kategori aktif ─────────────────────────────────────────────
const activeCategoryLabel = computed(() =>
  allCategories.value[activeCategory.value]?.label ?? activeCategory.value
)
const activeCategoryColor = computed(() =>
  allCategories.value[activeCategory.value]?.color ?? 'primary'
)
const activeCategoryIcon = computed(() =>
  allCategories.value[activeCategory.value]?.icon ?? 'ri-add-line'
)

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
        { icon: 'ri-list-check',    text: `${Object.keys(allCategories).length} kategori` },
        { icon: 'ri-stack-line',    text: `${totalItems} item` },
      ]"
    />

    <!-- Info -->
    <VAlert type="info" variant="tonal" border="start" density="compact" class="mb-4" closable>
      <div class="text-caption">
        <strong>Master Data</strong> adalah data referensi yang digunakan pada dropdown di seluruh formulir.
        Perubahan akan langsung berlaku di semua form yang menggunakan data tersebut.
      </div>
    </VAlert>

    <!-- Filter bar + Tambah Kategori -->
    <div class="d-flex gap-2 mb-4 flex-wrap align-center">
      <VTextField
        v-model="searchCat"
        label="Cari kategori..."
        prepend-inner-icon="ri-search-line"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        rounded="lg"
        style="max-width:220px"
      />
      <VChip
        v-for="g in groupNames"
        :key="g"
        :color="activeGroup === g ? 'primary' : 'default'"
        :variant="activeGroup === g ? 'elevated' : 'outlined'"
        size="small"
        class="cursor-pointer"
        @click="activeGroup = g"
      >{{ g }}</VChip>

      <VSpacer />

      <VBtn
        color="primary"
        variant="tonal"
        prepend-icon="ri-add-circle-line"
        rounded="lg"
        size="small"
        @click="openAddCategoryDialog"
      >
        Tambah Kategori
      </VBtn>
    </div>

    <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-4" rounded />

    <!-- Groups -->
    <div v-for="(groupItems, groupName) in visibleGroups" :key="groupName" class="mb-6">
      <div class="d-flex align-center gap-2 mb-3">
        <VDivider />
        <VChip color="primary" variant="tonal" size="small"
          class="text-caption font-weight-bold text-uppercase flex-shrink-0">
          {{ groupName }}
        </VChip>
        <VDivider />
      </div>

      <VRow>
        <VCol v-for="cat in groupItems" :key="cat.key" cols="12" sm="6" lg="4">
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
                    <p class="text-caption text-medium-emphasis mb-0">
                      <span class="text-xs opacity-60 me-1">{{ cat.key }}</span>·
                      {{ cat.items.length }} item
                    </p>
                  </div>
                </div>

                <div class="d-flex gap-1">
                  <template v-if="!editMode[cat.key]">
                    <VTooltip text="Tambah Item">
                      <template #activator="{ props }">
                        <VBtn v-bind="props" icon size="x-small" variant="text" :color="cat.color"
                          @click="openAddDialog(cat.key)">
                          <VIcon icon="ri-add-line" size="16" />
                        </VBtn>
                      </template>
                    </VTooltip>
                    <VTooltip text="Edit Semua">
                      <template #activator="{ props }">
                        <VBtn v-bind="props" icon size="x-small" variant="text" color="warning"
                          @click="startEdit(cat.key)">
                          <VIcon icon="ri-pencil-line" size="16" />
                        </VBtn>
                      </template>
                    </VTooltip>
                    <VTooltip text="Hapus Kategori">
                      <template #activator="{ props }">
                        <VBtn v-bind="props" icon size="x-small" variant="text" color="error"
                          @click="openDeleteCategoryDialog(cat)">
                          <VIcon icon="ri-delete-bin-2-line" size="16" />
                        </VBtn>
                      </template>
                    </VTooltip>
                  </template>
                  <template v-else>
                    <VBtn size="x-small" color="success" variant="tonal" rounded="lg" :loading="saving"
                      @click="saveEdit(cat.key)">
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

            <!-- Items — normal mode -->
            <VCardText v-if="!editMode[cat.key]" class="pa-3">
              <div v-if="cat.items.length === 0" class="text-center py-4 text-medium-emphasis">
                <VIcon icon="ri-inbox-line" size="24" class="mb-1 opacity-40" />
                <p class="text-caption mb-1">Belum ada item</p>
                <VBtn size="x-small" variant="tonal" :color="cat.color" prepend-icon="ri-add-line"
                  rounded="lg" @click="openAddDialog(cat.key)">
                  Tambah item pertama
                </VBtn>
              </div>
              <div v-else class="d-flex flex-wrap gap-1">
                <VChip
                  v-for="(item, idx) in cat.items"
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

            <!-- Items — edit mode -->
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
                <p v-if="!(editBuffer[cat.key] ?? []).length" class="text-caption text-medium-emphasis mb-0">
                  Semua item dihapus. Klik Simpan untuk konfirmasi.
                </p>
              </div>
              <p class="text-caption text-medium-emphasis mb-0">
                Klik × di chip untuk hapus. Klik "Simpan" untuk konfirmasi.
              </p>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>

    <!-- Empty state -->
    <div v-if="!loading && Object.keys(visibleGroups).length === 0" class="text-center py-10">
      <VIcon icon="ri-search-line" size="40" class="opacity-30 mb-3" />
      <p class="text-medium-emphasis">Tidak ada kategori yang cocok.</p>
    </div>

    <!-- ── Dialog: Tambah Kategori ─────────────────────────────────────────── -->
    <VDialog v-model="showAddCategoryDialog" max-width="440">
      <VCard rounded="xl">
        <VCardTitle class="pa-5 pb-2">
          <div class="d-flex align-center gap-3">
            <VAvatar color="primary" variant="tonal" size="40" rounded="lg">
              <VIcon icon="ri-add-circle-line" size="18" />
            </VAvatar>
            <div>
              <h6 class="text-subtitle-1 font-weight-bold mb-0">Tambah Kategori Baru</h6>
              <p class="text-caption text-medium-emphasis mb-0">
                Kategori akan otomatis dibuatkan key dari label
              </p>
            </div>
          </div>
        </VCardTitle>
        <VCardText class="pa-5 pt-3">
          <VTextField
            v-model="newCategoryLabel"
            label="Nama Kategori"
            placeholder="Contoh: Cara Pembayaran"
            variant="outlined"
            density="compact"
            autofocus
            hint="Key otomatis: cara_pembayaran"
            :persistent-hint="!!newCategoryLabel.trim()"
            @keyup.enter="addCategory"
          >
            <template v-if="newCategoryLabel.trim()" #details>
              <span class="text-caption text-medium-emphasis">
                Key: <code>{{ newCategoryLabel.trim().toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '') }}</code>
              </span>
            </template>
          </VTextField>
          <VAlert type="info" variant="tonal" density="compact" class="mt-3 text-caption">
            Key adalah identifer unik yang digunakan di backend dan store.
            Tidak dapat diubah setelah dibuat.
          </VAlert>
        </VCardText>
        <VCardActions class="pa-4 pt-0 d-flex gap-2">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showAddCategoryDialog = false">Batal</VBtn>
          <VBtn
            color="primary"
            rounded="lg"
            class="flex-grow-1"
            :loading="addCategoryLoading"
            :disabled="!newCategoryLabel.trim()"
            @click="addCategory"
          >
            Buat Kategori
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Dialog: Hapus Kategori ──────────────────────────────────────────── -->
    <VDialog v-model="showDeleteCategoryDialog" max-width="380">
      <VCard rounded="lg">
        <VCardText class="pa-6 text-center">
          <VAvatar color="error" variant="tonal" size="52" rounded="xl" class="mb-3">
            <VIcon icon="ri-folder-reduce-line" size="24" />
          </VAvatar>
          <h6 class="text-subtitle-1 font-weight-bold mb-2">Hapus Kategori?</h6>
          <p class="text-body-2 text-medium-emphasis mb-1">
            Kategori <strong>{{ deleteCategoryTarget.label }}</strong>
            <VChip size="x-small" color="secondary" variant="tonal" class="ms-1">{{ deleteCategoryTarget.key }}</VChip>
            dan semua item di dalamnya akan dihapus permanen.
          </p>
          <p class="text-caption text-error">Tindakan ini tidak dapat dibatalkan.</p>
        </VCardText>
        <VCardActions class="px-5 pb-5 pt-0 d-flex gap-2">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showDeleteCategoryDialog = false">Batal</VBtn>
          <VBtn color="error" rounded="lg" class="flex-grow-1" :loading="saving" @click="deleteCategory">
            Hapus Kategori
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Dialog: Tambah Item ─────────────────────────────────────────────── -->
    <VDialog v-model="showAddDialog" max-width="420">
      <VCard rounded="xl">
        <VCardTitle class="pa-5 pb-2">
          <div class="d-flex align-center gap-3">
            <VAvatar :color="activeCategoryColor" variant="tonal" size="40" rounded="lg">
              <VIcon :icon="activeCategoryIcon" size="18" />
            </VAvatar>
            <div>
              <h6 class="text-subtitle-1 font-weight-bold mb-0">Tambah Item</h6>
              <p class="text-caption text-medium-emphasis mb-0">{{ activeCategoryLabel }}</p>
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
            :color="activeCategoryColor"
            rounded="lg"
            class="flex-grow-1"
            :loading="addLoading"
            :disabled="!newItem.trim()"
            @click="addItem"
          >
            Tambah
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Dialog: Hapus Item ──────────────────────────────────────────────── -->
    <VDialog v-model="showDeleteDialog" max-width="360">
      <VCard rounded="lg">
        <VCardText class="pa-6 text-center">
          <VAvatar color="error" variant="tonal" size="52" rounded="xl" class="mb-3">
            <VIcon icon="ri-delete-bin-2-line" size="24" />
          </VAvatar>
          <h6 class="text-subtitle-1 font-weight-bold mb-2">Hapus Item?</h6>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Item <VChip size="x-small" color="error" variant="tonal">{{ deleteTarget.item }}</VChip>
            akan dihapus dari <strong>{{ allCategories[deleteTarget.category]?.label ?? deleteTarget.category }}</strong>.
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
