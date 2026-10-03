import { playableStore } from '@/stores/playableStore'
import { useDialogBox } from '@/composables/useDialogBox'
import { useErrorHandler } from '@/composables/useErrorHandler'
import { useMessageToaster } from '@/composables/useMessageToaster'
import { pluralize } from '@/utils/formatters'
import { eventBus } from '@/utils/eventBus'

export const useAlbumGenre = () => {
  const { showConfirmDialog } = useDialogBox()
  const { toastSuccess } = useMessageToaster()
  const { handleHttpError } = useErrorHandler('toast')

  /** Set the genre of all the songs of an album, after the user confirms. Resolves to whether it was applied. */
  const setAlbumGenre = async (album: Album, genre: string) => {
    try {
      const songs = await playableStore.fetchSongsForAlbum(album)

      if (!songs.length) {
        return false
      }

      const target = genre ? `"${genre}"` : 'empty'

      if (!(await showConfirmDialog(`Set the genre of ${pluralize(songs, 'song')} in "${album.name}" to ${target}?`))) {
        return false
      }

      // Only the genre is sent: with multiple songs, the missing fields keep their current values.
      const result = await playableStore.updateSongs(songs, { genre })

      toastSuccess(`Updated the genre of ${pluralize(songs, 'song')}.`)
      eventBus.emit('SONGS_UPDATED', result)

      return true
    } catch (error: unknown) {
      handleHttpError(error)

      return false
    }
  }

  return { setAlbumGenre }
}
