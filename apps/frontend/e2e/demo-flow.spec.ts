import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

test('local demo goes from synthetic receipt to active deadline', async ({ page }) => {
  await page.goto('/')
  await page.getByRole('link', { name: 'Try the local demo' }).first().click()

  await expect(page.getByRole('heading', { name: /Good morning, Alex/ })).toBeVisible()
  await page.getByRole('link', { name: 'Add purchase' }).first().click()

  await expect(page.getByRole('heading', { name: 'Add proof of purchase' })).toBeVisible()
  await page.getByRole('button', { name: 'Use synthetic receipt' }).click()
  await page.getByRole('button', { name: /Continue with local processing/ }).click()
  await page.getByRole('button', { name: 'Process receipt' }).click()

  await expect(page.getByRole('heading', { name: 'Check what KeepProof found' })).toBeVisible()
  await page.getByRole('checkbox', { name: /I checked these values/ }).check()
  await page.getByRole('button', { name: 'Confirm purchase details' }).click()

  await expect(page.getByRole('heading', { name: 'Evergreen Market' })).toBeVisible()
  await expect(page.getByText('Aug 17, 2026')).toBeVisible()

  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Add to calendar' }).click()
  expect((await download).suggestedFilename()).toContain('.ics')
})

test('dashboard has no automatically detectable accessibility violations', async ({ page }) => {
  await page.goto('/demo')
  await expect(page.getByRole('heading', { name: /Good morning, Alex/ })).toBeVisible()

  const results = await new AxeBuilder({ page }).exclude('.synthetic-receipt').analyze()
  expect(results.violations).toEqual([])
})

test('privacy export and destructive confirmation are explicit', async ({ page }) => {
  await page.goto('/demo')
  await page.getByRole('link', { name: 'Privacy & data' }).click()

  const exportDownload = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Export my data' }).click()
  expect((await exportDownload).suggestedFilename()).toBe('keepproof-demo-export.json')

  await page.getByRole('link', { name: 'Review account deletion' }).click()
  await page.getByLabel('Confirm your password').fill('KeepProof!2026')
  await page.getByLabel(/Type DELETE MY ACCOUNT/).fill('DELETE MY ACCOUNT')
  await page.getByRole('checkbox', { name: /I understand/ }).check()
  await page.getByRole('button', { name: 'Permanently delete account' }).click()

  await expect(page.getByText('Your local demo data was cleared.')).toBeVisible()
})
