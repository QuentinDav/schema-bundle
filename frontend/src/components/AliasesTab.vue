<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useAliasesStore } from '@/stores/aliases'

const props = defineProps({
  entity: {
    type: Object,
    required: true
  }
})

const aliasesStore = useAliasesStore()

const newAlias = ref('')
const newLanguage = ref('')
const newDescription = ref('')
const editingId = ref(null)
const editingAlias = ref({})
const isSubmitting = ref(false)
const showAddForm = ref(false)

const aliases = computed(() => {
  return aliasesStore.getAliasesForEntity(props.entity.fqcn) || []
})

const isEditing = computed(() => editingId.value !== null)

// Load aliases when entity changes
watch(() => props.entity?.fqcn, async (fqcn) => {
  if (fqcn) {
    await aliasesStore.fetchAliasesForEntity(fqcn)
  }
}, { immediate: true })

async function addAlias() {
  if (!newAlias.value.trim()) return

  isSubmitting.value = true
  try {
    await aliasesStore.createAlias({
      entityFqcn: props.entity.fqcn,
      alias: newAlias.value.trim(),
      language: newLanguage.value.trim() || null,
      description: newDescription.value.trim() || null
    })

    // Reset form
    newAlias.value = ''
    newLanguage.value = ''
    newDescription.value = ''
    showAddForm.value = false
  } catch (error) {
    alert(`Failed to add alias: ${error.message}`)
  } finally {
    isSubmitting.value = false
  }
}

function startEdit(alias) {
  editingId.value = alias.id
  editingAlias.value = { ...alias }
}

function cancelEdit() {
  editingId.value = null
  editingAlias.value = {}
}

async function saveEdit() {
  if (!editingAlias.value.alias?.trim()) return

  isSubmitting.value = true
  try {
    await aliasesStore.updateAlias(editingId.value, {
      alias: editingAlias.value.alias.trim(),
      language: editingAlias.value.language?.trim() || null,
      description: editingAlias.value.description?.trim() || null
    })

    editingId.value = null
    editingAlias.value = {}
  } catch (error) {
    alert(`Failed to update alias: ${error.message}`)
  } finally {
    isSubmitting.value = false
  }
}

async function deleteAliasConfirm(alias) {
  if (!confirm(`Delete alias "${alias.alias}"?`)) return

  isSubmitting.value = true
  try {
    await aliasesStore.deleteAlias(alias.id, props.entity.fqcn)
  } catch (error) {
    alert(`Failed to delete alias: ${error.message}`)
  } finally {
    isSubmitting.value = false
  }
}

function getLanguageFlag(lang) {
  const flags = {
    'en': '🇬🇧',
    'fr': '🇫🇷',
    'es': '🇪🇸',
    'de': '🇩🇪',
    'it': '🇮🇹',
    'pt': '🇵🇹',
    'ja': '🇯🇵',
    'zh': '🇨🇳',
    'ru': '🇷🇺',
    'ar': '🇸🇦'
  }
  return flags[lang?.toLowerCase()] || '🌐'
}
</script>

