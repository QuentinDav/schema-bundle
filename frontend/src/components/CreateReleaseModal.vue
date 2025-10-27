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
    <div class="modal-overlay" @click="handleClose">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>Create New Release</h2>
          <button @click="handleClose" class="close-btn" :disabled="isSubmitting">
            <Icon name="x-mark" :size="24" />
          </button>
        </div>

        <div class="modal-body">
          <p class="description">
            Snapshot the current state of your database schema with automatic semantic versioning.
          </p>

          <form @submit.prevent="handleSubmit">
            <div class="form-group">
              <label>Version Type</label>
              <div class="version-options">
                <div
                  v-for="type in ['major', 'minor', 'patch']"
                  :key="type"
                  class="version-option"
                  :class="{ selected: versionType === type }"
                  @click="versionType = type"
                >
                  <div class="version-header">
                    <div class="radio-circle">
                      <div v-if="versionType === type" class="radio-dot"></div>
                    </div>
                    <div class="version-info">
                      <span class="version-type">{{ type.toUpperCase() }}</span>
                      <span v-if="!isLoadingSuggestion" class="version-number">
                        {{ suggestedVersions?.[type] || 'v1.0.0' }}
                      </span>
                      <span v-else class="version-loading">Loading...</span>
                    </div>
                  </div>
                  <p v-if="versionType === type" class="version-description">
                    {{ versionExplanation }}
                  </p>
                </div>
              </div>
            </div>

            <div class="selected-version-display">
              <Icon name="sparkles" :size="20" />
              <span>Release will be created as: <strong>{{ selectedVersion }}</strong></span>
            </div>

            <div class="form-group">
              <label for="release-description">Description (optional)</label>
              <textarea
                id="release-description"
                v-model="description"
                placeholder="Describe what changed in this release..."
                class="textarea"
                :disabled="isSubmitting"
                rows="4"
              ></textarea>
            </div>

            <div v-if="errorMessage" class="error-message">
              <Icon name="exclamation-triangle" :size="16" />
              {{ errorMessage }}
            </div>

            <div class="modal-actions">
              <button type="button" @click="handleClose" class="btn btn-secondary" :disabled="isSubmitting">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                <span v-if="!isSubmitting">Create Release</span>
                <span v-else class="spinner"></span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}

.modal-content {
  background: white;
  border-radius: 16px;
  width: 90%;
  max-width: 600px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.5rem 2rem;
  border-bottom: 1px solid #e5e7eb;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.modal-header h2 {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
}

.close-btn {
  background: none;
  border: none;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  border-radius: 6px;
  transition: background 0.2s ease;
  color: white;
}

.close-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.2);
}

.close-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.modal-body {
  padding: 2rem;
}

.description {
  margin: 0 0 1.5rem 0;
  color: #6b7280;
  font-size: 0.875rem;
  line-height: 1.6;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.75rem;
  font-weight: 600;
  color: #374151;
  font-size: 0.875rem;
}

.version-options {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.version-option {
  padding: 1rem;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  background: white;
}

.version-option:hover {
  border-color: #667eea;
  background: #f9fafb;
}

.version-option.selected {
  border-color: #667eea;
  background: linear-gradient(135deg, #667eea08 0%, #764ba208 100%);
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.version-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.radio-circle {
  width: 20px;
  height: 20px;
  border: 2px solid #d1d5db;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: border-color 0.2s ease;
}

.version-option.selected .radio-circle {
  border-color: #667eea;
}

.radio-dot {
  width: 10px;
  height: 10px;
  background: #667eea;
  border-radius: 50%;
}

.version-info {
  display: flex;
  align-items: baseline;
  gap: 0.75rem;
  flex: 1;
}

.version-type {
  font-weight: 700;
  font-size: 0.875rem;
  color: #374151;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.version-number {
  font-size: 1.125rem;
  font-weight: 700;
  color: #667eea;
  font-family: 'Monaco', 'Courier New', monospace;
}

.version-loading {
  font-size: 0.875rem;
  color: #9ca3af;
  font-style: italic;
}

.version-description {
  margin: 0.5rem 0 0 2.5rem;
  font-size: 0.75rem;
  color: #6b7280;
  line-height: 1.5;
}

.selected-version-display {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem;
  background: linear-gradient(135deg, #ecfdf5 0%, #dbeafe 100%);
  border: 1px solid #a7f3d0;
  border-radius: 12px;
  margin-bottom: 1.5rem;
  font-size: 0.875rem;
  color: #065f46;
}

.selected-version-display strong {
  font-weight: 700;
  color: #047857;
  font-family: 'Monaco', 'Courier New', monospace;
}

.textarea {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-family: inherit;
  font-size: 1rem;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
  resize: vertical;
  min-height: 100px;
}

.textarea:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.textarea:disabled {
  background: #f3f4f6;
  cursor: not-allowed;
}

.error-message {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: #fee2e2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  color: #dc2626;
  font-size: 0.875rem;
  margin-bottom: 1.5rem;
}

.modal-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 120px;
}

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
}

.btn-secondary:hover:not(:disabled) {
  background: #e5e7eb;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.modal-enter-from .modal-content {
  transform: scale(0.9);
}

.modal-leave-to .modal-content {
  transform: scale(0.9);
}
</style>
