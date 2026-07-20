import { randomUUID } from 'node:crypto'
import { readFile } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { performance } from 'node:perf_hooks'

const baseUrl = (process.env.LOAD_BASE_URL ?? 'http://nginx:8080').replace(/\/$/, '')
const email = process.env.LOAD_EMAIL ?? 'demo@keepproof.local'
const password = process.env.LOAD_PASSWORD ?? 'KeepProof!2026'
const readRequests = positiveInteger('LOAD_READ_REQUESTS', 50)
const readConcurrency = positiveInteger('LOAD_READ_CONCURRENCY', 5)
const uploadRequests = positiveInteger('LOAD_UPLOAD_REQUESTS', 5)
const uploadConcurrency = positiveInteger('LOAD_UPLOAD_CONCURRENCY', 1)
const fixturePath =
  process.env.LOAD_FIXTURE ??
  resolve(dirname(fileURLToPath(import.meta.url)), '../fixtures/receipts/generated/northstar-receipt.png')

const cookies = new Map()

function positiveInteger(name, fallback) {
  const raw = process.env[name]
  if (raw === undefined) return fallback

  const value = Number.parseInt(raw, 10)
  if (!Number.isSafeInteger(value) || value < 1) {
    throw new Error(`${name} must be a positive integer.`)
  }

  return value
}

function captureCookies(response) {
  const values =
    typeof response.headers.getSetCookie === 'function'
      ? response.headers.getSetCookie()
      : [response.headers.get('set-cookie')].filter(Boolean)

  for (const value of values) {
    const pair = value.split(';', 1)[0]
    const separator = pair.indexOf('=')
    if (separator < 1) continue
    cookies.set(pair.slice(0, separator), pair.slice(separator + 1))
  }
}

function cookieHeader() {
  return [...cookies.entries()].map(([name, value]) => `${name}=${value}`).join('; ')
}

function requestHeaders({ unsafe = false, contentType } = {}) {
  const headers = {
    Accept: 'application/json',
    Origin: baseUrl,
    Referer: `${baseUrl}/`,
  }
  const cookie = cookieHeader()
  if (cookie) headers.Cookie = cookie
  if (contentType) headers['Content-Type'] = contentType

  if (unsafe) {
    const token = cookies.get('XSRF-TOKEN')
    if (!token) throw new Error('The CSRF cookie was not initialized.')
    headers['X-XSRF-TOKEN'] = decodeURIComponent(token)
    headers['X-Correlation-ID'] = randomUUID()
  }

  return headers
}

async function checkedFetch(path, init, expectedStatus) {
  const response = await fetch(`${baseUrl}${path}`, init)
  captureCookies(response)
  const body = await response.arrayBuffer()

  if (response.status !== expectedStatus) {
    const detail = new TextDecoder().decode(body).slice(0, 500)
    throw new Error(`${init.method ?? 'GET'} ${path} returned ${response.status}: ${detail}`)
  }

  return { response, body }
}

async function authenticate() {
  await checkedFetch(
    '/sanctum/csrf-cookie',
    { headers: requestHeaders(), redirect: 'manual' },
    204,
  )
  await checkedFetch(
    '/api/v1/auth/login',
    {
      method: 'POST',
      headers: requestHeaders({ unsafe: true, contentType: 'application/json' }),
      body: JSON.stringify({ email, password }),
    },
    200,
  )

  const { body } = await checkedFetch(
    '/api/v1/households?per_page=10',
    { headers: requestHeaders() },
    200,
  )
  const payload = JSON.parse(new TextDecoder().decode(body))
  const householdId = payload?.data?.[0]?.id
  if (typeof householdId !== 'string') throw new Error('The load account has no household.')

  return householdId
}

function percentile(sorted, fraction) {
  const index = Math.min(sorted.length - 1, Math.ceil(sorted.length * fraction) - 1)
  return sorted[Math.max(index, 0)]
}

async function runScenario(name, total, concurrency, operation) {
  const latencies = []
  let cursor = 0
  const startedAt = performance.now()

  const worker = async () => {
    while (true) {
      const index = cursor
      cursor += 1
      if (index >= total) return

      const requestStartedAt = performance.now()
      await operation(index)
      latencies.push(performance.now() - requestStartedAt)
    }
  }

  await Promise.all(Array.from({ length: Math.min(concurrency, total) }, () => worker()))
  const durationMs = performance.now() - startedAt
  const sorted = [...latencies].sort((left, right) => left - right)
  const averageMs = sorted.reduce((sum, value) => sum + value, 0) / sorted.length

  return {
    scenario: name,
    requests: total,
    concurrency: Math.min(concurrency, total),
    failures: 0,
    duration_ms: Number(durationMs.toFixed(1)),
    requests_per_second: Number(((total * 1000) / durationMs).toFixed(2)),
    latency_ms: {
      min: Number(sorted[0].toFixed(1)),
      average: Number(averageMs.toFixed(1)),
      p50: Number(percentile(sorted, 0.5).toFixed(1)),
      p95: Number(percentile(sorted, 0.95).toFixed(1)),
      max: Number(sorted.at(-1).toFixed(1)),
    },
  }
}

const householdId = await authenticate()

for (let warmup = 0; warmup < 3; warmup += 1) {
  await checkedFetch(
    `/api/v1/households/${householdId}/dashboard`,
    { headers: requestHeaders() },
    200,
  )
}

const readResult = await runScenario(
  'authenticated dashboard read',
  readRequests,
  readConcurrency,
  async () => {
    await checkedFetch(
      `/api/v1/households/${householdId}/dashboard`,
      { headers: requestHeaders() },
      200,
    )
  },
)

const fixture = await readFile(fixturePath)
const uploadResult = await runScenario(
  'private receipt upload accepted',
  uploadRequests,
  uploadConcurrency,
  async () => {
    const form = new FormData()
    form.set('receipt', new Blob([fixture], { type: 'image/png' }), 'northstar-receipt.png')
    form.set('external_ai_consent', '0')

    await checkedFetch(
      `/api/v1/households/${householdId}/receipts`,
      {
        method: 'POST',
        headers: {
          ...requestHeaders({ unsafe: true }),
          'Idempotency-Key': `load-${randomUUID()}`,
        },
        body: form,
      },
      202,
    )
  },
)

console.log(
  JSON.stringify(
    {
      measured_at: new Date().toISOString(),
      target: baseUrl,
      fixture_bytes: fixture.byteLength,
      warmup_reads: 3,
      results: [readResult, uploadResult],
      scope:
        'Upload latency ends at HTTP 202 after validation, sanitization, private object persistence, and durable dispatch intent; it does not include asynchronous OCR completion.',
    },
    null,
    2,
  ),
)
