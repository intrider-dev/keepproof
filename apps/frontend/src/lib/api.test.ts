import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiProblem, apiRequest, resetCsrfForTests } from './api'

describe('same-origin API client', () => {
  beforeEach(() => {
    resetCsrfForTests()
    document.cookie = 'XSRF-TOKEN=demo-csrf-token; path=/'
    vi.restoreAllMocks()
  })

  it('initializes CSRF and sends credentialed unsafe requests', async () => {
    const fetchMock = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ id: 'purchase-1' }), {
          status: 201,
          headers: { 'Content-Type': 'application/json' },
        }),
      )
    vi.stubGlobal('fetch', fetchMock)

    await expect(
      apiRequest<{ id: string }>('/purchases', {
        method: 'POST',
        body: JSON.stringify({ merchant: 'North & Pine' }),
      }),
    ).resolves.toEqual({ id: 'purchase-1' })

    expect(fetchMock).toHaveBeenNthCalledWith(
      1,
      '/sanctum/csrf-cookie',
      expect.objectContaining({ credentials: 'include' }),
    )
    const request = fetchMock.mock.calls[1]
    expect(request?.[0]).toBe('/api/v1/purchases')
    expect(request?.[1]).toEqual(
      expect.objectContaining({ credentials: 'include', method: 'POST' }),
    )
    expect((request?.[1]?.headers as Headers).get('X-XSRF-TOKEN')).toBe('demo-csrf-token')
  })

  it('normalizes Problem Details errors for the UI', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValueOnce(
      new Response(
        JSON.stringify({
          title: 'Validation failed',
          status: 422,
          detail: 'The purchase date is invalid.',
          errors: { purchase_date: ['Use an ISO date.'] },
        }),
        { status: 422, headers: { 'Content-Type': 'application/problem+json' } },
      ),
    )
    vi.stubGlobal('fetch', fetchMock)

    const request = apiRequest('/purchases')
    await expect(request).rejects.toBeInstanceOf(ApiProblem)
    await expect(request).rejects.toMatchObject({
      problem: {
        status: 422,
        detail: 'The purchase date is invalid.',
      },
    })
  })
})
