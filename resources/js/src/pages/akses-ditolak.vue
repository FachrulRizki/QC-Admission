<script setup>
// Halaman akses ditolak — user login tapi tidak punya role yang diperlukan
</script>

<template>
  <div class="d-flex flex-column align-center justify-center" style="min-height:100vh">
    <VIcon icon="ri-shield-cross-line" size="72" color="error" class="mb-4" />
    <h2 class="text-h5 font-weight-bold mb-2">Akses Ditolak</h2>
    <p class="text-body-2 text-medium-emphasis mb-6 text-center" style="max-width:360px">
      Akun Anda tidak memiliki izin untuk mengakses halaman ini.
      Hubungi administrator jika ini adalah kesalahan.
    </p>
    <VBtn href="/dashboard" color="primary" variant="tonal" rounded="xl" prepend-icon="ri-arrow-left-line">
      Kembali
    </VBtn>
  </div>
</template>
