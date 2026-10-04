<template>
  <div class="space-y-5">
    <section aria-labelledby="song-filter-genre">
      <h3 id="song-filter-genre" class="mb-2 text-k-fg-70">Genre</h3>
      <div class="rounded-md border border-k-fg-10">
        <GenrePicker :current="filters.genre" library-only @select="toggleGenre" />
      </div>
    </section>

    <fieldset>
      <legend class="mb-2 text-k-fg-70">Format</legend>
      <label v-for="format in formats" :key="format.value" class="flex items-center gap-2 py-1 cursor-pointer">
        <input v-model="filters.formats" :value="format.value" type="checkbox" />
        {{ format.label }}
      </label>
    </fieldset>

    <SongCreditFilter v-model:role="filters.credit_role" v-model:name="filters.credit" />

    <Btn v-if="filters.genre || filters.formats.length || filters.credit" variant="ghost" @click.prevent="reset"
      >Reset filters</Btn
    >
  </div>
</template>

<script lang="ts" setup>
import Btn from '@/components/ui/form/Btn.vue'
import SongCreditFilter from '@/components/playable/SongCreditFilter.vue'
import GenrePicker from '@/components/genre/GenrePicker.vue'

const filters = defineModel<SongFilters>({ required: true })

const formats: { value: SongFormat; label: string }[] = [
  { value: 'flac', label: 'FLAC' },
  { value: 'mp3', label: 'MP3' },
]

// Picking the active genre again clears it.
const toggleGenre = (genre: string) => (filters.value.genre = genre === filters.value.genre ? '' : genre)

const reset = () => {
  filters.value.genre = ''
  filters.value.formats = []
  filters.value.credit = ''
  filters.value.credit_role = ''
}
</script>
