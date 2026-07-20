export interface ProblemDetails {
  type?: string
  title: string
  status: number
  detail: string
  correlation_id?: string
  errors?: Record<string, string[]>
}

export class ApiProblem extends Error {
  readonly problem: ProblemDetails

  constructor(problem: ProblemDetails) {
    super(problem.detail)
    this.name = 'ApiProblem'
    this.problem = problem
  }
}

const apiBase = import.meta.env.VITE_API_BASE_URL || '/api/v1'
let csrfReady = false

const readCookie = (name: string): string | undefined => {
  const prefix = `${name}=`
  const value = document.cookie
    .split(';')
    .map((part) => part.trim())
    .find((part) => part.startsWith(prefix))

  return value ? decodeURIComponent(value.slice(prefix.length)) : undefined
}

export const ensureCsrfCookie = async (): Promise<void> => {
  if (csrfReady) return

  const response = await fetch('/sanctum/csrf-cookie', {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    throw new ApiProblem({
      title: 'Security check unavailable',
      status: response.status,
      detail: 'The secure session could not be initialized. Please try again.',
    })
  }

  csrfReady = true
}

const isUnsafe = (method: string): boolean =>
  !['GET', 'HEAD', 'OPTIONS'].includes(method.toUpperCase())

export const createCorrelationId = (): string => {
  if (typeof globalThis.crypto.randomUUID === 'function') {
    return globalThis.crypto.randomUUID()
  }

  const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16))
  bytes[6] = (bytes[6] & 0x0f) | 0x40
  bytes[8] = (bytes[8] & 0x3f) | 0x80
  const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('')

  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}

const apiResponse = async (path: string, init: RequestInit = {}): Promise<Response> => {
  const method = init.method ?? 'GET'
  const headers = new Headers(init.headers)

  headers.set('Accept', 'application/json')
  headers.set('X-Correlation-ID', createCorrelationId())

  if (isUnsafe(method)) {
    await ensureCsrfCookie()
    const token = readCookie('XSRF-TOKEN')
    if (token) headers.set('X-XSRF-TOKEN', token)
  }

  if (init.body && !(init.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  let response: Response
  try {
    response = await fetch(`${apiBase}${path}`, {
      ...init,
      method,
      headers,
      credentials: 'include',
    })
  } catch {
    throw new ApiProblem({
      title: 'Connection unavailable',
      status: 0,
      detail: 'KeepProof could not reach the API. Check your connection and try again.',
    })
  }

  if (!response.ok) {
    const fallback: ProblemDetails = {
      title: 'Request failed',
      status: response.status,
      detail: 'KeepProof could not complete this request.',
    }

    let problem = fallback
    try {
      problem = { ...fallback, ...((await response.json()) as Partial<ProblemDetails>) }
    } catch {
      // A non-JSON upstream failure still becomes a safe, predictable problem.
    }

    throw new ApiProblem(problem)
  }

  return response
}

export const apiRequest = async <T>(path: string, init: RequestInit = {}): Promise<T> => {
  const response = await apiResponse(path, init)
  if (response.status === 204) return undefined as T
  return (await response.json()) as T
}

export const apiDownload = async (path: string, init: RequestInit = {}): Promise<Blob> => {
  const response = await apiResponse(path, init)
  return response.blob()
}

export const resetCsrfForTests = (): void => {
  csrfReady = false
}
