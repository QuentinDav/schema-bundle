<template>
  <div class="flex flex-col h-full bg-[var(--color-surface)]">
    <!-- Header with actions -->
    <div class="p-4 border-b border-[var(--color-border)]">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <BeakerIcon class="w-5 h-5 text-purple-600 dark:text-purple-400" />
          <h2 class="font-semibold text-[var(--color-text-primary)]">Playground</h2>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="exportDoctrine"
            :disabled="virtualEntities.length === 0"
            class="p-2 text-gray-600 dark:text-gray-400 hover:bg-[var(--color-surface-raised)] disabled:opacity-30 disabled:cursor-not-allowed rounded-lg transition-colors"
            title="Export as Doctrine YAML"
          >
            <Icon name="arrow-down-tray" :size="18" />
          </button>
          <button
            @click="clearAllVirtual"
            :disabled="virtualEntities.length === 0"
            class="p-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-30 disabled:cursor-not-allowed rounded-lg transition-colors"
            title="Clear all virtual entities"
          >
            <Icon name="trash" :size="18" />
          </button>
        </div>
      </div>

      <p class="text-xs text-[var(--color-text-tertiary)]">
        Right-click on canvas to create or edit entities
      </p>
    </div>

    <!-- Tabs -->
    <div class="flex border-b border-[var(--color-border)]">
      <button
        @click="activeTab = 'history'"
        :class="[
          'flex-1 px-4 py-3 text-sm font-medium transition-colors relative',
          activeTab === 'history'
            ? 'text-[var(--color-primary)] bg-[var(--color-surface-raised)]'
            : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:bg-[var(--color-surface-raised)]'
        ]"
      >
        <div class="flex items-center justify-center gap-2">
          <Icon name="clock" :size="16" />
          <span>History</span>
          <span v-if="viewsStore.playgroundHistory.length > 0" class="px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-xs rounded-full">
            {{ viewsStore.playgroundHistory.length }}
          </span>
        </div>
        <div v-if="activeTab === 'history'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-[var(--color-primary)]"></div>
      </button>

      <button
        @click="activeTab = 'entities'"
        :class="[
          'flex-1 px-4 py-3 text-sm font-medium transition-colors relative',
          activeTab === 'entities'
            ? 'text-[var(--color-primary)] bg-[var(--color-surface-raised)]'
            : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:bg-[var(--color-surface-raised)]'
        ]"
      >
        <div class="flex items-center justify-center gap-2">
          <Icon name="table-cells" :size="16" />
          <span>Entities</span>
          <span v-if="selectedRealEntitiesCount > 0" class="px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-xs rounded-full">
            {{ selectedRealEntitiesCount }}
          </span>
        </div>
        <div v-if="activeTab === 'entities'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-[var(--color-primary)]"></div>
      </button>
    </div>

    <!-- History Tab -->
    <div v-if="activeTab === 'history'" class="flex-1 flex flex-col overflow-hidden">
      <div v-if="viewsStore.playgroundHistory.length === 0" class="flex-1 flex items-center justify-center p-8">
        <div class="text-center">
          <Icon name="clock" :size="64" class="mx-auto mb-4 text-gray-300 dark:text-gray-600" />
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-2">No History Yet</h3>
          <p class="text-xs text-[var(--color-text-tertiary)]">Your actions will appear here</p>
        </div>
      </div>

      <div v-else class="flex-1 overflow-y-auto p-4">
        <div class="space-y-2">
          <div
            v-for="entry in viewsStore.playgroundHistory.slice().reverse()"
            :key="entry.id"
            class="group p-3 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg hover:border-[var(--color-primary)]/40 hover:shadow-sm transition-all"
          >
            <div class="flex items-start gap-3">
              <div :class="[
                'w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0',
                entry.action === 'add_entity' ? 'bg-purple-100 dark:bg-purple-900/30' :
                entry.action === 'update_entity' ? 'bg-blue-100 dark:bg-blue-900/30' :
                'bg-red-100 dark:bg-red-900/30'
              ]">
                <Icon
                  v-if="entry.action === 'add_entity'"
                  name="plus-circle"
                  :size="16"
                  class="text-purple-600 dark:text-purple-400"
                />
                <Icon
                  v-else-if="entry.action === 'update_entity'"
                  name="pencil-square"
                  :size="16"
                  class="text-blue-600 dark:text-blue-400"
                />
                <Icon
                  v-else
                  name="trash"
                  :size="16"
                  class="text-red-600 dark:text-red-400"
                />
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2 mb-1">
                  <p class="text-sm font-medium text-[var(--color-text-primary)]">
                    {{ getActionLabel(entry.action) }}
                  </p>
                  <button
                    @click="restore(entry.id)"
                    class="opacity-0 group-hover:opacity-100 px-2 py-1 text-xs bg-[var(--color-primary)] text-white rounded hover:bg-[var(--color-primary-hover)] transition-all flex items-center gap-1 flex-shrink-0"
                    title="Restore to this point"
                  >
                    <Icon name="arrow-path" :size="12" />
                    Restore
                  </button>
                </div>

                <p class="text-xs text-[var(--color-text-tertiary)] mb-2">
                  {{ formatTimestamp(entry.timestamp) }}
                </p>

                <div v-if="entry.data" class="text-xs text-[var(--color-text-secondary)] bg-[var(--color-surface)] px-2 py-1.5 rounded border border-[var(--color-border)]">
                  <template v-if="entry.action === 'add_entity'">
                    <span class="font-mono">{{ entry.data.entity?.name }}</span>
                  </template>
                  <template v-else-if="entry.action === 'update_entity'">
                    <span class="font-mono">{{ entry.data.oldEntity?.name }}</span>
                    <span class="text-[var(--color-text-tertiary)] mx-1">→</span>
                    <span class="font-mono">{{ entry.data.newEntity?.name }}</span>
                  </template>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="viewsStore.playgroundHistory.length > 0" class="p-4 border-t border-[var(--color-border)] bg-[var(--color-surface-raised)]">
        <button
          @click="clearHistoryConfirm"
          class="w-full px-3 py-2 text-sm text-left text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors flex items-center gap-2"
        >
          <Icon name="trash" :size="16" />
          <span>Clear History</span>
        </button>
      </div>
    </div>

    <!-- Entities Tab -->
    <div v-else-if="activeTab === 'entities'" class="flex-1 flex flex-col overflow-hidden">
      <!-- Search bar -->
      <div class="p-4 border-b border-[var(--color-border)]">
        <div class="relative">
          <Icon name="magnifying-glass" :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search entities..."
            class="w-full pl-10 pr-3 py-2 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg text-[var(--color-text-primary)] placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent text-sm"
          />
        </div>
      </div>

      <!-- Entities list (Real entities only - Virtual entities managed via canvas) -->
      <div class="flex-1 overflow-y-auto">
        <div v-if="filteredRealEntities.length === 0" class="flex items-center justify-center h-full p-8">
          <div class="text-center">
            <Icon name="magnifying-glass" :size="48" class="mx-auto mb-3 text-gray-300 dark:text-gray-600" />
            <p class="text-sm text-[var(--color-text-secondary)] mb-1">No entities found</p>
            <p class="text-xs text-[var(--color-text-tertiary)]">Try a different search term</p>
          </div>
        </div>

        <div v-else class="p-4 space-y-1">
          <div
            v-for="entity in filteredRealEntities"
            :key="entity.fqcn || entity.name"
            :class="[
              'p-2.5 rounded-lg border transition-all cursor-pointer',
              isEntityOnCanvas(entity)
                ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-300 dark:border-blue-700 hover:border-blue-400 dark:hover:border-blue-600'
                : 'bg-[var(--color-surface-raised)] border-transparent hover:border-[var(--color-border)] hover:bg-[var(--color-surface)]'
            ]"
            @click="toggleEntity(entity)"
          >
            <div class="flex items-center gap-2">
              <div :class="[
                'w-4 h-4 rounded border-2 flex items-center justify-center flex-shrink-0 transition-all',
                isEntityOnCanvas(entity)
                  ? 'bg-blue-600 border-blue-600'
                  : 'border-gray-300 dark:border-gray-600'
              ]">
                <Icon v-if="isEntityOnCanvas(entity)" name="check" :size="12" class="text-white" />
              </div>

              <span :class="[
                'text-sm font-medium truncate flex-1',
                isEntityOnCanvas(entity)
                  ? 'text-gray-900 dark:text-gray-100'
                  : 'text-gray-600 dark:text-gray-400'
              ]">
                {{ entity.name }}
              </span>

              <span v-if="isEntityOnCanvas(entity)" class="px-1.5 py-0.5 text-xs rounded font-medium text-blue-600 dark:text-blue-400">
                On Canvas
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Entity Modal (called from context menu) -->
    <Teleport to="body">
      <TransitionRoot appear :show="showAddEntityModal" as="template">
        <Dialog as="div" @close="showAddEntityModal = false" class="relative z-50">
          <TransitionChild
            as="template"
            enter="duration-300 ease-out"
            enter-from="opacity-0"
            enter-to="opacity-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100"
            leave-to="opacity-0"
          >
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" />
          </TransitionChild>

          <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
              <TransitionChild
                as="template"
                enter="duration-300 ease-out"
                enter-from="opacity-0 scale-95"
                enter-to="opacity-100 scale-100"
                leave="duration-200 ease-in"
                leave-from="opacity-100 scale-100"
                leave-to="opacity-0 scale-95"
              >
                <DialogPanel class="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border)] p-6 shadow-2xl transition-all">
                  <DialogTitle class="text-lg font-semibold text-[var(--color-text-primary)] mb-4 flex items-center gap-2">
                    <BeakerIcon class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                    Create Virtual Entity
                  </DialogTitle>

                  <div class="space-y-4">
                    <div>
                      <label class="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                        Entity Name
                      </label>
                      <input
                        v-model="newEntity.name"
                        type="text"
                        placeholder="e.g., User, Product, Order"
                        class="w-full px-3 py-2 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg text-[var(--color-text-primary)] placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                      />
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                        Table Name (optional)
                      </label>
                      <input
                        v-model="newEntity.table"
                        type="text"
                        placeholder="e.g., users, products"
                        class="w-full px-3 py-2 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg text-[var(--color-text-primary)] placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                      />
                    </div>

                    <div>
                      <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-[var(--color-text-primary)]">
                          Fields
                        </label>
                        <button
                          @click="addField"
                          class="px-2 py-1 text-xs bg-purple-600 hover:bg-purple-700 text-white rounded flex items-center gap-1"
                        >
                          <Icon name="plus" :size="12" />
                          Add Field
                        </button>
                      </div>

                      <div class="space-y-2 max-h-60 overflow-y-auto">
                        <div
                          v-for="(field, index) in newEntity.fields"
                          :key="index"
                          class="flex gap-2 items-center p-2 bg-[var(--color-surface-raised)] rounded-lg border border-[var(--color-border)]"
                        >
                          <input
                            v-model="field.name"
                            type="text"
                            placeholder="Field name"
                            class="flex-1 px-2 py-1 text-sm bg-[var(--color-surface)] border border-[var(--color-border)] rounded text-[var(--color-text-primary)] focus:outline-none focus:ring-1 focus:ring-purple-500"
                          />
                          <input
                            v-model="field.type"
                            type="text"
                            placeholder="Type"
                            class="flex-1 px-2 py-1 text-sm bg-[var(--color-surface)] border border-[var(--color-border)] rounded text-[var(--color-text-primary)] focus:outline-none focus:ring-1 focus:ring-purple-500"
                          />
                          <button
                            @click="removeField(index)"
                            class="p-1 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"
                          >
                            <Icon name="trash" :size="14" />
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="mt-6 flex gap-3 justify-end">
                    <button
                      @click="showAddEntityModal = false"
                      class="px-4 py-2 text-sm font-medium text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] transition-colors"
                    >
                      Cancel
                    </button>
                    <button
                      @click="createEntity"
                      :disabled="!newEntity.name"
                      class="px-4 py-2 text-sm font-medium bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg transition-all"
                    >
                      Create Entity
                    </button>
                  </div>
                </DialogPanel>
              </TransitionChild>
            </div>
          </div>
        </Dialog>
      </TransitionRoot>
    </Teleport>

    <!-- Edit Entity Modal (called from context menu) -->
    <Teleport to="body">
      <TransitionRoot appear :show="showEditEntityModal" as="template">
        <Dialog as="div" @close="showEditEntityModal = false" class="relative z-50">
          <TransitionChild
            as="template"
            enter="duration-300 ease-out"
            enter-from="opacity-0"
            enter-to="opacity-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100"
            leave-to="opacity-0"
          >
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" />
          </TransitionChild>

          <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
              <TransitionChild
                as="template"
                enter="duration-300 ease-out"
                enter-from="opacity-0 scale-95"
                enter-to="opacity-100 scale-100"
                leave="duration-200 ease-in"
                leave-from="opacity-100 scale-100"
                leave-to="opacity-0 scale-95"
              >
                <DialogPanel class="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border)] p-6 shadow-2xl transition-all">
                  <DialogTitle class="text-lg font-semibold text-[var(--color-text-primary)] mb-4 flex items-center gap-2">
                    <Icon name="pencil-square" :size="24" class="text-blue-600 dark:text-blue-400" />
                    Edit Virtual Entity
                  </DialogTitle>

                  <div class="space-y-4">
                    <div>
                      <label class="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                        Entity Name
                      </label>
                      <input
                        v-model="editingEntity.name"
                        type="text"
                        placeholder="e.g., User, Product, Order"
                        class="w-full px-3 py-2 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg text-[var(--color-text-primary)] placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      />
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                        Table Name (optional)
                      </label>
                      <input
                        v-model="editingEntity.table"
                        type="text"
                        placeholder="e.g., users, products"
                        class="w-full px-3 py-2 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg text-[var(--color-text-primary)] placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      />
                    </div>

                    <div>
                      <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-[var(--color-text-primary)]">
                          Fields
                        </label>
                        <button
                          @click="addFieldToEdit"
                          class="px-2 py-1 text-xs bg-blue-600 hover:bg-blue-700 text-white rounded flex items-center gap-1"
                        >
                          <Icon name="plus" :size="12" />
                          Add Field
                        </button>
                      </div>

                      <div class="space-y-2 max-h-60 overflow-y-auto">
                        <div
                          v-for="(field, index) in editingEntity.fields"
                          :key="index"
                          class="flex gap-2 items-center p-2 bg-[var(--color-surface-raised)] rounded-lg border border-[var(--color-border)]"
                        >
                          <input
                            v-model="field.name"
                            type="text"
                            placeholder="Field name"
                            class="flex-1 px-2 py-1 text-sm bg-[var(--color-surface)] border border-[var(--color-border)] rounded text-[var(--color-text-primary)] focus:outline-none focus:ring-1 focus:ring-blue-500"
                          />
                          <input
                            v-model="field.type"
                            type="text"
                            placeholder="Type"
                            class="flex-1 px-2 py-1 text-sm bg-[var(--color-surface)] border border-[var(--color-border)] rounded text-[var(--color-text-primary)] focus:outline-none focus:ring-1 focus:ring-blue-500"
                          />
                          <button
                            @click="removeFieldFromEdit(index)"
                            class="p-1 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"
                          >
                            <Icon name="trash" :size="14" />
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="mt-6 flex gap-3 justify-end">
                    <button
                      @click="showEditEntityModal = false"
                      class="px-4 py-2 text-sm font-medium text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] transition-colors"
                    >
                      Cancel
                    </button>
                    <button
                      @click="saveEdit"
                      :disabled="!editingEntity.name"
                      class="px-4 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg transition-all"
                    >
                      Save Changes
                    </button>
                  </div>
                </DialogPanel>
              </TransitionChild>
            </div>
          </div>
        </Dialog>
      </TransitionRoot>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useViewsStore } from '@/stores/views'
