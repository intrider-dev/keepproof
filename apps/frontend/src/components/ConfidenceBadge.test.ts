import { render } from '@testing-library/vue'
import { describe, expect, it } from 'vitest'
import ConfidenceBadge from './ConfidenceBadge.vue'

describe('ConfidenceBadge', () => {
  it('announces confidence and source without relying on color', () => {
    const view = render(ConfidenceBadge, {
      props: { confidence: 0.97, source: 'Receipt header' },
    })

    expect(view.getByText('97%')).toBeTruthy()
    expect(view.getByLabelText('97% confidence, from Receipt header')).toBeTruthy()
  })
})
