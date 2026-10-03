import { describe, expect, it, vi } from 'vite-plus/test'
import { screen } from '@testing-library/vue'
import userEvent from '@testing-library/user-event'
import { genreStore } from '@/stores/genreStore'
import { createHarness } from '@/__tests__/TestHarness'
import Component from './GenrePicker.vue'

describe('genrePicker.vue', () => {
  const h = createHarness({
    beforeEach: () => {
      vi.spyOn(genreStore, 'fetchAll').mockResolvedValue([
        { type: 'genres', id: 'x', name: 'Zydeco Fusion', song_count: 1, length: 1 },
      ])
    },
  })

  const renderComponent = (props: Record<string, unknown> = {}) => h.render(Component, { props })

  it('lists standard and library genres', async () => {
    renderComponent()

    await screen.findByRole('option', { name: 'Zydeco Fusion' })
    screen.getByRole('option', { name: 'Rock' })
  })

  it('filters by keyword', async () => {
    renderComponent()

    await userEvent.type(screen.getByLabelText('Search genres'), 'jazz')

    const options = screen.getAllByRole('option').map(option => option.textContent?.trim().toLowerCase())
    expect(options.length).toBeGreaterThan(0)
    expect(options.every(option => option?.includes('jazz'))).toBe(true)
  })

  it('emits the clicked genre', async () => {
    const { emitted } = renderComponent()

    await userEvent.click(screen.getByRole('option', { name: 'Rock' }))

    expect(emitted().select[0]).toEqual(['Rock'])
  })

  it('emits the first match on enter', async () => {
    const { emitted } = renderComponent()

    await userEvent.type(screen.getByLabelText('Search genres'), 'zydeco{Enter}')
    await screen.findByRole('option', { name: 'Zydeco Fusion' })
    await userEvent.type(screen.getByLabelText('Search genres'), '{Enter}')

    expect(emitted().select.at(-1)).toEqual(['Zydeco Fusion'])
  })

  it('offers clearing only when clearable', async () => {
    const { unmount } = renderComponent()
    expect(screen.queryByText('Clear genre')).toBeNull()
    unmount()

    const { emitted } = renderComponent({ clearable: true })
    await userEvent.click(screen.getByText('Clear genre'))

    expect(emitted().select[0]).toEqual([''])
  })
})
