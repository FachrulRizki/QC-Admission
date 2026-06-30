<script setup>
import VerticalNavLink from '@layouts/components/VerticalNavLink.vue'
import { useAuthStore } from '@/stores/useAuthStore'

const auth = useAuthStore()
</script>

<template>
  <!-- Admin + QC Admission -->
  <template v-if="auth.canAccessMain">
    <VerticalNavLink :item="{ title: 'Dashboard',        icon: 'ri-home-smile-line',       to: '/dashboard' }" />
    <VerticalNavLink :item="{ title: 'Quality Control',  icon: 'ri-shield-check-line',     to: '/quality-control' }" />
    <VerticalNavLink :item="{ title: 'Edukasi Lanjutan', icon: 'ri-book-open-line',        to: '/edukasi-lanjutan' }" />
    <VerticalNavLink :item="{ title: 'Batal Ranap',      icon: 'ri-close-circle-line',     to: '/batal-ranap' }" />
    <VerticalNavLink :item="{ title: 'Up Selling',       icon: 'ri-arrow-up-circle-line',  to: '/up-selling' }" />
    <VerticalNavLink :item="{ title: 'View Data Input',  icon: 'ri-table-line',            to: '/view-data-input' }" />
  </template>

  <!-- Admin only -->
  <template v-if="auth.isAdmin">
    <VerticalNavLink :item="{ title: 'Log Aktivitas',    icon: 'ri-history-line',          to: '/activity-log' }" />
  </template>

  <!-- Kasir -->
  <template v-if="auth.isKasir">
    <VerticalNavLink :item="{ title: 'Batal Ranap',      icon: 'ri-close-circle-line',     to: '/batal-ranap-view' }" />
  </template>
</template>
