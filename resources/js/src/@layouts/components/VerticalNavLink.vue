<script setup>
import { Icon } from '@iconify/vue'
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  item: { type: Object, required: true },
})

const page = usePage()
// Active jika URL saat ini cocok dengan item.to
const isActive = computed(() => page.url === props.item.to)
</script>

<template>
  <li class="nav-link" :class="{ disabled: item.disable }">
    <Link
      v-if="item.to"
      :href="item.to"
      :target="item.target"
      :class="{ 'inertia-active': isActive }"
    >
      <Icon v-if="item.icon" :icon="item.icon" class="nav-item-icon" width="22" height="22" />
      <span class="nav-item-title">{{ item.title }}</span>
      <span v-if="item.badgeContent" class="nav-item-badge" :class="item.badgeClass">
        {{ item.badgeContent }}
      </span>
    </Link>

    <a v-else :href="item.href" :target="item.target">
      <Icon v-if="item.icon" :icon="item.icon" class="nav-item-icon" width="22" height="22" />
      <span class="nav-item-title">{{ item.title }}</span>
      <span v-if="item.badgeContent" class="nav-item-badge" :class="item.badgeClass">
        {{ item.badgeContent }}
      </span>
    </a>
  </li>
</template>

<style lang="scss">
.layout-vertical-nav {
  .nav-link a {
    display: flex;
    align-items: center;
    cursor: pointer;
    gap: 10px;
    padding-inline: 20px;
    padding-block: 10px;
    border-radius: 8px;
    text-decoration: none;
    color: inherit;
    transition: background 0.15s;

    &:hover {
      background: rgba(var(--v-theme-primary), 0.08);
    }
  }

  .nav-item-icon {
    flex-shrink: 0;
    font-size: 1.375rem;
    color: rgba(var(--v-theme-on-surface), 0.7);
  }

  .nav-link > .router-link-exact-active,
  .nav-link > .inertia-active {
    background: rgba(var(--v-theme-primary), 0.12);
    color: rgb(var(--v-theme-primary));

    .nav-item-icon {
      color: rgb(var(--v-theme-primary));
    }
  }
}
</style>
