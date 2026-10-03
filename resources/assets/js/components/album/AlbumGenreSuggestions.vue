<template>
  <div class="w-64 p-3 space-y-3" @click.stop @dblclick.stop>
    <p v-if="loading" class="text-k-fg-50" role="status">Looking up genres…</p>
    <p v-else-if="!genres.length" class="text-k-fg-50">No genre suggestions found for this album.</p>
    <template v-else>
      <p class="text-k-fg-70">Suggested by MusicBrainz. They are added to the genres the songs already have.</p>
      <ul class="flex flex-wrap gap-2" aria-label="Suggested genres">
        <li v-for="genre in genres" :key="genre" class="px-2 py-0.5 rounded-sm bg-k-fg-10 text-k-fg">{{ genre }}</li>
      </ul>
    </template>

    <div class="flex gap-2">
      <Btn v-if="genres.length" :disabled="applying" @click.prevent="apply">Apply genres</Btn>
      <Btn variant="ghost" @click.prevent="emit('back')">Back</Btn>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { onMounted, ref } from 'vue'
import { useAlbumGenre } from '@/composables/useAlbumGenre'

import Btn from '@/components/ui/form/Btn.vue'

const props = defineProps<{ album: Album }>()
const emit = defineEmits<{ (e: 'back'): void; (e: 'applied'): void }>()

const { fetchSuggestions, addGenres } = useAlbumGenre()

const loading = ref(true)
const applying = ref(false)
const genres = ref<string[]>([])

const apply = async () => {
  applying.value = true

  try {
    if (await addGenres(props.album, genres.value)) {
      emit('applied')
    }
  } finally {
    applying.value = false
  }
}

onMounted(async () => {
  genres.value = await fetchSuggestions(props.album)
  loading.value = false
})
</script>
