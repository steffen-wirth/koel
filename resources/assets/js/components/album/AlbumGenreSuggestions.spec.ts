import { describe, expect, it, vi } from 'vite-plus/test'
import { screen, waitFor } from '@testing-library/vue'
import userEvent from '@testing-library/user-event'
import { createHarness } from '@/__tests__/TestHarness'
import Component from './AlbumGenreSuggestions.vue'

const fetchSuggestionsMock = vi.fn()
const addGenresMock = vi.fn()

vi.mock('@/composables/useAlbumGenre', () => ({
  useAlbumGenre: () => ({ fetchSuggestions: fetchSuggestionsMock, addGenres: addGenresMock }),
}))

describe('albumGenreSuggestions.vue', () => {
  const h = createHarness({
    beforeEach: () => {
      fetchSuggestionsMock.mockReset()
      addGenresMock.mockReset()
    },
  })

  const renderComponent = () => h.render(Component, { props: { album: h.factory('album').make() } })

  it('lists the suggested genres and applies them', async () => {
    fetchSuggestionsMock.mockResolvedValue(['Rock', 'Metal'])
    addGenresMock.mockResolvedValue(true)

    const { emitted } = renderComponent()

    await screen.findByText('Metal')
    screen.getByText('Rock')
    await userEvent.click(screen.getByRole('button', { name: 'Apply genres' }))

    await waitFor(() => expect(emitted().applied).toBeTruthy())
    expect(addGenresMock).toHaveBeenCalledWith(expect.anything(), ['Rock', 'Metal'])
  })

  it('does not report success when applying fails', async () => {
    fetchSuggestionsMock.mockResolvedValue(['Rock'])
    addGenresMock.mockResolvedValue(false)

    const { emitted } = renderComponent()

    await userEvent.click(await screen.findByRole('button', { name: 'Apply genres' }))

    await waitFor(() => expect(addGenresMock).toHaveBeenCalled())
    expect(emitted().applied).toBeUndefined()
  })

  it('tells when there is nothing to suggest', async () => {
    fetchSuggestionsMock.mockResolvedValue([])

    renderComponent()

    await screen.findByText('No genre suggestions found for this album.')
    expect(screen.queryByRole('button', { name: 'Apply genres' })).toBeNull()
  })

  it('goes back', async () => {
    fetchSuggestionsMock.mockResolvedValue([])

    const { emitted } = renderComponent()

    await userEvent.click(await screen.findByRole('button', { name: 'Back' }))

    expect(emitted().back).toBeTruthy()
  })
})
