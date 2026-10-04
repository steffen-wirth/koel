import { describe, expect, it, vi } from 'vite-plus/test'
import { screen } from '@testing-library/vue'
import userEvent from '@testing-library/user-event'
import { genreStore } from '@/stores/genreStore'
import { createHarness } from '@/__tests__/TestHarness'
import Component from './SongFilterPanel.vue'

describe('songFilterPanel.vue', () => {
  const h = createHarness({
    beforeEach: () => {
      vi.spyOn(genreStore, 'fetchAll').mockResolvedValue([
        { type: 'genres', id: 'a', name: 'Zydeco', song_count: 2, length: 1 },
      ])
    },
  })

  const renderComponent = (
    filters: SongFilters = { genre: '', formats: [], credit: '', credit_role: '', bpm_min: '', bpm_max: '' },
  ) => h.render(Component, { props: { modelValue: filters } })

  it('only offers the genres of the library', async () => {
    renderComponent()

    await screen.findByRole('option', { name: 'Zydeco' })
    expect(screen.queryByRole('option', { name: 'Rock' })).toBeNull()
  })

  it('selects a genre and unselects it when picked again', async () => {
    const filters: SongFilters = { genre: '', formats: [], credit: '', credit_role: '', bpm_min: '', bpm_max: '' }
    renderComponent(filters)

    await userEvent.click(await screen.findByRole('option', { name: 'Zydeco' }))
    expect(filters.genre).toBe('Zydeco')

    await userEvent.click(screen.getByRole('option', { name: 'Zydeco' }))
    expect(filters.genre).toBe('')
  })

  it('toggles formats', async () => {
    const filters: SongFilters = { genre: '', formats: [], credit: '', credit_role: '', bpm_min: '', bpm_max: '' }
    renderComponent(filters)

    await userEvent.click(screen.getByLabelText('FLAC'))
    await userEvent.click(screen.getByLabelText('MP3'))
    expect(filters.formats).toEqual(['flac', 'mp3'])

    await userEvent.click(screen.getByLabelText('FLAC'))
    expect(filters.formats).toEqual(['mp3'])
  })

  it('resets the filters', async () => {
    const filters: SongFilters = { genre: 'Zydeco', formats: ['flac'], bpm_min: 100, bpm_max: '' }
    renderComponent(filters)

    await userEvent.click(screen.getByRole('button', { name: 'Reset filters' }))

    expect(filters).toEqual({ genre: '', formats: [], credit: '', credit_role: '', bpm_min: '', bpm_max: '' })
  })

  it('offers no reset without filters', () => {
    renderComponent()

    expect(screen.queryByRole('button', { name: 'Reset filters' })).toBeNull()
  })
})
