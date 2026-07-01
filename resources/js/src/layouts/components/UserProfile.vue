<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router    = useRouter()

const user = computed(() => authStore.user)
const initials = computed(() => {
  const name = user.value?.name ?? 'U'
  return name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase()
})

const roleLabel = computed(() => {
  const map = {
    admin:        'Administrator',
    qc_admission: 'QC Admission',
    kasir:        'Kasir',
  }
  return map[user.value?.role] ?? 'Pengguna'
})

async function handleLogout() {
  try {
    await fetch('/api/auth/logout', {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${authStore.token}`,
        Accept: 'application/json',
      },
    })
  } catch {}
  authStore.logout()
  router.push('/login')
}
</script>

<template>
  <VBadge
    dot
    location="bottom right"
    offset-x="3"
    offset-y="3"
    color="success"
    bordered
  >
    <VAvatar
      class="cursor-pointer"
      color="primary"
      variant="tonal"
      size="36"
    >
      <span class="text-subtitle-2 font-weight-bold">{{ initials }}</span>

      <VMenu
        activator="parent"
        width="230"
        location="bottom end"
        offset="14px"
      >
        <VList rounded="lg" elevation="4">
          <!-- User info -->
          <VListItem class="pb-2">
            <template #prepend>
              <VAvatar color="primary" variant="tonal" size="38" class="me-3">
                <span class="text-subtitle-2 font-weight-bold">{{ initials }}</span>
              </VAvatar>
            </template>
            <VListItemTitle class="font-weight-semibold text-body-2">
              {{ user?.name ?? 'Pengguna' }}
            </VListItemTitle>
            <VListItemSubtitle>{{ roleLabel }}</VListItemSubtitle>
          </VListItem>

          <!-- Login type badge -->
          <div class="px-4 pb-2">
            <VChip
              :color="user?.login_type === 'sso' ? 'info' : 'primary'"
              size="x-small"
              variant="tonal"
              :prepend-icon="user?.login_type === 'sso' ? 'ri-key-2-line' : 'ri-user-3-line'"
            >
              {{ user?.login_type === 'sso' ? 'SSO Keycloak' : 'Login Lokal' }}
            </VChip>
          </div>

          <VDivider class="my-1" />

          <!-- Logout -->
          <VListItem @click="handleLogout">
            <template #prepend>
              <VIcon icon="ri-logout-box-r-line" size="20" color="error" class="me-2" />
            </template>
            <VListItemTitle class="text-error">Logout</VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>
    </VAvatar>
  </VBadge>
</template>
