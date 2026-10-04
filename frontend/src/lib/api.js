const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api').replace(/\/$/, '')

export async function api(path, options = {}) {
  const method = options.method || 'GET'
  let token
  if (method !== 'GET') {
    const csrf = await fetch(`${apiBaseUrl}/auth/csrf`, { credentials: 'include', headers: { Accept: 'application/json' } })
    if (!csrf.ok) throw new Error('Could not prepare a secure request. Please retry.')
    token = (await csrf.json()).token
  }
  const response = await fetch(`${apiBaseUrl}${path}`, {
    ...options, method, credentials: 'include',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { 'X-CSRF-TOKEN': token } : {}) },
    body: options.body ? JSON.stringify(options.body) : undefined,
  })
  const data = response.status === 204 ? null : await response.json()
  if (!response.ok) {
    const error = new Error(Object.values(data?.errors || {}).flat().join(' ') || data?.message || 'Request failed')
    error.status = response.status
    throw error
  }
  return data
}

export async function checkApi(signal) {
  const response = await fetch(`${apiBaseUrl}/health`, { signal, headers: { Accept: 'application/json' } })
  if (!response.ok) throw new Error(`API returned HTTP ${response.status}`)
  const result = await response.json()
  if (typeof result !== 'object' || result === null || !('status' in result) || result.status !== 'ok') {
    throw new Error('Unexpected health response')
  }
}
