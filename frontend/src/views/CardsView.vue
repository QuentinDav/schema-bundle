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
  <div class="cards-view">
    <div v-if="sortedEntities.length === 0" class="empty-state">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <rect x="3" y="3" width="7" height="7" rx="1" stroke-width="2" />
        <rect x="14" y="3" width="7" height="7" rx="1" stroke-width="2" />
        <rect x="3" y="14" width="7" height="7" rx="1" stroke-width="2" />
        <rect x="14" y="14" width="7" height="7" rx="1" stroke-width="2" />
      </svg>
      <h3>No tables found</h3>
      <p>Try adjusting your search query</p>
    </div>

    <div v-else class="cards-grid">
      <div
        v-for="entity in sortedEntities"
        :key="entity.fqcn"
        class="entity-card"
        :class="{ selected: schemaStore.selectedEntity?.fqcn === entity.fqcn }"
        @click="selectEntity(entity)"
      >
        <div class="card-header">
          <div class="card-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
              <path
                d="M3 6a3 3 0 013-3h12a3 3 0 013 3v12a3 3 0 01-3 3H6a3 3 0 01-3-3V6z"
                opacity="0.2"
              />
              <path
                d="M3 6a3 3 0 013-3h12a3 3 0 013 3v3H3V6zm0 5h18v7a3 3 0 01-3 3H6a3 3 0 01-3-3v-7z"
              />
            </svg>
          </div>
          <h3 class="card-title">{{ entity.name }}</h3>
        </div>

        <div class="card-meta">
          <div class="meta-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path
                d="M4 6h16M4 12h16M4 18h16"
                stroke-width="2"
                stroke-linecap="round"
              />
            </svg>
            <span>{{ entity.fields?.length || 0 }} fields</span>
          </div>
          <div class="meta-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path
                d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"
                stroke-width="2"
                stroke-linecap="round"
              />
              <path
                d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"
                stroke-width="2"
                stroke-linecap="round"
              />
            </svg>
            <span>{{ getRelationCount(entity) }} relations</span>
          </div>
        </div>

        <div class="card-divider"></div>

        <div class="card-fields">
          <div
            v-for="field in (entity.fields || []).slice(0, 6)"
            :key="field.name"
            class="field-item"
          >
            <span class="field-icon">{{ getFieldIcon(field) }}</span>
            <span class="field-name">{{ field.name }}</span>
            <span class="field-type">{{ field.type }}</span>
          </div>
          <div v-if="(entity.fields || []).length > 6" class="more-fields">
            +{{ entity.fields.length - 6 }} more fields
          </div>
        </div>

        <div class="card-footer">
          <span class="table-name" v-if="entity.table">
            Table: {{ entity.table }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cards-view {
  height: 100%;
  overflow-y: auto;
  background: #f9fafb;
  padding: 2rem;
}

.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
  max-width: 1800px;
  margin: 0 auto;
}

.entity-card {
  background: white;
  border: 2px solid #e5e7eb;
  border-radius: 16px;
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.entity-card:hover {
  border-color: #667eea;
  transform: translateY(-4px);
  box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
}

.entity-card.selected {
  border-color: #667eea;
  background: linear-gradient(to bottom, #f5f7ff, white);
  box-shadow: 0 8px 16px rgba(102, 126, 234, 0.2);
}

.card-header {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.card-icon {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  color: white;
  flex-shrink: 0;
}

.card-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
  line-height: 1.2;
}

.card-meta {
  display: flex;
  gap: 1.5rem;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #6b7280;
}

.meta-item svg {
  color: #9ca3af;
}

.card-divider {
  height: 1px;
  background: #e5e7eb;
}

.card-fields {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  flex: 1;
}

.field-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.875rem;
  padding: 0.5rem;
  border-radius: 8px;
  background: #f9fafb;
  transition: background 0.2s ease;
}

.field-item:hover {
  background: #f3f4f6;
}

.field-icon {
  font-size: 1rem;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
}

.field-name {
  font-weight: 600;
  color: #374151;
  flex: 1;
}

.field-type {
  color: #9ca3af;
  font-size: 0.75rem;
  font-family: 'Courier New', monospace;
  background: white;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
}

.more-fields {
  font-size: 0.875rem;
  color: #9ca3af;
  font-style: italic;
  text-align: center;
  padding: 0.5rem;
}

.card-footer {
  padding-top: 0.5rem;
  border-top: 1px solid #e5e7eb;
}

.table-name {
  font-size: 0.75rem;
  color: #9ca3af;
  font-family: 'Courier New', monospace;
}

.empty-state {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  color: #9ca3af;
}

.empty-state svg {
  margin: 0 auto 1rem;
  stroke: currentColor;
}

.empty-state h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #6b7280;
  margin: 0 0 0.5rem 0;
}

.empty-state p {
  font-size: 0.875rem;
  margin: 0;
}

@media (max-width: 768px) {
  .cards-view {
    padding: 1rem;
  }

  .cards-grid {
    grid-template-columns: 1fr;
  }
}
</style>
