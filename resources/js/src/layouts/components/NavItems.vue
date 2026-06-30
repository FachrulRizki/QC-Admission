<script setup>
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
      icon: 'ri-list-check-3',
      to: '/batal-ranap',
    }"
  />

  <!-- 👉 View Data Input -->
  <VerticalNavLink
    :item="{
      title: 'View Data Input',
      icon: 'ri-contacts-book-2-line',
      to: '/view-data-input',
    }"
  />

  <!-- 👉 Up Selling -->
  <VerticalNavLink
    :item="{
      title: 'Up Selling',
      icon: 'ri-shopping-bag-3-line',
      to: '/up-selling',
    }"
  />
</template>
