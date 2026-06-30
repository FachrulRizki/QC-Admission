<script setup>
import NavItems from '@/layouts/components/NavItems.vue'
import VerticalNavLayout from '@layouts/components/VerticalNavLayout.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'

// Current date for navbar display
const today = new Date().toLocaleDateString('id-ID', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
})
</script>

<template>
  <VerticalNavLayout>
    <!-- 👉 Navbar -->
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center w-100">
        <!-- Mobile menu toggle -->
        <IconBtn
          class="ms-n3 d-lg-none"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon icon="ri-menu-line" />
        </IconBtn>

        <!-- Page date info -->
        <div class="d-none d-md-flex align-center gap-2">
          <VIcon icon="ri-calendar-check-line" size="18" color="primary" />
          <span class="text-body-2 text-medium-emphasis">{{ today }}</span>
        </div>

        <VSpacer />

        <!-- Notification -->
        <!-- <IconBtn class="me-1">
          <VIcon icon="ri-notification-3-line" />
          <VBadge
            color="error"
            content="3"
            floating
          />
        </IconBtn> -->

        <!-- Theme switcher -->
        <NavbarThemeSwitcher class="me-2" />

        <!-- User profile -->
        <UserProfile />
      </div>
    </template>

    <!-- 👉 Vertical nav header -->
    <template #vertical-nav-header="{ toggleIsOverlayNavActive }">
      <RouterLink
        to="/"
        class="app-logo app-title-wrapper"
      >
        <div class="app-logo-icon d-flex align-center justify-center rounded-lg">
          <VIcon icon="ri-shield-check-fill" size="22" color="white" />
        </div>
        <div class="ms-2">
          <h1 class="app-logo-title">QC ADMISSION</h1>
          <p class="app-logo-subtitle mb-0">Quality Control</p>
        </div>
      </RouterLink>

      <IconBtn
        class="d-block d-lg-none"
        @click="toggleIsOverlayNavActive(false)"
      >
        <VIcon icon="ri-close-line" />
      </IconBtn>
    </template>

    <!-- 👉 Nav items -->
    <template #vertical-nav-content>
      <NavItems />
    </template>

    <!-- 👉 Page content -->
    <slot />
  </VerticalNavLayout>
</template>

<style lang="scss" scoped>
.app-logo {
  display: flex;
  align-items: center;
  text-decoration: none;
  column-gap: 0.5rem;
}

.app-logo-icon {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)), rgba(var(--v-theme-primary), 0.7));
  inline-size: 34px;
  block-size: 34px;
  flex-shrink: 0;
}

.app-logo-title {
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1.2;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: rgb(var(--v-theme-on-surface));
}

.app-logo-subtitle {
  font-size: 0.6875rem;
  color: rgba(var(--v-theme-on-surface), 0.5);
  line-height: 1.2;
}
</style>
