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
  <div class="list-view">
    <div v-if="sortedEntities.length === 0" class="empty-state">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M3 4h18v2H3V4zm0 5h18v2H3V9zm0 5h18v2H3v-2zm0 5h18v2H3v-2z" stroke-width="2" />
      </svg>
      <h3>No tables found</h3>
      <p>Try adjusting your search query</p>
    </div>

    <div v-else class="table-container">
      <table class="entities-table">
        <thead>
          <tr>
            <th class="expand-col"></th>
            <th @click="sortBy('name')" class="sortable">
              <div class="th-content">
                <span>Table Name</span>
                <svg
                  v-if="sortKey === 'name'"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  :class="{ rotated: sortOrder === 'desc' }"
                >
                  <path d="M12 5l7 7H5l7-7z" fill="currentColor" />
                </svg>
              </div>
            </th>
            <th @click="sortBy('table')" class="sortable">
              <div class="th-content">
                <span>Database Table</span>
                <svg
                  v-if="sortKey === 'table'"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  :class="{ rotated: sortOrder === 'desc' }"
                >
                  <path d="M12 5l7 7H5l7-7z" fill="currentColor" />
                </svg>
              </div>
            </th>
            <th @click="sortBy('fields')" class="sortable number-col">
              <div class="th-content">
                <span>Fields</span>
                <svg
                  v-if="sortKey === 'fields'"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  :class="{ rotated: sortOrder === 'desc' }"
                >
                  <path d="M12 5l7 7H5l7-7z" fill="currentColor" />
                </svg>
              </div>
            </th>
            <th @click="sortBy('relations')" class="sortable number-col">
              <div class="th-content">
                <span>Relations</span>
                <svg
                  v-if="sortKey === 'relations'"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  :class="{ rotated: sortOrder === 'desc' }"
                >
                  <path d="M12 5l7 7H5l7-7z" fill="currentColor" />
                </svg>
              </div>
            </th>
            <th class="actions-col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="entity in sortedEntities" :key="entity.fqcn">
            <tr
              class="entity-row"
              :class="{
                expanded: expandedRows.has(entity.fqcn),
                selected: schemaStore.selectedEntity?.fqcn === entity.fqcn,
              }"
            >
              <td class="expand-col">
                <button @click="toggleRow(entity.fqcn)" class="expand-btn">
                  <svg
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    :class="{ rotated: expandedRows.has(entity.fqcn) }"
                  >
                    <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" fill="none" />
                  </svg>
                </button>
              </td>
              <td class="name-col">
                <div class="entity-name">
                  <div class="entity-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                      <rect
                        x="3"
                        y="3"
                        width="18"
                        height="18"
                        rx="2"
                        opacity="0.2"
                      />
                      <path d="M3 9h18M9 3v18" stroke="currentColor" stroke-width="2" fill="none" />
                    </svg>
                  </div>
                  <span>{{ entity.name }}</span>
                </div>
              </td>
              <td class="table-name-col">
                <code v-if="entity.table">{{ entity.table }}</code>
                <span v-else class="muted">-</span>
              </td>
              <td class="number-col">
                <span class="badge">{{ entity.fields?.length || 0 }}</span>
              </td>
              <td class="number-col">
                <span class="badge badge-primary">{{ getRelationCount(entity) }}</span>
              </td>
              <td class="actions-col">
                <button @click="selectEntity(entity)" class="action-btn">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="1" fill="currentColor" />
                    <path
                      d="M12 5c7 0 10 7 10 7s-3 7-10 7-10-7-10-7 3-7 10-7z"
                      stroke-width="2"
                    />
                  </svg>
                  View
                </button>
              </td>
            </tr>

            <tr v-if="expandedRows.has(entity.fqcn)" class="expanded-row">
              <td colspan="6">
                <div class="expanded-content">
                  <h4 v-if="(entity.fields || []).length > 0" class="section-title">Fields</h4>
                  <div class="fields-grid">
                    <div
                      v-for="field in entity.fields || []"
                      :key="field.name"
                      class="field-item"
                    >
                      <div class="field-header">
                        <span class="field-name">{{ field.name }}</span>
                        <span class="field-type">{{ field.type }}</span>
                      </div>
                      <div class="field-meta">
                        <span v-if="field.nullable" class="field-tag">nullable</span>
                        <span v-if="field.unique" class="field-tag">unique</span>
                        <span v-if="field.length" class="field-tag">length: {{ field.length }}</span>
                      </div>
                    </div>
                  </div>

                  <h4 v-if="(entity.relations || []).length > 0" class="section-title">Relations</h4>
                  <div v-if="(entity.relations || []).length > 0" class="relations-grid">
                    <div
                      v-for="relation in entity.relations"
                      :key="relation.field"
                      class="relation-item"
                    >
                      <div class="relation-header">
                        <span class="relation-field">{{ relation.field }}</span>
                        <span class="relation-type-badge" :data-type="relation.type">
                          {{ getRelationTypeName(relation.type) }}
                        </span>
                      </div>
                      <div class="relation-meta">
                        <span class="relation-target">→ {{ relation.target }}</span>
                        <span v-if="relation.isOwning" class="field-tag">owning side</span>
                        <span v-else class="field-tag">inverse side</span>
                        <span v-if="relation.mappedBy" class="field-tag">mappedBy: {{ relation.mappedBy }}</span>
                        <span v-if="relation.inversedBy" class="field-tag">inversedBy: {{ relation.inversedBy }}</span>
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