import { useSchemaStore } from '@/stores/schema'
import { Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild } from '@headlessui/vue'
import { BeakerIcon } from '@heroicons/vue/24/outline'
import Icon from './Icon.vue'

const viewsStore = useViewsStore()
const schemaStore = useSchemaStore()

const activeTab = ref('history')
const searchQuery = ref('')
const showAddEntityModal = ref(false)
const showEditEntityModal = ref(false)

const newEntity = ref({
  name: '',
  table: '',
  fields: []
})

const editingEntity = ref({
  fqcn: '',
  name: '',
  table: '',
  fields: []
})

const virtualEntities = computed(() => {
  const view = viewsStore.currentView
  if (!view || view.type !== 'playground' || !view.virtualChanges) return []
  return view.virtualChanges.addedEntities || []
})

const allRealEntities = computed(() => {
  return schemaStore.entities.filter(e => !e.isVirtual)
})

const filteredRealEntities = computed(() => {
  if (!searchQuery.value) return allRealEntities.value
  const query = searchQuery.value.toLowerCase()
  return allRealEntities.value.filter(e =>
    e.name.toLowerCase().includes(query) ||
    (e.table && e.table.toLowerCase().includes(query))
  )
})

const selectedRealEntitiesCount = computed(() => {
  return allRealEntities.value.filter(e => isEntityOnCanvas(e)).length
})

