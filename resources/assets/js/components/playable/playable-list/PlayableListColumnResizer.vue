<template>
  <span
    class="column-resizer"
    role="separator"
    aria-orientation="vertical"
    @click.stop
    @mousedown.prevent.stop="startResize"
  />
</template>

<script lang="ts" setup>
import { usePlayableListColumnWidths } from '@/composables/usePlayableListColumnWidths'

const props = defineProps<{ column: string }>()

const { setWidth, persist } = usePlayableListColumnWidths()

const startResize = (event: MouseEvent) => {
  const cell = (event.currentTarget as HTMLElement).parentElement!
  const startX = event.clientX
  const startWidth = cell.getBoundingClientRect().width

  const onMove = (e: MouseEvent) => setWidth(props.column, startWidth + e.clientX - startX)

  const onUp = () => {
    window.removeEventListener('mousemove', onMove)
    window.removeEventListener('mouseup', onUp)
    document.body.style.userSelect = ''
    persist()

    // releasing the mouse outside the handle makes the browser fire a click on the header cell (→ sort), swallow it
    const swallowClick = (e: MouseEvent) => e.stopPropagation()
    window.addEventListener('click', swallowClick, { capture: true, once: true })
    setTimeout(() => window.removeEventListener('click', swallowClick, { capture: true }), 0)
  }

  document.body.style.userSelect = 'none'
  window.addEventListener('mousemove', onMove)
  window.addEventListener('mouseup', onUp)
}
</script>

<style lang="postcss" scoped>
@reference '@css/app.pcss';
.column-resizer {
  @apply absolute top-0 right-0 h-full w-2 cursor-col-resize;

  &::after {
    content: '';
    @apply absolute right-0 top-1/4 h-1/2 w-px bg-k-fg-10;
  }

  &:hover::after {
    @apply bg-k-highlight w-0.5;
  }
}
</style>
