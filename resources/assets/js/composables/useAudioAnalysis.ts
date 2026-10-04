import { http } from '@/services/http'
import { useErrorHandler } from '@/composables/useErrorHandler'
import { useMessageToaster } from '@/composables/useMessageToaster'
import { pluralize } from '@/utils/formatters'

export const useAudioAnalysis = () => {
  const { toastSuccess } = useMessageToaster()
  const { handleHttpError } = useErrorHandler('toast')

  /** Queue the detection of the BPM and the key of the given songs. */
  const analyze = async (songs: Song[]) => {
    try {
      await http.post('songs/analyze', { songs: songs.map(({ id }) => id) })
      toastSuccess(`Analyzing the BPM and key of ${pluralize(songs, 'song')}. Reload the list when it's done.`)
    } catch (error: unknown) {
      handleHttpError(error)
    }
  }

  return { analyze }
}
