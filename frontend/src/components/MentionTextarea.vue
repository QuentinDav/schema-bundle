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

  const userSuggestions = (query
    ? props.users.filter((user) => user.name.toLowerCase().includes(query))
    : props.users
  ).map((user) => ({ type: 'user', data: user }))

  const fieldSuggestions = (query
    ? props.fields.filter((field) => field.name.toLowerCase().includes(query))
    : props.fields
  ).map((field) => ({ type: 'field', data: field }))

  const combined = [...userSuggestions, ...fieldSuggestions].slice(0, 10)

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

  const textBeforeCursor = value.substring(0, cursorPos)
  const lastSlashIndex = textBeforeCursor.lastIndexOf('/')

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

  const textBeforeCursor = value.substring(0, cursorPos)
  const lastTriggerIndex = textBeforeCursor.lastIndexOf('/')

  if (lastTriggerIndex === -1) return

  const before = value.substring(0, lastTriggerIndex)
  const after = value.substring(cursorPos)

  let insertText = ''
  if (suggestion.type === 'user') {
    insertText = `@${suggestion.data.username} `
  } else {
    insertText = `[[${suggestion.data.name}]] `
  }

  const newValue = before + insertText + after
  internalValue.value = newValue

  showSuggestions.value = false

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
  <div class="relative">
    <textarea
      ref="textareaRef"
      :value="modelValue"
      @input="handleInput"
      @keydown="handleKeyDown"
      :placeholder="placeholder"
      :disabled="disabled"
      class="w-full px-3 py-3 border border-[var(--color-border)] rounded-lg font-sans text-sm resize-vertical min-h-[80px] transition-all duration-200 bg-[var(--color-background)] text-[var(--color-text-primary)] placeholder:text-[var(--color-text-tertiary)] focus:outline-none focus:border-[var(--color-primary)] focus:shadow-lg focus:shadow-[var(--color-primary)]/10 disabled:bg-[var(--color-surface-hover)] disabled:cursor-not-allowed"
      rows="3"
    />

    <div
      v-if="showSuggestions && suggestions.length > 0"
      class="absolute z-[1000] bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg shadow-2xl max-h-[250px] overflow-y-auto min-w-[250px]"
      :style="{ bottom: suggestionPosition.bottom + 'px', left: suggestionPosition.left + 'px' }"
    >
      <div
        v-for="(suggestion, index) in suggestions"
        :key="suggestion.type === 'user' ? suggestion.data.id : suggestion.data.name"
        @click="clickSuggestion(suggestion)"
        class="px-3 py-3 cursor-pointer transition-all duration-150 border-b border-[var(--color-border)] last:border-b-0"
        :class="{ 'bg-[var(--color-primary-light)]': index === selectedSuggestionIndex, 'hover:bg-[var(--color-surface-hover)]': index !== selectedSuggestionIndex }"
      >
        <div v-if="suggestion.type === 'user'" class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[var(--color-primary)] to-[var(--color-primary-hover)] text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
            {{ suggestion.data.name.charAt(0).toUpperCase() }}
          </div>
          <div class="flex flex-col">
            <span class="text-sm font-semibold text-[var(--color-text-primary)]">{{ suggestion.data.name }}</span>
            <span class="text-xs text-[var(--color-text-secondary)]">@{{ suggestion.data.username }}</span>
          </div>
        </div>

        <div v-else class="flex items-center gap-3">
          <div class="w-8 h-8 flex items-center justify-center bg-[var(--color-surface)] rounded-md text-[var(--color-primary)] flex-shrink-0">
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
          <div class="flex flex-col">
            <span class="text-sm font-semibold text-[var(--color-text-primary)]">{{ suggestion.data.name }}</span>
            <span class="text-xs text-[var(--color-text-secondary)] font-mono">{{ suggestion.data.type }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
