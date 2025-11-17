<template>
  <Transition name="slide">
    <div
      v-if="playgroundStore.isActive"
      class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t-2 border-orange-500 dark:border-orange-600 shadow-2xl z-40"
      style="height: 400px"
    >
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-3 bg-gradient-to-r from-orange-500 to-orange-600 dark:from-orange-600 dark:to-orange-700 text-white">
        <div class="flex items-center gap-3">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
            />
          </svg>
          <div>
            <h3 class="text-lg font-bold">Schema Playground</h3>
            <p class="text-xs opacity-90">Experiment with temporary changes • Changes are not saved</p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <span
            v-if="playgroundStore.hasModifications"
            class="px-3 py-1 bg-white/20 rounded-full text-xs font-semibold"
          >
            {{ modificationsCount }} modification{{ modificationsCount > 1 ? 's' : '' }}
          </span>
          <button
            @click="showAddRelationModal = true"
            class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-semibold transition-colors"
            title="Add temporary relation"
          >
            + Add Relation
          </button>
          <button
            v-if="playgroundStore.hasModifications"
            @click="clearAll"
            class="px-4 py-2 bg-red-500/80 hover:bg-red-600 rounded-lg text-sm font-semibold transition-colors"
          >
            Clear All
          </button>
          <button
            @click="playgroundStore.deactivate()"
            class="p-2 hover:bg-white/20 rounded-lg transition-colors"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="h-full overflow-y-auto p-6 pb-24">
        <!-- Empty state -->
        <div v-if="!playgroundStore.hasModifications" class="flex flex-col items-center justify-center h-full text-center">
          <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
            />
          </svg>
          <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300 mb-2">No modifications yet</h3>
          <p class="text-gray-500 dark:text-gray-400 mb-6">
            Add temporary relations to experiment with schema changes
          </p>
          <button
            @click="showAddRelationModal = true"
            class="px-6 py-3 bg-orange-500 hover:bg-orange-600 dark:bg-orange-600 dark:hover:bg-orange-700 text-white font-semibold rounded-lg transition-colors"
          >
            Add Your First Relation
          </button>
        </div>

        <!-- Modifications List -->
        <div v-else class="space-y-4">
          <!-- Temporary Relations -->
          <div v-if="playgroundStore.temporaryRelations.length > 0">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                />
              </svg>
              Temporary Relations ({{ playgroundStore.temporaryRelations.length }})
            </h4>
            <div class="grid grid-cols-2 gap-3">
              <div
                v-for="relation in playgroundStore.temporaryRelations"
                :key="relation.id"
                class="p-4 bg-orange-50 dark:bg-orange-900/20 border-2 border-orange-200 dark:border-orange-800 rounded-lg hover:border-orange-300 dark:hover:border-orange-700 transition-colors"
              >
                <div class="flex items-start justify-between mb-2">
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                      <span class="font-semibold text-gray-900 dark:text-gray-100">{{ getShortName(relation.sourceEntity) }}</span>
                      <svg class="w-4 h-4 text-orange-500 dark:text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                      </svg>
                      <span class="font-semibold text-gray-900 dark:text-gray-100">{{ getShortName(relation.targetEntity) }}</span>
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400 space-y-0.5">
                      <div>
                        <span class="font-medium">Field:</span>
                        <code class="ml-1 px-1.5 py-0.5 bg-white dark:bg-gray-700 rounded">{{ relation.fieldName }}</code>
                      </div>
                      <div>
                        <span class="font-medium">Type:</span>
                        <span
                          class="ml-1 px-2 py-0.5 bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-400 rounded text-xs font-semibold"
                        >
                          {{ playgroundStore.getRelationTypeName(relation.relationType) }}
                        </span>
                      </div>
                    </div>
                  </div>
                  <button
                    @click="playgroundStore.removeTemporaryRelation(relation.id)"
                    class="p-1 text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors"
                    title="Remove"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Hidden Entities -->
          <div v-if="playgroundStore.hiddenEntities.size > 0">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                />
              </svg>
              Hidden Entities ({{ playgroundStore.hiddenEntities.size }})
            </h4>
            <div class="flex flex-wrap gap-2">
              <div
                v-for="entityId in Array.from(playgroundStore.hiddenEntities)"
                :key="entityId"
                class="px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg flex items-center gap-2"
              >
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ getShortName(entityId) }}</span>
                <button
                  @click="playgroundStore.showEntity(entityId)"
                  class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300"
                  title="Show"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Relation Modal -->
      <Teleport to="body">
        <Transition name="modal">
          <div
            v-if="showAddRelationModal"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50"
            @click.self="showAddRelationModal = false"
          >
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl p-6">
              <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-6">Add Temporary Relation</h3>

              <div class="space-y-4">
                <!-- Source Entity -->
                <div>
                  <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                    Source Entity
                  </label>
                  <select
                    v-model="newRelation.sourceEntity"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                  >
                    <option value="">Select source entity...</option>
                    <option v-for="entity in schemaStore.rawEntities" :key="entity.fqcn || entity.name" :value="entity.fqcn || entity.name">
                      {{ entity.name }} ({{ entity.table }})
                    </option>
                  </select>
                </div>

                <!-- Target Entity -->
                <div>
                  <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                    Target Entity
                  </label>
                  <select
                    v-model="newRelation.targetEntity"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                  >
                    <option value="">Select target entity...</option>
                    <option v-for="entity in schemaStore.rawEntities" :key="entity.fqcn || entity.name" :value="entity.fqcn || entity.name">
                      {{ entity.name }} ({{ entity.table }})
                    </option>
                  </select>
                </div>

                <!-- Field Name -->
                <div>
                  <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                    Field Name
                  </label>
                  <input
                    v-model="newRelation.fieldName"
                    type="text"
                    placeholder="e.g., author, category, tags"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 dark:placeholder-gray-500"
                  />
                </div>

                <!-- Relation Type -->
                <div>
                  <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                    Relation Type
                  </label>
                  <div class="grid grid-cols-2 gap-3">
                    <button
                      v-for="type in playgroundStore.relationTypes"
                      :key="type.value"
                      @click="newRelation.relationType = type.value"
                      class="p-3 border-2 rounded-lg text-left transition-all"
                      :class="
                        newRelation.relationType === type.value
                          ? 'border-orange-500 dark:border-orange-600 bg-orange-50 dark:bg-orange-900/20'
                          : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                      "
                    >
                      <div class="font-semibold text-sm text-gray-900 dark:text-gray-100">{{ type.label }}</div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">{{ type.description }}</div>
                    </button>
                  </div>
                </div>

                <!-- Nullable -->
                <div>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input
                      v-model="newRelation.nullable"
                      type="checkbox"
                      class="w-4 h-4 text-orange-500 border-gray-300 dark:border-gray-600 rounded focus:ring-orange-500 bg-white dark:bg-gray-700"
                    />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Nullable (optional relation)</span>
                  </label>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex justify-end gap-3 mt-6">
                <button
                  @click="showAddRelationModal = false"
                  class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg font-medium transition-colors"
                >
                  Cancel
                </button>
                <button
                  @click="addRelation"
                  :disabled="!canAddRelation"
                  class="px-6 py-2 bg-orange-500 hover:bg-orange-600 dark:bg-orange-600 dark:hover:bg-orange-700 text-white font-semibold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Add Relation
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>
    </div>
  </Transition>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePlaygroundStore } from '../stores/playground'
