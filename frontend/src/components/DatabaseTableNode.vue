t<script setup>
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
    '#667eea',
    '#3b82f6',
    '#10b981',
    '#f59e0b',
    '#06b6d4',
    '#8b5cf6',
    '#ec4899',
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
  <div class="database-table-node">
    <Handle
      id="top"
      type="target"
      :position="Position.Top"
      class="node-handle node-handle-top"
      :connectable="false"
    />

    <Handle
      id="bottom"
      type="source"
      :position="Position.Bottom"
      class="node-handle node-handle-bottom"
      :connectable="false"
    />

    <div class="table-header" :style="{ backgroundColor: headerColor }">
      <div class="table-icon">
        <Icon name="table-cells" :size="16" />
      </div>
      <div class="table-info">
        <div class="table-name">{{ data.name }}</div>
        <div v-if="data.table" class="table-db-name">{{ data.table }}</div>
      </div>
    </div>

    <div class="table-body">
      <div
        v-for="(field, index) in fieldsToShow"
        :key="index"
        class="table-field"
      >
        <div class="field-left">
          <Icon
            v-if="isPrimaryKey(field)"
            name="key"
            :size="12"
            class="field-key-icon"
          />
          <span class="field-name">{{ field.name }}</span>
          <span v-if="field.nullable" class="field-nullable">?</span>
        </div>
        <div class="field-type" :style="{ color: getTypeColor(field.type) }">
          {{ formatType(field) }}
        </div>
      </div>

      <div v-if="hasMoreFields" class="more-fields">
        +{{ data.fields.length - 8 }} more fields...
      </div>
    </div>
  </div>
</template>

<style scoped>
.database-table-node {
  min-width: 280px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  border: 1px solid var(--color-gray-200);
  transition: all 0.2s ease;
}

.database-table-node:hover {
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
  transform: translateY(-2px);
}

.table-header {
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  color: white;
}

.table-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 6px;
}

.table-info {
  flex: 1;
  min-width: 0;
}

.table-name {
  font-size: 14px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.table-db-name {
  font-size: 11px;
  opacity: 0.85;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.table-body {
  padding: 8px 0;
  background: white;
}

.table-field {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 16px;
  transition: background 0.15s ease;
}

.table-field:hover {
  background: var(--color-gray-50);
}

.field-left {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1;
  min-width: 0;
}

.field-key-icon {
  color: #f59e0b;
  flex-shrink: 0;
}

.field-name {
  font-size: 12px;
  font-weight: 500;
  color: var(--color-gray-800);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.field-nullable {
  font-size: 10px;
  color: var(--color-gray-400);
  font-weight: 600;
}

.field-type {
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
  margin-left: 8px;
}

.more-fields {
  padding: 8px 16px;
  font-size: 11px;
  color: var(--color-gray-500);
  font-style: italic;
  text-align: center;
  background: var(--color-gray-50);
}

.node-handle {
  width: 10px;
  height: 10px;
  background: transparent;
  border: none;
  opacity: 0;
  pointer-events: none;
}

.node-handle-top {
  top: -5px;
}

.node-handle-bottom {
  bottom: -5px;
}
</style>
