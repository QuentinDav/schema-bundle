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
  <span class="comment-text-parsed">
    <template v-for="(part, index) in parsedContent" :key="index">
      <span v-if="part.type === 'text'">{{ part.content }}</span>
      <span v-else-if="part.type === 'mention'" class="mention-tag">
        {{ part.content }}
      </span>
      <span v-else-if="part.type === 'field'" class="field-tag">
        {{ part.content }}
      </span>
    </template>
  </span>
</template>

<style scoped>
.comment-text-parsed {
  font-size: 0.875rem;
  color: #374151;
  line-height: 1.6;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.mention-tag {
  color: #667eea;
  font-weight: 600;
  background: rgba(102, 126, 234, 0.1);
  padding: 0.125rem 0.375rem;
  border-radius: 4px;
  transition: all 0.2s ease;
}

.mention-tag:hover {
  background: rgba(102, 126, 234, 0.2);
}

.field-tag {
  display: inline-block;
  font-family: 'Courier New', monospace;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #4b5563;
  background: #e5e7eb;
  padding: 0.125rem 0.5rem;
  border-radius: 6px;
  border: 1px solid #d1d5db;
  margin: 0 0.125rem;
}
</style>
