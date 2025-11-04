<script setup>
import { computed } from 'vue'
import { Handle, Position } from '@vue-flow/core'
import Icon from './Icon.vue'

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
})

function getNamespaceColor(namespace) {
  const colors = [
    '#6366f1',
    '#8b5cf6',
    '#ec4899',
    '#f59e0b',
    '#10b981',
    '#06b6d4',
    '#3b82f6',
    '#14b8a6'
  ]
  const hash = namespace.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0)
  return colors[hash % colors.length]
}

function getTypeColor(type) {
  const typeUpper = type.toUpperCase()
  if (typeUpper.includes('INT') || typeUpper.includes('BIGINT') || typeUpper.includes('SMALLINT')) {
    return '#f59e0b'
  }
  if (typeUpper.includes('VARCHAR') || typeUpper.includes('TEXT') || typeUpper.includes('STRING')) {
    return '#8b5cf6'
  }
  if (typeUpper.includes('DATE') || typeUpper.includes('TIME')) {
    return '#3b82f6'
  }
  if (typeUpper.includes('BOOL')) {
    return '#10b981'
  }
  if (typeUpper.includes('DECIMAL') || typeUpper.includes('FLOAT') || typeUpper.includes('DOUBLE')) {
    return '#06b6d4'
  }
  return '#6b7280'
}

function isPrimaryKey(field) {
  return field.isPrimary || field.name === 'id' || field.primary
}

function formatType(field) {
  let type = field.type || 'unknown'
  if (field.length) {
    type += `(${field.length})`
  }
  return type.toUpperCase()
}

const headerColor = computed(() => getNamespaceColor(props.data.namespace))
const fieldsToShow = computed(() => props.data.fields?.slice(0, 8) || [])
const hasMoreFields = computed(() => (props.data.fields?.length || 0) > 8)
</script>

<template>
  <div class="min-w-[280px] bg-[var(--color-surface)] rounded-lg shadow-lg overflow-hidden border border-[var(--color-border)] transition-all duration-200 hover:shadow-xl hover:-translate-y-1">
    <Handle
      id="top"
      type="target"
      :position="Position.Top"
      class="w-2.5 h-2.5 bg-transparent border-0 opacity-0 pointer-events-none !-top-1"
      :connectable="false"
    />

    <Handle
      id="bottom"
      type="source"
      :position="Position.Bottom"
      class="w-2.5 h-2.5 bg-transparent border-0 opacity-0 pointer-events-none !-bottom-1"
      :connectable="false"
    />

    <div class="px-4 py-3 flex items-center gap-2.5 text-white" :style="{ backgroundColor: headerColor }">
      <div class="flex items-center justify-center w-7 h-7 bg-white/20 rounded-md flex-shrink-0">
        <Icon name="table-cells" class="w-4 h-4" />
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-bold truncate">{{ data.name }}</div>
        <div v-if="data.table" class="text-[11px] opacity-85 truncate font-mono">{{ data.table }}</div>
      </div>
    </div>

    <div class="py-2">
      <div
        v-for="(field, index) in fieldsToShow"
        :key="index"
        class="px-4 py-1.5 flex items-center justify-between hover:bg-[var(--color-surface-hover)] transition-colors"
      >
        <div class="flex items-center gap-1.5 flex-1 min-w-0">
          <Icon
            v-if="isPrimaryKey(field)"
            name="key"
            class="w-3 h-3 text-[var(--color-warning)] flex-shrink-0"
          />
          <span class="text-xs font-medium text-[var(--color-text-primary)] truncate">{{ field.name }}</span>
          <span v-if="field.nullable" class="text-[10px] text-[var(--color-text-tertiary)] font-semibold">?</span>
        </div>
        <div class="text-[11px] font-semibold whitespace-nowrap ml-2" :style="{ color: getTypeColor(field.type) }">
          {{ formatType(field) }}
        </div>
      </div>

      <div v-if="hasMoreFields" class="px-4 py-2 text-[11px] text-[var(--color-text-tertiary)] italic text-center bg-[var(--color-surface-raised)]">
        +{{ data.fields.length - 8 }} more fields...
      </div>
    </div>
  </div>
</template>
