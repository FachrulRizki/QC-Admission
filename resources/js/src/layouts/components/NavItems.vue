<script setup>
import VerticalNavLink from '@layouts/components/VerticalNavLink.vue'
import { useAuthStore } from '@/stores/useAuthStore'

const auth = useAuthStore()
</script>

<template>
  <!-- Dashboard -->
  <VerticalNavLink
    v-if="auth.hasPermission('dashboard:view')"
    :item="{ title: 'Dashboard', icon: 'ri-dashboard-line', to: '/dashboard' }"
  />

  <!-- Quality Control -->
  <VerticalNavLink
    v-if="auth.hasPermission('quality-control:view')"
    :item="{ title: 'Quality Control', icon: 'ri-shield-check-line', to: '/quality-control' }"
  />

  <!-- Edukasi Lanjutan -->
  <VerticalNavLink
    v-if="auth.hasPermission('edukasi-lanjutan:view')"
    :item="{ title: 'Edukasi Lanjutan', icon: 'ri-book-open-line', to: '/edukasi-lanjutan' }"
  />

  <!-- Batal Ranap -->
  <VerticalNavLink
    v-if="auth.hasPermission('batal-ranap:view')"
    :item="{ title: 'Batal Ranap', icon: 'ri-close-circle-line', to: '/batal-ranap' }"
  />

  <!-- Up Selling -->
  <VerticalNavLink
    v-if="auth.hasPermission('up-selling:view')"
    :item="{ title: 'Up Selling', icon: 'ri-arrow-up-circle-line', to: '/up-selling' }"
  />

  <!-- View Data Input — semua yang sudah login -->
  <VerticalNavLink
    v-if="auth.isLoggedIn"
    :item="{ title: 'View Data Input', icon: 'ri-table-line', to: '/view-data-input' }"
  />

  <!-- Admin section -->
  <template v-if="auth.hasPermission('activity-log:view') || auth.hasPermission('master-data:view')">
    <div class="nav-section-label px-4 py-2 mt-2">
      <span class="text-caption text-disabled" style="text-transform:uppercase;letter-spacing:0.08em;font-weight:700;font-size:0.65rem">
        Administrasi
      </span>
    </div>
    <VerticalNavLink
      v-if="auth.hasPermission('activity-log:view')"
      :item="{ title: 'Log Aktivitas', icon: 'ri-history-line', to: '/activity-log' }"
    />
    <VerticalNavLink
      v-if="auth.hasPermission('master-data:view')"
      :item="{ title: 'Master Data', icon: 'ri-database-2-line', to: '/master-data' }"
    />
  </template>
</template>
