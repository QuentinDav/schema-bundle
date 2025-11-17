<template>
  <div>
    <!-- Backdrop -->
    <Transition
      enter-active-class="transition-opacity duration-300"
      leave-active-class="transition-opacity duration-300"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        @click="isOpen = false"
        class="fixed inset-0 bg-black/30 dark:bg-black/50 z-40"
      ></div>
    </Transition>

    <!-- Sidebar -->
    <Transition
      enter-active-class="transition-transform duration-300 ease-out"
      enter-from-class="translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transition-transform duration-300 ease-in"
      leave-from-class="translate-x-0"
      leave-to-class="translate-x-full"
    >
      <div
        v-if="isOpen"
        class="fixed right-0 top-0 bottom-0 w-96 bg-white dark:bg-gray-800 shadow-2xl z-50 flex flex-col"
      >
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 dark:from-blue-600 dark:to-blue-700 px-4 py-3 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <h3 class="text-white font-semibold text-lg">Views</h3>
          </div>
          <button
            @click="isOpen = false"
            class="p-1.5 hover:bg-white/20 rounded transition-colors text-white"
            title="Close"
          >
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
          <!-- Search & Actions -->
          <div class="p-4 space-y-3 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search views..."
                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-600 focus:border-transparent text-sm"
              />
              <button
                @click="showCreateModal = true"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2"
              >
                <PlusIcon class="w-5 h-5" />
                <span>New</span>
              </button>
            </div>

            <button
              @click="showImportModal = true"
              class="w-full text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors flex items-center justify-center gap-1"
            >
              <ArrowUpTrayIcon class="w-3 h-3" />
              <span>Import view from JSON</span>
            </button>
          </div>

        <div class="max-h-96 overflow-y-auto">
          <div v-if="savedViewsList.length === 0" class="p-6 text-center text-gray-500 dark:text-gray-400">
            <EyeIcon class="w-12 h-12 mx-auto mb-2 text-gray-400 dark:text-gray-600" />
            <p class="text-sm">No saved views yet</p>
            <p class="text-xs mt-1">Create your first custom view</p>
          </div>

          <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
            <div
              v-for="view in savedViewsList"
              :key="view.id"
              class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
              :class="{ 'bg-blue-50 dark:bg-blue-900/20': view.id === viewsStore.currentViewId }"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1 cursor-pointer" @click="loadView(view.id)">
                  <div class="flex items-center gap-2">
                    <EyeIcon v-if="view.type === 'filter'" class="w-4 h-4 text-blue-600 dark:text-blue-400 flex-shrink-0" />
                    <BeakerIcon v-else class="w-4 h-4 text-purple-600 dark:text-purple-400 flex-shrink-0" />
                    <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ view.name }}</h4>
                    <span v-if="view.type === 'playground'" class="px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-[10px] font-bold rounded uppercase">Lab</span>
                    <span v-if="view.id === viewsStore.currentViewId" class="px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-xs rounded-full">
                      Active
                    </span>
                  </div>
                  <p v-if="view.description" class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                    {{ view.description }}
                  </p>
                  <div class="flex flex-wrap items-center gap-2 mt-2">
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(view.updatedAt) }}</span>

                    <span v-if="view.filter.boundedContexts.length > 0" class="px-2 py-0.5 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-xs rounded-full flex items-center gap-1">
                      <FunnelIcon class="w-3 h-3" />
                      {{ view.filter.boundedContexts.length }} filters
                    </span>

                    <span v-if="view.layout.nodes.size > 0" class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-xs rounded-full">
                      {{ view.layout.nodes.size }} positions
                    </span>

                    <span v-if="view.filter.excludeEntities.size > 0" class="px-2 py-0.5 bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 text-xs rounded-full">
                      {{ view.filter.excludeEntities.size }} hidden
                    </span>
                  </div>
                </div>

                <div class="flex items-center gap-1 ml-2">
                  <button
                    v-if="view.id === viewsStore.currentViewId && viewsStore.hasUnsavedChanges"
                    @click.stop="updateView(view.id)"
                    class="p-1.5 text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/30 rounded transition-colors"
                    title="Save changes"
                  >
                    <ArrowDownTrayIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click.stop="duplicateView(view.id)"
                    class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded transition-colors"
                    title="Duplicate"
                  >
                    <DocumentDuplicateIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click.stop="exportView(view.id)"
                    class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded transition-colors"
                    title="Export"
                  >
                    <ArrowDownTrayIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click.stop="confirmDelete(view.id)"
                    class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition-colors"
                    title="Delete"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        </div>
      </div>
    </Transition>
  </div>

  <!-- Create View Modal -->
  <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        leave-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showCreateModal"
          class="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-[9999]"
          @click.self="showCreateModal = false"
        >
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl w-full max-w-md p-6">
            <div class="flex items-center gap-2 mb-4">
              <EyeIcon class="w-6 h-6 text-blue-600 dark:text-blue-400" />
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Create New View
              </h3>
            </div>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">View Type</label>
                <div class="grid grid-cols-2 gap-3">
                  <button
                    @click="showCreateType = 'filter'"
                    class="p-3 rounded-lg border-2 transition-all"
                    :class="showCreateType === 'filter'
                      ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                      : 'border-gray-300 dark:border-gray-600 hover:border-blue-400 dark:hover:border-blue-500'"
                  >
                    <EyeIcon class="w-6 h-6 mx-auto mb-1" :class="showCreateType === 'filter' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400'" />
                    <div class="text-sm font-medium" :class="showCreateType === 'filter' ? 'text-blue-700 dark:text-blue-300' : 'text-gray-700 dark:text-gray-300'">Filter View</div>
                  </button>
                  <button
                    @click="showCreateType = 'playground'"
                    class="p-3 rounded-lg border-2 transition-all"
                    :class="showCreateType === 'playground'
                      ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20'
                      : 'border-gray-300 dark:border-gray-600 hover:border-purple-400 dark:hover:border-purple-500'"
                  >
                    <BeakerIcon class="w-6 h-6 mx-auto mb-1" :class="showCreateType === 'playground' ? 'text-purple-600 dark:text-purple-400' : 'text-gray-400'" />
                    <div class="text-sm font-medium" :class="showCreateType === 'playground' ? 'text-purple-700 dark:text-purple-300' : 'text-gray-700 dark:text-gray-300'">Playground</div>
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">View Name</label>
                <input
                  v-model="newViewName"
                  type="text"
                  placeholder="e.g., User Management Schema"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-600 focus:border-transparent"
                  @keydown.enter="createView"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description (optional)</label>
                <textarea
                  v-model="newViewDescription"
                  placeholder="Describe what this view shows..."
                  rows="3"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-600 focus:border-transparent resize-none"
                ></textarea>
              </div>
            </div>

            <div class="flex items-center gap-3 mt-6">
              <button
                @click="createView"
                :disabled="!newViewName.trim()"
                class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 dark:disabled:bg-gray-600 text-white rounded-lg font-medium transition-colors disabled:cursor-not-allowed"
              >
                Create View
              </button>
              <button
                @click="showCreateModal = false"
                class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium transition-colors"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Import View Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        leave-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showImportModal"
          class="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-[9999]"
          @click.self="showImportModal = false"
        >
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl w-full max-w-md p-6">
            <div class="flex items-center gap-2 mb-4">
              <ArrowUpTrayIcon class="w-6 h-6 text-blue-600 dark:text-blue-400" />
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Import View</h3>
            </div>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paste View JSON</label>
                <textarea
                  v-model="importJson"
                  placeholder='{"name": "My View", ...}'
                  rows="10"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-600 focus:border-transparent resize-none font-mono text-xs"
                ></textarea>
              </div>

              <p v-if="importError" class="text-sm text-red-600 dark:text-red-400">
                {{ importError }}
              </p>
            </div>

            <div class="flex items-center gap-3 mt-6">
              <button
                @click="importView"
                :disabled="!importJson.trim()"
                class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 dark:disabled:bg-gray-600 text-white rounded-lg font-medium transition-colors disabled:cursor-not-allowed"
              >
                Import
              </button>
              <button
                @click="showImportModal = false"
                class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium transition-colors"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Add Virtual Entity Modal -->
    <TransitionRoot :show="showAddEntityModal" as="template">
      <Dialog @close="showAddEntityModal = false" class="relative z-[9999]">
        <TransitionChild
          as="template"
          enter="ease-out duration-300"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="ease-in duration-200"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <div class="fixed inset-0 bg-black/50 dark:bg-black/70" />
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-center justify-center p-4">
            <TransitionChild
              as="template"
              enter="ease-out duration-300"
              enter-from="opacity-0 scale-95"
              enter-to="opacity-100 scale-100"
              leave="ease-in duration-200"
              leave-from="opacity-100 scale-100"
              leave-to="opacity-0 scale-95"
            >
              <DialogPanel class="w-full max-w-md transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl transition-all">
                <DialogTitle class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                  <PlusCircleIcon class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                  <span>Add Virtual Entity</span>
                </DialogTitle>

                <div class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Entity Name</label>
                    <input
                      v-model="newEntityName"
                      type="text"
                      placeholder="e.g., User, Product, Order"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-purple-500 dark:focus:ring-purple-600 focus:border-transparent"
                      @keydown.enter="createVirtualEntity"
                    />
                  </div>

                  <div>
                    <div class="flex items-center justify-between mb-2">
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fields</label>
                      <button
                        @click="addEntityField"
                        class="text-xs px-2 py-1 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded hover:bg-purple-200 dark:hover:bg-purple-900/50 transition-colors"
                      >
                        + Add Field
                      </button>
                    </div>

                    <div class="space-y-2 max-h-60 overflow-y-auto">
                      <div
                        v-for="(field, index) in newEntityFields"
                        :key="index"
                        class="flex items-center gap-2"
                      >
                        <input
                          v-model="field.name"
                          type="text"
                          placeholder="Field name"
                          class="flex-1 px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-1 focus:ring-purple-500"
                        />
                        <select
                          v-model="field.type"
                          class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-purple-500"
                        >
                          <option value="string">String</option>
                          <option value="integer">Integer</option>
                          <option value="boolean">Boolean</option>
                          <option value="datetime">DateTime</option>
                          <option value="text">Text</option>
                        </select>
                        <button
                          v-if="newEntityFields.length > 1"
                          @click="removeEntityField(index)"
                          class="p-1 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition-colors"
                        >
                          <XMarkIcon class="w-4 h-4" />
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="flex items-center gap-3 mt-6">
                  <button
                    @click="createVirtualEntity"
                    :disabled="!newEntityName.trim()"
                    class="flex-1 px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-300 dark:disabled:bg-gray-600 text-white rounded-lg font-medium transition-colors disabled:cursor-not-allowed"
                  >
                    Create Entity
                  </button>
                  <button
                    @click="showAddEntityModal = false"
                    class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium transition-colors"
                  >
                    Cancel
                  </button>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useViewsStore } from '@/stores/views'
