import { describe, expect, it } from 'vite-plus/test'
import { screen } from '@testing-library/vue'
import userEvent from '@testing-library/user-event'
import { createHarness } from '@/__tests__/TestHarness'
import Component from './SlideInSidebar.vue'

describe('slideInSidebar.vue', () => {
  const h = createHarness()

  const renderComponent = (open: boolean) =>
    h.render(Component, {
      props: { open, title: 'Filters' },
      slots: { default: '<p>Content</p>' },
    })

  it('renders its content when open', () => {
    renderComponent(true)

    screen.getByRole('complementary', { name: 'Filters' })
    screen.getByText('Content')
  })

  it('renders nothing when closed', () => {
    renderComponent(false)

    expect(screen.queryByRole('complementary')).toBeNull()
  })

  it('requests closing', async () => {
    const { emitted } = renderComponent(true)

    await userEvent.click(screen.getByTitle('Close'))

    expect(emitted().close).toBeTruthy()
  })
})