function isEntityOnCanvas(entity) {
  const fqcn = entity.fqcn || entity.name
  return schemaStore.selectedEntities.has(fqcn)
}

function toggleEntity(entity) {
  const fqcn = entity.fqcn || entity.name
  if (isEntityOnCanvas(entity)) {
    schemaStore.removeEntityFromSelection(fqcn)
  } else {
    schemaStore.addEntityToSelection(fqcn)
  }
}

function addField() {
  newEntity.value.fields.push({ name: '', type: 'string' })
}

function removeField(index) {
  newEntity.value.fields.splice(index, 1)
}

function createEntity() {
  if (!newEntity.value.name) return

  const entity = {
    name: newEntity.value.name,
    fqcn: `Virtual\\${newEntity.value.name}`,
    table: newEntity.value.table || newEntity.value.name.toLowerCase(),
    fields: newEntity.value.fields.filter(f => f.name),
    relations: [],
    isVirtual: true
  }

  viewsStore.addVirtualEntity(entity)
  schemaStore.addEntityToSelection(entity.fqcn)

  newEntity.value = { name: '', table: '', fields: [] }
  showAddEntityModal.value = false
}

function editEntity(entity) {
  editingEntity.value = {
    fqcn: entity.fqcn,
    name: entity.name,
    table: entity.table,
    fields: [...(entity.fields || [])]
  }
  showEditEntityModal.value = true
}

