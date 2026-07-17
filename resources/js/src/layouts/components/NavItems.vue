<script setup>
import VerticalNavLink from '@layouts/components/VerticalNavLink.vue'
import { useAuthStore } from '@/stores/useAuthStore'

const auth = useAuthStore()
</script>

<template>
  <!-- Admin + QC Admission -->
  <template v-if="auth.canAccessMain">
    <VerticalNavLink :item="{ title: 'Dashboard',        icon: 'ri-dashboard-line',         to: '/dashboard' }" />
    <VerticalNavLink :item="{ title: 'Quality Control',  icon: 'ri-shield-check-line',      to: '/quality-control' }" />
    <VerticalNavLink :item="{ title: 'Edukasi Lanjutan', icon: 'ri-book-open-line',         to: '/edukasi-lanjutan' }" />
    <VerticalNavLink :item="{ title: 'Batal Ranap',      icon: 'ri-close-circle-line',      to: '/batal-ranap' }" />
    <VerticalNavLink :item="{ title: 'Up Selling',       icon: 'ri-arrow-up-circle-line',   to: '/up-selling' }" />
    <VerticalNavLink :item="{ title: 'View Data Input',  icon: 'ri-table-line',             to: '/view-data-input' }" />
  </template>

  <!-- Kasir only — satu menu saja -->
  <template v-if="auth.isKasir">
    <VerticalNavLink :item="{ title: 'View Data Input',  icon: 'ri-table-line',             to: '/view-data-input' }" />
  </template>

  <!-- Admin only section -->
  <template v-if="auth.isAdmin">
    <div class="nav-section-label px-4 py-2 mt-2">
      <span class="text-caption text-disabled" style="text-transform:uppercase;letter-spacing:0.08em;font-weight:700;font-size:0.65rem">
        Administrasi
      </span>
    </div>
    <VerticalNavLink :item="{ title: 'Log Aktivitas',  icon: 'ri-history-line',      to: '/activity-log' }" />
    <VerticalNavLink :item="{ title: 'Master Data',    icon: 'ri-database-2-line',   to: '/master-data' }" />
    <VerticalNavLink :item="{ title: 'Token Info',     icon: 'ri-key-2-line',        to: '/token-info' }" />
  </template>
</template>
