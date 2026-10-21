<script setup>
/**
 * DateField — pengganti <input type="date"> / VTextField type="date".
 * Input tanggal bawaan browser menyembunyikan ikon kalender saat kolom sempit (tablet),
 * jadi tanggal susah diganti. Komponen ini: klik di mana saja → popup kalender.
 *
 * v-model: string 'YYYY-MM-DD' (sama dengan input date sebelumnya).
 * class/style/atribut lain diteruskan ke VTextField (bukan ke VMenu).
 */
import { mergeProps, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })
const attrs = useAttrs()

const props = defineProps({
  modelValue:       { type: String,  default: '' },
  label:            { type: String,  default: '' },
  placeholder:      { type: String,  default: 'Pilih tanggal' },
  variant:          { type: String,  default: 'outlined' },
  density:          { type: String,  default: 'compact' },
  rounded:          { type: String,  default: undefined },
  hideDetails:      { type: [Boolean, String], default: true },
  clearable:        { type: Boolean, default: false },
  prependInnerIcon: { type: String,  default: 'ri-calendar-line' },
  min:              { type: String,  default: undefined },
  max:              { type: String,  default: undefined },
  // true → kalender dirender di dalam parent (perlu jika DateField ada di panel dengan v-click-outside)
  attach:           { type: [Boolean, String], default: false },
})
const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const pad  = n => String(n).padStart(2, '0')

// 'YYYY-MM-DD' ↔ Date (pakai tanggal lokal, bukan UTC, agar tidak geser 1 hari)
function toDate(str) {
  const m = String(str || '').match(/^(\d{4})-(\d{2})-(\d{2})/)
  return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null
}
function toYmd(d) {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

const pickerValue = computed(() => toDate(props.modelValue))

// Tampilan DD/MM/YYYY
const display = computed(() => {
  const d = pickerValue.value
  return d ? `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}` : ''
})

function onPick(val) {
  const d = Array.isArray(val) ? val[0] : val
  if (!d) return
  emit('update:modelValue', toYmd(new Date(d)))
  open.value = false
}

function clear() {
  emit('update:modelValue', '')
}
</script>

<template>
  <VMenu
    v-model="open"
    :close-on-content-click="false"
    :attach="attach"
    location="bottom start"
    offset="4"
  >
    <template #activator="{ props: menuProps }">
      <VTextField
        v-bind="mergeProps(menuProps, attrs)"
        :model-value="display"
        :label="label"
        :placeholder="label ? undefined : placeholder"
        :variant="variant"
        :density="density"
        :rounded="rounded"
        :hide-details="hideDetails"
        :prepend-inner-icon="prependInnerIcon || undefined"
        readonly
        class="date-field"
      >
        <!-- Tombol hapus sendiri: clearable bawaan tidak tampil pada field readonly -->
        <template v-if="clearable && modelValue" #append-inner>
          <VIcon icon="ri-close-circle-fill" size="16" class="date-field__clear" @click.stop="clear" />
        </template>
      </VTextField>
    </template>

    <VCard rounded="lg" elevation="8">
      <VDatePicker
        :model-value="pickerValue"
        :min="min"
        :max="max"
        color="primary"
        show-adjacent-months
        hide-header
        @update:model-value="onPick"
      />
      <div class="d-flex justify-space-between px-3 pb-3">
        <VBtn size="small" variant="text" @click="emit('update:modelValue', toYmd(new Date())); open = false">
          Hari ini
        </VBtn>
        <VBtn size="small" variant="text" color="secondary" @click="open = false">Tutup</VBtn>
      </div>
    </VCard>
  </VMenu>
</template>

<style scoped>
.date-field :deep(input) { cursor: pointer; }
.date-field :deep(.v-field) { cursor: pointer; }
.date-field__clear { opacity: .45; cursor: pointer; }
.date-field__clear:hover { opacity: .8; }
</style>
