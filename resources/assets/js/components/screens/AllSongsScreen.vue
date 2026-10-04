<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader :disabled="loading" :layout="songs.length ? headerLayout : 'collapsed'">
        All Songs

        <template #thumbnail>
          <ThumbnailStack :thumbnails />
        </template>

        <template v-if="totalSongCount" #meta>
          <span>{{ pluralize(totalSongCount, 'song') }}</span>
          <span>{{ totalDuration }}</span>
        </template>

        <template #controls>
          <div class="controls w-full flex justify-between items-center gap-4">
            <SongListControls v-if="totalSongCount" :config @play-all="playAll" @play-selected="playSelected" />
            <Btn
              v-if="totalSongCount"
              v-koel-tooltip
              :class="activeFilterCount && 'text-k-highlight'"
              class="border border-k-fg-10"
              title="Filter songs"
              variant="ghost"
              @click.prevent="filtersOpen = !filtersOpen"
            >
              <Icon :icon="faFilter" />
              <span v-if="activeFilterCount" class="ml-1.5">{{ activeFilterCount }}</span>
            </Btn>
          </div>
        </template>
      </ScreenHeader>
    </template>

    <SongListSkeleton v-if="showSkeletons" class="-m-6" role="status" aria-busy="true" aria-label="Loading" />
    <template v-else>
      <SongList
        v-if="songs?.length > 0"
        ref="songList"
        class="-m-6"
        @sort="sort"
        @swipe="onSwipe"
        @press:enter="onPressEnter"
        @scrolled-to-end="fetchSongs"
      />
      <ScreenEmptyState v-else>
        <template #icon>
          <Icon :icon="faVolumeOff" />
        </template>
        {{ activeFilterCount ? 'No songs match the filters.' : 'Your library is empty.' }}
      </ScreenEmptyState>
    </template>

    <SlideInSidebar :open="filtersOpen" title="Filters" @close="filtersOpen = false">
      <SongFilterPanel v-model="filters" />
    </SlideInSidebar>
  </ScreenBase>
</template>

<script lang="ts" setup>
import { faFilter, faVolumeOff } from '@fortawesome/free-solid-svg-icons'
import { computed, onMounted, reactive, ref, toRef, watch } from 'vue'
import { pluralize, secondsToHumanReadable } from '@/utils/formatters'
import { commonStore } from '@/stores/commonStore'
import { queueStore } from '@/stores/queueStore'
import { playableStore } from '@/stores/playableStore'
import { useRouter } from '@/composables/useRouter'
import { useErrorHandler } from '@/composables/useErrorHandler'
import { usePlayableList } from '@/composables/usePlayableList'
import { usePlayableListControls } from '@/composables/usePlayableListControls'
import { useLocalStorage } from '@/composables/useLocalStorage'
import { playback } from '@/services/playbackManager'

import ScreenHeader from '@/components/ui/ScreenHeader.vue'
import SongListSkeleton from '@/components/playable/playable-list/PlayableListSkeleton.vue'
import ScreenEmptyState from '@/components/ui/ScreenEmptyState.vue'
import ScreenBase from '@/components/screens/ScreenBase.vue'
import Btn from '@/components/ui/form/Btn.vue'
import SlideInSidebar from '@/components/ui/SlideInSidebar.vue'
import SongFilterPanel from '@/components/playable/SongFilterPanel.vue'

const totalSongCount = toRef(commonStore.state, 'song_count')
const totalDuration = computed(() => secondsToHumanReadable(commonStore.state.song_length))

const {
  PlayableList: SongList,
  ThumbnailStack,
  headerLayout,
  thumbnails,
  playables: songs,
  playableList: songList,
  onPressEnter,
  playSelected,
  onSwipe,
  sort: composableSort,
} = usePlayableList(toRef(playableStore.state, 'playables'), { type: 'Songs' }, { filterable: false, sortable: true })

const { PlayableListControls: SongListControls, config } = usePlayableListControls('Songs')
const { go, url } = useRouter()
const { get: lsGet, set: lsSet } = useLocalStorage()

const loading = ref(false)
const filtersOpen = ref(false)
const filters = reactive<SongFilters>({ genre: '', formats: [], credit: '', credit_role: '' })
const activeFilterCount = computed(
  () => (filters.genre ? 1 : 0) + (filters.formats.length ? 1 : 0) + (filters.credit ? 1 : 0),
)
let sortField: MaybeArray<PlayableListSortField> = lsGet<PlayableListSortField>('all-songs-sort-field', 'title')!
let sortOrder: SortOrder = lsGet<SortOrder>('all-songs-sort-order', 'asc')!

const cursor = ref<string | null>('')
const moreSongsAvailable = computed(() => cursor.value !== null)
const showSkeletons = computed(() => loading.value && songs.value.length === 0)

const fetchSongs = async () => {
  if (!moreSongsAvailable.value || loading.value) {
    return
  }

  loading.value = true

  try {
    cursor.value = await playableStore.paginateSongs({
      sort: sortField,
      order: sortOrder,
      cursor: cursor.value,
      genre: filters.genre || undefined,
      formats: filters.formats,
      credit: filters.credit || undefined,
      credit_role: filters.credit ? filters.credit_role || undefined : undefined,
    })
  } catch (error: any) {
    useErrorHandler().handleHttpError(error)
  } finally {
    loading.value = false
  }
}

const playAll = async (shuffle: boolean) => {
  if (shuffle) {
    await queueStore.fetchRandom(500, filters)
  } else {
    await queueStore.fetchInOrder(Array.isArray(sortField) ? sortField[0] : sortField, sortOrder, 500, filters)
  }

  go(url('queue'))
  await playback().playFirstInQueue()
}

const sort = async (field: MaybeArray<PlayableListSortField>, order: SortOrder) => {
  cursor.value = ''
  playableStore.state.playables = []
  sortField = field
  sortOrder = order

  lsSet('all-songs-sort-field', field)
  lsSet('all-songs-sort-order', order)

  await fetchSongs()
}

const refetch = async () => {
  cursor.value = ''
  playableStore.state.playables = []

  await fetchSongs()
}

watch(filters, refetch, { deep: true })

onMounted(async () => {
  composableSort(sortField, sortOrder)
  await fetchSongs()
})
</script>

<style lang="postcss" scoped>
@reference '@css/app.pcss';
.collapsed .controls {
  @apply w-auto;
}
</style>
