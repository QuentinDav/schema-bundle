<script setup>
import { computed, ref } from 'vue'
import { useSchemaStore, getRelationTypeName } from '@/stores/schema'

const schemaStore = useSchemaStore()

const sortKey = ref('name')
const sortOrder = ref('asc')
const expandedRows = ref(new Set())

const sortedEntities = computed(() => {
  const entities = [...schemaStore.filteredEntities]
  return entities.sort((a, b) => {
    let aVal = a[sortKey.value]
    let bVal = b[sortKey.value]

    if (sortKey.value === 'fields') {
      aVal = a.fields?.length || 0
      bVal = b.fields?.length || 0
    } else if (sortKey.value === 'relations') {
      aVal = getRelationCount(a)
      bVal = getRelationCount(b)
    } else if (sortKey.value === 'table') {
      aVal = a.table
      bVal = b.table
    }

    if (typeof aVal === 'string') {
      aVal = aVal.toLowerCase()
      bVal = bVal?.toLowerCase() || ''
    }

    const comparison = aVal > bVal ? 1 : aVal < bVal ? -1 : 0
    return sortOrder.value === 'asc' ? comparison : -comparison
  })
})

function getRelationCount(entity) {
  return entity.relations?.length || 0
}

function sortBy(key) {
  if (sortKey.value === key) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortOrder.value = 'asc'
  }
}

function toggleRow(fqcn) {
  if (expandedRows.value.has(fqcn)) {
    expandedRows.value.delete(fqcn)
  } else {
    expandedRows.value.add(fqcn)
  }
}

function selectEntity(entity) {
  schemaStore.selectEntity(entity.fqcn)
}
</script>

