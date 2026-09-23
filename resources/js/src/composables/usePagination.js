export function usePagination(source, size = 10) {
  const page = ref(1)
  const perPage = size

  // Reset ke page 1 saat source berubah panjangnya (akibat filter)
  watch(() => source.value?.length, () => { page.value = 1 })

  const pageCount = computed(() => Math.max(1, Math.ceil((source.value?.length ?? 0) / perPage)))

  const paginated = computed(() => {
    const start = (page.value - 1) * perPage
    return (source.value ?? []).slice(start, start + perPage)
  })

  function setPage(n) {
    page.value = Math.max(1, Math.min(n, pageCount.value))
  }

  function resetPage() { page.value = 1 }

  return { page, perPage, pageCount, paginated, setPage, resetPage }
}
