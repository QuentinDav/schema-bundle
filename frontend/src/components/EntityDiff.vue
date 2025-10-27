<script setup>
import { ref } from 'vue'
import Icon from './Icon.vue'

const props = defineProps({
  entityName: {
    type: String,
    required: true
  },
  diff: {
    type: Object,
    required: true
  },
  oldSchema: {
    type: Object,
    default: null
  },
  newSchema: {
    type: Object,
    default: null
  }
})

const isExpanded = ref(false)

function toggleExpand() {
  isExpanded.value = !isExpanded.value
}

function hasChanges() {
  const d = props.diff
  return (
    (d.fields_added?.length > 0) ||
    (d.fields_removed?.length > 0) ||
    (d.fields_changed?.length > 0) ||
    (d.rels_added?.length > 0) ||
    (d.rels_removed?.length > 0) ||
    (d.rels_changed?.length > 0)
  )
}

function getFieldSpec(field) {
  const parts = []
  parts.push(field.type || 'unknown')
  if (field.length) parts.push(`len=${field.length}`)
  if (field.nullable === false) parts.push('not-null')
  if (field.nullable === true) parts.push('nullable')
  if (field.unique) parts.push('unique')
  return parts.join(', ')
}

function getRelationSpec(rel) {
  const parts = []
  const typeMap = { 1: 'OneToOne', 2: 'ManyToOne', 4: 'OneToMany', 8: 'ManyToMany' }
  parts.push(typeMap[rel.type] || `type=${rel.type}`)
  if (rel.isOwning) parts.push('owning')
  if (rel.nullable) parts.push('nullable')
  return parts.join(', ')
}

function getChangedProperties(from, to) {
  const changes = []

  if (from.type !== to.type) {
    changes.push(`type: ${from.type} → ${to.type}`)
  }
  if (from.length !== to.length) {
    changes.push(`length: ${from.length || 'none'} → ${to.length || 'none'}`)
  }
  if (from.nullable !== to.nullable) {
    changes.push(`nullable: ${from.nullable ? 'yes' : 'no'} → ${to.nullable ? 'yes' : 'no'}`)
  }
  if (from.unique !== to.unique) {
    changes.push(`unique: ${from.unique ? 'yes' : 'no'} → ${to.unique ? 'yes' : 'no'}`)
  }

  return changes
}

function getRelationChangedProperties(from, to) {
  const changes = []
  const typeMap = { 1: 'OneToOne', 2: 'ManyToOne', 4: 'OneToMany', 8: 'ManyToMany' }

  if (from.type !== to.type) {
    changes.push(`type: ${typeMap[from.type]} → ${typeMap[to.type]}`)
  }
  if (from.isOwning !== to.isOwning) {
    changes.push(`owning: ${from.isOwning ? 'yes' : 'no'} → ${to.isOwning ? 'yes' : 'no'}`)
  }
  if (from.nullable !== to.nullable) {
    changes.push(`nullable: ${from.nullable ? 'yes' : 'no'} → ${to.nullable ? 'yes' : 'no'}`)
  }

  return changes
}
</script>

<template>
  <div class="entity-diff">
    <div class="diff-header" @click="toggleExpand">
      <div class="diff-header-left">
        <Icon
          :name="isExpanded ? 'chevron-down' : 'chevron-right'"
          :size="20"
          class="expand-icon"
        />
        <h4>{{ entityName }}</h4>
      </div>

      <div class="diff-summary">
        <span v-if="diff.fields_added?.length" class="diff-badge added">
          +{{ diff.fields_added.length }} field{{ diff.fields_added.length > 1 ? 's' : '' }}
        </span>
        <span v-if="diff.fields_removed?.length" class="diff-badge removed">
          -{{ diff.fields_removed.length }} field{{ diff.fields_removed.length > 1 ? 's' : '' }}
        </span>
        <span v-if="diff.fields_changed?.length" class="diff-badge modified">
          ~{{ diff.fields_changed.length }} field{{ diff.fields_changed.length > 1 ? 's' : '' }}
        </span>
        <span v-if="diff.rels_added?.length" class="diff-badge added">
          +{{ diff.rels_added.length }} rel{{ diff.rels_added.length > 1 ? 's' : '' }}
        </span>
        <span v-if="diff.rels_removed?.length" class="diff-badge removed">
          -{{ diff.rels_removed.length }} rel{{ diff.rels_removed.length > 1 ? 's' : '' }}
        </span>
        <span v-if="diff.rels_changed?.length" class="diff-badge modified">
          ~{{ diff.rels_changed.length }} rel{{ diff.rels_changed.length > 1 ? 's' : '' }}
        </span>
      </div>
    </div>

    <div v-if="isExpanded" class="diff-details">
      <div v-if="diff.fields_added?.length > 0" class="diff-section">
        <h5 class="section-title added">
          <Icon name="plus" :size="16" />
          Added Fields
        </h5>
        <div class="diff-items">
          <div v-for="field in diff.fields_added" :key="field.name" class="diff-item added">
            <div class="diff-line">
              <span class="diff-marker">+</span>
              <code class="field-name">{{ field.name }}</code>
              <span class="field-spec">({{ getFieldSpec(field) }})</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="diff.fields_removed?.length > 0" class="diff-section">
        <h5 class="section-title removed">
          <Icon name="minus" :size="16" />
          Removed Fields
        </h5>
        <div class="diff-items">
          <div v-for="field in diff.fields_removed" :key="field.name" class="diff-item removed">
            <div class="diff-line">
              <span class="diff-marker">-</span>
              <code class="field-name">{{ field.name }}</code>
            </div>
          </div>
        </div>
      </div>

      <div v-if="diff.fields_changed?.length > 0" class="diff-section">
        <h5 class="section-title modified">
          <Icon name="arrow-path" :size="16" />
          Modified Fields
        </h5>
        <div class="diff-items">
          <div v-for="field in diff.fields_changed" :key="field.name" class="diff-item modified">
            <div class="diff-line">
              <span class="diff-marker">~</span>
              <code class="field-name">{{ field.name }}</code>
            </div>
            <div class="change-details">
              <div v-for="change in getChangedProperties(field.from, field.to)" :key="change" class="change-item">
                {{ change }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="diff.rels_added?.length > 0" class="diff-section">
        <h5 class="section-title added">
          <Icon name="plus" :size="16" />
          Added Relations
        </h5>
        <div class="diff-items">
          <div v-for="rel in diff.rels_added" :key="rel.field" class="diff-item added">
            <div class="diff-line">
              <span class="diff-marker">+</span>
              <code class="field-name">{{ rel.field }}</code>
              <span class="relation-target">→ {{ rel.target }}</span>
              <span class="field-spec">({{ getRelationSpec(rel) }})</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="diff.rels_removed?.length > 0" class="diff-section">
        <h5 class="section-title removed">
          <Icon name="minus" :size="16" />
          Removed Relations
        </h5>
        <div class="diff-items">
          <div v-for="rel in diff.rels_removed" :key="rel.field" class="diff-item removed">
            <div class="diff-line">
              <span class="diff-marker">-</span>
              <code class="field-name">{{ rel.field }}</code>
            </div>
          </div>
        </div>
      </div>

      <div v-if="diff.rels_changed?.length > 0" class="diff-section">
        <h5 class="section-title modified">
          <Icon name="arrow-path" :size="16" />
          Modified Relations
        </h5>
        <div class="diff-items">
          <div v-for="rel in diff.rels_changed" :key="rel.field" class="diff-item modified">
            <div class="diff-line">
              <span class="diff-marker">~</span>
              <code class="field-name">{{ rel.field }}</code>
              <span class="relation-target">→ {{ rel.to?.target || rel.from?.target }}</span>
            </div>
            <div class="change-details">
              <div v-for="change in getRelationChangedProperties(rel.from, rel.to)" :key="change" class="change-item">
                {{ change }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="!hasChanges()" class="no-changes">
        <Icon name="check" :size="24" />
        <p>No changes detected</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.entity-diff {
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all var(--transition-base);
}

.entity-diff:hover {
  border-color: var(--color-primary-300);
  box-shadow: var(--shadow-sm);
}

.diff-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-4);
  cursor: pointer;
  user-select: none;
  transition: background var(--transition-base);
}

