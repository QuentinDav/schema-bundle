import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useToastStore } from './toast'

export const useCommentsStore = defineStore('comments', () => {
  const toastStore = useToastStore()

  const comments = ref([])
  const users = ref([])
  const loading = ref(false)
  const loadingUsers = ref(false)
  const error = ref(null)
  const activeTarget = ref(null)

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

  async function fetchComments(entityFqcn = null, fieldName = null) {
    loading.value = true
    error.value = null

    try {
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
    } catch (e) {
      error.value = e.message
      console.error('Error fetching comments:', e)
      toastStore.error('Failed to load comments')
      comments.value = []
    } finally {
      loading.value = false
    }
  }

  async function addComment(commentData) {
    try {
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

      comments.value = comments.value.filter((c) => c.id !== commentId)
      toastStore.success('Comment deleted successfully')
    } catch (e) {
      error.value = e.message
      console.error('Error deleting comment:', e)
      toastStore.error('Failed to delete comment')
      throw e
    }
  }

  async function fetchUsers() {
    loadingUsers.value = true
    error.value = null

    try {
      const res = await fetch('/schema-doc/api/users')
      if (!res.ok) throw new Error('Failed to fetch users')

      const data = await res.json()

      users.value = (data.users || []).map(user => ({
        id: user.id,
        name: user.email,
        username: user.username || user.email?.split('@')[0] || `user${user.id}`,
        email: user.email,
      }))
    } catch (e) {
      error.value = e.message
      console.error('Error fetching users:', e)
      users.value = []
    } finally {
      loadingUsers.value = false
    }
  }

  function setActiveTarget(type, entityFqcn, fieldName = null) {
    activeTarget.value = { type, entityFqcn, fieldName }
  }

  function clearActiveTarget() {
    activeTarget.value = null
  }

  return {
    comments,
    users,
    loading,
    loadingUsers,
    error,
    activeTarget,

    activeComments,
    getCommentsCount,
    totalComments,

    fetchComments,
    fetchUsers,
    addComment,
    deleteComment,
    setActiveTarget,
    clearActiveTarget,
  }
})
