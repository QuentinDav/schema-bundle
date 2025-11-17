<script setup>
import { computed, ref, watch } from 'vue'
import { useSchemaStore, getRelationTypeName } from '@/stores/schema'
import { useCommentsStore } from '@/stores/comments'
import MentionTextarea from './MentionTextarea.vue'
import CommentText from './CommentText.vue'
import MigrationTimeline from './MigrationTimeline.vue'
import AliasesTab from './AliasesTab.vue'

const schemaStore = useSchemaStore()
const commentsStore = useCommentsStore()

const entity = computed(() => schemaStore.selectedEntity)
const newCommentText = ref('')
const isSubmitting = ref(false)
const showSystemComments = ref(true)
const activeTab = ref('comments') // 'comments', 'history', or 'aliases'

const filteredComments = computed(() => {
  if (showSystemComments.value) return commentsStore.activeComments
  return commentsStore.activeComments.filter((c) => !c.isSystem)
})

watch(entity, (newEntity) => {
  if (newEntity) {
    commentsStore.setActiveTarget('table', newEntity.fqcn)
    commentsStore.fetchComments(newEntity.fqcn)
  } else {
    commentsStore.clearActiveTarget()
  }
}, { immediate: true })

const relations = computed(() => entity.value?.relations || [])
const fields = computed(() => entity.value?.fields || [])

function close() {
  schemaStore.clearSelection()
}

function getFieldIcon(field) {
  if (field.relation || field.targetEntity) return '🔗'
  if (field.type?.includes('int') || field.type === 'integer') return '🔢'
  if (field.type?.includes('string') || field.type === 'text') return '📝'
  if (field.type?.includes('bool')) return '✓'
  if (field.type?.includes('date') || field.type?.includes('time')) return '📅'
  if (field.type === 'json') return '📋'
  return '•'
}

function getRelationTypeColor(type) {
  const colors = { 1: '#10b981', 2: '#3b82f6', 4: '#f59e0b', 8: '#ef4444' }
  return colors[type] || '#6b7280'
}

