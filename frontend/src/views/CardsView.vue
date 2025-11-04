<script setup>
import { computed } from 'vue'
import { useSchemaStore } from '@/stores/schema'

const schemaStore = useSchemaStore()

const sortedEntities = computed(() => {
  return [...schemaStore.filteredEntities].sort((a, b) => a.name.localeCompare(b.name))
})

function getRelationCount(entity) {
  return entity.relations?.length || 0
}

function selectEntity(entity) {
  schemaStore.selectEntity(entity.fqcn)
}

function getFieldIcon(field) {
  if (field.type?.includes('int') || field.type === 'integer') return '🔢'
  if (field.type?.includes('string') || field.type === 'text') return '📝'
  if (field.type?.includes('bool')) return '✓'
  if (field.type?.includes('date') || field.type?.includes('time')) return '📅'
  if (field.type === 'json') return '📋'
  return '•'
}
</script>

<template>
  <div class="h-full overflow-auto p-6 bg-[var(--color-background)]">
    <div v-if="sortedEntities.length === 0" class="flex flex-col items-center justify-center h-full text-center">
      <svg class="w-16 h-16 mb-4 text-[var(--color-text-tertiary)]" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <rect x="3" y="3" width="7" height="7" rx="1" stroke-width="2" />
        <rect x="14" y="3" width="7" height="7" rx="1" stroke-width="2" />
        <rect x="3" y="14" width="7" height="7" rx="1" stroke-width="2" />
        <rect x="14" y="14" width="7" height="7" rx="1" stroke-width="2" />
      </svg>
      <h3 class="text-xl font-semibold text-[var(--color-text-primary)] mb-2">No tables found</h3>
      <p class="text-sm text-[var(--color-text-secondary)]">Try adjusting your search query</p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <div
        v-for="entity in sortedEntities"
        :key="entity.fqcn"
        class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg p-4 cursor-pointer transition-all hover:border-[var(--color-primary)] hover:-translate-y-1 hover:shadow-lg"
        :class="{ 'border-[var(--color-primary)] ring-2 ring-[var(--color-primary)]/20': schemaStore.selectedEntity?.fqcn === entity.fqcn }"
        @click="selectEntity(entity)"
      >
        <div class="flex items-start gap-3 mb-3">
          <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-[var(--color-primary)] to-[var(--color-primary-hover)] flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor">
              <path d="M3 6a3 3 0 013-3h12a3 3 0 013 3v12a3 3 0 01-3 3H6a3 3 0 01-3-3V6z" opacity="0.2"/>
              <path d="M3 6a3 3 0 013-3h12a3 3 0 013 3v3H3V6zm0 5h18v7a3 3 0 01-3 3H6a3 3 0 01-3-3v-7z"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-[var(--color-text-primary)] truncate">{{ entity.name }}</h3>
            <code v-if="entity.table" class="text-xs text-[var(--color-text-tertiary)] font-mono">{{ entity.table }}</code>
          </div>
        </div>

        <div class="flex items-center gap-4 mb-3 text-sm">
          <div class="flex items-center gap-1.5 text-[var(--color-text-secondary)]">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path d="M4 6h16M4 12h16M4 18h16" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span>{{ entity.fields?.length || 0 }}</span>
          </div>
          <div class="flex items-center gap-1.5 text-[var(--color-primary)]">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71" stroke-width="2" stroke-linecap="round"/>
              <path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span>{{ getRelationCount(entity) }}</span>
          </div>
        </div>

        <div class="border-t border-[var(--color-border)] pt-3">
          <div class="space-y-1.5">
            <div
              v-for="field in (entity.fields || []).slice(0, 6)"
              :key="field.name"
              class="flex items-center gap-2 text-xs"
            >
              <span>{{ getFieldIcon(field) }}</span>
              <span class="font-mono text-[var(--color-text-secondary)] truncate flex-1">{{ field.name }}</span>
              <span class="text-[var(--color-text-tertiary)] text-[10px]">{{ field.type }}</span>
            </div>
            <div v-if="(entity.fields?.length || 0) > 6" class="text-xs text-[var(--color-text-tertiary)] pl-6">
              +{{ entity.fields.length - 6 }} more
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