import { useSchemaStore } from '../stores/schema'

const playgroundStore = usePlaygroundStore()
const schemaStore = useSchemaStore()

const showAddRelationModal = ref(false)
const newRelation = ref({
  sourceEntity: '',
  targetEntity: '',
  fieldName: '',
  relationType: 2, // Default: ManyToOne
  nullable: false,
})

const modificationsCount = computed(() => {
  return (
    playgroundStore.temporaryRelations.length +
    playgroundStore.temporaryEntities.length +
    playgroundStore.hiddenEntities.size
  )
})

const canAddRelation = computed(() => {
  return (
    newRelation.value.sourceEntity &&
    newRelation.value.targetEntity &&
    newRelation.value.fieldName &&
    newRelation.value.relationType
  )
})

function getShortName(fqcn) {
  const parts = fqcn.split('\\')
  return parts[parts.length - 1]
}

function addRelation() {
  if (!canAddRelation.value) return

  playgroundStore.addTemporaryRelation({ ...newRelation.value })

  // Reset form
  newRelation.value = {
    sourceEntity: '',
    targetEntity: '',
    fieldName: '',
    relationType: 2,
    nullable: false,
  }

  showAddRelationModal.value = false
}

function clearAll() {
  if (confirm('Are you sure you want to clear all playground modifications?')) {
    playgroundStore.clearAll()
  }
}
</script>

<style scoped>
.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
  transform: translateY(100%);
}

.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