import { useSchemaStore } from '@/stores/schema'
import {
  XMarkIcon,
  InformationCircleIcon,
  PlusIcon,
  DocumentDuplicateIcon,
  ArrowDownTrayIcon,
  TrashIcon,
  ComputerDesktopIcon,
  LinkIcon,
  PlusCircleIcon,
  EyeIcon,
  BeakerIcon,
  ArrowUpTrayIcon,
  FunnelIcon
} from '@heroicons/vue/24/outline'
import { Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild } from '@headlessui/vue'

const viewsStore = useViewsStore()
const schemaStore = useSchemaStore()

const isOpen = ref(false)
const searchQuery = ref('')
const showCreateModal = ref(false)
const showCreateType = ref('filter')
const showImportModal = ref(false)
const showAddEntityModal = ref(false)
const showAddRelationModal = ref(false)
const newViewName = ref('')
const newViewDescription = ref('')
const importJson = ref('')
const importError = ref('')

const newEntityName = ref('')
const newEntityFields = ref([{ name: '', type: 'string' }])

const savedViewsList = computed(() => {
  let views = Array.from(viewsStore.savedViews.values())

  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    views = views.filter(view =>
      view.name.toLowerCase().includes(query) ||
      view.description?.toLowerCase().includes(query)
    )
  }

  return views.sort((a, b) => new Date(b.updatedAt) - new Date(a.updatedAt))
})

