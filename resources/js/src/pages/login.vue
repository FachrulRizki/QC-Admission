<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import { useRouter } from 'vue-router'

const router = useRouter()
const authStore = useAuthStore()

const form = ref({
  username: '',
  password: '',
})

const isPasswordVisible = ref(false)
const loading = ref(false)
const errorMsg = ref('')

async function handleLogin() {
  errorMsg.value = ''
  if (!form.value.username || !form.value.password) {
    errorMsg.value = 'Username dan password wajib diisi.'
    return
  }

  loading.value = true
  const result = await authStore.login(form.value)
  loading.value = false

  if (result.success) {
    router.push('/dashboard')
  } else {
    errorMsg.value = result.message ?? 'Login gagal. Periksa kembali username dan password.'
  }
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <VCard
      class="auth-card pa-6 pt-8"
      max-width="420"
      width="100%"
      elevation="8"
      rounded="lg"
    >
      <!-- Logo & Title -->
      <VCardItem class="justify-center pb-2">
        <div class="d-flex flex-column align-center gap-2">
          <div class="auth-logo-wrapper mb-1">
            <VIcon
              icon="ri-shield-check-fill"
              size="52"
              color="primary"
            />
          </div>
          <h1 class="text-h5 font-weight-bold text-uppercase tracking-wide">
            QC Admission
          </h1>
          <p class="text-body-2 text-medium-emphasis text-center mb-0">
            Quality Control Admisi Rumah Sakit
          </p>
        </div>
      </VCardItem>

      <VDivider class="my-4" />

      <VCardText class="pt-2">
        <!-- Error Alert -->
        <VAlert
          v-if="errorMsg"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-4"
          closable
          @click:close="errorMsg = ''"
        >
          {{ errorMsg }}
        </VAlert>

        <VForm @submit.prevent="handleLogin">
          <VRow>
            <!-- Username -->
            <VCol cols="12">
              <VTextField
                v-model="form.username"
                label="Username"
                placeholder="Masukkan username"
                prepend-inner-icon="ri-user-3-line"
                variant="outlined"
                autofocus
                :disabled="loading"
              />
            </VCol>

            <!-- Password -->
            <VCol cols="12">
              <VTextField
                v-model="form.password"
                label="Password"
                placeholder="············"
                prepend-inner-icon="ri-lock-2-line"
                :type="isPasswordVisible ? 'text' : 'password'"
                :append-inner-icon="isPasswordVisible ? 'ri-eye-off-line' : 'ri-eye-line'"
                variant="outlined"
                :disabled="loading"
                @click:append-inner="isPasswordVisible = !isPasswordVisible"
              />
            </VCol>

            <!-- Login Button -->
            <VCol cols="12">
              <VBtn
                block
                type="submit"
                size="large"
                color="primary"
                :loading="loading"
                prepend-icon="ri-login-circle-line"
              >
                Login / Masuk
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardText class="text-center pt-0">
        <p class="text-caption text-disabled mb-0">
          &copy; {{ new Date().getFullYear() }} QC Admission &mdash; RSUD
        </p>
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.auth-wrapper {
  min-block-size: 100dvh;
  background: rgb(var(--v-theme-background));
}

.auth-card {
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12) !important;
}

.tracking-wide {
  letter-spacing: 0.08em;
}
</style>
