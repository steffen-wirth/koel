<template>
  <section class="space-y-4 rounded-md border border-k-fg-10 p-4" aria-labelledby="smart-playlist-selection">
    <h3 id="smart-playlist-selection" class="text-k-fg-70">Selection</h3>
    <p class="text-k-fg-50">Narrows down the songs matched by all the groups below.</p>

    <FormRow>
      <template #label>Genre</template>
      <GenrePickerPopover title="Pick a genre" :current="selection.genre" clearable @select="selection.genre = $event">
        <span class="block px-3.5 py-2 rounded-sm bg-k-bg-input text-k-fg-input border border-k-fg-10">
          {{ selection.genre || 'Any genre' }}
        </span>
        <template #picker="{ close }">
          <GenrePicker
            :current="selection.genre"
            clearable
            library-only
            @select="
              (genre: string) => {
                selection.genre = genre
                close()
              }
            "
          />
        </template>
      </GenrePickerPopover>
    </FormRow>

    <SongBpmFilter v-model:min="selection.bpm_min" v-model:max="selection.bpm_max" />

    <FormRow>
      <template #label>Maximum number of songs</template>
      <TextInput
        v-model.number="selection.max_songs"
        max="500"
        min="1"
        name="max_songs"
        placeholder="No limit"
        type="number"
      />
    </FormRow>

    <label class="flex items-center cursor-pointer">
      <CheckBox v-model="selection.randomize" name="randomize" />
      Pick the songs at random
    </label>
  </section>
</template>

<script lang="ts" setup>
import CheckBox from '@/components/ui/form/CheckBox.vue'
import FormRow from '@/components/ui/form/FormRow.vue'
import TextInput from '@/components/ui/form/TextInput.vue'
import GenrePicker from '@/components/genre/GenrePicker.vue'
import GenrePickerPopover from '@/components/genre/GenrePickerPopover.vue'
import SongBpmFilter from '@/components/playable/SongBpmFilter.vue'

const selection = defineModel<SmartPlaylistSelection>({ required: true })
</script>
