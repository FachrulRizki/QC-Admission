<script setup>
import axios from 'axios'
import SummaryCards from '@/components/SummaryCards.vue'
import PageHero from '@/components/PageHero.vue'

const loading   = ref(false)
const records   = ref([])
const total     = ref(0)
const lastPage  = ref(1)
const page      = ref(1)

const search       = ref('')
const filterModule = ref('')
const filterAction = ref('')
const dateFrom     = ref('')
const dateTo       = ref('')

const MODULE_OPTIONS = [
  '','quality-control','edukasi-lanjutan','batal-ranap','up-selling','user','auth',
].map(v => ({ title: v ? v.replace(/-/g,' ').replace(/\b\w/g,c=>c.toUpperCase()) : 'Semua Modul', value: v }))

const ACTION_OPTIONS = [
  '', 'login', 'logout', 'create', 'update', 'delete', 'verifikasi', 'closing',
].map(v => ({ title: v ? v.charAt(0).toUpperCase()+v.slice(1) : 'Semua Aksi', value: v }))

const headers = [
  { title: 'Waktu',      key: 'created_at', width: '155px' },
  { title: 'User',       key: 'user_name' },
  { title: 'Role',       key: 'user_role',   align: 'center', width: '110px' },
  { title: 'IP Address', key: 'ip_address',  align: 'center', width: '130px' },
  { title: 'Modul',      key: 'module',      align: 'center', width: '140px' },
  { title: 'Petugas',    key: 'petugas',     align: 'center', width: '140px' },
  { title: 'Aksi',       key: 'action',      align: 'center', width: '110px' },
  { title: 'Keterangan', key: 'subject' },
]

const actionColor = a => ({'login':'success','logout':'secondary','create':'primary','update':'warning','delete':'error','verifikasi':'info','closing':'teal'})[a] ?? 'default'
const moduleColor = m => ({'quality-control':'primary','edukasi-lanjutan':'warning','batal-ranap':'error','up-selling':'success','auth':'secondary','user':'info'})[m] ?? 'default'
const roleColor   = r => ({'admin':'error','qc_admission':'primary','kasir':'warning'})[r] ?? 'default'
const fmtDate     = d => new Date(d).toLocaleString('id-ID',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'})

const statCards = computed(() => [
  { value: total.value, label: 'Total Log', color: 'primary', icon: 'ri-history-line' },
  { value: records.value.filter(r=>r.action==='login').length, label: 'Login', color: 'success', icon: 'ri-login-circle-line' },
  { value: records.value.filter(r=>r.action==='create').length, label: 'Input Data', color: 'info', icon: 'ri-add-circle-line' },
  { value: records.value.filter(r=>r.action==='delete').length, label: 'Hapus Data', color: 'error', icon: 'ri-delete-bin-line' },
])

const todayFormatted = computed(() =>
  new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
)

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/activity-log', {
      params: {
        search:    search.value       || undefined,
        module:    filterModule.value || undefined,
        action:    filterAction.value || undefined,
        date_from: dateFrom.value     || undefined,
        date_to:   dateTo.value       || undefined,
        page: page.value, per_page: 30,
      }
    })
    records.value  = data.data ?? []
    total.value    = data.meta?.total ?? data.total ?? 0
    lastPage.value = data.meta?.last_page ?? 1
  } catch(e) { console.error(e) }
  finally { loading.value = false }
}

function resetFilter() { search.value=''; filterModule.value=''; filterAction.value=''; dateFrom.value=''; dateTo.value=''; page.value=1; load() }

watch([search, filterModule, filterAction, dateFrom, dateTo], () => { page.value=1; load() })
onMounted(load)
</script>