.diff-header:hover {
  background: var(--color-gray-50);
}

.diff-header-left {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
}

.expand-icon {
  color: var(--color-gray-400);
  transition: transform var(--transition-base);
}

.diff-header h4 {
  margin: 0;
  font-size: var(--text-lg);
  font-weight: 700;
  color: var(--color-gray-900);
}

.diff-summary {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-2);
}

.diff-badge {
  padding: 0.25rem 0.75rem;
  border-radius: var(--radius-md);
  font-size: var(--text-xs);
  font-weight: 600;
  white-space: nowrap;
}

.diff-badge.added {
  background: #ecfdf5;
  color: #047857;
  border: 1px solid #34d399;
}

.diff-badge.removed {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fca5a5;
}

.diff-badge.modified {
  background: #fffbeb;
  color: #d97706;
  border: 1px solid #fbbf24;
}

.diff-details {
  border-top: 1px solid var(--color-gray-200);
  padding: var(--spacing-4);
  background: var(--color-gray-50);
}

.diff-section {
  margin-bottom: var(--spacing-4);
}

.diff-section:last-child {
  margin-bottom: 0;
}

.section-title {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  margin: 0 0 var(--spacing-3) 0;
  font-size: var(--text-sm);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.section-title.added {
  color: #047857;
}

.section-title.removed {
  color: #dc2626;
}

.section-title.modified {
  color: #d97706;
}

.diff-items {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
}

.diff-item {
  padding: var(--spacing-3);
  border-radius: var(--radius-md);
  font-family: 'Monaco', 'Courier New', monospace;
  font-size: var(--text-sm);
}

.diff-item.added {
  background: #f0fdf4;
  border-left: 3px solid #10b981;
}

.diff-item.removed {
  background: #fef2f2;
  border-left: 3px solid #ef4444;
}

.diff-item.modified {
  background: #fffbeb;
  border-left: 3px solid #f59e0b;
}

.diff-line {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
}

.diff-marker {
  font-weight: 700;
  font-size: var(--text-base);
  width: 16px;
  flex-shrink: 0;
}

.diff-item.added .diff-marker {
  color: #10b981;
}

.diff-item.removed .diff-marker {
  color: #ef4444;
}

.diff-item.modified .diff-marker {
  color: #f59e0b;
}

.field-name {
  font-weight: 700;
  color: var(--color-gray-900);
  background: rgba(0, 0, 0, 0.05);
  padding: 0.125rem 0.375rem;
  border-radius: var(--radius-sm);
}

.field-spec {
  color: var(--color-gray-600);
  font-size: var(--text-xs);
}

.relation-target {
  color: var(--color-primary-600);
  font-weight: 600;
}

.change-details {
  margin-top: var(--spacing-2);
  margin-left: calc(16px + var(--spacing-2));
  padding-left: var(--spacing-3);
  border-left: 2px solid rgba(0, 0, 0, 0.1);
}

.change-item {
  font-size: var(--text-xs);
  color: var(--color-gray-700);
  padding: 0.125rem 0;
}

.no-changes {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--spacing-6);
  color: var(--color-gray-400);
  text-align: center;
}

.no-changes p {
  margin: var(--spacing-2) 0 0 0;
  font-size: var(--text-sm);
}
</style>
