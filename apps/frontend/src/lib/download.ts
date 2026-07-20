export const downloadTextFile = (filename: string, content: string, type: string): void => {
  downloadBlob(filename, new Blob([content], { type }))
}

export const downloadBlob = (filename: string, blob: Blob): void => {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  document.body.append(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
}

export const followPrivateDownload = (url: string): void => {
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.rel = 'noopener noreferrer'
  document.body.append(anchor)
  anchor.click()
  anchor.remove()
}
