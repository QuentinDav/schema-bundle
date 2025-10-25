<script setup>
import {computed, ref, watch} from 'vue'
import {useSchemaStore, getRelationTypeName} from '@/stores/schema'
import {useCommentsStore} from '@/stores/comments'
import MentionTextarea from './MentionTextarea.vue'
import CommentText from './CommentText.vue'

const schemaStore = useSchemaStore()
const commentsStore = useCommentsStore()

const entity = computed(() => schemaStore.selectedEntity)

const newCommentText = ref('')
const isSubmitting = ref(false)
const showSystemComments = ref(true)

const filteredComments = computed(() => {
  if (showSystemComments.value) {
    return commentsStore.activeComments
  }
  return commentsStore.activeComments.filter((c) => !c.isSystem)
})

watch(entity, (newEntity) => {
  if (newEntity) {
    commentsStore.setActiveTarget('table', newEntity.fqcn)
    commentsStore.fetchComments(newEntity.fqcn)
  } else {
    commentsStore.clearActiveTarget()
  }
}, {immediate: true})

const relations = computed(() => {
  if (!entity.value) return []
  return entity.value.relations || []
})

const fields = computed(() => {
  if (!entity.value) return []
  return entity.value.fields || []
})

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
  const colors = {
    1: '#10b981', // OneToOne
    2: '#3b82f6', // ManyToOne
    4: '#f59e0b', // OneToMany
    8: '#ef4444', // ManyToMany
  }
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
    <div v-if="entity" class="drawer-overlay" @click.self="close">
      <div class="drawer-container">
        <div class="drawer-header">
          <div class="header-content">
            <div class="entity-icon">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path
                  d="M3 6a3 3 0 013-3h12a3 3 0 013 3v12a3 3 0 01-3 3H6a3 3 0 01-3-3V6z"
                  opacity="0.2"
                />
                <path
                  d="M3 6a3 3 0 013-3h12a3 3 0 013 3v3H3V6zm0 5h18v7a3 3 0 01-3 3H6a3 3 0 01-3-3v-7z"
                />
              </svg>
            </div>
            <div>
              <h2 class="entity-name">{{ entity.name }}</h2>
              <p class="entity-table" v-if="entity.table">{{ entity.table }}</p>
            </div>
          </div>
          <button @click="close" class="close-btn">
            <svg width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" fill="none">
              <path d="M6 6l12 12M18 6L6 18" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </button>
        </div>

        <div class="drawer-body">
          <!-- Left: Table Info -->
          <div class="drawer-content">
            <!-- Stats Section -->
            <div class="stats-section">
              <div class="stat-card">
                <div class="stat-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path
                      d="M4 6h16M4 12h16M4 18h16"
                      stroke-width="2"
                      stroke-linecap="round"
                    />
                  </svg>
                </div>
                <div>
                  <div class="stat-value">{{ fields.length }}</div>
                  <div class="stat-label">Total Fields</div>
                </div>
              </div>

              <div class="stat-card">
                <div class="stat-icon relation">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path
                      d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"
                      stroke-width="2"
                      stroke-linecap="round"
                    />
                    <path
                      d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"
                      stroke-width="2"
                      stroke-linecap="round"
                    />
                  </svg>
                </div>
                <div>
                  <div class="stat-value">{{ relations.length }}</div>
                  <div class="stat-label">Relations</div>
                </div>
              </div>

              <div class="stat-card">
                <div class="stat-icon regular">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                    <path d="M12 8v8" stroke-width="2" stroke-linecap="round"/>
                  </svg>
                </div>
                <div>
                  <div class="stat-value">{{ entity.pk?.length || 0 }}</div>
                  <div class="stat-label">Primary Keys</div>
                </div>
              </div>
            </div>

            <!-- Relations Section -->
            <div v-if="relations.length > 0" class="section">
              <h3 class="section-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path
                    d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"
                    stroke-width="2"
                    stroke-linecap="round"
                  />
                </svg>
                Relations
              </h3>
              <div class="fields-list">
                <div v-for="relation in relations" :key="relation.field"
                     class="field-card relation">
                  <div class="field-header">
                    <span class="field-icon">🔗</span>
                    <span class="field-name">{{ relation.field }}</span>
                    <span
                      class="relation-badge"
                      :style="{ background: getRelationTypeColor(relation.type) }"
                    >
                    {{ getRelationTypeName(relation.type) }}
                  </span>
                  </div>
                  <div class="field-meta">
                  <span class="meta-item">
                    <strong>Target:</strong>
                    {{ relation.target }}
                  </span>
                    <span v-if="relation.isOwning" class="meta-tag">owning side</span>
                    <span v-else class="meta-tag">inverse side</span>
                    <span v-if="relation.mappedBy" class="meta-tag">
                    mappedBy: {{ relation.mappedBy }}
                  </span>
                    <span v-if="relation.inversedBy" class="meta-tag">
                    inversedBy: {{ relation.inversedBy }}
                  </span>
                    <span v-if="relation.nullable" class="meta-tag">nullable</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Regular Fields Section -->
            <div class="section">
              <h3 class="section-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path
                    d="M4 6h16M4 12h16M4 18h16"
                    stroke-width="2"
                    stroke-linecap="round"
                  />
                </svg>
                Fields
              </h3>
              <div class="fields-list">
                <div v-for="field in fields" :key="field.name" class="field-card">
                  <div class="field-header">
                    <span class="field-icon">{{ getFieldIcon(field) }}</span>
                    <span class="field-name">{{ field.name }}</span>
                    <span class="field-type">{{ field.type }}</span>
                  </div>
                  <div class="field-meta">
                  <span v-if="field.length" class="meta-item">
                    <strong>Length:</strong>
                    {{ field.length }}
                  </span>
                    <span v-if="field.nullable" class="meta-tag">nullable</span>
                    <span v-if="field.unique" class="meta-tag">unique</span>
                    <span v-if="entity.pk?.includes(field.name)" class="meta-tag primary">
                    primary key
                  </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right: Comments -->
          <div class="drawer-comments">
            <div class="comments-header">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path
                  d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                  stroke-width="2"
                />
              </svg>
              <h4>Comments ({{ commentsStore.activeComments.length }})</h4>
            </div>

            <div class="filter-bar">
              <label class="filter-toggle">
                <input type="checkbox" v-model="showSystemComments" />
                <span>Show system comments</span>
              </label>
            </div>

            <div class="comments-list">
              <div v-if="filteredComments.length === 0" class="empty-comments">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path
                    d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                    stroke-width="2"
                  />
                </svg>
                <p>{{ commentsStore.activeComments.length === 0 ? 'No comments yet' : 'No comments to display' }}</p>
                <span class="empty-hint">{{ commentsStore.activeComments.length === 0 ? 'Be the first to add a comment!' : 'Try showing system comments above' }}</span>
              </div>

              <div
                v-for="comment in filteredComments"
                :key="comment.id"
                class="comment-item"
                :class="{ 'system-comment': comment.isSystem }"
              >
                <div class="comment-header-item">
                  <div class="comment-author">
                    <div class="author-avatar">
                      {{ comment.author.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <span class="author-name">{{ comment.author }}</span>
                      <span class="comment-date">{{ formatDate(comment.createdAt) }}</span>
                    </div>
                  </div>
                  <button @click="deleteComment(comment.id)" class="delete-comment-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor">
                      <path
                        d="M3 6h18M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"
                        stroke-width="2"
                      />
                    </svg>
                  </button>
                </div>
                <CommentText :text="comment.content" :is-system="comment.isSystem"/>
              </div>
            </div>

            <div class="comments-form">
              <MentionTextarea
                v-model="newCommentText"
                placeholder="Write a comment... (/ to mention users or reference fields)"
                :disabled="isSubmitting"
                :users="commentsStore.users"
                :fields="entity?.fields || []"
              />
              <div class="form-footer">
              <span class="char-counter" :class="{ warning: newCommentText.length > 500 }">
                {{ newCommentText.length }}/500
              </span>
                <button
                  @click="submitComment"
                  class="submit-comment-btn"
                  :disabled="!newCommentText.trim() || isSubmitting || newCommentText.length > 500"
                >
                  <svg
                    v-if="!isSubmitting"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                  >
                    <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke-width="2"/>
                  </svg>
                  <span v-else class="spinner-tiny"></span>
                  {{ isSubmitting ? 'Sending...' : 'Send' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.drawer-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  justify-content: flex-end;
}

.drawer-container {
  width: 1200px;
  max-width: 95vw;
  background: white;
  box-shadow: -4px 0 24px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.drawer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 2rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.header-content {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
}

.entity-icon {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  flex-shrink: 0;
}

.entity-name {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0;
  line-height: 1.2;
}

.entity-table {
  font-size: 0.875rem;
  opacity: 0.9;
  font-family: 'Courier New', monospace;
  margin: 0.25rem 0 0 0;
}

.close-btn {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  color: white;
}

.close-btn:hover {
  background: rgba(255, 255, 255, 0.3);
}

.drawer-body {
  flex: 1;
  display: flex;
  overflow: hidden;
}

.drawer-content {
  flex: 1;
  overflow-y: auto;
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 2rem;
  border-right: 1px solid #e5e7eb;
}

.drawer-comments {
  width: 400px;
  display: flex;
  flex-direction: column;
  background: #f9fafb;
}

.comments-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1.5rem 1.5rem 1rem;
}

.comments-header svg {
  color: #667eea;
}

.comments-header h4 {
  font-size: 1rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.filter-bar {
  padding: 0.75rem 1.5rem;
  border-bottom: 1px solid #e5e7eb;
  background: white;
}

.filter-toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.8125rem;
  color: #374151;
  user-select: none;
}

.filter-toggle input[type='checkbox'] {
  width: 15px;
  height: 15px;
  cursor: pointer;
  accent-color: #667eea;
}

.filter-toggle span {
  font-weight: 500;
}

.comments-list {
  flex: 1;
  overflow-y: auto;
  padding: 1rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.empty-comments {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  text-align: center;
  color: #9ca3af;
  gap: 0.5rem;
}

.empty-comments svg {
  margin-bottom: 0.5rem;
}

.empty-comments p {
  font-size: 0.875rem;
  font-weight: 500;
  margin: 0;
}

.empty-hint {
  font-size: 0.75rem;
  color: #9ca3af;
}

.comment-item {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 1rem;
}

.comment-item.system-comment {
  background: #fef3c7;
  border-color: #fde68a;
}

.comment-item.system-comment .author-avatar {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.comment-header-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.75rem;
}

.comment-author {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.author-avatar {
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

.author-name {
  display: block;
  font-size: 0.875rem;
  font-weight: 600;
  color: #111827;
}

.comment-date {
  display: block;
  font-size: 0.75rem;
  color: #9ca3af;
}

.delete-comment-btn {
  background: none;
  border: none;
  padding: 0.25rem;
  cursor: pointer;
  color: #9ca3af;
  transition: color 0.2s ease;
}

.delete-comment-btn:hover {
  color: #ef4444;
}

.comment-text {
  font-size: 0.875rem;
  color: #374151;
  line-height: 1.6;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.comments-form {
  padding: 1rem 1.5rem;
  border-top: 1px solid #e5e7eb;
  background: white;
}

.comment-textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-family: inherit;
  font-size: 0.875rem;
  resize: vertical;
  min-height: 70px;
  transition: border-color 0.2s ease;
}

.comment-textarea:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.comment-textarea:disabled {
  background: #f3f4f6;
  cursor: not-allowed;
}

.form-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 0.75rem;
}

.char-counter {
  font-size: 0.75rem;
  color: #9ca3af;
}

.char-counter.warning {
  color: #ef4444;
  font-weight: 600;
}

.submit-comment-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.submit-comment-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.submit-comment-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.spinner-tiny {
  width: 12px;
  height: 12px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

.stats-section {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
}

.stat-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 10px;
  color: white;
  flex-shrink: 0;
}

.stat-icon.relation {
  background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
}

.stat-icon.regular {
  background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
  line-height: 1;
}

.stat-label {
  font-size: 0.75rem;
  color: #6b7280;
  margin-top: 0.25rem;
}

.section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 1.125rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.section-title svg {
  color: #667eea;
}

.fields-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.field-card {
  padding: 1rem;
  background: white;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  transition: all 0.2s ease;
}

.field-card:hover {
  border-color: #d1d5db;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.field-card.relation {
  background: linear-gradient(to right, #fff7ed, white);
  border-color: #fed7aa;
}

.field-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.field-icon {
  font-size: 1.25rem;
  width: 24px;
  text-align: center;
  flex-shrink: 0;
}

.field-name {
  font-weight: 600;
  color: #111827;
  font-size: 1rem;
  flex: 1;
}

.field-type {
  font-family: 'Courier New', monospace;
  font-size: 0.75rem;
  color: #9ca3af;
  background: #f3f4f6;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
}

.relation-badge {
  font-size: 0.75rem;
  font-weight: 600;
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 6px;
  text-transform: capitalize;
}

.field-meta {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  font-size: 0.875rem;
}

.meta-item {
  color: #6b7280;
}

.meta-item strong {
  color: #374151;
}

.meta-tag {
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
  background: #f3f4f6;
  color: #6b7280;
  border-radius: 4px;
  font-weight: 500;
}

.meta-tag.primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

/* Transitions */
.drawer-enter-active,
.drawer-leave-active {
  transition: all 0.3s ease;
}

.drawer-enter-from,
.drawer-leave-to {
  opacity: 0;
}

.drawer-enter-from .drawer-container,
.drawer-leave-to .drawer-container {
  transform: translateX(100%);
}

@media (max-width: 768px) {
  .drawer-container {
    width: 100vw;
  }

  .drawer-body {
    flex-direction: column;
  }

  .drawer-comments {
    width: 100%;
    border-top: 1px solid #e5e7eb;
  }

  .drawer-content {
    border-right: none;
  }

  .stats-section {
    grid-template-columns: 1fr;
  }
}
</style>
