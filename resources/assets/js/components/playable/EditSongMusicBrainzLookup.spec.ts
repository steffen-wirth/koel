import { screen, waitFor } from '@testing-library/vue'
import { describe, expect, it } from 'vite-plus/test'
import { createHarness } from '@/__tests__/TestHarness'
import { http } from '@/services/http'
import Component from './EditSongMusicBrainzLookup.vue'

describe('editSongMusicBrainzLookup', () => {
  const h = createHarness()

  it('looks up matches and emits the chosen one', async () => {
    const match = {
      url: 'https://musicbrainz.org/recording/x',
      mbid: 'rec-1',
      title: 'Song',
      artist_name: 'Artist',
      album_name: 'Album',
      year: 1999,
      genre: 'Rock',
    }
    const credits = [{ role: 'composer', name: 'Ada' }]
    const getMock = h
      .mock(http, 'get')
      .mockResolvedValueOnce({ matches: [match] })
      .mockResolvedValueOnce({ credits })

    const { emitted } = h.render(Component, { props: { title: 'Song', artist: 'Artist' } })

    await h.user.click(screen.getByRole('button', { name: 'Look up on MusicBrainz' }))
    await waitFor(() => screen.getByRole('list', { name: 'MusicBrainz matches' }))

    expect(getMock).toHaveBeenCalledWith('musicbrainz/songs?title=Song&artist=Artist')

    await h.user.click(screen.getByText('Song – Artist'))

    await waitFor(() => expect(emitted().apply).toBeTruthy())

    expect(getMock).toHaveBeenCalledWith('musicbrainz/recordings/rec-1/credits')
    expect(emitted().apply[0]).toEqual([{ ...match, credits }])
  })

  it('tells when nothing is found', async () => {
    h.mock(http, 'get').mockResolvedValue({ matches: [] })
    h.render(Component, { props: { title: 'Song' } })

    await h.user.click(screen.getByRole('button', { name: 'Look up on MusicBrainz' }))

    await waitFor(() => screen.getByText('No matches found on MusicBrainz.'))
  })
})