<template>
  <div>
    <!-- Header -->
    <PageHero
      icon="ri-history-line"
      badge="Admin · Log Aktivitas"
      title="Log Aktivitas"
      subtitle="Rekam jejak semua aktivitas pengguna di sistem"
      color-from="#0EA5E9"
      color-to="#0369A1"
      :pills="[
        { icon: 'ri-calendar-line', text: todayFormatted },
        { icon: 'ri-database-line', text: `${total} log` },
      ]"
    >
    </PageHero>

    <!-- Stats -->
    <SummaryCards :cards="statCards" />

    <!-- Filter -->
    <VCard elevation="0" border rounded="xl" class="mb-4">
      <VCardText class="pa-3">
        <VRow dense align="center">
          <VCol cols="12" sm="3">
            <VTextField v-model="search" label="Cari..." prepend-inner-icon="ri-search-line"
              variant="outlined" density="compact" hide-details clearable rounded="lg" />
          </VCol>
          <VCol cols="6" sm="2">
            <VSelect v-model="filterModule" :items="MODULE_OPTIONS" item-title="title" item-value="value"
              label="Modul" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="6" sm="2">
            <VSelect v-model="filterAction" :items="ACTION_OPTIONS" item-title="title" item-value="value"
              label="Aksi" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="auto">
            <VBtn size="small" variant="text" color="secondary" @click="resetFilter">Reset</VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Count -->
    <div class="d-flex align-center gap-3 mb-4">
      <VChip size="small" color="primary" variant="tonal" rounded="pill">{{ total }} log</VChip>
    </div>

    <!-- Table -->
    <VCard elevation="0" border rounded="xl">
      <VDataTable
        :headers="headers"
        :items="records"
        :loading="loading"
        density="comfortable"
        hover
        :items-per-page="30"
        hide-default-footer
        class="activity-table"
      >
        <template #item.created_at="{ item }">
          <span class="text-caption" style="color:var(--qc-text-2)">{{ fmtDate(item.created_at) }}</span>
        </template>
        <template #item.user_name="{ item }">
          <div class="d-flex align-center gap-2">
            <VAvatar :color="roleColor(item.user_role)" variant="tonal" size="26" rounded="md">
              <span style="font-size:10px;font-weight:700">{{ item.user_name?.charAt(0)?.toUpperCase() }}</span>
            </VAvatar>
            <span class="text-body-2 font-weight-medium">{{ item.user_name || '—' }}</span>
          </div>
        </template>
        <template #item.user_role="{ item }">
          <VChip :color="roleColor(item.user_role)" size="x-small" variant="tonal">{{ item.user_role }}</VChip>
        </template>
        <template #item.ip_address="{ item }">
          <span class="text-caption font-weight-medium" style="color:var(--qc-text-2);font-family:monospace">
            {{ item.ip_address || '—' }}
          </span>
        </template>
        <template #item.module="{ item }">
          <VChip :color="moduleColor(item.module)" size="x-small" variant="tonal">{{ item.module || '—' }}</VChip>
        </template>
        <template #item.petugas="{ item }">
          <span v-if="item.petugas" class="text-caption font-weight-medium" style="color:var(--qc-text)">
            <VIcon icon="ri-user-3-line" size="11" class="me-1 opacity-60" />{{ item.petugas }}
          </span>
          <span v-else class="text-caption" style="color:var(--qc-text-2)">—</span>
        </template>
        <template #item.action="{ item }">
          <VChip :color="actionColor(item.action)" size="x-small" variant="tonal">{{ item.action }}</VChip>
        </template>
        <template #no-data>
          <div class="text-center py-12" style="color:var(--qc-text-2)">
            <VIcon icon="ri-history-line" size="40" class="mb-2 opacity-30" />
            <p class="text-body-2 font-weight-medium mb-0">Belum ada log</p>
          </div>
        </template>
        <template #bottom>
          <div class="d-flex align-center justify-end gap-2 pa-3">
            <span class="text-caption" style="color:var(--qc-text-2)">{{ page }}/{{ lastPage }}</span>
            <VBtn icon size="x-small" variant="tonal" :disabled="page<=1||loading" @click="page--;load()">
              <VIcon icon="ri-arrow-left-s-line" />
            </VBtn>
            <VBtn icon size="x-small" variant="tonal" :disabled="page>=lastPage||loading" @click="page++;load()">
              <VIcon icon="ri-arrow-right-s-line" />
            </VBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>