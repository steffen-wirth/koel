<template>
  <div @keydown.esc="emit('close')">
    <header>
      <h1>{{ suggest ? 'Suggest Genres' : 'Set Genre for All Songs' }}</h1>
    </header>

    <main>
      <p class="text-k-fg-70 mb-3">For “{{ album.name }}”.</p>
      <div class="rounded-md border border-k-fg-10 bg-k-bg-context-menu w-fit">
        <AlbumGenrePicker :album :suggest @done="emit('close')" />
      </div>
    </main>

    <footer>
      <Btn variant="ghost" @click.prevent="emit('close')">Cancel</Btn>
    </footer>
  </div>
</template>

<script lang="ts" setup>
import Btn from '@/components/ui/form/Btn.vue'
import AlbumGenrePicker from '@/components/album/AlbumGenrePicker.vue'

defineProps<{ album: Album; suggest?: boolean }>()
const emit = defineEmits<{ (e: 'close'): void }>()
</script>
