import { computed, ref } from 'vue'
import { useLocalStorage } from '@/composables/useLocalStorage'

const STORAGE_KEY = 'playable-list-column-widths'
const MIN_WIDTH = 40

const widths = ref<Record<string, number>>({})
let loaded = false

const load = () => {
  if (loaded) {
    return
  }

  loaded = true

  try {
    widths.value = useLocalStorage().get<Record<string, number>>(STORAGE_KEY, {}) ?? {}
  } catch {
    widths.value = {}
  }
}

export const usePlayableListColumnWidths = () => {
  load()

  // CSS custom properties consumed by the column rules in PlayableList.vue
  const cssVars = computed(() =>
    Object.fromEntries(Object.entries(widths.value).map(([column, width]) => [`--col-${column}`, `${width}px`])),
  )

  const setWidth = (column: string, width: number) => {
    widths.value = { ...widths.value, [column]: Math.max(MIN_WIDTH, Math.round(width)) }
  }

  const persist = () => {
    try {
      useLocalStorage().set(STORAGE_KEY, widths.value)
    } catch {}
  }

  return { cssVars, setWidth, persist }
}
