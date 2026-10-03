<template>
  <AlbumGenreSuggestions v-if="suggesting" :album @applied="emit('done')" @back="suggesting = false" />
  <GenrePicker v-else :current clearable @select="select">
    <template #footer>
      <button
        class="flex items-center gap-2 w-full px-4 py-2 border-t border-k-fg-10 text-left text-k-highlight hover:bg-k-highlight hover:text-k-highlight-fg"
        type="button"
        @click.stop="suggesting = true"
      >
        <Icon :icon="faWandMagicSparkles" />
        Suggest genres…
      </button>
    </template>
  </GenrePicker>
</template>

<script lang="ts" setup>
import { faWandMagicSparkles } from '@fortawesome/free-solid-svg-icons'
import { ref } from 'vue'
import { useAlbumGenre } from '@/composables/useAlbumGenre'

import AlbumGenreSuggestions from '@/components/album/AlbumGenreSuggestions.vue'
import GenrePicker from '@/components/genre/GenrePicker.vue'

const props = defineProps<{ album: Album; current?: string | null; suggest?: boolean }>()
const emit = defineEmits<{ (e: 'done'): void }>()

const { setAlbumGenre } = useAlbumGenre()

const suggesting = ref(props.suggest ?? false)

const select = async (genre: string) => {
  if (await setAlbumGenre(props.album, genre)) {
    emit('done')
  }
}
</script>
