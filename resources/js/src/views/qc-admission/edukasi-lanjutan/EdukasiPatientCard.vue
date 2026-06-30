<script setup>
const props = defineProps({
  patient: { type: Object, required: true },
})
const emit = defineEmits(['view', 'edit'])

const statusColor = computed(() => {
  return props.patient.status === 'Selesai' ? 'success' : 'warning'
})

const statusIcon = computed(() => {
  return props.patient.status === 'Selesai' ? 'ri-check-double-line' : 'ri-time-line'
})
</script>

<template>
  <VCard
    class="patient-card cursor-pointer"
    border
    elevation="0"
    rounded="lg"
    @click="emit('view', patient)"
  >
    <!-- Color accent bar -->
    <div class="card-accent" :class="patient.status === 'Selesai' ? 'accent-success' : 'accent-warning'" />

    <VCardText class="pa-4">
      <!-- Top row -->
      <div class="d-flex align-start justify-space-between mb-3">
        <VAvatar
          :color="statusColor"
          variant="tonal"
          size="44"
          rounded="lg"
        >
          <VIcon icon="ri-user-heart-line" size="22" />
        </VAvatar>
        <VChip :color="statusColor" size="x-small" variant="tonal" :prepend-icon="statusIcon">
          {{ patient.status || 'Menunggu' }}
        </VChip>
      </div>

      <!-- Patient info -->
      <p class="text-subtitle-2 font-weight-bold mb-1 text-primary patient-name">
        {{ patient.nama_pasien }}
      </p>
      <p class="text-caption text-medium-emphasis mb-3">
        <VIcon icon="ri-id-card-line" size="12" class="me-1" />
        NoMR: {{ patient.no_mr }}
      </p>

      <!-- Meta chips -->
      <div class="d-flex flex-wrap gap-1">
        <VChip size="x-small" variant="tonal" color="primary">
          <VIcon icon="ri-calendar-line" size="10" class="me-1" />
          {{ patient.bulan }}
        </VChip>
        <VChip v-if="patient.edukasi_kamar" size="x-small" variant="tonal" color="info">
          <VIcon icon="ri-hospital-line" size="10" class="me-1" />
          {{ patient.edukasi_kamar }}
        </VChip>
        <VChip v-if="patient.petugas" size="x-small" variant="tonal" color="secondary">
          <VIcon icon="ri-user-3-line" size="10" class="me-1" />
          {{ patient.petugas }}
        </VChip>
      </div>
    </VCardText>

    <!-- Footer: date -->
    <div class="card-footer d-flex align-center justify-space-between px-4 py-2">
      <p class="text-caption text-medium-emphasis mb-0">
        <VIcon icon="ri-time-line" size="12" class="me-1" />
        {{ patient.tanggal }}
      </p>
      <VBtn
        icon
        size="x-small"
        variant="text"
        color="primary"
        @click.stop="emit('edit', patient)"
      >
        <VIcon icon="ri-pencil-line" size="14" />
      </VBtn>
    </div>
  </VCard>
</template>

<style scoped>
.patient-card {
  position: relative;
  transition: box-shadow 0.2s, transform 0.15s;
  overflow: hidden;
}
.patient-card:hover {
  box-shadow: 0 6px 20px rgba(var(--v-theme-primary), 0.15) !important;
  transform: translateY(-2px);
}

.card-accent {
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
}
.accent-warning { background: rgb(var(--v-theme-warning)); }
.accent-success { background: rgb(var(--v-theme-success)); }

.patient-name {
  line-height: 1.3;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.card-footer {
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-on-surface), 0.02);
}
</style>
