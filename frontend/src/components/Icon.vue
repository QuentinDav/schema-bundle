<script setup>
import { computed, defineAsyncComponent } from 'vue'

const props = defineProps({
  name: {
    type: String,
    required: true,
  },
  size: {
    type: [String, Number],
    default: 20,
  },
})

const iconMap = {
  'arrow-down-circle': 'ArrowDownCircleIcon',
  'arrow-down-tray': 'ArrowDownTrayIcon',
  'arrow-path': 'ArrowPathIcon',
  'arrow-right': 'ArrowRightIcon',
  'arrow-up-circle': 'ArrowUpCircleIcon',
  'arrows-pointing-out': 'ArrowsPointingOutIcon',
  'bolt': 'BoltIcon',
  'check': 'CheckIcon',
  'check-circle': 'CheckCircleIcon',
  'chevron-down': 'ChevronDownIcon',
  'chevron-right': 'ChevronRightIcon',
  'chevron-up': 'ChevronUpIcon',
  'clipboard': 'ClipboardIcon',
  'clock': 'ClockIcon',
  'code-bracket': 'CodeBracketIcon',
  'command-line': 'CommandLineIcon',
  'exclamation-circle': 'ExclamationCircleIcon',
  'exclamation-triangle': 'ExclamationTriangleIcon',
  'eye': 'EyeIcon',
  'folder': 'FolderIcon',
  'funnel': 'FunnelIcon',
  'inbox': 'InboxIcon',
  'information-circle': 'InformationCircleIcon',
  'key': 'KeyIcon',
  'light-bulb': 'LightBulbIcon',
  'link': 'LinkIcon',
  'list-bullet': 'ListBulletIcon',
  'magnifying-glass': 'MagnifyingGlassIcon',
  'map': 'MapIcon',
  'minus': 'MinusIcon',
  'photo': 'PhotoIcon',
  'plus': 'PlusIcon',
  'plus-circle': 'PlusCircleIcon',
  'sparkles': 'SparklesIcon',
  'squares-2x2': 'Squares2X2Icon',
  'squares-plus': 'SquaresPlusIcon',
  'star': 'StarIcon',
  'table-cells': 'TableCellsIcon',
  'user-group': 'UserGroupIcon',
  'x-mark': 'XMarkIcon',
}

const iconComponent = computed(() => {
  const heroiconName = iconMap[props.name]

  if (!heroiconName) {
    console.warn(`Icon "${props.name}" not found in iconMap`)
    return null
  }

  return defineAsyncComponent(() =>
    import('@heroicons/vue/24/outline').then((module) => module[heroiconName])
  )
})

const sizeValue = computed(() => {
  return typeof props.size === 'number' ? `${props.size}px` : props.size
})
</script>

<template>
  <component
    v-if="iconComponent"
    :is="iconComponent"
    :style="{ width: sizeValue, height: sizeValue }"
    class="inline-block align-middle flex-shrink-0"
  />
  <span v-else class="inline-block align-middle" :style="{ width: sizeValue, height: sizeValue }">?</span>
</template>
