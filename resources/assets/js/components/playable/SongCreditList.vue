<template>
  <p v-if="!credits.length" class="text-k-fg-50">
    No credits yet. Look up the song on MusicBrainz in the Details tab to add them.
  </p>
  <dl v-else class="space-y-3">
    <div v-for="group in groups" :key="group.role">
      <dt class="text-k-fg-50 uppercase text-[0.8rem] tracking-wide">{{ group.role }}</dt>
      <dd v-for="credit in group.credits" :key="`${credit.name}-${credit.instrument}`" class="text-k-fg">
        {{ credit.name }}<span v-if="credit.instrument" class="text-k-fg-50"> ({{ credit.instrument }})</span>
      </dd>
    </div>
  </dl>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { groupBy } from 'lodash-es'

const props = defineProps<{ credits: SongCredit[] }>()

const groups = computed(() =>
  Object.entries(groupBy(props.credits, 'role')).map(([role, credits]) => ({ role, credits })),
)
</script>
