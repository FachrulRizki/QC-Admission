<script setup>
const props = defineProps({
  page:      { type: Number, required: true },
  pageCount: { type: Number, required: true },
  total:     { type: Number, default: 0 },
  perPage:   { type: Number, default: 10 },
})
const emit = defineEmits(['update:page'])

// Rentang item yang ditampilkan
const from = computed(() => props.total === 0 ? 0 : (props.page - 1) * props.perPage + 1)
const to   = computed(() => Math.min(props.page * props.perPage, props.total))

// Nomor halaman yang ditampilkan — max 5 tombol, centered pada page aktif
const pageNumbers = computed(() => {
  const total = props.pageCount
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)

  const cur  = props.page
  const pages = []

  pages.push(1)
  if (cur > 3) pages.push('...')

  const start = Math.max(2, cur - 1)
  const end   = Math.min(total - 1, cur + 1)
  for (let i = start; i <= end; i++) pages.push(i)

  if (cur < total - 2) pages.push('...')
  pages.push(total)

  return pages
})
</script>

<template>
  <div v-if="pageCount > 1" class="pg-bar">
    <!-- Info -->
    <span class="pg-info">{{ from }}–{{ to }} dari {{ total }}</span>

    <!-- Prev -->
    <button class="pg-btn" :disabled="page <= 1" @click="emit('update:page', page - 1)">
      <VIcon icon="ri-arrow-left-s-line" size="16" />
    </button>

    <!-- Page numbers -->
    <template v-for="(p, i) in pageNumbers" :key="i">
      <span v-if="p === '...'" class="pg-ellipsis">…</span>
      <button v-else class="pg-btn" :class="{ 'pg-btn--active': p === page }" @click="emit('update:page', p)">
        {{ p }}
      </button>
    </template>

    <!-- Next -->
    <button class="pg-btn" :disabled="page >= pageCount" @click="emit('update:page', page + 1)">
      <VIcon icon="ri-arrow-right-s-line" size="16" />
    </button>
  </div>
</template>

<style scoped>
.pg-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 10px 16px;
  border-top: 1px solid var(--qc-border, rgba(0,0,0,0.07));
  flex-wrap: wrap;
}

.pg-info {
  font-size: 0.72rem;
  color: var(--qc-text-2, #64748b);
  margin-right: 4px;
  white-space: nowrap;
}

.pg-btn {
  min-width: 32px;
  height: 32px;
  padding: 0 6px;
  border-radius: 8px;
  border: 1px solid transparent;
  background: transparent;
  font-size: 0.82rem;
  font-weight: 500;
  color: var(--qc-text, #1e293b);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background 0.12s, border-color 0.12s, color 0.12s;
  line-height: 1;
}

.pg-btn:hover:not(:disabled):not(.pg-btn--active) {
  background: rgba(var(--v-theme-primary), 0.08);
  color: rgb(var(--v-theme-primary));
}

.pg-btn--active {
  background: rgb(var(--v-theme-primary));
  color: #fff;
  border-color: rgb(var(--v-theme-primary));
  font-weight: 700;
}

.pg-btn:disabled {
  opacity: 0.3;
  cursor: default;
}

.pg-ellipsis {
  font-size: 0.82rem;
  color: var(--qc-text-2, #64748b);
  padding: 0 2px;
  line-height: 32px;
}
</style>
