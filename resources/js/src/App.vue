<script setup>
// QC Admission — root component
// RouterView is here at the top level only.
// Layouts (default.vue / blank.vue) are mounted as route components,
// so the page slot content flows: App > Layout > Page (no double-wrap).
</script>

<template>
  <VApp>
    <RouterView />
  </VApp>
</template>
