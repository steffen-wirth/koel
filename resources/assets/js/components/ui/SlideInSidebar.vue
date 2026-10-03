<template>
  <Transition name="slide-in">
    <aside
      v-if="open"
      :aria-label="title"
      class="absolute inset-y-0 right-0 z-20 w-72 max-w-full flex flex-col bg-k-bg-context-menu border-l border-k-fg-10 shadow-xl"
      @keydown.esc="emit('close')"
    >
      <header class="flex items-center justify-between gap-2 px-4 py-3 border-b border-k-fg-10">
        <h2 class="uppercase tracking-widest text-sm text-k-fg-70">{{ title }}</h2>
        <button class="p-1 text-k-fg-50 hover:text-k-fg" title="Close" type="button" @click.prevent="emit('close')">
          <Icon :icon="faTimes" />
        </button>
      </header>

      <div class="flex-1 overflow-y-auto p-4 space-y-5">
        <slot />
      </div>

      <footer v-if="$slots.footer" class="px-4 py-3 border-t border-k-fg-10">
        <slot name="footer" />
      </footer>
    </aside>
  </Transition>
</template>

<script lang="ts" setup>
import { faTimes } from '@fortawesome/free-solid-svg-icons'

defineProps<{ open: boolean; title: string }>()
const emit = defineEmits<{ (e: 'close'): void }>()
</script>

<style lang="postcss" scoped>
.slide-in-enter-active,
.slide-in-leave-active {
  transition: transform 0.2s ease;
}

.slide-in-enter-from,
.slide-in-leave-to {
  transform: translateX(100%);
}
</style>
