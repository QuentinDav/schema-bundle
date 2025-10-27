import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useToastStore } from './toast'

export const useReleasesStore = defineStore('releases', () => {
  const toastStore = useToastStore()
  const releases = ref([])
  const currentRelease = ref(null)
  const selectedReleaseDetail = ref(null)
  const compareReleases = ref(null)
  const entityHistory = ref(null)
  const loading = ref(false)
  const error = ref(null)

  const isLiveMode = computed(() => currentRelease.value === null)

  const sortedReleases = computed(() => {
    return [...releases.value].sort((a, b) =>
      new Date(b.created_at) - new Date(a.created_at)
    )
  })

  async function fetchReleases() {
    loading.value = true
    error.value = null

    try {
      const res = await fetch('/schema-doc/api/releases')
      if (!res.ok) throw new Error('Failed to fetch releases')
      const data = await res.json()
      releases.value = data.releases || []
    } catch (e) {
      error.value = e.message
      console.error('Error fetching releases:', e)
      toastStore.error('Failed to load releases')
      releases.value = []
    } finally {
      loading.value = false
    }
  }

  async function createRelease(name = null, description = null, versionType = 'minor') {
    loading.value = true
    error.value = null

    try {
      const res = await fetch('/schema-doc/api/releases', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ version_type: versionType, description }),
      })

      if (!res.ok) throw new Error('Failed to create release')
      const result = await res.json()

      await fetchReleases()

      toastStore.success(`Release ${result.release_name} created successfully`)
      return result
    } catch (e) {
      error.value = e.message
      console.error('Error creating release:', e)
      toastStore.error(e.message || 'Failed to create release')
      throw e
    } finally {
      loading.value = false
    }
  }

  async function fetchReleaseDetail(releaseId) {
    loading.value = true
    error.value = null

    try {
      const res = await fetch(`/schema-doc/api/releases/${releaseId}`)
      if (!res.ok) throw new Error('Failed to fetch release detail')
      const data = await res.json()
      selectedReleaseDetail.value = data
      return data
    } catch (e) {
      error.value = e.message
      console.error('Error fetching release detail:', e)
      toastStore.error('Failed to load release details')
      throw e
    } finally {
      loading.value = false
    }
  }

  async function compareReleasesAction(releaseId1, releaseId2) {
    loading.value = true
    error.value = null

    try {
      const res = await fetch(
        `/schema-doc/api/releases/compare/${releaseId1}/${releaseId2}`
      )
      if (!res.ok) throw new Error('Failed to compare releases')
      const data = await res.json()
      compareReleases.value = data
      return data
    } catch (e) {
      error.value = e.message
      console.error('Error comparing releases:', e)
      toastStore.error('Failed to compare releases')
      throw e
    } finally {
      loading.value = false
    }
  }

  async function fetchEntityHistory(entityFqcn) {
    loading.value = true
    error.value = null

    try {
      const encodedFqcn = encodeURIComponent(entityFqcn)
      const res = await fetch(
        `/schema-doc/api/snapshots/entity/${encodedFqcn}`
      )
      if (!res.ok) throw new Error('Failed to fetch entity history')
      const data = await res.json()
      entityHistory.value = data
      return data
    } catch (e) {
      error.value = e.message
      console.error('Error fetching entity history:', e)
      throw e
    } finally {
      loading.value = false
    }
  }

  function setCurrentRelease(release) {
    currentRelease.value = release
  }

  function clearCurrentRelease() {
    currentRelease.value = null
  }

  function clearReleaseDetail() {
    selectedReleaseDetail.value = null
  }

  function clearCompare() {
    compareReleases.value = null
  }

  function clearEntityHistory() {
    entityHistory.value = null
  }

  return {
    releases,
    currentRelease,
    selectedReleaseDetail,
    compareReleases,
    entityHistory,
    loading,
    error,

    isLiveMode,
    sortedReleases,

    fetchReleases,
    createRelease,
    fetchReleaseDetail,
    compareReleasesAction,
    fetchEntityHistory,
    setCurrentRelease,
    clearCurrentRelease,
    clearReleaseDetail,
    clearCompare,
    clearEntityHistory,
  }
})
