<script setup>
import { ref, computed, watch, nextTick } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Write a comment...',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  users: {
    type: Array,
    default: () => [],
  },
  fields: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])

const textareaRef = ref(null)
const showSuggestions = ref(false)
const suggestionQuery = ref('')
const suggestionPosition = ref({ top: 0, left: 0 })
const selectedSuggestionIndex = ref(0)
const cursorPosition = ref(0)

const internalValue = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const suggestions = computed(() => {
  if (!showSuggestions.value) return []

  const query = suggestionQuery.value.toLowerCase()

  // Combine users and fields into one list
  const userSuggestions = (query
    ? props.users.filter((user) => user.name.toLowerCase().includes(query))
    : props.users
  ).map((user) => ({ type: 'user', data: user }))

  const fieldSuggestions = (query
    ? props.fields.filter((field) => field.name.toLowerCase().includes(query))
    : props.fields
  ).map((field) => ({ type: 'field', data: field }))

  // Combine and limit total results
  const combined = [...userSuggestions, ...fieldSuggestions].slice(0, 10)

  console.log('Suggestions debug:', {
    query,
    usersCount: props.users.length,
    fieldsCount: props.fields.length,
    userSuggestionsCount: userSuggestions.length,
    fieldSuggestionsCount: fieldSuggestions.length,
    combinedCount: combined.length,
  })

  return combined
})

watch(suggestions, () => {
  selectedSuggestionIndex.value = 0
})

function handleInput(event) {
  const value = event.target.value
  const cursorPos = event.target.selectionStart
  cursorPosition.value = cursorPos

  internalValue.value = value

  // Check for / trigger only
  const textBeforeCursor = value.substring(0, cursorPos)
  const lastSlashIndex = textBeforeCursor.lastIndexOf('/')

  // Check if we're in a mention context
  const slashMatch =
    lastSlashIndex !== -1 && textBeforeCursor.substring(lastSlashIndex).match(/^\/(\w*)$/)

  if (slashMatch) {
    showSuggestions.value = true
    suggestionQuery.value = slashMatch[1]
    updateSuggestionPosition()
  } else {
    showSuggestions.value = false
  }
}

function handleKeyDown(event) {
  if (!showSuggestions.value || suggestions.value.length === 0) return

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    selectedSuggestionIndex.value = Math.min(
      selectedSuggestionIndex.value + 1,
      suggestions.value.length - 1,
    )
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    selectedSuggestionIndex.value = Math.max(selectedSuggestionIndex.value - 1, 0)
  } else if (event.key === 'Enter' || event.key === 'Tab') {
    event.preventDefault()
    insertSuggestion(suggestions.value[selectedSuggestionIndex.value])
  } else if (event.key === 'Escape') {
    showSuggestions.value = false
  }
}

function insertSuggestion(suggestion) {
  const value = internalValue.value
  const cursorPos = cursorPosition.value

  // Find the start of the mention/field
  const textBeforeCursor = value.substring(0, cursorPos)
  const lastTriggerIndex = textBeforeCursor.lastIndexOf('/')

  if (lastTriggerIndex === -1) return

  // Replace from trigger to cursor with the suggestion
  const before = value.substring(0, lastTriggerIndex)
  const after = value.substring(cursorPos)

  let insertText = ''
  if (suggestion.type === 'user') {
    insertText = `@${suggestion.data.username} `
  } else {
    // Use a special format for fields: [[fieldname]]
    insertText = `[[${suggestion.data.name}]] `
  }

  const newValue = before + insertText + after
  internalValue.value = newValue

  showSuggestions.value = false

  // Move cursor after the insertion
  nextTick(() => {
    const newCursorPos = lastTriggerIndex + insertText.length
    textareaRef.value.setSelectionRange(newCursorPos, newCursorPos)
    textareaRef.value.focus()
  })
}

function updateSuggestionPosition() {
  nextTick(() => {
    if (!textareaRef.value) return

    const textarea = textareaRef.value
    const rect = textarea.getBoundingClientRect()

    // Position above the textarea
    suggestionPosition.value = {
      bottom: textarea.offsetHeight + 5,
      left: 10,
    }
  })
}

function clickSuggestion(suggestion) {
  insertSuggestion(suggestion)
}
</script>

<template>
  <div class="mention-textarea-wrapper">
    <textarea
      ref="textareaRef"
      :value="modelValue"
      @input="handleInput"
      @keydown="handleKeyDown"
      :placeholder="placeholder"
      :disabled="disabled"
      class="mention-textarea"
      rows="3"
    />

    <div
      v-if="showSuggestions && suggestions.length > 0"
      class="suggestions-dropdown"
      :style="{ bottom: suggestionPosition.bottom + 'px', left: suggestionPosition.left + 'px' }"
    >
      <div
        v-for="(suggestion, index) in suggestions"
        :key="suggestion.type === 'user' ? suggestion.data.id : suggestion.data.name"
        @click="clickSuggestion(suggestion)"
        class="suggestion-item"
        :class="{ selected: index === selectedSuggestionIndex }"
      >
        <div v-if="suggestion.type === 'user'" class="user-suggestion">
          <div class="user-avatar">{{ suggestion.data.name.charAt(0).toUpperCase() }}</div>
          <div class="user-info">
            <span class="user-name">{{ suggestion.data.name }}</span>
            <span class="user-username">@{{ suggestion.data.username }}</span>
          </div>
        </div>

        <div v-else class="field-suggestion">
          <div class="field-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <circle cx="12" cy="12" r="2" />
              <path
                d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"
                stroke="currentColor"
                stroke-width="2"
                fill="none"
              />
            </svg>
          </div>
          <div class="field-info">
            <span class="field-name">{{ suggestion.data.name }}</span>
            <span class="field-type">{{ suggestion.data.type }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.mention-textarea-wrapper {
  position: relative;
}

.mention-textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-family: inherit;
  font-size: 0.875rem;
  resize: vertical;
  min-height: 80px;
  transition: border-color 0.2s ease;
}

.mention-textarea:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.mention-textarea:disabled {
  background: #f3f4f6;
  cursor: not-allowed;
}

.suggestions-dropdown {
  position: absolute;
  z-index: 1000;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  max-height: 250px;
  overflow-y: auto;
  min-width: 250px;
}

.suggestion-item {
  padding: 0.75rem;
  cursor: pointer;
  transition: background 0.15s ease;
  border-bottom: 1px solid #f3f4f6;
}

.suggestion-item:last-child {
  border-bottom: none;
}

.suggestion-item:hover,
.suggestion-item.selected {
  background: #f3f4f6;
}

.user-suggestion {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.75rem;
  flex-shrink: 0;
}

.user-info {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-size: 0.875rem;
  font-weight: 600;
  color: #111827;
}

.user-username {
  font-size: 0.75rem;
  color: #9ca3af;
}

.field-suggestion {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.field-icon {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  border-radius: 6px;
  color: #667eea;
  flex-shrink: 0;
}

.field-info {
  display: flex;
  flex-direction: column;
}

.field-name {
  font-size: 0.875rem;
  font-weight: 600;
  color: #111827;
}

.field-type {
  font-size: 0.75rem;
  color: #9ca3af;
  font-family: 'Courier New', monospace;
}
</style>
