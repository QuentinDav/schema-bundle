<script setup>
import { computed } from 'vue'

const props = defineProps({
  text: {
    type: String,
    required: true
  },
  isSystem: false
})

const parsedContent = computed(() => {
  const parts = []
  const isSystem = props.isSystem
  const text = props.text
  let lastIndex = 0

  const regex = /(@\w+)|(\[\[(\w+)\]\])/g
  let match

  while ((match = regex.exec(text)) !== null) {
    if (match.index > lastIndex) {
      parts.push({
        type: 'text',
        content: text.substring(lastIndex, match.index),
        isSystem
      })
    }

    if (match[1]) {
      parts.push({
        type: 'mention',
        content: match[1],
        isSystem
      })
    } else if (match[2]) {
      parts.push({
        type: 'field',
        content: match[3],
        isSystem
      })
    }

    lastIndex = regex.lastIndex
  }

  if (lastIndex < text.length) {
    parts.push({
      type: 'text',
      content: text.substring(lastIndex),
    })
  }

  return parts
})
</script>

<template>
  <span class="text-sm text-[var(--color-text-primary)] leading-relaxed whitespace-pre-wrap break-words">
    <template v-for="(part, index) in parsedContent" :key="index">
      <span v-if="part.type === 'text'">{{ part.content }}</span>
      <span v-else-if="part.type === 'mention'" class="text-[var(--color-primary)] font-semibold bg-[var(--color-primary-light)] px-1.5 py-0.5 rounded transition-all duration-200 hover:bg-[var(--color-primary)]/20">
        {{ part.content }}
      </span>
      <span v-else-if="part.type === 'field'" class="inline-block font-mono text-[13px] font-semibold text-[var(--color-text-secondary)] bg-[var(--color-surface-raised)] px-1.5 py-0.5 rounded border border-[var(--color-border)] transition-all duration-200 hover:border-[var(--color-primary)]/30 hover:text-[var(--color-primary)]">
        {{ part.content }}
      </span>
    </template>
  </span>
</template>
