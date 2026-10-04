import { describe, expect, it } from 'vite-plus/test'
import { screen, waitFor } from '@testing-library/vue'
import userEvent from '@testing-library/user-event'
import { http } from '@/services/http'
import { createHarness } from '@/__tests__/TestHarness'
import Component from './SongCreditFilter.vue'

describe('songCreditFilter.vue', () => {
  const h = createHarness()

  it('loads the credited names and reloads them for a role', async () => {
    const getMock = h.mock(http, 'get').mockResolvedValue({ names: ['Ada'] })
    h.render(Component, { props: { role: '', name: '' } })

    await waitFor(() => expect(getMock).toHaveBeenCalledWith('song-credits/names'))

    await userEvent.selectOptions(screen.getAllByRole('combobox')[0], 'composer')

    await waitFor(() => expect(getMock).toHaveBeenCalledWith('song-credits/names?role=composer'))
  })
})