function addFieldToEdit() {
  editingEntity.value.fields.push({ name: '', type: 'string' })
}

function removeFieldFromEdit(index) {
  editingEntity.value.fields.splice(index, 1)
}

function saveEdit() {
  if (!editingEntity.value.name) return

  const updatedEntity = {
    name: editingEntity.value.name,
    fqcn: editingEntity.value.fqcn,
    table: editingEntity.value.table || editingEntity.value.name.toLowerCase(),
    fields: editingEntity.value.fields.filter(f => f.name),
    relations: [],
    isVirtual: true
  }

  viewsStore.updateVirtualEntity(editingEntity.value.fqcn, updatedEntity)
  showEditEntityModal.value = false
}

function exportDoctrine() {
  console.log('🚀 Export Doctrine YAML:', viewsStore.currentView?.virtualChanges?.addedEntities)
}

function clearAllVirtual() {
  if (confirm('Clear all virtual entities? This cannot be undone.')) {
    viewsStore.clearVirtualChanges()
  }
}

function getActionLabel(action) {
  const labels = {
    'add_entity': 'Entity Created',
    'update_entity': 'Entity Updated',
    'remove_entity': 'Entity Removed',
    'add_relation': 'Relation Added',
    'remove_relation': 'Relation Removed'
  }
  return labels[action] || action
}

function formatTimestamp(timestamp) {
  const date = new Date(timestamp)
  const now = new Date()
  const diff = now - date

  if (diff < 60000) return 'Just now'
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`
  return date.toLocaleDateString()
}

function restore(historyId) {
  if (confirm('Restore to this point? This will undo all changes after this action.')) {
    viewsStore.restoreFromHistory(historyId)
  }
}

function clearHistoryConfirm() {
  if (confirm('Clear all history? This cannot be undone.')) {
    viewsStore.clearHistory()
  }
}

function openAddEntityModal() {
  showAddEntityModal.value = true
}

function openEditEntityModal(entity) {
  editEntity(entity)
}

defineExpose({
  openAddEntityModal,
  openEditEntityModal
})
</script>
