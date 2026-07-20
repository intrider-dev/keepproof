import { fireEvent, render } from '@testing-library/vue'
import { describe, expect, it } from 'vitest'
import ExtractionFieldEditor from './ExtractionFieldEditor.vue'

describe('ExtractionFieldEditor', () => {
  it('associates proposed-value warnings and validation errors with the field', async () => {
    const view = render(ExtractionFieldEditor, {
      props: {
        field: {
          key: 'orderNumber',
          label: 'Receipt number',
          value: 'BH-73104',
          normalizedValue: 'BH-73104',
          confidence: 0.84,
          source: 'Header · line 4',
          errors: ['One character was unclear.'],
        },
        modelValue: 'BH-73104',
        error: 'Receipt number must be checked.',
      },
    })

    const input = view.getByLabelText('Receipt number')
    expect(input.getAttribute('aria-invalid')).toBe('true')
    expect(view.getByText('84%')).toBeTruthy()
    expect(view.getByText('One character was unclear.')).toBeTruthy()
    expect(view.getByText('Receipt number must be checked.')).toBeTruthy()

    await fireEvent.update(input, 'BH-731O4')
    expect(view.emitted()['update:modelValue']?.[0]).toEqual(['BH-731O4'])
  })
})
