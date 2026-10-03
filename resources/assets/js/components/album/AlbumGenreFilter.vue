<template>
  <div class="flex items-center border border-k-fg-10 rounded-md h-full">
    <GenrePickerPopover :current="genre" class="min-w-0" title="Filter by genre">
      <span class="flex items-center gap-2 px-3 py-2" :class="genre && 'text-k-highlight'">
        <Icon :icon="faTags" />
        <span class="truncate max-w-40">{{ genre || 'Genre' }}</span>
      </span>
    </GenrePickerPopover>
    <button
      v-if="genre"
      class="px-2 text-k-fg-50 hover:text-k-fg"
      title="Clear genre filter"
      type="button"
      @click.prevent="genre = ''"
    >
      <Icon :icon="faTimes" />
    </button>
  </div>
</template>

<script lang="ts" setup>
import { faTags, faTimes } from '@fortawesome/free-solid-svg-icons'

import GenrePickerPopover from '@/components/genre/GenrePickerPopover.vue'

const genre = defineModel<string>({ default: '' })
</script>
