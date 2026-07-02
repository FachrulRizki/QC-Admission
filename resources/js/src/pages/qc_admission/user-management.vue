<script setup>
import axios from 'axios'
import PageHero from '@/components/PageHero.vue'

const loading     = ref(false)
const users       = ref([])
const showDialog  = ref(false)
const showDelete  = ref(false)
const editItem    = ref(null)
const deleteTarget = ref(null)
const snackbar    = ref({ show: false, message: '', color: 'success' })
const search      = ref('')

const formDefault = () => ({
  name:     '',
  username: '',
  email:    '',
  password: '',
  role:     'qc_admission',
})

const form         = ref(formDefault())
const formLoading  = ref(false)
const showPass     = ref(false)

const ROLE_OPTIONS = [
  { title: 'Administrator',  value: 'admin',        icon: 'ri-shield-star-line',  color: 'error' },
  { title: 'QC Admission',   value: 'qc_admission', icon: 'ri-nurse-line',        color: 'primary' },
  { title: 'Kasir',          value: 'kasir',        icon: 'ri-money-cny-box-line',color: 'warning' },
]

const headers = [
  { title: 'Nama',     key: 'name',       sortable: true },
  { title: 'Username', key: 'username',   sortable: true },
  { title: 'Email',    key: 'email',      sortable: true },
  { title: 'Role',     key: 'role',       sortable: true, align: 'center' },
  { title: 'Login',    key: 'login_type', sortable: true, align: 'center' },
  { title: 'Dibuat',   key: 'created_at', sortable: true },
  { title: 'Aksi',     key: 'actions',    sortable: false, align: 'center', width: '100px' },
]

const filteredUsers = computed(() => {
  if (!search.value.trim()) return users.value
  const q = search.value.toLowerCase()
  return users.value.filter(u =>
    u.name?.toLowerCase().includes(q) ||
    u.username?.toLowerCase().includes(q) ||
    u.email?.toLowerCase().includes(q) ||
    u.role?.toLowerCase().includes(q)
  )
})

const stats = computed(() => ({
  total:   users.value.length,
  admin:   users.value.filter(u => u.role === 'admin').length,
  qc:      users.value.filter(u => u.role === 'qc_admission').length,
  kasir:   users.value.filter(u => u.role === 'kasir').length,
}))

function roleColor(r) {
  return { admin: 'error', qc_admission: 'primary', kasir: 'warning' }[r] ?? 'secondary'
}
function roleIcon(r) {
  return { admin: 'ri-shield-star-line', qc_admission: 'ri-nurse-line', kasir: 'ri-money-cny-box-line' }[r] ?? 'ri-user-line'
}
function roleLabel(r) {
  return { admin: 'Administrator', qc_admission: 'QC Admission', kasir: 'Kasir' }[r] ?? r
}
function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function notify(message, color = 'success') {
  snackbar.value = { show: true, message, color }
}

async function loadUsers() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/users')
    users.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch (e) {
    notify('Gagal memuat data user.', 'error')
  } finally {
    loading.value = false
  }
}

function openAdd() {
  editItem.value = null
  form.value     = formDefault()
  showPass.value = false
  showDialog.value = true
}

function openEdit(item) {
  editItem.value   = item
  form.value = {
    name:     item.name,
    username: item.username,
    email:    item.email,
    password: '',
    role:     item.role,
  }
  showPass.value   = false
  showDialog.value = true
}

function openDelete(item) {
  deleteTarget.value = item
  showDelete.value   = true
}

async function saveUser() {
  formLoading.value = true
  try {
    const payload = { ...form.value }
    if (!payload.password) delete payload.password

    if (editItem.value) {
      await axios.put(`/api/users/${editItem.value.id}`, payload)
      notify('User berhasil diperbarui.')
    } else {
      await axios.post('/api/users', payload)
      notify('User berhasil ditambahkan.')
    }
    showDialog.value = false
    await loadUsers()
  } catch (e) {
    const msg = e.response?.data?.message ?? 'Gagal menyimpan user.'
    const errors = e.response?.data?.errors
    if (errors) {
      const firstError = Object.values(errors)[0]
      notify(Array.isArray(firstError) ? firstError[0] : firstError, 'error')
    } else {
      notify(msg, 'error')
    }
  } finally {
    formLoading.value = false
  }
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  formLoading.value = true
  try {
    await axios.delete(`/api/users/${deleteTarget.value.id}`)
    notify('User berhasil dihapus.')
    showDelete.value = false
    deleteTarget.value = null
    await loadUsers()
  } catch (e) {
    notify('Gagal menghapus user.', 'error')
  } finally {
    formLoading.value = false
  }
}

