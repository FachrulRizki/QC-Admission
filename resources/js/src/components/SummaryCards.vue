<script setup>
/**
 * SummaryCards — reusable stat cards
 * cards: [{ value, label, color, icon, filterValue?, pct? }]
 * v-model: active filter value
 */
const props = defineProps({
  cards:      { type: Array, default: () => [] },
  modelValue: { default: null },
})
const emit = defineEmits(['update:modelValue'])

function toggle(card) {
  if (card.filterValue === undefined) return
  // If same card clicked again and it's not a "reset" card → deselect
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
    error:   'stat-active-error',
    info:    'stat-active-info',
  }
  return map[card.color] ?? 'stat-active'
}

function numColor(card) {
  const map = {
    primary: 'var(--qc-green)',
    success: 'rgb(var(--v-theme-success))',
    warning: 'rgb(var(--v-theme-warning))',
    error:   'rgb(var(--v-theme-error))',
    info:    'rgb(var(--v-theme-info))',
  }
  return map[card.color] ?? 'var(--qc-green)'
}

const colsMap = {
  2: { cols: 6, sm: 6 },
  3: { cols: 4, sm: 4 },
  4: { cols: 6, sm: 3 },
}
const colConfig = computed(() => colsMap[props.cards.length] ?? { cols: 6, sm: 3 })
</script>

<template>
  <VRow dense class="mb-4">
    <VCol
      v-for="(card, i) in cards"
      :key="i"
      :cols="colConfig.cols"
      :sm="colConfig.sm"
    >
      <VCard
        elevation="0" border rounded="xl"
        class="stat-card pa-4 text-center"
        :class="[borderClass(card), card.filterValue !== undefined ? 'cursor-pointer' : '']"
        @click="toggle(card)"
      >
        <!-- Icon -->
        <div class="d-flex justify-center mb-2">
          <div
            class="d-flex align-center justify-center rounded-lg"
            :style="{
              width:'36px', height:'36px',
              background: isActive(card)
                ? `color-mix(in srgb, ${numColor(card)} 15%, transparent)`
                : 'var(--qc-bg)',
            }"
          >
            <VIcon :icon="card.icon" size="18" :style="{ color: numColor(card) }" />
          </div>
        </div>

        <!-- Value -->
        <p class="text-h3 font-weight-black mb-0 lh-1" :style="{ color: numColor(card) }">
          {{ typeof card.value === 'number' ? card.value.toLocaleString('id-ID') : (card.value ?? 0) }}
        </p>

        <!-- Label -->
        <p class="text-caption mt-1 mb-0" style="color:var(--qc-text-2)">{{ card.label }}</p>

        <!-- Optional progress -->
        <template v-if="card.pct != null">
          <VProgressLinear
            :model-value="card.pct"
            :color="card.color ?? 'primary'"
            rounded height="3"
            :bg-color="card.color"
            bg-opacity="0.1"
            class="mt-2"
          />
          <p class="text-caption mt-1 mb-0" style="color:var(--qc-text-2)">{{ card.pct }}%</p>
        </template>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.lh-1 { line-height: 1.1; }
.stat-card {
  transition: box-shadow 0.18s, transform 0.18s, border-color 0.15s;
}
.stat-card.cursor-pointer:hover {
  box-shadow: 0 6px 20px rgba(0,179,126,0.12) !important;
  transform: translateY(-2px);
}
</style>
