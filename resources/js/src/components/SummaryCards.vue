<script setup>
/**
 * SummaryCards — reusable stat cards
 * cards: [{ value, label, color, icon, filterValue?, pct? }]
 * v-model: active filter value
 */
const props = defineProps({
  cards: { type: Array, default: () => [] },
  modelValue: { default: null },
})
const emit = defineEmits(['update:modelValue'])

function toggle(card) {
  if (card.filterValue === undefined) return
  if (props.modelValue === card.filterValue && card.filterValue !== null && card.filterValue !== 'All') {
    emit('update:modelValue', null)
  } else {
    emit('update:modelValue', card.filterValue)
  }
}

function isActive(card) {
  return props.modelValue === card.filterValue
}

function borderClass(card) {
  if (!isActive(card)) return ''
  const map = {
    primary: 'stat-active',
    success: 'stat-active-success',
    warning: 'stat-active-warning',
    error: 'stat-active-error',
    info: 'stat-active-info',
  }
  return map[card.color] ?? 'stat-active'
}

function numColor(card) {
  const map = {
    primary: 'rgb(var(--v-theme-primary))',
    success: 'rgb(var(--v-theme-success))',
    warning: 'rgb(var(--v-theme-warning))',
    error:   'rgb(var(--v-theme-error))',
    info:    'rgb(var(--v-theme-info))',
  }
  return map[card.color] ?? 'rgb(var(--v-theme-primary))'
}


</script>

<template>
  <!-- All layouts: flex row, wrap on mobile -->
  <div class="sc-flex-row mb-4">
    <div
      v-for="(card, i) in cards"
      :key="i"
      class="sc-flex-card"
    >
      <VCard elevation="0" border rounded="md" class="stat-card px-3 py-2 h-100"
        :class="[borderClass(card), card.filterValue !== undefined ? 'cursor-pointer' : '']"
        @click="toggle(card)">
        <div class="d-flex align-center gap-2">
          <!-- Icon bubble -->
          <div class="sc-icon-wrap flex-shrink-0" :style="{
            background: isActive(card)
              ? `color-mix(in srgb, ${numColor(card)} 18%, transparent)`
              : 'rgba(var(--v-theme-on-surface), 0.05)',
          }">
            <VIcon :icon="card.icon" size="14" :style="{ color: numColor(card) }" />
          </div>
          <!-- Value + label -->
          <div class="min-width-0">
            <p class="sc-value mb-0" :style="{ color: numColor(card) }">
              {{ typeof card.value === 'number' ? card.value.toLocaleString('id-ID') : (card.value ?? 0) }}
            </p>
            <p class="sc-label mb-0 text-truncate">{{ card.label }}</p>
          </div>
        </div>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
/* Icon bubble */
.sc-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  flex-shrink: 0;
}

/* Value number */
.sc-value {
  font-size: 1.05rem;
  font-weight: 800;
  line-height: 1.1;
}

/* Label */
.sc-label {
  font-size: 0.7rem;
  color: var(--qc-text-2, #64748b);
  line-height: 1.2;
}

/* Flex row — all cards in one line */
.sc-flex-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.sc-flex-card {
  flex: 1 1 0;
  min-width: 110px;
}

@media (max-width: 599px) {
  .sc-flex-card {
    flex: 1 1 calc(50% - 4px);
    max-width: calc(50% - 4px);
  }
}

.stat-card {
  transition: box-shadow 0.18s, transform 0.18s, border-color 0.15s;
}

.stat-card.cursor-pointer:hover {
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.09) !important;
  transform: translateY(-1px);
}

/* Active border states */
.stat-active         { border-color: rgb(var(--v-theme-primary)) !important; }
.stat-active-success { border-color: rgb(var(--v-theme-success)) !important; }
.stat-active-warning { border-color: rgb(var(--v-theme-warning)) !important; }
.stat-active-error   { border-color: rgb(var(--v-theme-error))   !important; }
.stat-active-info    { border-color: rgb(var(--v-theme-info))    !important; }
</style>
