import { readFile } from 'node:fs/promises'
import { expect, type BrowserContext, type Page, test } from '@playwright/test'

const connected = process.env.CONNECTED_E2E === 'true'
const mailpitBaseUrl = process.env.MAILPIT_BASE_URL ?? 'http://127.0.0.1:8025'
const password = 'E2eSecure!2026'

interface HouseholdCollection {
  data: Array<{ id: string }>
}

const uniqueEmail = (label: string): string =>
  `e2e-${label}-${Date.now()}-${Math.random().toString(16).slice(2)}@keepproof.test`

const dateInTimezone = (timezone: string): string => {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date())
  const value = (type: Intl.DateTimeFormatPartTypes): string =>
    parts.find((part) => part.type === type)?.value ?? ''

  return `${value('year')}-${value('month')}-${value('day')}`
}

const addCalendarDays = (date: string, days: number): string => {
  const value = new Date(`${date}T12:00:00Z`)
  value.setUTCDate(value.getUTCDate() + days)

  return value.toISOString().slice(0, 10)
}

const displayDate = (date: string): string =>
  new Intl.DateTimeFormat('en-US', {
    timeZone: 'UTC',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(`${date}T12:00:00Z`))

const verificationUrlFromMailpit = async (page: Page, email: string): Promise<string> => {
  const deadline = Date.now() + 30_000

  while (Date.now() < deadline) {
    const response = await page.request.get(
      `${mailpitBaseUrl}/view/latest.html?query=${encodeURIComponent(`to:${email}`)}`,
    )
    if (response.ok()) {
      const html = await response.text()
      const match = html.match(/https?:\/\/[^"' <]+\/api\/v1\/email\/verify\/[^"' <]+/i)
      if (match) return match[0].replaceAll('&amp;', '&')
    }

    await page.waitForTimeout(500)
  }

  throw new Error(`Mailpit did not receive a verification link for ${email}.`)
}

const registerAndVerify = async (page: Page, name: string, email: string): Promise<string> => {
  await page.goto('/register')
  await page.getByLabel('Your name').fill(name)
  await page.getByLabel('Email address').fill(email)
  await page.getByLabel('Password', { exact: true }).fill(password)
  await page.getByLabel('Confirm password').fill(password)
  await page.getByRole('button', { name: 'Create account' }).click()

  await expect(page.getByRole('heading', { name: 'Verify your email' })).toBeVisible()
  const verificationUrl = await verificationUrlFromMailpit(page, email)
  await page.goto(verificationUrl)
  await expect(
    page.getByRole('heading', { name: new RegExp(`Good morning, ${name}`) }),
  ).toBeVisible()

  const response = await page.request.get('/api/v1/households?per_page=10', {
    headers: { Accept: 'application/json' },
  })
  expect(response.ok()).toBe(true)
  const households = (await response.json()) as HouseholdCollection
  expect(households.data).toHaveLength(1)

  return households.data[0].id
}

const closeContext = async (context: BrowserContext): Promise<void> => {
  try {
    await context.close()
  } catch {
    // Preserve the original assertion failure if browser cleanup also fails.
  }
}

test.describe('connected receipt lifecycle', () => {
  test.skip(!connected, 'Runs against the Docker Compose application with real infrastructure.')

  test('verified account can activate deadlines and another household cannot read them', async ({
    browser,
  }) => {
    test.setTimeout(150_000)
    const baseURL = process.env.PLAYWRIGHT_BASE_URL
    const ownerContext = await browser.newContext({ baseURL, acceptDownloads: true })
    const ownerPage = await ownerContext.newPage()
    const ownerEmail = uniqueEmail('owner')
    let memberContext: BrowserContext | undefined

    try {
      const householdId = await registerAndVerify(ownerPage, 'Morgan', ownerEmail)
      const purchaseDate = dateInTimezone('America/New_York')
      const returnDeadline = addCalendarDays(purchaseDate, 30)

      await ownerPage.getByRole('link', { name: 'Add purchase' }).first().click()
      await expect(ownerPage.getByRole('heading', { name: 'Add proof of purchase' })).toBeVisible()
      await ownerPage.getByRole('button', { name: 'Use synthetic receipt' }).click()
      await ownerPage.getByRole('button', { name: 'Continue with local processing' }).click()
      await ownerPage.getByRole('button', { name: 'Process receipt' }).click()

      await expect(
        ownerPage.getByRole('heading', { name: 'Check what KeepProof found' }),
      ).toBeVisible({ timeout: 75_000 })
      await ownerPage.getByLabel('Purchase date').fill(purchaseDate)
      await ownerPage.getByRole('checkbox', { name: /I checked these values/ }).check()
      await ownerPage.getByRole('button', { name: 'Confirm purchase details' }).click()

      await expect(ownerPage.getByRole('heading', { name: 'Northstar Home' })).toBeVisible()
      await expect(ownerPage.getByText(displayDate(returnDeadline))).toBeVisible()
      const purchaseId = new URL(ownerPage.url()).pathname.split('/').at(-1)
      expect(purchaseId).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/)

      const calendarDownloadPromise = ownerPage.waitForEvent('download')
      await ownerPage.getByRole('button', { name: 'Add to calendar' }).click()
      const calendarDownload = await calendarDownloadPromise
      expect(calendarDownload.suggestedFilename()).toMatch(/\.ics$/)
      const calendarPath = await calendarDownload.path()
      expect(calendarPath).not.toBeNull()
      const calendar = await readFile(calendarPath as string, 'utf8')
      expect(calendar).toContain('BEGIN:VCALENDAR')
      expect(calendar).toContain('UID:')
      expect(calendar).toContain(
        'KeepProof records supplied terms and does not provide legal advice.',
      )

      const claimDownloadPromise = ownerPage.waitForEvent('download')
      await ownerPage.getByRole('button', { name: 'Prepare claim package' }).click()
      const claimDownload = await claimDownloadPromise
      expect(claimDownload.suggestedFilename()).toMatch(/\.zip$/)
      const claimPath = await claimDownload.path()
      expect(claimPath).not.toBeNull()
      const claim = await readFile(claimPath as string)
      expect(claim.subarray(0, 2).toString('ascii')).toBe('PK')

      await ownerPage.goto('/app/dashboard')
      await expect(ownerPage.getByText(/reminder scheduled/i).first()).toBeVisible()

      memberContext = await browser.newContext({ baseURL })
      const memberPage = await memberContext.newPage()
      await registerAndVerify(memberPage, 'Riley', uniqueEmail('outsider'))

      const hiddenHousehold = await memberPage.request.get(`/api/v1/households/${householdId}`, {
        headers: { Accept: 'application/json' },
      })
      expect(hiddenHousehold.status()).toBe(404)

      const hiddenPurchase = await memberPage.request.get(
        `/api/v1/households/${householdId}/purchases/${purchaseId}`,
        { headers: { Accept: 'application/json' } },
      )
      expect(hiddenPurchase.status()).toBe(404)
    } finally {
      if (memberContext) await closeContext(memberContext)
      await closeContext(ownerContext)
    }
  })
})