<template>
  <div class="h-full overflow-auto p-6 bg-[var(--color-background)]">
    <div v-if="sortedEntities.length === 0" class="flex flex-col items-center justify-center h-full text-center">
      <svg class="w-16 h-16 mb-4 text-[var(--color-text-tertiary)]" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M3 4h18v2H3V4zm0 5h18v2H3V9zm0 5h18v2H3v-2zm0 5h18v2H3v-2z" stroke-width="2" />
      </svg>
      <h3 class="text-xl font-semibold text-[var(--color-text-primary)] mb-2">No tables found</h3>
      <p class="text-sm text-[var(--color-text-secondary)]">Try adjusting your search query</p>
    </div>

    <div v-else class="bg-[var(--color-surface)] rounded-lg border border-[var(--color-border)] overflow-hidden">
      <table class="w-full">
        <thead class="bg-[var(--color-surface-raised)] border-b border-[var(--color-border)]">
          <tr>
            <th class="w-12"></th>
            <th @click="sortBy('name')" class="px-4 py-3 text-left text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wider cursor-pointer hover:text-[var(--color-text-primary)] select-none">
              <div class="flex items-center gap-2">
                <span>Table Name</span>
                <svg v-if="sortKey === 'name'" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': sortOrder === 'desc' }" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 5l7 7H5l7-7z" />
                </svg>
              </div>
            </th>
            <th @click="sortBy('table')" class="px-4 py-3 text-left text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wider cursor-pointer hover:text-[var(--color-text-primary)] select-none">
              <div class="flex items-center gap-2">
                <span>Database Table</span>
                <svg v-if="sortKey === 'table'" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': sortOrder === 'desc' }" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 5l7 7H5l7-7z" />
                </svg>
              </div>
            </th>
            <th @click="sortBy('fields')" class="px-4 py-3 text-center text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wider cursor-pointer hover:text-[var(--color-text-primary)] select-none">
              <div class="flex items-center justify-center gap-2">
                <span>Fields</span>
                <svg v-if="sortKey === 'fields'" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': sortOrder === 'desc' }" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 5l7 7H5l7-7z" />
                </svg>
              </div>
            </th>
            <th @click="sortBy('relations')" class="px-4 py-3 text-center text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wider cursor-pointer hover:text-[var(--color-text-primary)] select-none">
              <div class="flex items-center justify-center gap-2">
                <span>Relations</span>
                <svg v-if="sortKey === 'relations'" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': sortOrder === 'desc' }" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 5l7 7H5l7-7z" />
                </svg>
              </div>
            </th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[var(--color-border)]">
          <template v-for="entity in sortedEntities" :key="entity.fqcn">
            <tr class="hover:bg-[var(--color-surface-hover)] transition-colors" :class="{ 'bg-[var(--color-primary-light)]': schemaStore.selectedEntity?.fqcn === entity.fqcn }">
              <td class="px-4 py-3 text-center">
                <button @click="toggleRow(entity.fqcn)" class="p-1 hover:bg-[var(--color-surface-raised)] rounded transition-all">
                  <svg class="w-4 h-4 text-[var(--color-text-secondary)] transition-transform" :class="{ 'rotate-90': expandedRows.has(entity.fqcn) }" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M9 6l6 6-6 6" stroke-width="2" />
                  </svg>
                </button>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-lg bg-[var(--color-primary-light)] text-[var(--color-primary)] flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                      <rect x="3" y="3" width="18" height="18" rx="2" opacity="0.2" />
                      <path d="M3 9h18M9 3v18" stroke="currentColor" stroke-width="2" fill="none" />
                    </svg>
                  </div>
                  <span class="font-medium text-[var(--color-text-primary)]">{{ entity.name }}</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <code v-if="entity.table" class="px-2 py-1 bg-[var(--color-surface-raised)] text-[var(--color-text-secondary)] text-xs font-mono rounded">{{ entity.table }}</code>
                <span v-else class="text-[var(--color-text-tertiary)]">-</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-semibold text-[var(--color-text-primary)] bg-[var(--color-surface-raised)] rounded">
                  {{ entity.fields?.length || 0 }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-semibold text-[var(--color-primary)] bg-[var(--color-primary-light)] rounded">
                  {{ getRelationCount(entity) }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <button @click="selectEntity(entity)" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-[var(--color-primary)] hover:bg-[var(--color-primary-light)] rounded-lg transition-all">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="1" fill="currentColor" />
                    <path d="M12 5c7 0 10 7 10 7s-3 7-10 7-10-7-10-7 3-7 10-7z" stroke-width="2" />
                  </svg>
                  View
                </button>
              </td>
            </tr>
            <tr v-if="expandedRows.has(entity.fqcn)" class="bg-[var(--color-surface-raised)]">
              <td colspan="6" class="px-4 py-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <div class="font-semibold text-[var(--color-text-primary)] mb-2">Fields ({{ entity.fields?.length || 0 }})</div>
                    <div class="space-y-1">
                      <div v-for="field in entity.fields?.slice(0, 5)" :key="field.name" class="flex items-center gap-2 text-[var(--color-text-secondary)]">
                        <span class="w-2 h-2 rounded-full bg-[var(--color-primary)]"></span>
                        <span class="font-mono text-xs">{{ field.name }}</span>
                        <span class="text-xs text-[var(--color-text-tertiary)]">{{ field.type }}</span>
                      </div>
                      <div v-if="entity.fields?.length > 5" class="text-xs text-[var(--color-text-tertiary)] pl-4">
                        +{{ entity.fields.length - 5 }} more
                      </div>
                    </div>
                  </div>
                  <div>
                    <div class="font-semibold text-[var(--color-text-primary)] mb-2">Relations ({{ getRelationCount(entity) }})</div>
                    <div class="space-y-1">
                      <div v-for="relation in entity.relations?.slice(0, 5)" :key="relation.field" class="flex items-center gap-2 text-[var(--color-text-secondary)]">
                        <span class="w-2 h-2 rounded-full bg-[var(--color-accent)]"></span>
                        <span class="font-mono text-xs">{{ relation.field }}</span>
                        <span class="text-xs text-[var(--color-text-tertiary)]">→ {{ relation.target }}</span>
                      </div>
                      <div v-if="entity.relations?.length > 5" class="text-xs text-[var(--color-text-tertiary)] pl-4">
                        +{{ entity.relations.length - 5 }} more
                      </div>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>
