<template>
  <div class="w-64" @click.stop @dblclick.stop>
    <div class="p-2">
      <input
        ref="search"
        v-model="keyword"
        aria-label="Search genres"
        class="w-full px-3 py-1.5 rounded-sm bg-k-bg-input text-k-fg-input border border-k-fg-10"
        placeholder="Search genres…"
        type="search"
        @keydown.enter.prevent="matches.length && emit('select', matches[0])"
      />
    </div>
    <menu class="max-h-64 overflow-y-auto py-1" role="listbox" aria-label="Genres">
      <li
        v-for="genre in matches"
        :key="genre"
        :aria-selected="genre === current"
        :class="genre === current && 'text-k-highlight'"
        class="cursor-pointer hover:bg-k-highlight hover:text-k-highlight-fg px-4 py-1.5 leading-7"
        role="option"
        @click.stop="emit('select', genre)"
      >
        {{ genre }}
      </li>
      <li v-if="!matches.length" class="text-k-fg-50 px-4 py-1.5 leading-7">No matching genres</li>
      <li
        v-if="clearable && !keyword"
        class="cursor-pointer text-k-fg-50 hover:bg-k-highlight hover:text-k-highlight-fg px-4 py-1.5 leading-7"
        @click.stop="emit('select', '')"
      >
        Clear genre
      </li>
    </menu>
    <slot name="footer" />
  </div>
</template>

<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue'
import { genres as standardGenres } from '@/config/genres'
import { genreStore } from '@/stores/genreStore'

const props = defineProps<{ current?: string | null; clearable?: boolean; libraryOnly?: boolean }>()
const emit = defineEmits<{ (e: 'select', genre: string): void }>()

const search = ref<HTMLInputElement>()
const keyword = ref('')
const libraryGenres = ref<string[]>([])

const matches = computed(() => {
  const options = Array.from(
    new Set([...(props.libraryOnly ? [] : standardGenres), ...libraryGenres.value, props.current].filter(Boolean)),
  )
    .map(String)
    .sort((a, b) => a.localeCompare(b))

  const needle = keyword.value.trim().toLowerCase()

  return needle ? options.filter(genre => genre.toLowerCase().includes(needle)) : options
})

onMounted(async () => {
  search.value?.focus()

  try {
    libraryGenres.value = (await genreStore.fetchAll()).map(genre => genre.name)
  } catch {
    // The standard genres are still usable.
  }
})
</script>
