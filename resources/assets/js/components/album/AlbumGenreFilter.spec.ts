import { describe, expect, it, vi } from 'vite-plus/test'
import { screen } from '@testing-library/vue'
import userEvent from '@testing-library/user-event'
import { createHarness } from '@/__tests__/TestHarness'
import Component from './AlbumGenreFilter.vue'

vi.mock('@/components/genre/GenrePickerPopover.vue', () => ({
  default: {
    emits: ['select'],
    template: `<div><slot /><button @click="$emit('select', 'Rock')">pick rock</button></div>`,
  },
}))

describe('albumGenreFilter.vue', () => {
  const h = createHarness()

  it('shows a generic label without a filter and offers no clearing', () => {
    h.render(Component)

    screen.getByText('Genre')
    expect(screen.queryByTitle('Clear genre filter')).toBeNull()
  })

  it('shows the active genre and clears it', async () => {
    const { emitted } = h.render(Component, { props: { modelValue: 'Rock' } })

    screen.getByText('Rock')
    await userEvent.click(screen.getByTitle('Clear genre filter'))

    expect(emitted()['update:modelValue'][0]).toEqual([''])
  })
})