<style scoped>
.list-view {
  height: 100%;
  background: #f9fafb;
  overflow-y: auto;
}

.table-container {
  padding: 2rem;
  max-width: 1800px;
  margin: 0 auto;
}

.entities-table {
  width: 100%;
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border-collapse: separate;
  border-spacing: 0;
}

thead {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

thead th {
  padding: 1rem 1.5rem;
  text-align: left;
  font-weight: 600;
  font-size: 0.875rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.sortable {
  cursor: pointer;
  user-select: none;
  transition: background 0.2s ease;
}

.sortable:hover {
  background: rgba(255, 255, 255, 0.1);
}

.th-content {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.th-content svg {
  transition: transform 0.2s ease;
}

.th-content svg.rotated {
  transform: rotate(180deg);
}

.expand-col {
  width: 50px;
}

.number-col {
  width: 100px;
  text-align: center;
}

.actions-col {
  width: 120px;
  text-align: center;
}

tbody tr.entity-row {
  border-bottom: 1px solid #e5e7eb;
  transition: all 0.2s ease;
}

tbody tr.entity-row:hover {
  background: #f9fafb;
}

tbody tr.entity-row.selected {
  background: linear-gradient(to right, #f5f7ff, white);
  border-left: 3px solid #667eea;
}

tbody tr.entity-row.expanded {
  background: #fafbff;
}

tbody td {
  padding: 1rem 1.5rem;
  vertical-align: middle;
}

.expand-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
  transition: all 0.2s ease;
}

.expand-btn:hover {
  color: #667eea;
}

.expand-btn svg {
  transition: transform 0.2s ease;
}

.expand-btn svg.rotated {
  transform: rotate(90deg);
}

.entity-name {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-weight: 600;
  color: #111827;
}

.entity-icon {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 8px;
  color: white;
}

.table-name-col code {
  font-family: 'Courier New', monospace;
  font-size: 0.875rem;
  color: #6b7280;
  background: #f3f4f6;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
}

.muted {
  color: #d1d5db;
}

.badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.25rem 0.75rem;
  background: #f3f4f6;
  color: #6b7280;
  border-radius: 12px;
  font-size: 0.875rem;
  font-weight: 600;
}

.badge-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  transition: all 0.2s ease;
}

.action-btn:hover {
  background: #667eea;
  color: white;
  border-color: #667eea;
}

.expanded-row td {
  padding: 0;
  background: #fafbff;
}

.expanded-content {
  padding: 1.5rem;
  border-top: 2px solid #e5e7eb;
}

.fields-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
}

.field-item {
  padding: 0.75rem 1rem;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.field-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.field-name {
  font-weight: 600;
  color: #374151;
  font-size: 0.875rem;
}

.field-type {
  font-family: 'Courier New', monospace;
  font-size: 0.75rem;
  color: #9ca3af;
  background: #f9fafb;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
}

.field-meta {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.field-tag {
  font-size: 0.75rem;
  padding: 0.125rem 0.5rem;
  background: #f3f4f6;
  color: #6b7280;
  border-radius: 4px;
  font-weight: 500;
}

.field-tag.relation {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
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

.section-title {
  font-size: 1rem;
  font-weight: 700;
  color: #374151;
  margin: 0 0 1rem 0;
  padding-top: 1rem;
}

.section-title:first-child {
  padding-top: 0;
}

.relations-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}

.relation-item {
  padding: 1rem;
  background: white;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
}

.relation-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.relation-field {
  font-weight: 700;
  color: #111827;
  font-size: 1rem;
}

.relation-type-badge {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.375rem 0.75rem;
  border-radius: 6px;
  color: white;
}

.relation-type-badge[data-type='1'] {
  background: #10b981;
}

.relation-type-badge[data-type='2'] {
  background: #3b82f6;
}

.relation-type-badge[data-type='3'] {
  background: #f59e0b;
}

.relation-type-badge[data-type='4'] {
  background: #ef4444;
}

.relation-meta {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  align-items: center;
}

.relation-target {
  font-weight: 600;
  color: #667eea;
  font-size: 0.875rem;
}
</style>
