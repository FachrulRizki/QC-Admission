<script setup>
import NavItems from '@/layouts/components/NavItems.vue'
import VerticalNavLayout from '@layouts/components/VerticalNavLayout.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import { Link, router as inertiaRouter } from '@inertiajs/vue3'
import { useUpSellingStore } from '@/stores/useUpSellingStore'

const upSellingStore = useUpSellingStore()

// Fetch alert saat layout mount — berjalan di semua halaman
onMounted(() => upSellingStore.fetchAlertBedah())

// Refresh setiap 2 menit
let alertTimer = null
onMounted(() => { alertTimer = setInterval(() => upSellingStore.fetchAlertBedah(), 120_000) })
onUnmounted(() => clearInterval(alertTimer))

const showNotifPanel = ref(false)

function goToUpSelling() {
  showNotifPanel.value = false
  inertiaRouter.visit('/up-selling')
}

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
        <IconBtn class="ms-n3 d-lg-none" @click="toggleVerticalOverlayNavActive(true)">
          <VIcon icon="ri-menu-line" />
        </IconBtn>

        <!-- Page date info -->
        <div class="d-none d-md-flex align-center gap-2">
          <VIcon icon="ri-calendar-check-line" size="18" color="primary" />
          <span class="text-body-2 text-medium-emphasis">{{ today }}</span>
        </div>

        <VSpacer />

        <!-- Notification Bell — Alert Bedah Up Selling -->
        <VMenu v-model="showNotifPanel" location="bottom end" :close-on-content-click="false" max-width="340">
          <template #activator="{ props: menuProps }">
            <IconBtn v-bind="menuProps" class="me-1" style="position:relative">
              <VIcon icon="ri-notification-3-line" />
              <span
                v-if="upSellingStore.alertBedahCount"
                class="notif-badge"
              >{{ upSellingStore.alertBedahCount > 9 ? '9+' : upSellingStore.alertBedahCount }}</span>
            </IconBtn>
          </template>

          <VCard rounded="lg" elevation="4" class="notif-panel overflow-hidden">
            <!-- Header -->
            <div class="notif-header">
              <div class="d-flex align-center gap-2">
                <VAvatar color="error" variant="tonal" size="32" rounded="lg">
                  <VIcon icon="ri-alarm-warning-line" size="16" />
                </VAvatar>
                <div>
                  <p class="notif-title mb-0">Perlu Perhatian</p>
                  <p class="notif-sub mb-0">Pasien Bedah belum ada keterangan tindakan</p>
                </div>
              </div>
            </div>

            <!-- Empty state -->
            <div v-if="!upSellingStore.alertBedahCount" class="notif-empty">
              <VIcon icon="ri-checkbox-circle-line" size="32" color="success" class="mb-2" />
              <p class="text-body-2 font-weight-semibold mb-0">Semua sudah lengkap</p>
              <p class="text-caption text-medium-emphasis">Tidak ada data yang perlu diperbarui</p>
            </div>

            <!-- List -->
            <div v-else class="notif-list">
              <div
                v-for="item in upSellingStore.alertBedah.slice(0, 5)"
                :key="item.id"
                class="notif-row"
                @click="goToUpSelling"
              >
                <VAvatar color="error" variant="tonal" size="32" rounded="lg" class="flex-shrink-0">
                  <VIcon icon="ri-surgical-mask-line" size="14" />
                </VAvatar>
                <div class="flex-grow-1 min-width-0">
                  <p class="notif-pasien text-truncate mb-0">{{ item.nama_pasien }}</p>
                  <p class="notif-reg mb-0">{{ item.no_reg }} · {{ item.petugas }}</p>
                </div>
                <VIcon icon="ri-arrow-right-s-line" size="16" color="error" class="flex-shrink-0" />
              </div>
            </div>

            <!-- Footer -->
            <div v-if="upSellingStore.alertBedahCount" class="notif-footer">
              <VBtn
                block variant="tonal" color="error" rounded="lg" size="small"
                @click="goToUpSelling"
              >
                <VIcon icon="ri-arrow-up-circle-line" size="15" class="me-1" />
                Lihat Semua di Up Selling
                <VChip size="x-small" color="error" class="ms-2">{{ upSellingStore.alertBedahCount }}</VChip>
              </VBtn>
            </div>
          </VCard>
        </VMenu>

        <!-- Theme switcher -->
        <NavbarThemeSwitcher class="me-2" />

        <!-- User profile -->
        <UserProfile />
      </div>
    </template>

    <!-- 👉 Vertical nav header -->
    <template #vertical-nav-header="{ toggleIsOverlayNavActive }">
      <Link href="/dashboard" class="app-logo app-title-wrapper">
        <div class="app-logo-icon d-flex align-center justify-center rounded-lg">
          <VIcon icon="ri-shield-check-fill" size="22" color="white" />
        </div>
        <div class="ms-2">
          <h1 class="app-logo-title">QC ADMISSION</h1>
          <p class="app-logo-subtitle mb-0">Quality Control</p>
        </div>
      </Link>

      <IconBtn class="d-block d-lg-none" @click="toggleIsOverlayNavActive(false)">
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

/* ── Notification Bell ── */
.notif-badge {
  position: absolute;
  top: 4px; right: 4px;
  min-width: 16px; height: 16px;
  border-radius: 8px;
  background: rgb(var(--v-theme-error));
  color: #fff;
  font-size: 0.6rem;
  font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  padding: 0 3px;
  line-height: 1;
  pointer-events: none;
}

/* ── Notification Panel ── */
.notif-panel { min-width: 300px; }

.notif-header {
  padding: 14px 16px 12px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-error), 0.04);
}
.notif-title {
  font-size: 0.82rem; font-weight: 700;
  color: rgb(var(--v-theme-on-surface));
}
.notif-sub {
  font-size: 0.68rem;
  color: rgba(var(--v-theme-on-surface), 0.55);
}

.notif-empty {
  display: flex; flex-direction: column; align-items: center;
  padding: 24px 16px; text-align: center;
}

.notif-list { max-height: 240px; overflow-y: auto; }
.notif-row {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 16px;
  cursor: pointer;
  transition: background 0.12s;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.notif-row:last-child { border-bottom: none; }
.notif-row:hover { background: rgba(var(--v-theme-error), 0.04); }
.notif-pasien {
  font-size: 0.82rem; font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
}
.notif-reg {
  font-size: 0.68rem;
  color: rgba(var(--v-theme-on-surface), 0.55);
}

.notif-footer {
  padding: 10px 12px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-surface-variant), 0.3);
}
</style>
