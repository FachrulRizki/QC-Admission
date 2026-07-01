<script setup>
const props = defineProps({
  patient:    { type: Object,  required: true },
  sesiCount:  { type: Number,  default: 1 },  // jumlah sesi edukasi untuk pasien ini
})
const emit = defineEmits(['view', 'edit'])

const statusColor = computed(() => props.patient.status === 'Selesai' ? 'success' : 'warning')
const statusIcon  = computed(() => props.patient.status === 'Selesai' ? 'ri-check-double-line' : 'ri-time-line')
</script>

<template>
  <VCard
    class="patient-card cursor-pointer"
    border elevation="0" rounded="xl"
    @click="emit('view', patient)"
  >
    <!-- Accent bar -->
    <div class="card-accent" :class="patient.status === 'Selesai' ? 'accent-success' : 'accent-warning'" />

    <VCardText class="pa-4">
      <!-- Top: avatar + status -->
      <div class="d-flex align-start justify-space-between mb-3">
        <VAvatar :color="statusColor" variant="tonal" size="44" rounded="lg">
          <VIcon icon="ri-user-heart-line" size="22" />
        </VAvatar>
        <div class="d-flex flex-column align-end gap-1">
          <VChip :color="statusColor" size="x-small" variant="tonal" :prepend-icon="statusIcon">
            {{ patient.status || 'Menunggu' }}
          </VChip>
          <VChip color="secondary" size="x-small" variant="tonal" prepend-icon="ri-repeat-line">
            {{ sesiCount }} sesi
          </VChip>
        </div>
      </div>

      <!-- Nama pasien -->
      <p class="text-subtitle-2 font-weight-bold mb-1 text-truncate" style="color:rgb(var(--v-theme-primary))">
        {{ patient.nama_pasien }}
      </p>
      <div class="d-flex align-center gap-2 mb-3">
        <span class="text-caption text-medium-emphasis">No. MR: <strong>{{ patient.no_mr }}</strong></span>
        <span v-if="patient.jaminan" class="text-caption text-medium-emphasis">· {{ patient.jaminan }}</span>
      </div>

      <!-- Meta chips -->
      <div class="d-flex flex-wrap gap-1">
        <VChip size="x-small" variant="tonal" color="primary" prepend-icon="ri-calendar-line">{{ patient.bulan }}</VChip>
        <VChip v-if="patient.edukasi_kamar" size="x-small" variant="tonal" color="info" prepend-icon="ri-hospital-line">{{ patient.edukasi_kamar }}</VChip>
        <VChip v-if="patient.petugas" size="x-small" variant="tonal" color="secondary" prepend-icon="ri-nurse-line">{{ patient.petugas }}</VChip>
      </div>
    </VCardText>

    <!-- Footer -->
    <div class="card-footer d-flex align-center justify-space-between px-4 py-2">
      <p class="text-caption text-medium-emphasis mb-0">
        <VIcon icon="ri-time-line" size="12" class="me-1" />{{ patient.tanggal }}
      </p>
      <div class="d-flex gap-1">
        <VTooltip text="Lihat Riwayat">
          <template #activator="{ props: tp }">
            <VBtn v-bind="tp" icon size="x-small" variant="text" color="warning" @click.stop="emit('view', patient)">
              <VIcon icon="ri-eye-line" size="14" />
            </VBtn>
          </template>
        </VTooltip>
        <VTooltip text="Tambah Sesi Edukasi">
          <template #activator="{ props: tp }">
            <VBtn v-bind="tp" icon size="x-small" variant="tonal" color="warning" @click.stop="emit('edit', patient)">
              <VIcon icon="ri-add-line" size="14" />
            </VBtn>
          </template>
        </VTooltip>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.patient-card { position: relative; transition: box-shadow 0.2s, transform 0.15s; overflow: hidden; }
.patient-card:hover { box-shadow: 0 6px 20px rgba(var(--v-theme-warning), 0.2) !important; transform: translateY(-2px); }
.card-accent { position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
.accent-warning { background: rgb(var(--v-theme-warning)); }
.accent-success { background: rgb(var(--v-theme-success)); }
.card-footer { border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); background: rgba(var(--v-theme-on-surface), 0.02); }
</style>
