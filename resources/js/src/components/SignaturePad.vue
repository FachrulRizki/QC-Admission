<script setup>
import SignaturePad from 'signature_pad'

const props = defineProps({
  modelValue: { type: String, default: '' },   // base64 PNG
  label: { type: String, default: 'Tanda Tangan' },
  readonly: { type: Boolean, default: false },
  height: { type: Number, default: 160 },
})
const emit = defineEmits(['update:modelValue'])

const canvasRef = ref(null)
let signaturePad = null

onMounted(() => {
  if (!canvasRef.value) return
  signaturePad = new SignaturePad(canvasRef.value, {
    backgroundColor: 'rgba(255,255,255,0)',
    penColor: '#1a1a2e',
    minWidth: 1,
    maxWidth: 2.5,
  })

  if (props.modelValue) loadExisting()
  if (props.readonly) signaturePad.off()

  signaturePad.addEventListener('endStroke', () => {
    emit('update:modelValue', signaturePad.toDataURL('image/png'))
  })

  resizeCanvas()
  window.addEventListener('resize', resizeCanvas)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', resizeCanvas)
})

watch(() => props.modelValue, (val) => {
  if (!signaturePad) return
  if (!val) { signaturePad.clear(); return }
  loadExisting()
})

watch(() => props.readonly, (val) => {
  if (!signaturePad) return
  val ? signaturePad.off() : signaturePad.on()
})

function loadExisting() {
  if (!props.modelValue || !signaturePad) return
  signaturePad.fromDataURL(props.modelValue)
}

function resizeCanvas() {
  if (!canvasRef.value) return
  const ratio = Math.max(window.devicePixelRatio || 1, 1)
  const canvas = canvasRef.value
  const data = signaturePad?.toData?.() ?? []
  canvas.width = canvas.offsetWidth * ratio
  canvas.height = props.height * ratio
  canvas.getContext('2d').scale(ratio, ratio)
  if (data.length && signaturePad) signaturePad.fromData(data)
}

function clear() {
  signaturePad?.clear()
  emit('update:modelValue', '')
}

function isEmpty() {
  return !props.modelValue && (signaturePad?.isEmpty() ?? true)
}

defineExpose({ clear, isEmpty })
</script>

<template>
  <div class="sig-pad-wrapper">
    <p v-if="label" class="text-caption font-weight-medium mb-1">{{ label }}</p>

    <div class="sig-pad-box" :style="{ height: height + 'px' }" :class="{ 'sig-readonly': readonly }">
      <canvas ref="canvasRef" class="sig-canvas" />

      <!-- Placeholder when empty -->
      <div v-if="isEmpty() && !readonly" class="sig-placeholder text-caption text-medium-emphasis">
        <VIcon icon="ri-pen-nib-line" size="20" class="me-1" />
        Tanda tangan di sini
      </div>
    </div>

    <!-- Clear button -->
    <div v-if="!readonly" class="d-flex justify-end mt-1">
      <VBtn size="x-small" variant="text" color="error" prepend-icon="ri-eraser-line" @click="clear">
        Hapus TTD
      </VBtn>
    </div>
  </div>
</template>

<style scoped>
.sig-pad-wrapper {
  width: 100%;
}

.sig-pad-box {
  position: relative;
  border: 1.5px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: rgb(var(--v-theme-surface));
  overflow: hidden;
  cursor: crosshair;
  transition: border-color 0.2s;
}

.sig-pad-box:hover:not(.sig-readonly) {
  border-color: rgb(var(--v-theme-primary));
}

.sig-readonly {
  cursor: default;
  background: rgba(var(--v-theme-on-surface), 0.03);
}

.sig-canvas {
  width: 100%;
  height: 100%;
  display: block;
  touch-action: none;
}

.sig-placeholder {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
  user-select: none;
}
</style>