const isEditSelf = computed(() => {
  // Tidak bisa hapus diri sendiri
  return false // akan diisi nanti dengan auth store jika perlu
})

onMounted(() => loadUsers())
</script>

<template>
  <div>
    <!-- Hero -->
    <PageHero
      icon="ri-team-line"
      badge="Admin · Manajemen User"
      title="Manajemen User"
      subtitle="Kelola akun pengguna aplikasi QC Admission · 3 level akses"
      color-from="#0EA5E9"
      color-to="#0369A1"
      text-color="dark"
      :pills="[
        { icon: 'ri-team-line', text: `${stats.total} user` },
        { icon: 'ri-shield-user-line', text: `${stats.admin} admin` },
      ]"
    >
      <template #actions>
        <VBtn color="white" variant="elevated" rounded="lg" prepend-icon="ri-user-add-line" style="color:#0369A1" @click="openAdd">
          Tambah User
        </VBtn>
      </template>
    </PageHero>

    <!-- Stats -->
    <VRow dense class="mb-4">
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="pa-3 text-center">
          <p class="text-h5 font-weight-bold text-primary mb-0">{{ stats.total }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Total User</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="pa-3 text-center">
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-error))">{{ stats.admin }}</p>
          <p class="text-caption text-medium-emphasis mb-0">
            <VIcon icon="ri-shield-star-line" size="12" class="me-1" />Administrator
          </p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="pa-3 text-center">
          <p class="text-h5 font-weight-bold text-primary mb-0">{{ stats.qc }}</p>
          <p class="text-caption text-medium-emphasis mb-0">
            <VIcon icon="ri-nurse-line" size="12" class="me-1" />QC Admission
          </p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="pa-3 text-center">
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-warning))">{{ stats.kasir }}</p>
          <p class="text-caption text-medium-emphasis mb-0">
            <VIcon icon="ri-money-cny-box-line" size="12" class="me-1" />Kasir
          </p>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filter -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VCardText class="py-3">
        <VTextField
          v-model="search"
          placeholder="Cari nama, username, email, role..."
          prepend-inner-icon="ri-search-line"
          variant="outlined" density="compact" hide-details clearable
        />
      </VCardText>
    </VCard>

    <!-- Table -->
    <VCard elevation="0" border rounded="lg">
      <VDataTable
        :headers="headers"
        :items="filteredUsers"
        :loading="loading"
        density="comfortable"
        hover
        :items-per-page="15"
      >
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3 py-1">
            <VAvatar :color="roleColor(item.role)" variant="tonal" size="36" rounded="lg">
              <VIcon :icon="roleIcon(item.role)" size="18" />
            </VAvatar>
            <div>
              <p class="text-body-2 font-weight-semibold mb-0">{{ item.name }}</p>
              <p class="text-caption text-medium-emphasis mb-0">{{ item.email }}</p>
            </div>
          </div>
        </template>

        <template #item.role="{ item }">
          <VChip :color="roleColor(item.role)" size="small" variant="tonal" :prepend-icon="roleIcon(item.role)">
            {{ roleLabel(item.role) }}
          </VChip>
        </template>

        <template #item.login_type="{ item }">
          <VChip
            :color="item.login_type === 'sso' ? 'info' : 'secondary'"
            size="x-small" variant="tonal"
            :prepend-icon="item.login_type === 'sso' ? 'ri-key-2-line' : 'ri-user-3-line'"
          >
            {{ item.login_type === 'sso' ? 'SSO' : 'Lokal' }}
          </VChip>
        </template>

        <template #item.created_at="{ item }">
          <span class="text-caption text-medium-emphasis">{{ formatDate(item.created_at) }}</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-center">
            <VTooltip text="Edit User">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="x-small" variant="text" color="primary" @click="openEdit(item)">
                  <VIcon icon="ri-pencil-line" size="16" />
                </VBtn>
              </template>
            </VTooltip>
            <VTooltip text="Hapus User">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="x-small" variant="text" color="error" @click="openDelete(item)">
                  <VIcon icon="ri-delete-bin-line" size="16" />
                </VBtn>
              </template>
            </VTooltip>
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-10 text-medium-emphasis">
            <VIcon icon="ri-team-line" size="40" class="mb-2 opacity-40" />
            <p class="mb-0 font-weight-medium">Belum ada user</p>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Form Dialog -->
    <VDialog v-model="showDialog" max-width="500" persistent>
      <VCard rounded="xl">
        <VCardTitle class="pa-6 pb-2">
          <div class="d-flex align-center gap-3">
            <VAvatar color="primary" variant="tonal" size="42" rounded="lg">
              <VIcon :icon="editItem ? 'ri-pencil-line' : 'ri-user-add-line'" size="20" />
            </VAvatar>
            <div>
              <h6 class="text-h6 font-weight-bold mb-0">{{ editItem ? 'Edit User' : 'Tambah User Baru' }}</h6>
              <p class="text-caption text-medium-emphasis mb-0">Lengkapi data pengguna</p>
            </div>
          </div>
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-6">
          <VRow dense>
            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Nama Lengkap"
                placeholder="Contoh: Budi Santoso"
                prepend-inner-icon="ri-user-line"
                variant="outlined" density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="form.username"
                label="Username"
                placeholder="Contoh: budi.santoso"
                prepend-inner-icon="ri-at-line"
                variant="outlined" density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="form.email"
                label="Email"
                placeholder="budi@rsud.local"
                prepend-inner-icon="ri-mail-line"
                type="email"
                variant="outlined" density="compact"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="form.password"
                label="Password"
                :placeholder="editItem ? 'Kosongkan jika tidak diubah' : 'Minimal 6 karakter'"
                prepend-inner-icon="ri-lock-line"
                :type="showPass ? 'text' : 'password'"
                :append-inner-icon="showPass ? 'ri-eye-off-line' : 'ri-eye-line'"
                variant="outlined" density="compact"
                @click:append-inner="showPass = !showPass"
              />
            </VCol>
            <VCol cols="12">
              <p class="text-caption font-weight-medium mb-2">Role / Hak Akses</p>
              <div class="d-flex gap-2 flex-wrap">
                <VBtn
                  v-for="opt in ROLE_OPTIONS"
                  :key="opt.value"
                  :color="form.role === opt.value ? opt.color : 'default'"
                  :variant="form.role === opt.value ? 'elevated' : 'tonal'"
                  size="small"
                  rounded="lg"
                  :prepend-icon="opt.icon"
                  @click="form.role = opt.value"
                >
                  {{ opt.title }}
                </VBtn>
              </div>
              <div class="mt-2">
                <VAlert v-if="form.role === 'admin'" type="error" variant="tonal" density="compact" class="text-caption">
                  <strong>Admin</strong> memiliki akses penuh termasuk manajemen user & master data.
                </VAlert>
                <VAlert v-else-if="form.role === 'qc_admission'" type="info" variant="tonal" density="compact" class="text-caption">
                  <strong>QC Admission</strong> dapat input data QC, Edukasi, Batal Ranap, Up Selling.
                </VAlert>
                <VAlert v-else type="warning" variant="tonal" density="compact" class="text-caption">
                  <strong>Kasir</strong> hanya dapat melihat data Batal Ranap & View Data Input.
                </VAlert>
              </div>
            </VCol>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4 d-flex gap-2">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showDialog = false">Batal</VBtn>
          <VBtn color="primary" rounded="lg" class="flex-grow-1" :loading="formLoading" @click="saveUser">
            {{ editItem ? 'Simpan Perubahan' : 'Tambah User' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Dialog -->
    <VDialog v-model="showDelete" max-width="380">
      <VCard rounded="lg">
        <VCardText class="pa-6 text-center">
          <VAvatar color="error" variant="tonal" size="60" rounded="xl" class="mb-4">
            <VIcon icon="ri-delete-bin-2-line" size="30" />
          </VAvatar>
          <h6 class="text-h6 font-weight-bold mb-2">Hapus User?</h6>
          <p class="text-body-2 text-medium-emphasis mb-0">
            User <strong>{{ deleteTarget?.name }}</strong>
            <VChip :color="roleColor(deleteTarget?.role)" size="x-small" variant="tonal" class="ms-1">
              {{ roleLabel(deleteTarget?.role) }}
            </VChip>
            akan dihapus permanen.
          </p>
        </VCardText>
        <VCardActions class="px-6 pb-5 d-flex gap-2 pt-0">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showDelete = false">Batal</VBtn>
          <VBtn color="error" rounded="lg" class="flex-grow-1" :loading="formLoading" prepend-icon="ri-delete-bin-line" @click="confirmDelete">Hapus</VBtn>
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