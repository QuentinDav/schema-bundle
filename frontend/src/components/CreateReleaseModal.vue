<script setup>
import { ref, onMounted, computed } from 'vue'
import { useReleasesStore } from '@/stores/releases'
import Icon from './Icon.vue'

const emit = defineEmits(['close', 'created'])

const releasesStore = useReleasesStore()

const versionType = ref('minor')
const description = ref('')
const isSubmitting = ref(false)
const errorMessage = ref(null)
const suggestedVersions = ref(null)
const isLoadingSuggestion = ref(false)

onMounted(async () => {
  await fetchSuggestedVersion()
})

async function fetchSuggestedVersion() {
  isLoadingSuggestion.value = true
  try {
    const response = await fetch('/schema-doc/api/releases/suggested-version')
    const data = await response.json()
    suggestedVersions.value = data.available_types
    versionType.value = data.type
  } catch (e) {
    console.error('Failed to fetch suggested version:', e)
  } finally {
    isLoadingSuggestion.value = false
  }
}

const selectedVersion = computed(() => {
  return suggestedVersions.value?.[versionType.value] || 'v1.0.0'
})

const versionExplanation = computed(() => {
  const explanations = {
    major: 'Breaking changes - Major schema modifications (>20% entities changed)',
    minor: 'New features - New entities or relations added',
    patch: 'Small changes - Minor field modifications'
  }
  return explanations[versionType.value]
})

async function handleSubmit() {
  isSubmitting.value = true
  errorMessage.value = null

  try {
    const result = await releasesStore.createRelease(
      null,
      description.value.trim() || null,
      versionType.value
    )
    emit('created', result)
    emit('close')
  } catch (e) {
    errorMessage.value = e.message || 'Failed to create release'
  } finally {
    isSubmitting.value = false
  }
}

function handleClose() {
  if (!isSubmitting.value) {
    emit('close')
  }
}
</script>

<template>
  <Transition name="modal">
    <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-[1000] backdrop-blur-sm" @click="handleClose">
      <div class="bg-[var(--color-surface)] rounded-2xl w-[90%] max-w-[600px] shadow-2xl overflow-hidden" @click.stop>
        <div class="flex items-center justify-between px-8 py-6 border-b border-[var(--color-border)] bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-primary-hover)]">
          <h2 class="m-0 text-2xl font-bold text-white">Create New Release</h2>
          <button @click="handleClose" class="w-8 h-8 flex items-center justify-center cursor-pointer rounded-md transition-all duration-200 text-white hover:bg-white/20 disabled:opacity-50 disabled:cursor-not-allowed" :disabled="isSubmitting">
            <Icon name="x-mark" :size="24" />
          </button>
        </div>

        <div class="p-8">
          <p class="m-0 mb-6 text-[var(--color-text-secondary)] text-sm leading-relaxed">
            Snapshot the current state of your database schema with automatic semantic versioning.
          </p>

          <form @submit.prevent="handleSubmit">
            <div class="mb-6">
              <label class="block mb-3 font-semibold text-[var(--color-text-primary)] text-sm">Version Type</label>
              <div class="flex flex-col gap-3">
                <div
                  v-for="type in ['major', 'minor', 'patch']"
                  :key="type"
                  class="p-4 border-2 rounded-xl cursor-pointer transition-all duration-200 bg-[var(--color-surface-raised)]"
                  :class="versionType === type ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)] shadow-lg shadow-[var(--color-primary)]/10' : 'border-[var(--color-border)] hover:border-[var(--color-primary)]/50 hover:bg-[var(--color-surface-hover)]'"
                  @click="versionType = type"
                >
                  <div class="flex items-center gap-3">
                    <div class="w-5 h-5 border-2 rounded-full flex items-center justify-center flex-shrink-0 transition-colors duration-200" :class="versionType === type ? 'border-[var(--color-primary)]' : 'border-[var(--color-border)]'">
                      <div v-if="versionType === type" class="w-2.5 h-2.5 bg-[var(--color-primary)] rounded-full"></div>
                    </div>
                    <div class="flex items-baseline gap-3 flex-1">
                      <span class="font-bold text-sm text-[var(--color-text-primary)] uppercase tracking-wide">{{ type }}</span>
                      <span v-if="!isLoadingSuggestion" class="text-lg font-bold text-[var(--color-primary)] font-mono">
                        {{ suggestedVersions?.[type] || 'v1.0.0' }}
                      </span>
                      <span v-else class="text-sm text-[var(--color-text-tertiary)] italic">Loading...</span>
                    </div>
                  </div>
                  <p v-if="versionType === type" class="mt-2 ml-8 text-xs text-[var(--color-text-secondary)] leading-relaxed">
                    {{ versionExplanation }}
                  </p>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-2 px-4 py-3 bg-gradient-to-r from-[#10b981]/10 to-[#3b82f6]/10 border border-[#10b981]/30 rounded-xl mb-6 text-sm text-[var(--color-text-primary)]">
              <Icon name="sparkles" :size="20" class="text-[#10b981]" />
              <span>Release will be created as: <strong class="font-bold text-[#10b981] font-mono">{{ selectedVersion }}</strong></span>
            </div>

            <div class="mb-6">
              <label for="release-description" class="block mb-3 font-semibold text-[var(--color-text-primary)] text-sm">Description (optional)</label>
              <textarea
                id="release-description"
                v-model="description"
                placeholder="Describe what changed in this release..."
                class="w-full px-4 py-3 border border-[var(--color-border)] rounded-lg font-sans text-base transition-all duration-200 resize-vertical min-h-[100px] bg-[var(--color-background)] text-[var(--color-text-primary)] placeholder:text-[var(--color-text-tertiary)] focus:outline-none focus:border-[var(--color-primary)] focus:shadow-lg focus:shadow-[var(--color-primary)]/10 disabled:bg-[var(--color-surface-hover)] disabled:cursor-not-allowed"
                :disabled="isSubmitting"
                rows="4"
              ></textarea>
            </div>

            <div v-if="errorMessage" class="flex items-center gap-2 px-4 py-3 bg-[var(--color-danger-light)] border border-[var(--color-danger)]/30 rounded-lg text-[var(--color-danger)] text-sm mb-6">
              <Icon name="exclamation-triangle" :size="16" />
              {{ errorMessage }}
            </div>

            <div class="flex gap-4 justify-end">
              <button type="button" @click="handleClose" class="px-6 py-3 border-0 rounded-lg font-semibold text-sm cursor-pointer transition-all duration-200 flex items-center justify-center min-w-[120px] bg-[var(--color-surface-hover)] text-[var(--color-text-primary)] hover:bg-[var(--color-border)] disabled:opacity-60 disabled:cursor-not-allowed" :disabled="isSubmitting">
                Cancel
              </button>
              <button type="submit" class="px-6 py-3 border-0 rounded-lg font-semibold text-sm cursor-pointer transition-all duration-200 flex items-center justify-center min-w-[120px] bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-primary-hover)] text-white hover:-translate-y-px hover:shadow-lg hover:shadow-[var(--color-primary)]/40 disabled:opacity-60 disabled:cursor-not-allowed disabled:transform-none" :disabled="isSubmitting">
                <span v-if="!isSubmitting">Create Release</span>
                <span v-else class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from > div,
.modal-leave-to > div {
  transform: scale(0.9);
}

.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-active > div,
.modal-leave-active > div {
  transition: transform 0.2s ease;
}
</style>
