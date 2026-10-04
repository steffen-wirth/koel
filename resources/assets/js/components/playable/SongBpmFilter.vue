<template>
  <fieldset>
    <legend class="mb-2 text-k-fg-70">BPM</legend>
    <div class="flex items-center gap-2">
      <TextInput
        v-model.number="min"
        aria-label="Minimum BPM"
        min="0"
        name="bpm_min"
        placeholder="From"
        type="number"
      />
      <span class="text-k-fg-50">–</span>
      <TextInput v-model.number="max" aria-label="Maximum BPM" min="0" name="bpm_max" placeholder="To" type="number" />
    </div>
  </fieldset>
</template>

<script lang="ts" setup>
import TextInput from '@/components/ui/form/TextInput.vue'

// An emptied input gives an empty string, which means "no limit".
const min = defineModel<number | '' | null>('min')
const max = defineModel<number | '' | null>('max')
</script>
