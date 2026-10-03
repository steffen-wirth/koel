<template>
  <span class="block">
    <button
      ref="button"
      :title
      class="block w-full truncate text-left hover:text-k-highlight focus:text-k-highlight"
      type="button"
      @click.stop
      @dblclick.stop
    >
      <slot>{{ current || '—' }}</slot>
    </button>
    <Popover ref="popover" :anchor="button" placement="bottom-start" class="context-menu" @toggle="open = $event">
      <GenrePicker v-if="open" :current :clearable @select="onSelect" />
    </Popover>
  </span>
</template>

<script lang="ts" setup>
import { ref } from 'vue'

import Popover from '@/components/ui/Popover.vue'
import GenrePicker from '@/components/genre/GenrePicker.vue'

defineProps<{ title: string; current?: string | null; clearable?: boolean }>()
const emit = defineEmits<{ (e: 'select', genre: string): void }>()

const button = ref<HTMLButtonElement>()
const popover = ref<InstanceType<typeof Popover>>()
const open = ref(false)

const onSelect = (genre: string) => {
  popover.value?.hide()
  emit('select', genre)
}
</script>
