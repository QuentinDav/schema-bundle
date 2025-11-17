<script setup>
import { computed } from 'vue'
import { Handle, Position } from '@vue-flow/core'
import Icon from './Icon.vue'
import { BeakerIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
})

const isVirtual = computed(() => props.data.isVirtual || false)
const isPlaygroundMode = computed(() => props.data.isPlaygroundMode || false)

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
  <div
    class="min-w-[280px] rounded-lg shadow-lg overflow-hidden transition-all duration-200 relative"
    :class="[
      isVirtual
        ? 'bg-purple-50 dark:bg-purple-900/10 border-2 border-dashed border-purple-400 dark:border-purple-600'
        : 'bg-[var(--color-surface)] border border-[var(--color-border)]',
      !isPlaygroundMode ? 'hover:shadow-xl hover:-translate-y-1' : ''
    ]"
  >
    <!-- Virtual Badge -->
    <div
      v-if="isVirtual"
      class="absolute top-2 right-2 z-10 px-2 py-1 bg-purple-600 text-white text-[10px] font-bold rounded-md flex items-center gap-1 shadow-lg"
    >
      <BeakerIcon class="w-3 h-3" />
      <span>VIRTUAL</span>
    </div>

    <template v-if="isPlaygroundMode">
      <Handle
        id="top"
        type="target"
        :position="Position.Top"
        class="handle-target handle-target-top"
        :connectable="true"
      />

      <Handle
        id="left"
        type="target"
        :position="Position.Left"
        class="handle-target handle-target-left"
        :connectable="true"
      />

      <Handle
        id="right"
        type="target"
        :position="Position.Right"
        class="handle-target handle-target-right"
        :connectable="true"
      />

      <Handle
        id="bottom"
        type="target"
        :position="Position.Bottom"
        class="handle-target handle-target-bottom"
        :connectable="true"
      />

      <Handle
        id="source-top"
        type="source"
        :position="Position.Top"
        :class="['handle-source', 'handle-source-top', isVirtual ? 'handle-virtual' : '']"
        :connectable="true"
      />

      <Handle
        id="source-left"
        type="source"
        :position="Position.Left"
        :class="['handle-source', 'handle-source-left', isVirtual ? 'handle-virtual' : '']"
        :connectable="true"
      />

      <Handle
        id="source-right"
        type="source"
        :position="Position.Right"
        :class="['handle-source', 'handle-source-right', isVirtual ? 'handle-virtual' : '']"
        :connectable="true"
      />

      <Handle
        id="source-bottom"
        type="source"
        :position="Position.Bottom"
        :class="['handle-source', 'handle-source-bottom', isVirtual ? 'handle-virtual' : '']"
        :connectable="true"
      />
    </template>

    <div
      class="px-4 py-3 flex items-center gap-2.5 text-white"
      :style="{ backgroundColor: isVirtual ? '#9333ea' : headerColor }"
    >
      <div class="flex items-center justify-center w-7 h-7 bg-white/20 rounded-md flex-shrink-0">
        <BeakerIcon v-if="isVirtual" class="w-4 h-4" />
        <Icon v-else name="table-cells" class="w-4 h-4" />
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

<style>
:deep(.handle-target),
:deep(.handle-source) {
  position: relative !important;
  width: 16px !important;
  height: 16px !important;
  border-radius: 50% !important;
  border-width: 2.5px !important;
  border-style: solid !important;
  opacity: 1 !important;
  visibility: visible !important;
  pointer-events: all !important;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.6) !important;
  transition: all 0.2s ease !important;
  z-index: 100 !important;
}

:deep(.handle-target)::before,
:deep(.handle-source)::before {
  content: '' !important;
  position: absolute !important;
  top: 50% !important;
  left: 50% !important;
  transform: translate(-50%, -50%) !important;
  width: 32px !important;
  height: 32px !important;
  border-radius: 50% !important;
  pointer-events: all !important;
  z-index: 101 !important;
}

:deep(.handle-target) {
  background-color: #1f2937 !important;
  border-color: #ef4444 !important;
}

:deep(.handle-target:hover) {
  border-color: #f87171 !important;
  box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.3) !important;
  width: 18px !important;
  height: 18px !important;
}

:deep(.handle-source) {
  background-color: #1e40af !important;
  border-color: #3b82f6 !important;
  cursor: crosshair !important;
}

:deep(.handle-source:hover) {
  border-color: #60a5fa !important;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.3) !important;
  width: 18px !important;
  height: 18px !important;
}

:deep(.handle-source.handle-virtual) {
  background-color: #581c87 !important;
  border-color: #f97316 !important;
}

:deep(.handle-source.handle-virtual:hover) {
  border-color: #fb923c !important;
  box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.3) !important;
}

:deep(.vue-flow__handle.connecting) {
  width: 20px !important;
  height: 20px !important;
  box-shadow: 0 0 0 6px rgba(59, 130, 246, 0.4) !important;
}
</style>
