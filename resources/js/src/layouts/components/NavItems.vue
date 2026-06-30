<script setup>
import VerticalNavLink from '@layouts/components/VerticalNavLink.vue'
</script>

<template>
  <VerticalNavLink :item="{ title: 'Dashboard',       icon: 'ri-home-smile-line',       to: '/dashboard' }" />
  <VerticalNavLink :item="{ title: 'Quality Control', icon: 'ri-shield-check-line',     to: '/quality-control' }" />
  <VerticalNavLink :item="{ title: 'Edukasi Lanjutan',icon: 'ri-book-open-line',        to: '/edukasi-lanjutan' }" />
  <VerticalNavLink :item="{ title: 'Batal Ranap',     icon: 'ri-close-circle-line',     to: '/batal-ranap' }" />
  <VerticalNavLink :item="{ title: 'View Data Input', icon: 'ri-table-line',            to: '/view-data-input' }" />
  <VerticalNavLink :item="{ title: 'Up Selling',      icon: 'ri-arrow-up-circle-line',  to: '/up-selling' }" />
</template>