function createView() {
  if (!newViewName.value.trim()) return

  viewsStore.saveView(newViewName.value.trim(), newViewDescription.value.trim(), showCreateType.value)

  newViewName.value = ''
  newViewDescription.value = ''
  showCreateModal.value = false
}

function loadView(viewId) {
  // The store's loadView already handles restoring selected entities
  viewsStore.loadView(viewId)
}

function updateView(viewId) {
  viewsStore.updateView(viewId)
}

function duplicateView(viewId) {
  const view = viewsStore.savedViews.get(viewId)
  if (!view) return

  const newName = prompt('Enter name for duplicated view:', `${view.name} (Copy)`)
  if (newName) {
    viewsStore.duplicateView(viewId, newName)
  }
}

function exportView(viewId) {
  const json = viewsStore.exportView(viewId)
  if (!json) return

  // Copy to clipboard
  navigator.clipboard.writeText(json).then(() => {
    alert('View exported to clipboard!')
  }).catch(() => {
    // Fallback: show in modal
    prompt('Copy this JSON:', json)
  })
}

function importView() {
  importError.value = ''

  const viewId = viewsStore.importView(importJson.value)
  if (viewId) {
    importJson.value = ''
    showImportModal.value = false
    viewsStore.loadView(viewId)
  } else {
    importError.value = 'Invalid JSON format'
  }
}

function confirmDelete(viewId) {
  const view = viewsStore.savedViews.get(viewId)
  if (!view) return

  if (confirm(`Delete view "${view.name}"?`)) {
    viewsStore.deleteView(viewId)
  }
}

function formatDate(dateString) {
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now - date
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  if (diffDays < 7) return `${diffDays}d ago`

  return date.toLocaleDateString()
}

function addEntityField() {
  newEntityFields.value.push({ name: '', type: 'string' })
}

function removeEntityField(index) {
  newEntityFields.value.splice(index, 1)
}

function createVirtualEntity() {
  if (!newEntityName.value.trim()) return

  const entity = {
    fqcn: `Virtual\\${newEntityName.value.trim()}`,
    name: newEntityName.value.trim(),
    fields: newEntityFields.value.filter(f => f.name.trim()),
    relations: [],
    isVirtual: true
  }

  viewsStore.addVirtualEntity(entity)

  newEntityName.value = ''
  newEntityFields.value = [{ name: '', type: 'string' }]
  showAddEntityModal.value = false
}

// Expose methods for parent component
defineExpose({
  open: () => { isOpen.value = true },
  close: () => { isOpen.value = false }
})
</script>
