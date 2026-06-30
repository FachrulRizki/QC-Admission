<script setup>
import DefaultLayoutWithVerticalNav from './components/DefaultLayoutWithVerticalNav.vue'
</script>

<template>
  <!-- ⚠️ Do NOT wrap RouterView here again — the layout's slot renders it.
       The dark overlay between routes was caused by a double RouterView wrap
       combined with Vuetify's overlay scrim. We pass the page content
       directly via the default slot instead. -->
  <DefaultLayoutWithVerticalNav />
</template>

<style lang="scss">
@use "@layouts/styles/default-layout";
</style>
