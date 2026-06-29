<script setup>
import VerticalNavSectionTitle from '@/@layouts/components/VerticalNavSectionTitle.vue'
import VerticalNavGroup from '@layouts/components/VerticalNavGroup.vue'
import VerticalNavLink from '@layouts/components/VerticalNavLink.vue'
</script>

<template>
  <!-- 👉 Dashboard -->
  <VerticalNavLink
    :item="{
      title: 'Dashboard',
      icon: 'ri-home-smile-line',
      to: '/dashboard',
    }"
  />

  <!-- 👉 Quality Control -->
  <VerticalNavLink
    :item="{
      title: 'Quality Control',
      icon: 'ri-shield-check-line',
      to: '/quality-control',
    }"
  />

  <!-- 👉 Edukasi Lanjutan -->
  <VerticalNavLink
    :item="{
      title: 'Edukasi Lanjutan',
      icon: 'ri-book-open-line',
      to: '/edukasi-lanjutan',
    }"
  />

  <!-- 👉 Batal Ranap -->
  <VerticalNavLink
    :item="{
      title: 'Batal Ranap',
      icon: 'ri-close-circle-line',
      to: '/batal-ranap',
    }"
  />

  <!-- 👉 Up Selling -->
  <VerticalNavLink
    :item="{
      title: 'Up Selling',
      icon: 'ri-arrow-up-circle-line',
      to: '/up-selling',
    }"
  />

  <!-- 👉 Report Data Input -->
  <VerticalNavLink
    :item="{
      title: 'Report Data Input',
      icon: 'ri-file-chart-line',
      to: '/report-data-input',
    }"
  />
</template>
