<template>
  <fieldset class="space-y-2">
    <legend class="mb-2 text-k-fg-70">Credits</legend>
    <SelectBox v-model="role" aria-label="Credit role">
      <option value="">Any role</option>
      <option v-for="(label, value) in roles" :key="value" :value>{{ label }}</option>
    </SelectBox>
    <TextInput v-model="name" list="song-credit-names" name="credit" placeholder="Name, e.g. a composer" />
    <datalist id="song-credit-names">
      <option v-for="creditName in names" :key="creditName" :value="creditName" />
    </datalist>
  </fieldset>
</template>

<script lang="ts" setup>
import { ref, watch } from 'vue'
import { http } from '@/services/http'

import SelectBox from '@/components/ui/form/SelectBox.vue'
import TextInput from '@/components/ui/form/TextInput.vue'

const role = defineModel<string>('role', { default: '' })
const name = defineModel<string>('name', { default: '' })

const roles: Record<string, string> = {
  composer: 'Composer',
  lyricist: 'Lyricist',
  producer: 'Producer',
  arranger: 'Arranger',
  conductor: 'Conductor',
  mix: 'Mixer',
  engineer: 'Engineer',
  instrument: 'Instrumentalist',
  vocal: 'Vocalist',
}

const names = ref<string[]>([])

watch(
  role,
  async value => {
    try {
      names.value = (await http.get<{ names: string[] }>(`song-credits/names${value ? `?role=${value}` : ''}`)).names
    } catch {
      names.value = []
    }
  },
  { immediate: true },
)
</script>