<template>
  <div class="p-6 space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-lg font-semibold text-[var(--color-text)]">Entity Aliases</h3>
        <p class="text-sm text-[var(--color-text-muted)] mt-1">
          Define alternative names to improve Natural Language queries and reduce token usage
        </p>
      </div>
      <button
        v-if="!showAddForm"
        @click="showAddForm = true"
        class="px-4 py-2 bg-[var(--color-primary)] text-white rounded-lg hover:bg-[var(--color-primary-hover)] transition-colors text-sm font-medium"
      >
        +
      </button>
    </div>

    <!-- Add Form -->
    <div v-if="showAddForm" class="bg-[var(--color-surface-elevated)] rounded-lg p-4 border border-[var(--color-border)]">
      <h4 class="font-medium text-[var(--color-text)] mb-3">New Alias</h4>
      <div class="space-y-3">
        <div>
          <label class="block text-sm font-medium text-[var(--color-text)] mb-1">
            Alias Name <span class="text-red-500">*</span>
          </label>
          <input
            v-model="newAlias"
            type="text"
            placeholder="e.g., client, customer, utilisateur"
            class="w-full px-3 py-2 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg text-[var(--color-text)] placeholder-[var(--color-text-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
            @keydown.enter="addAlias"
          />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-[var(--color-text)] mb-1">
              Language (optional)
            </label>
            <input
              v-model="newLanguage"
              type="text"
              placeholder="e.g., en, fr, es"
              maxlength="5"
              class="w-full px-3 py-2 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg text-[var(--color-text)] placeholder-[var(--color-text-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-[var(--color-text)] mb-1">
              Description (optional)
            </label>
            <input
              v-model="newDescription"
              type="text"
              placeholder="e.g., French translation"
              class="w-full px-3 py-2 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg text-[var(--color-text)] placeholder-[var(--color-text-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
            />
          </div>
        </div>

        <div class="flex gap-2 justify-end">
          <button
            @click="showAddForm = false; newAlias = ''; newLanguage = ''; newDescription = ''"
            class="px-4 py-2 text-sm font-medium text-[var(--color-text)] hover:bg-[var(--color-surface)] rounded-lg transition-colors"
            :disabled="isSubmitting"
          >
            Cancel
          </button>
          <button
            @click="addAlias"
            :disabled="!newAlias.trim() || isSubmitting"
            class="px-4 py-2 bg-[var(--color-primary)] text-white rounded-lg hover:bg-[var(--color-primary-hover)] transition-colors text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ isSubmitting ? 'Adding...' : 'Add Alias' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Aliases List -->
    <div v-if="aliases.length > 0" class="space-y-2">
      <div
        v-for="alias in aliases"
        :key="alias.id"
        class="bg-[var(--color-surface-elevated)] rounded-lg p-4 border border-[var(--color-border)] hover:border-[var(--color-primary)] transition-colors"
      >
        <!-- Edit Mode -->
        <div v-if="editingId === alias.id" class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-[var(--color-text)] mb-1">
              Alias Name
            </label>
            <input
              v-model="editingAlias.alias"
              type="text"
              class="w-full px-3 py-2 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-[var(--color-text)] mb-1">
                Language
              </label>
              <input
                v-model="editingAlias.language"
                type="text"
                maxlength="5"
                class="w-full px-3 py-2 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-[var(--color-text)] mb-1">
                Description
              </label>
              <input
                v-model="editingAlias.description"
                type="text"
                class="w-full px-3 py-2 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
              />
            </div>
          </div>

          <div class="flex gap-2 justify-end">
            <button
              @click="cancelEdit"
              class="px-3 py-1.5 text-sm font-medium text-[var(--color-text)] hover:bg-[var(--color-surface)] rounded-lg transition-colors"
              :disabled="isSubmitting"
            >
              Cancel
            </button>
            <button
              @click="saveEdit"
              :disabled="!editingAlias.alias?.trim() || isSubmitting"
              class="px-3 py-1.5 bg-[var(--color-primary)] text-white rounded-lg hover:bg-[var(--color-primary-hover)] transition-colors text-sm font-medium disabled:opacity-50"
            >
              {{ isSubmitting ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </div>

        <!-- View Mode -->
        <div v-else class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-2">
              <span class="text-lg">🏷️</span>
              <span class="font-semibold text-[var(--color-text)]">{{ alias.alias }}</span>
              <span v-if="alias.language" class="text-lg" :title="alias.language">
                {{ getLanguageFlag(alias.language) }}
              </span>
            </div>
            <p v-if="alias.description" class="text-sm text-[var(--color-text-muted)] mt-1">
              {{ alias.description }}
            </p>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="startEdit(alias)"
              class="p-2 text-[var(--color-text-muted)] hover:text-[var(--color-primary)] hover:bg-[var(--color-surface)] rounded-lg transition-colors"
              title="Edit"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
              </svg>
            </button>
            <button
              @click="deleteAliasConfirm(alias)"
              class="p-2 text-[var(--color-text-muted)] hover:text-red-500 hover:bg-[var(--color-surface)] rounded-lg transition-colors"
              title="Delete"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!showAddForm" class="text-center py-12">
      <div class="text-6xl mb-4">🏷️</div>
      <h4 class="text-lg font-medium text-[var(--color-text)] mb-2">No aliases yet</h4>
      <p class="text-sm text-[var(--color-text-muted)] mb-4">
        Add aliases to help Natural Language queries understand alternative names for this entity
      </p>
      <button
        @click="showAddForm = true"
        class="px-4 py-2 bg-[var(--color-primary)] text-white rounded-lg hover:bg-[var(--color-primary-hover)] transition-colors text-sm font-medium"
      >
        Add First Alias
      </button>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
      <div class="flex gap-3">
        <div class="text-blue-500 text-xl">💡</div>
        <div class="flex-1">
          <h4 class="font-medium text-blue-900 dark:text-blue-100 mb-1">How aliases work</h4>
          <ul class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
            <li>• Aliases must be unique across all entities</li>
            <li>• Use aliases in Natural Language queries: "Show all <strong>clients</strong>" → User table</li>
            <li>• Reduces token usage by sending only relevant entities to the AI</li>
            <li>• Supports multiple languages for international teams</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Custom styles if needed */
</style>
