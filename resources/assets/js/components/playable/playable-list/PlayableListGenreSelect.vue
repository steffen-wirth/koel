<template>
  <GenrePickerPopover
    :current="song.genre"
    :title="`Change genre (${song.genre || 'none'})`"
    clearable
    @select="save"
  />
</template>

<script lang="ts" setup>
import { toRefs } from 'vue'
import { playableStore } from '@/stores/playableStore'
import { useErrorHandler } from '@/composables/useErrorHandler'
import { useMessageToaster } from '@/composables/useMessageToaster'
import { eventBus } from '@/utils/eventBus'

import GenrePickerPopover from '@/components/genre/GenrePickerPopover.vue'

const props = defineProps<{ song: Song }>()
const { song } = toRefs(props)

const { toastSuccess } = useMessageToaster()
const { handleHttpError } = useErrorHandler('toast')

const save = async (genre: string) => {
  if (genre === (song.value.genre ?? '')) {
    return
  }

  try {
    // A single-song update treats missing values as "clear", so all the other fields must be sent along.
    const result = await playableStore.updateSongs([song.value], {
      title: song.value.title,
      artist_name: song.value.artist_name,
      album_name: song.value.album_name,
      album_artist_name: song.value.album_artist_id === song.value.artist_id ? '' : song.value.album_artist_name,
      track: song.value.track || null,
      disc: song.value.disc || null,
      year: song.value.year,
      lyrics: song.value.lyrics,
      genre,
    })

    toastSuccess(genre ? `Genre set to ${genre}.` : 'Genre cleared.')
    eventBus.emit('SONGS_UPDATED', result)
  } catch (error: unknown) {
    handleHttpError(error)
  }
}
</script>
