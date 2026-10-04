import { ref } from 'vue'
import { playlistStore } from '@/stores/playlistStore'

import Btn from '@/components/ui/form/Btn.vue'
import RuleGroup from '@/components/playlist/smart-playlist/SmartPlaylistRuleGroup.vue'
import SoundBars from '@/components/ui/SoundBars.vue'

type SmartPlaylistFormTab = 'details' | 'rules'

export const createEmptySmartPlaylistSelection = (): SmartPlaylistSelection => ({
  genre: '',
  bpm_min: '',
  bpm_max: '',
  max_songs: '',
  randomize: false,
})

export const isSmartPlaylistSelectionEmpty = (selection: SmartPlaylistSelection) =>
  !selection.genre &&
  selection.bpm_min === '' &&
  selection.bpm_max === '' &&
  selection.max_songs === '' &&
  !selection.randomize

export const useSmartPlaylistForm = (
  initialRuleGroups: SmartPlaylistRuleGroup[] = [],
  initialSelection: SmartPlaylistSelection | null = null,
) => {
  const currentTab = ref<SmartPlaylistFormTab>('details')
  const activateTab = (tab: SmartPlaylistFormTab) => (currentTab.value = tab)
  const isTabActive = (tab: SmartPlaylistFormTab) => currentTab.value === tab

  const collectedRuleGroups = ref<SmartPlaylistRuleGroup[]>(initialRuleGroups)

  // The API leaves unset values as null, the inputs want empty strings.
  const selection = ref<SmartPlaylistSelection>({
    ...createEmptySmartPlaylistSelection(),
    ...Object.fromEntries(Object.entries(initialSelection ?? {}).map(([key, value]) => [key, value ?? ''])),
    randomize: Boolean(initialSelection?.randomize),
  })

  /** The selection as sent to the API: null when there is nothing to narrow down. */
  const serializedSelection = () => (isSmartPlaylistSelectionEmpty(selection.value) ? null : { ...selection.value })

  const addGroup = () => collectedRuleGroups.value.push(playlistStore.createEmptySmartPlaylistRuleGroup())

  const onGroupChanged = (data: SmartPlaylistRuleGroup) => {
    const changedGroup = Object.assign(collectedRuleGroups.value.find(({ id }) => id === data.id)!, data)

    // Remove empty groups
    if (changedGroup.rules.length === 0) {
      collectedRuleGroups.value = collectedRuleGroups.value.filter(({ id }) => id !== changedGroup.id)
    }
  }

  return {
    Btn,
    RuleGroup,
    SoundBars,
    currentTab,
    activateTab,
    isTabActive,
    collectedRuleGroups,
    selection,
    serializedSelection,
    addGroup,
    onGroupChanged,
  }
}
