import { describe, expect, it, vi } from 'vite-plus/test'
import { screen, waitFor } from '@testing-library/vue'
import userEvent from '@testing-library/user-event'
import { genreStore } from '@/stores/genreStore'
import { createHarness } from '@/__tests__/TestHarness'
import Component from './AlbumGenrePicker.vue'

const setAlbumGenreMock = vi.fn()

vi.mock('@/composables/useAlbumGenre', () => ({
  useAlbumGenre: () => ({
    setAlbumGenre: setAlbumGenreMock,
    fetchSuggestions: vi.fn().mockResolvedValue(['Rock']),
    addGenres: vi.fn(),
  }),
}))

describe('albumGenrePicker.vue', () => {
  const h = createHarness({
    beforeEach: () => {
      setAlbumGenreMock.mockReset()
      vi.spyOn(genreStore, 'fetchAll').mockResolvedValue([])
    },
  })

  const renderComponent = (props: Record<string, unknown> = {}) =>
    h.render(Component, { props: { album: h.factory('album').make(), ...props } })

  it('sets the picked genre for the album and reports done', async () => {
    setAlbumGenreMock.mockResolvedValue(true)
    const { emitted } = renderComponent()

    await userEvent.click(screen.getByRole('option', { name: 'Rock' }))

    await waitFor(() => expect(emitted().done).toBeTruthy())
    expect(setAlbumGenreMock).toHaveBeenCalledWith(expect.anything(), 'Rock')
  })

  it('stays open when the user declines', async () => {
    setAlbumGenreMock.mockResolvedValue(false)
    const { emitted } = renderComponent()

    await userEvent.click(screen.getByRole('option', { name: 'Rock' }))

    await waitFor(() => expect(setAlbumGenreMock).toHaveBeenCalled())
    expect(emitted().done).toBeUndefined()
  })

  it('switches to the suggestions and back', async () => {
    renderComponent()

    await userEvent.click(screen.getByRole('button', { name: /Suggest genres/ }))
    await screen.findByRole('button', { name: 'Apply genres' })

    await userEvent.click(screen.getByRole('button', { name: 'Back' }))
    screen.getByLabelText('Search genres')
  })

  it('can start with the suggestions', async () => {
    renderComponent({ suggest: true })

    await screen.findByRole('button', { name: 'Apply genres' })
  })
})
