<template>
  <div @keydown.esc="emit('close')">
    <header>
      <h1>Set Genre for All Songs</h1>
    </header>

    <main>
      <p class="text-k-fg-70 mb-3">Pick a genre for every song of “{{ album.name }}”.</p>
      <div class="rounded-md border border-k-fg-10 bg-k-bg-context-menu w-fit">
        <GenrePicker clearable @select="onSelect" />
      </div>
    </main>

    <footer>
      <Btn variant="ghost" @click.prevent="emit('close')">Cancel</Btn>
    </footer>
  </div>
</template>

<script lang="ts" setup>
import { useAlbumGenre } from '@/composables/useAlbumGenre'

import Btn from '@/components/ui/form/Btn.vue'
import GenrePicker from '@/components/genre/GenrePicker.vue'

const props = defineProps<{ album: Album }>()
const emit = defineEmits<{ (e: 'close'): void }>()

const { setAlbumGenre } = useAlbumGenre()

const onSelect = async (genre: string) => {
  if (await setAlbumGenre(props.album, genre)) {
    emit('close')
  }
}
</script>
