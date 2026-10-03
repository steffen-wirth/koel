import { describe, expect, it, vi } from 'vite-plus/test'
import { screen, waitFor } from '@testing-library/vue'
import userEvent from '@testing-library/user-event'
import { playableStore } from '@/stores/playableStore'
import { createHarness } from '@/__tests__/TestHarness'
import Component from './PlayableListGenreSelect.vue'

vi.mock('@/components/genre/GenrePickerPopover.vue', () => ({
  default: {
    emits: ['select'],
    template: `<button @click="$emit('select', 'Rock')">pick rock</button>`,
  },
}))

describe('playableListGenreSelect.vue', () => {
  const h = createHarness()

  it('saves the picked genre along with the unchanged fields', async () => {
    const updateMock = h.mock(playableStore, 'updateSongs').mockResolvedValue({
      songs: [],
      albums: [],
      artists: [],
      removed: { album_ids: [], artist_ids: [] },
    })

    const song = h.factory('song').make({
      genre: 'Blues',
      title: 'Amet',
      artist_name: 'Koel',
      album_name: 'Vol. 1',
      track: 5,
      disc: 1,
      year: 2015,
      lyrics: 'La la',
    })

    h.render(Component, { props: { song } })
    await userEvent.click(screen.getByText('pick rock'))

    await waitFor(() =>
      expect(updateMock).toHaveBeenCalledWith(
        [song],
        expect.objectContaining({ genre: 'Rock', title: 'Amet', track: 5, year: 2015, lyrics: 'La la' }),
      ),
    )
  })
})