async function submitComment() {
  if (!newCommentText.value.trim() || !entity.value) return
  isSubmitting.value = true
  try {
    await commentsStore.addComment({
      targetType: 'table',
      entityFqcn: entity.value.fqcn,
      fieldName: null,
      content: newCommentText.value.trim(),
    })
    newCommentText.value = ''
  } catch (e) {
    console.error('Failed to add comment:', e)
  } finally {
    isSubmitting.value = false
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

function deleteComment(commentId) {
  if (confirm('Are you sure you want to delete this comment?')) {
    commentsStore.deleteComment(commentId)
  }
}
</script>

<template>
  <Transition name="drawer">
    <div v-if="entity" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[var(--z-modal)] flex justify-end" @click.self="close">
      <div class="w-[1200px] max-w-[95vw] bg-[var(--color-surface)] shadow-xl flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-primary-hover)] text-white">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-lg bg-white/20 flex items-center justify-center">
              <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 6a3 3 0 013-3h12a3 3 0 013 3v12a3 3 0 01-3 3H6a3 3 0 01-3-3V6z" opacity="0.2"/>
                <path d="M3 6a3 3 0 013-3h12a3 3 0 013 3v3H3V6zm0 5h18v7a3 3 0 01-3 3H6a3 3 0 01-3-3v-7z"/>
              </svg>
            </div>
            <div>
              <h2 class="text-xl font-bold">{{ entity.name }}</h2>
              <p v-if="entity.table" class="text-sm opacity-90 font-mono">{{ entity.table }}</p>
            </div>
          </div>
          <button @click="close" class="w-10 h-10 rounded-lg bg-white/20 hover:bg-white/30 flex items-center justify-center transition-colors">
            <svg class="w-6 h-6" viewBox="0 0 24 24" stroke="currentColor" fill="none">
              <path d="M6 6l12 12M18 6L6 18" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </button>
        </div>

        <div class="flex flex-1 overflow-hidden">
          <div class="flex-1 overflow-y-auto p-6 space-y-6 border-r border-[var(--color-border)]">
            <div class="grid grid-cols-3 gap-4">
              <div class="p-4 bg-[var(--color-surface-raised)] rounded-lg border border-[var(--color-border)]">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-lg bg-[var(--color-primary-light)] text-[var(--color-primary)] flex items-center justify-center">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path d="M4 6h16M4 12h16M4 18h16" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                  </div>
                  <div>
                    <div class="text-2xl font-bold text-[var(--color-text-primary)]">{{ fields.length }}</div>
                    <div class="text-xs text-[var(--color-text-tertiary)]">Fields</div>
                  </div>
                </div>
              </div>
              <div class="p-4 bg-[var(--color-surface-raised)] rounded-lg border border-[var(--color-border)]">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-lg bg-[var(--color-accent-light)] text-[var(--color-accent)] flex items-center justify-center">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71" stroke-width="2" stroke-linecap="round"/>
                      <path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                  </div>
                  <div>
                    <div class="text-2xl font-bold text-[var(--color-text-primary)]">{{ relations.length }}</div>
                    <div class="text-xs text-[var(--color-text-tertiary)]">Relations</div>
                  </div>
                </div>
              </div>
              <div class="p-4 bg-[var(--color-surface-raised)] rounded-lg border border-[var(--color-border)]">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-lg bg-[var(--color-success-light)] text-[var(--color-success)] flex items-center justify-center">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <circle cx="12" cy="12" r="10" stroke-width="2"/>
                      <path d="M12 8v8" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                  </div>
                  <div>
                    <div class="text-2xl font-bold text-[var(--color-text-primary)]">{{ entity.pk?.length || 0 }}</div>
                    <div class="text-xs text-[var(--color-text-tertiary)]">Primary Keys</div>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="relations.length > 0">
              <h3 class="flex items-center gap-2 text-base font-semibold text-[var(--color-text-primary)] mb-3">
                <svg class="w-5 h-5 text-[var(--color-primary)]" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Relations
              </h3>
              <div class="space-y-2">
                <div v-for="relation in relations" :key="relation.field" class="p-3 bg-[var(--color-accent-light)] border border-[var(--color-accent)]/20 rounded-lg">
                  <div class="flex items-center gap-2 mb-2">
                    <span class="text-base">🔗</span>
                    <span class="font-semibold text-[var(--color-text-primary)]">{{ relation.field }}</span>
                    <span class="px-2 py-0.5 text-xs font-semibold text-white rounded" :style="{ background: getRelationTypeColor(relation.type) }">
                      {{ getRelationTypeName(relation.type) }}
                    </span>
                  </div>
                  <div class="flex flex-wrap gap-2 text-xs text-[var(--color-text-secondary)]">
                    <span><strong>Target:</strong> {{ relation.target }}</span>
                    <span v-if="relation.isOwning" class="px-2 py-0.5 bg-[var(--color-surface-raised)] rounded">owning</span>
                    <span v-else class="px-2 py-0.5 bg-[var(--color-surface-raised)] rounded">inverse</span>
                    <span v-if="relation.nullable" class="px-2 py-0.5 bg-[var(--color-surface-raised)] rounded">nullable</span>
                  </div>
                </div>
              </div>
            </div>

            <div>
              <h3 class="flex items-center gap-2 text-base font-semibold text-[var(--color-text-primary)] mb-3">
                <svg class="w-5 h-5 text-[var(--color-primary)]" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path d="M4 6h16M4 12h16M4 18h16" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Fields
              </h3>
              <div class="space-y-2">
                <div v-for="field in fields" :key="field.name" class="p-3 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg hover:border-[var(--color-border-hover)] transition-colors">
                  <div class="flex items-center gap-2 mb-2">
                    <span class="text-base">{{ getFieldIcon(field) }}</span>
                    <span class="font-semibold text-[var(--color-text-primary)]">{{ field.name }}</span>
                    <span class="px-2 py-0.5 text-xs font-mono text-[var(--color-text-tertiary)] bg-[var(--color-surface-hover)] rounded">{{ field.type }}</span>
                  </div>
                  <div class="flex flex-wrap gap-2 text-xs text-[var(--color-text-secondary)]">
                    <span v-if="field.length"><strong>Length:</strong> {{ field.length }}</span>
                    <span v-if="field.nullable" class="px-2 py-0.5 bg-[var(--color-surface-hover)] rounded">nullable</span>
                    <span v-if="field.unique" class="px-2 py-0.5 bg-[var(--color-surface-hover)] rounded">unique</span>
                    <span v-if="entity.pk?.includes(field.name)" class="px-2 py-0.5 bg-[var(--color-primary)] text-white rounded">PK</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="w-[450px] flex flex-col bg-[var(--color-surface-raised)]">
            <!-- Tabs Header -->
            <div class="flex items-center border-b border-[var(--color-border)] bg-[var(--color-surface)]">
              <button
                @click="activeTab = 'comments'"
                class="flex-1 flex items-center justify-center gap-2 p-4 text-sm font-semibold transition-colors border-b-2"
                :class="activeTab === 'comments' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'"
              >
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke-width="2"/>
                </svg>
                <span>Comments</span>
                <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-[var(--color-primary-light)] text-[var(--color-primary)]">
                  {{ commentsStore.activeComments.length }}
                </span>
              </button>

              <button
                @click="activeTab = 'history'"
                class="flex-1 flex items-center justify-center gap-2 p-4 text-sm font-semibold transition-colors border-b-2"
                :class="activeTab === 'history' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'"
              >
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>History</span>
              </button>

              <button
                @click="activeTab = 'aliases'"
                class="flex-1 flex items-center justify-center gap-2 p-4 text-sm font-semibold transition-colors border-b-2"
                :class="activeTab === 'aliases' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'"
              >
                <span class="text-lg">🏷️</span>
                <span>Aliases</span>
                <span v-if="entity?.aliases?.length > 0" class="px-2 py-0.5 text-xs font-bold rounded-full bg-[var(--color-primary-light)] text-[var(--color-primary)]">
                  {{ entity.aliases.length }}
                </span>
              </button>
            </div>

            <!-- Comments Tab Content -->
            <div v-if="activeTab === 'comments'" class="flex flex-col flex-1 overflow-hidden">
              <div class="p-3 border-b border-[var(--color-border)]">
                <label class="flex items-center gap-2 text-xs text-[var(--color-text-secondary)] cursor-pointer">
                  <input type="checkbox" v-model="showSystemComments" class="w-4 h-4 accent-[var(--color-primary)] cursor-pointer"/>
                  <span>Show system comments</span>
                </label>
              </div>

              <div class="flex-1 overflow-y-auto p-4 space-y-3">
                <div v-if="filteredComments.length === 0" class="flex flex-col items-center justify-center h-full text-center text-[var(--color-text-tertiary)]">
                  <svg class="w-10 h-10 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke-width="2"/>
                  </svg>
                  <p class="text-sm font-medium">No comments yet</p>
                  <span class="text-xs">Be the first to add a comment!</span>
                </div>

                <div v-for="comment in filteredComments" :key="comment.id" class="p-3 rounded-lg" :class="comment.isSystem ? 'bg-[var(--color-warning-light)] border border-[var(--color-warning)]/20' : 'bg-[var(--color-surface)] border border-[var(--color-border)]'">
                  <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center gap-2">
                      <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white" :class="comment.isSystem ? 'bg-[var(--color-warning)]' : 'bg-[var(--color-primary)]'">
                        {{ comment.author.charAt(0).toUpperCase() }}
                      </div>
                      <div>
                        <div class="text-sm font-semibold text-[var(--color-text-primary)]">{{ comment.author }}</div>
                        <div class="text-xs text-[var(--color-text-tertiary)]">{{ formatDate(comment.createdAt) }}</div>
                      </div>
                    </div>
                    <button @click="deleteComment(comment.id)" class="p-1 text-[var(--color-text-tertiary)] hover:text-[var(--color-danger)] transition-colors">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M3 6h18M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6" stroke-width="2"/>
                      </svg>
                    </button>
                  </div>
                  <CommentText :text="comment.content" :is-system="comment.isSystem"/>
                </div>
              </div>

              <div class="p-4 border-t border-[var(--color-border)] bg-[var(--color-surface)]">
                <MentionTextarea v-model="newCommentText" placeholder="Write a comment... (/ to mention)" :disabled="isSubmitting" :users="commentsStore.users" :fields="entity?.fields || []"/>
                <div class="flex items-center justify-between mt-2">
                  <span class="text-xs" :class="newCommentText.length > 500 ? 'text-[var(--color-danger)] font-semibold' : 'text-[var(--color-text-tertiary)]'">
                    {{ newCommentText.length }}/500
                  </span>
                  <button @click="submitComment" :disabled="!newCommentText.trim() || isSubmitting || newCommentText.length > 500" class="flex items-center gap-2 px-4 py-2 bg-[var(--color-primary)] hover:bg-[var(--color-primary-hover)] text-white text-sm font-medium rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg v-if="!isSubmitting" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke-width="2"/>
                    </svg>
                    <div v-else class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                    {{ isSubmitting ? 'Sending...' : 'Send' }}
                  </button>
                </div>
              </div>
            </div>

            <!-- History Tab Content -->
            <div v-if="activeTab === 'history'" class="flex-1 overflow-y-auto p-4">
              <MigrationTimeline v-if="entity" :entity-fqcn="entity.fqcn" />
            </div>

            <!-- Aliases Tab Content -->
            <div v-if="activeTab === 'aliases'" class="flex-1 overflow-y-auto">
              <AliasesTab v-if="entity" :entity="entity" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
  transition: all 0.3s ease;
}

.drawer-enter-from,
.drawer-leave-to {
  opacity: 0;
}

.drawer-enter-from > div,
.drawer-leave-to > div {
  transform: translateX(100%);
}
</style>
