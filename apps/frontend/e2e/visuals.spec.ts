import { expect, test } from '@playwright/test'

test('capture deterministic product references', async ({ page }, testInfo) => {
  await page.goto('/')
  await expect(
    page.getByRole('heading', {
      name: 'Never miss a return window or warranty deadline.',
    }),
  ).toBeVisible()
  await page.screenshot({
    path: testInfo.outputPath('landing-reference.png'),
    fullPage: true,
    animations: 'disabled',
  })

  await page.goto('/demo')
  await expect(page.getByRole('heading', { name: /Good morning, Alex/ })).toBeVisible()
  await page.screenshot({
    path: testInfo.outputPath('dashboard-reference.png'),
    fullPage: true,
    animations: 'disabled',
  })
})
