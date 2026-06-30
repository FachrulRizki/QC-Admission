<script setup>
defineProps({
  patient: { type: Object, required: true },
})
const emit = defineEmits(['view', 'menu'])
</script>

<template>
  <VCard
    variant="outlined"
    class="patient-card cursor-pointer"
    @click="emit('view', patient)"
  >
    <VCardText class="pa-3">
      <div class="d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" size="40">
            <VIcon icon="ri-user-3-line" />
          </VAvatar>
          <div>
            <p class="text-body-2 font-weight-bold mb-0 text-primary">
              {{ patient.no_mr }}
            </p>
            <p class="text-caption text-medium-emphasis mb-0">
              {{ patient.nama_pasien }}
            </p>
          </div>
        </div>
        <VBtn
          icon
          variant="text"
          size="small"
          @click.stop="emit('menu', patient)"
        >
          <VIcon icon="ri-more-2-line" />
        </VBtn>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.patient-card {
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}
.patient-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1) !important;
  transform: translateY(-1px);
}
</style>
