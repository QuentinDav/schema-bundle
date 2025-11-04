<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import Icon from './Icon.vue'

const route = useRoute()

const navItems = [
  {
    name: 'Schema',
    path: '/schema',
    icon: 'squares-2x2',
    description: 'Visual graph',
  },
  {
    name: 'Cards',
    path: '/cards',
    icon: 'squares-plus',
    description: 'Grid view',
  },
  {
    name: 'List',
    path: '/list',
    icon: 'list-bullet',
    description: 'Table view',
  },
  {
    name: 'Releases',
    path: '/releases',
    icon: 'clock',
    description: 'Version history',
  },
  {
    name: 'Query Builder',
    path: '/query-builder',
    icon: 'command-line',
    description: 'AI-powered SQL',
  },
]

const isActive = computed(() => (path) => {
  return route.path === path || (route.path === '/' && path === '/schema')
})
</script>

<template>
  <aside class="w-64 border-r border-[var(--color-border)] bg-[var(--color-surface)] flex flex-col h-full">
    <nav class="flex-1 p-4 space-y-1">
      <router-link
        v-for="item in navItems"
        :key="item.path"
        :to="item.path"
        class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 group relative"
        :class="isActive(item.path)
          ? 'bg-[var(--color-primary-light)] text-[var(--color-primary)]'
          : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]'"
      >
        <div class="w-5 h-5 flex-shrink-0">
          <Icon :name="item.icon" class="w-5 h-5" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-medium">{{ item.name }}</div>
          <div class="text-xs opacity-70">{{ item.description }}</div>
        </div>
        <div
          v-if="isActive(item.path)"
          class="w-1.5 h-1.5 rounded-full bg-[var(--color-primary)] animate-pulse"
        ></div>
      </router-link>
    </nav>

    <div class="p-4 border-t border-[var(--color-border)]">
      <div class="flex items-center gap-2 text-xs text-[var(--color-text-tertiary)]">
        <Icon name="information-circle" class="w-4 h-4" />
        <span>Schema Viewer v1.0</span>
      </div>
    </div>
  </aside>
</template>
