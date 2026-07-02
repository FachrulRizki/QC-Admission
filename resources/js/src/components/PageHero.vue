<script setup>
defineProps({
  icon:      { type: String, default: 'ri-apps-line' },
  badge:     { type: String, default: '' },
  title:     { type: String, required: true },
  subtitle:  { type: String, default: '' },
  pills:     { type: Array,  default: () => [] }, // [{ icon, text }]
  colorFrom: { type: String, default: '#7C3AED' },
  colorTo:   { type: String, default: '#4C1D95' },
  textColor: { type: String, default: 'light' }, // 'light' | 'dark'
})
</script>

<template>
  <div
    class="page-hero"
    :class="`page-hero--text-${textColor}`"
    :style="{ '--hero-from': colorFrom, '--hero-to': colorTo }"
  >
    <!-- Decorative background -->
    <div class="page-hero__bg" aria-hidden="true">
      <span class="page-hero__blob page-hero__blob--1" />
      <span class="page-hero__blob page-hero__blob--2" />
      <span class="page-hero__blob page-hero__blob--3" />
      <span class="page-hero__grid" />
    </div>

    <div class="page-hero__row">
      <!-- Text -->
      <div class="page-hero__text">
        <div v-if="badge" class="page-hero__badge">
          <VIcon :icon="icon" size="12" />
          <span>{{ badge }}</span>
        </div>

        <h1 class="page-hero__title">{{ title }}</h1>
        <p v-if="subtitle" class="page-hero__subtitle">{{ subtitle }}</p>

        <div v-if="pills.length" class="page-hero__pills">
          <span v-for="(p, i) in pills" :key="i" class="page-hero__pill">
            <VIcon :icon="p.icon" size="11" />{{ p.text }}
          </span>
        </div>

        <div v-if="$slots.actions" class="page-hero__actions">
          <slot name="actions" />
        </div>
      </div>

      <!-- Illustration -->
      <div class="page-hero__art" aria-hidden="true">
        <span class="page-hero__float page-hero__float--dot page-hero__float--red" />
        <span class="page-hero__float page-hero__float--dot page-hero__float--teal" />
        <span class="page-hero__float page-hero__float--chip page-hero__float--a">
          <VIcon icon="ri-sparkling-2-fill" size="16" />
        </span>
        <span class="page-hero__float page-hero__float--chip page-hero__float--b">
          <VIcon icon="ri-checkbox-circle-fill" size="14" />
        </span>
        <div class="page-hero__art-core">
          <VIcon :icon="icon" size="46" />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-hero {
  position: relative;
  overflow: hidden;
  border-radius: 24px;
  padding: 24px 28px;
  margin-bottom: 24px;
  background: linear-gradient(135deg, var(--hero-from) 0%, var(--hero-to) 100%);
  box-shadow: 0 12px 28px -12px color-mix(in srgb, var(--hero-from) 55%, transparent);
  isolation: isolate;
}

.page-hero--text-light { color: #fff; }
.page-hero--text-dark  { color: rgba(0, 40, 30, 0.92); }

/* ---------- decorative background ---------- */
.page-hero__bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
}
.page-hero__grid {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255, 255, 255, 0.14) 1px, transparent 1px);
  background-size: 18px 18px;
  mask-image: linear-gradient(120deg, rgba(0, 0, 0, 0.5), transparent 65%);
  opacity: 0.6;
}
.page-hero__blob {
  position: absolute;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.16);
  filter: blur(2px);
}
.page-hero__blob--1 { width: 220px; height: 220px; top: -90px; right: 60px; background: rgba(255,255,255,0.14); }
.page-hero__blob--2 { width: 140px; height: 140px; bottom: -60px; right: 220px; background: rgba(255,255,255,0.10); }
.page-hero__blob--3 { width: 90px;  height: 90px;  top: 30%; left: -30px; background: rgba(255,255,255,0.10); }

/* ---------- layout ---------- */
.page-hero__row {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}

.page-hero__text {
  flex: 1 1 auto;
  min-width: 0;
}

.page-hero__badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  background: rgba(255, 255, 255, 0.18);
  backdrop-filter: blur(4px);
  margin-bottom: 12px;
  white-space: nowrap;
}
.page-hero--text-dark .page-hero__badge { background: rgba(0, 40, 20, 0.12); }

.page-hero__title {
  font-size: clamp(1.35rem, 1.1rem + 1vw, 1.85rem);
  font-weight: 800;
  color:white;
  line-height: 1.2;
  margin: 0 0 6px;
}

.page-hero__subtitle {
  font-size: 0.88rem;
  font-weight: 500;
  opacity: 0.92;
  margin: 0;
  max-width: 46ch;
}

.page-hero__pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 14px;
}
.page-hero__pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
  background: rgba(255, 255, 255, 0.16);
}
.page-hero--text-dark .page-hero__pill { background: rgba(0, 40, 20, 0.1); }

.page-hero__actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 16px;
}

/* ---------- illustration ---------- */
.page-hero__art {
  position: relative;
  flex: 0 0 auto;
  width: 110px;
  height: 110px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.page-hero__art-core {
  width: 84px;
  height: 84px;
  border-radius: 26px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.16);
  backdrop-filter: blur(6px);
  border: 1px solid rgba(255, 255, 255, 0.28);
  box-shadow: 0 10px 24px -8px rgba(0, 0, 0, 0.35);
  transform: rotate(-6deg);
  animation: hero-float 5s ease-in-out infinite;
}
.page-hero--text-dark .page-hero__art-core {
  background: rgba(0, 40, 20, 0.1);
  border-color: rgba(0, 40, 20, 0.18);
}

.page-hero__float { position: absolute; }
.page-hero__float--dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.12);
}
.page-hero__float--red  { top: -6px; left: 4px; background: #FF6B6B; animation: hero-float 4s ease-in-out infinite 0.3s; }
.page-hero__float--teal { bottom: 2px; left: -10px; background: #2DD4BF; animation: hero-float 4.5s ease-in-out infinite 0.6s; }

.page-hero__float--chip {
  width: 26px;
  height: 26px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.22);
  border: 1px solid rgba(255, 255, 255, 0.3);
}
.page-hero--text-dark .page-hero__float--chip {
  background: rgba(0, 40, 20, 0.12);
  border-color: rgba(0, 40, 20, 0.2);
}
.page-hero__float--a { top: -8px; right: -4px; animation: hero-float 3.6s ease-in-out infinite 0.2s; }
.page-hero__float--b { bottom: -6px; right: 14px; animation: hero-float 4.2s ease-in-out infinite 0.9s; }

@keyframes hero-float {
  0%, 100% { transform: translateY(0) rotate(-6deg); }
  50%      { transform: translateY(-7px) rotate(-2deg); }
}

/* ---------- responsive ---------- */
@media (max-width: 960px) {
  .page-hero { padding: 20px 22px; border-radius: 20px; }
  .page-hero__art { width: 88px; height: 88px; }
  .page-hero__art-core { width: 66px; height: 66px; border-radius: 20px; }
}

@media (max-width: 600px) {
  .page-hero { padding: 18px; }
  .page-hero__row { flex-wrap: wrap; }
  .page-hero__art { display: none; }
  .page-hero__subtitle { max-width: 100%; }
  .page-hero__actions { width: 100%; }
  .page-hero__actions :deep(.v-btn) { flex: 1 1 auto; }
}

@media (prefers-reduced-motion: reduce) {
  .page-hero__art-core,
  .page-hero__float { animation: none; }
}
</style>