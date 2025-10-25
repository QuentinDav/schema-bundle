import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useToastStore } from './toast'

export const useCommentsStore = defineStore('comments', () => {
  const toastStore = useToastStore()

  // State
  const comments = ref([])
  const users = ref([
    // Mock users - TODO: Replace with real API call
    { id: 1, name: 'Alice Dupont', username: 'alice' },
    { id: 2, name: 'Bob Martin', username: 'bob' },
    { id: 3, name: 'Charlie Bernard', username: 'charlie' },
    { id: 4, name: 'Diana Laurent', username: 'diana' },
    { id: 5, name: 'Eve Moreau', username: 'eve' },
  ])
  const loading = ref(false)
  const error = ref(null)
  const activeTarget = ref(null) // { type: 'table' | 'field', entityFqcn: string, fieldName?: string }

  // Getters
  const activeComments = computed(() => {
    if (!activeTarget.value) return []

    return comments.value.filter((comment) => {
      if (comment.targetType !== activeTarget.value.type) return false
      if (comment.entityFqcn !== activeTarget.value.entityFqcn) return false

      if (activeTarget.value.type === 'field') {
        return comment.fieldName === activeTarget.value.fieldName
      }

      return true
    })
  })

  const getCommentsCount = computed(() => {
    return (entityFqcn, fieldName = null) => {
      return comments.value.filter((comment) => {
        if (comment.entityFqcn !== entityFqcn) return false
        if (fieldName) {
          return comment.targetType === 'field' && comment.fieldName === fieldName
        }
        return comment.targetType === 'table'
      }).length
    }
  })

  const totalComments = computed(() => comments.value.length)

  // Actions
  async function fetchComments(entityFqcn = null, fieldName = null) {
    loading.value = true
    error.value = null

    try {
      // Build query params for filtering by entity
      const params = new URLSearchParams()
      if (entityFqcn) {
        params.append('entityFqcn', entityFqcn)
      }
      if (fieldName) {
        params.append('fieldName', fieldName)
      }

      const queryString = params.toString()
      const url = queryString
        ? `/schema-doc/api/comments?${queryString}`
        : null

      if(url === null){
        return;
      }

      const res = await fetch(url)
      if (!res.ok) throw new Error('API error')
      const data = await res.json()
      comments.value = data.comments || []

      // Mock data for now - remove when API is ready
      // comments.value = []
    } catch (e) {
      error.value = e.message
      console.error('Error fetching comments:', e)
      toastStore.error('Failed to load comments')
      // Set empty array on error to avoid stale data
      comments.value = []
    } finally {
      loading.value = false
    }
  }

  async function addComment(commentData) {
    try {
      // Prepare payload for backend API
      const payload = {
        body: commentData.content,
        entity_fqcn: commentData.entityFqcn,
        field_name: commentData.fieldName || null,
        target_type: commentData.targetType,
      }

      const res = await fetch('/schema-doc/api/comments', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })

      if (!res.ok) throw new Error('API error')
      const newComment = await res.json()

      // Add the new comment to local state
      comments.value.push(newComment)
      toastStore.success('Comment added successfully')
      return newComment
    } catch (e) {
      error.value = e.message
      console.error('Error adding comment:', e)
      toastStore.error('Failed to add comment')
      throw e
    }
  }

  async function deleteComment(commentId) {
    try {
      const res = await fetch(`/schema-doc/api/comments/${commentId}`, {
        method: 'DELETE',
      })
      if (!res.ok) throw new Error('API error')

      // Remove from local state after successful deletion
      comments.value = comments.value.filter((c) => c.id !== commentId)
      toastStore.success('Comment deleted successfully')
    } catch (e) {
      error.value = e.message
      console.error('Error deleting comment:', e)
      toastStore.error('Failed to delete comment')
      throw e
    }
  }

  function setActiveTarget(type, entityFqcn, fieldName = null) {
    activeTarget.value = { type, entityFqcn, fieldName }
  }

  function clearActiveTarget() {
    activeTarget.value = null
  }

  return {
    // State
    comments,
    users,
    loading,
    error,
    activeTarget,

    // Getters
    activeComments,
    getCommentsCount,
    totalComments,

    // Actions
    fetchComments,
    addComment,
    deleteComment,
    setActiveTarget,
    clearActiveTarget,
  }
})
