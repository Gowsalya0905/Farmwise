const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api').replace(/\/$/, '')

export async function checkApi(signal) {
  const response = await fetch(`${apiBaseUrl}/health`, { signal, headers: { Accept: 'application/json' } })
  if (!response.ok) throw new Error(`API returned HTTP ${response.status}`)
  const result = await response.json()
  if (typeof result !== 'object' || result === null || !('status' in result) || result.status !== 'ok') {
    throw new Error('Unexpected health response')
  }
}
