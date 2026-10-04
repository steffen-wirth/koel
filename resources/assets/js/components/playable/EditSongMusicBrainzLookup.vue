<template>
  <div class="space-y-3">
    <Btn
      type="button"
      variant="ghost"
      class="border border-k-fg-10"
      :disabled="loading || !title"
      @click.prevent="lookUp"
    >
      {{ loading ? 'Looking up…' : 'Look up on MusicBrainz' }}
    </Btn>

    <p v-if="searched && !loading && !matches.length" class="text-k-fg-50" role="status">
      No matches found on MusicBrainz.
    </p>

    <ul v-if="matches.length" class="space-y-1" aria-label="MusicBrainz matches">
      <li v-for="(match, index) in matches" :key="index" class="flex items-stretch gap-1">
        <button
          type="button"
          class="flex-1 text-left px-3 py-2 rounded-md bg-k-fg-5 hover:bg-k-fg-10"
          @click.prevent="choose(match)"
        >
          <span class="block text-k-fg">{{ match.title }} – {{ match.artist_name }}</span>
          <span class="block text-k-fg-50">
            {{ match.album_name }}<template v-if="match.year"> ({{ match.year }})</template>
            <template v-if="match.genre"> · {{ match.genre }}</template>
          </span>
        </button>
        <a
          :href="match.url"
          target="_blank"
          rel="noopener noreferrer"
          class="px-3 flex items-center rounded-md bg-k-fg-5 hover:bg-k-fg-10 text-k-fg-70"
          :aria-label="`Open ${match.title} on MusicBrainz`"
          title="Open on MusicBrainz"
        >
          <ExternalLinkIcon :size="16" />
        </a>
      </li>
    </ul>
  </div>
</template>

<script lang="ts" setup>
import { ExternalLinkIcon } from 'lucide-vue-next'
import { ref } from 'vue'
import { http } from '@/services/http'
import { useErrorHandler } from '@/composables/useErrorHandler'
import type { SongUpdateData } from '@/stores/playableStore'

import Btn from '@/components/ui/form/Btn.vue'

const props = defineProps<{ title?: string; artist?: string; album?: string }>()

export type MusicBrainzMatch = SongUpdateData & { url: string }

const emit = defineEmits<{ (e: 'apply', match: MusicBrainzMatch): void }>()

const loading = ref(false)
const searched = ref(false)
const matches = ref<MusicBrainzMatch[]>([])

const choose = async (match: MusicBrainzMatch) => {
  let credits: SongCredit[] = []

  try {
    if (match.mbid) {
      credits = (await http.get<{ credits: SongCredit[] }>(`musicbrainz/recordings/${match.mbid}/credits`)).credits
    }
  } catch (error: unknown) {
    useErrorHandler('toast').handleHttpError(error)
  }

  emit('apply', { ...match, credits })
}

const lookUp = async () => {
  loading.value = true

  try {
    const query = new URLSearchParams({ title: props.title ?? '' })
    props.artist && query.set('artist', props.artist)
    props.album && query.set('album', props.album)

    matches.value = (await http.get<{ matches: MusicBrainzMatch[] }>(`musicbrainz/songs?${query}`)).matches
    searched.value = true
  } catch (error: unknown) {
    useErrorHandler('toast').handleHttpError(error)
  } finally {
    loading.value = false
  }
}
</script>
