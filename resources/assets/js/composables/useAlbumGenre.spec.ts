import { describe, expect, it, vi } from 'vite-plus/test'
import { albumStore } from '@/stores/albumStore'
import { playableStore } from '@/stores/playableStore'
import { createHarness } from '@/__tests__/TestHarness'
import { useAlbumGenre } from '@/composables/useAlbumGenre'

const confirmMock = vi.fn()

vi.mock('@/composables/useDialogBox', () => ({
  useDialogBox: () => ({ showConfirmDialog: confirmMock }),
}))

vi.mock('@/composables/useMessageToaster', () => ({
  useMessageToaster: () => ({ toastSuccess: vi.fn() }),
}))

vi.mock('@/composables/useErrorHandler', () => ({
  useErrorHandler: () => ({ handleHttpError: vi.fn() }),
}))

describe('useAlbumGenre', () => {
  const h = createHarness({ beforeEach: () => confirmMock.mockReset() })

  const setup = () => {
    const album = h.factory('album').make({ name: 'Vol. 1' })
    const songs = h.factory('song').make(3)
    h.mock(playableStore, 'fetchSongsForAlbum').mockResolvedValue(songs)
    const updateMock = h.mock(playableStore, 'updateSongs').mockResolvedValue({
      songs: [],
      albums: [],
      artists: [],
      removed: { album_ids: [], artist_ids: [] },
    })

    return { album, songs, updateMock }
  }

  it('sets the genre of all songs after confirmation, sending only the genre', async () => {
    const { album, songs, updateMock } = setup()
    confirmMock.mockResolvedValue(true)

    expect(await useAlbumGenre().setAlbumGenre(album, 'Rock')).toBe(true)

    expect(confirmMock).toHaveBeenCalledWith(expect.stringContaining('3 songs'))
    expect(updateMock).toHaveBeenCalledWith(songs, { genre: 'Rock' })
  })

  it('does nothing when the user declines', async () => {
    const { album, updateMock } = setup()
    confirmMock.mockResolvedValue(false)

    expect(await useAlbumGenre().setAlbumGenre(album, 'Rock')).toBe(false)
    expect(updateMock).not.toHaveBeenCalled()
  })

  it('fetches genre suggestions', async () => {
    const { album } = setup()
    h.mock(albumStore, 'fetchGenreSuggestions').mockResolvedValue(['Rock'])

    expect(await useAlbumGenre().fetchSuggestions(album)).toEqual(['Rock'])
  })

  it('returns no suggestions when fetching fails', async () => {
    const { album } = setup()
    h.mock(albumStore, 'fetchGenreSuggestions').mockRejectedValue(new Error('nope'))

    expect(await useAlbumGenre().fetchSuggestions(album)).toEqual([])
  })

  it('adds genres to the songs of an album', async () => {
    const { album } = setup()
    const addMock = h.mock(albumStore, 'addGenres').mockResolvedValue({
      songs: h.factory('song').make(2),
      albums: [],
      artists: [],
      removed: { album_ids: [], artist_ids: [] },
    })

    expect(await useAlbumGenre().addGenres(album, ['Rock', 'Metal'])).toBe(true)
    expect(addMock).toHaveBeenCalledWith(album, ['Rock', 'Metal'])
  })

  it('reports a failure when adding genres fails', async () => {
    const { album } = setup()
    h.mock(albumStore, 'addGenres').mockRejectedValue(new Error('nope'))

    expect(await useAlbumGenre().addGenres(album, ['Rock'])).toBe(false)
  })
})
