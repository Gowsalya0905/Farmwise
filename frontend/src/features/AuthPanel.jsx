import { useEffect, useState } from 'react'
import { api } from '../lib/api'

export default function AuthPanel() {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)
  const [register, setRegister] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [farms, setFarms] = useState([])

  useEffect(() => {
    let active = true
    api('/auth/user').then(async ({ user: current }) => {
      const result = await api('/farms')
      if (active) { setUser(current); setFarms(result.data) }
    }).catch((err) => { if (active && err.status !== 401) setError(err.message) })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [])

  async function run(action) {
    setBusy(true); setError('')
    try { await action() } catch (err) {
      setError(err.message)
      if (err.status === 401 || err.status === 419) { setUser(null); setFarms([]) }
    } finally { setBusy(false) }
  }

  function authenticate(event) {
    event.preventDefault()
    const form = event.currentTarget
    const body = Object.fromEntries(new FormData(form))
    void run(async () => {
      const result = await api(`/auth/${register ? 'register' : 'login'}`, { method: 'POST', body })
      setFarms([]); setUser(result.user); form.reset()
      setFarms((await api('/farms')).data)
    })
  }

  return <section aria-labelledby="account-heading">
    <h2 id="account-heading">{user ? `Welcome, ${user.name}` : register ? 'Create your farmer account' : 'Sign in'}</h2>
    {error && <p role="alert">{error}</p>}
    {loading ? <p role="status">Loading your account…</p> : user ? <>
      <button disabled={busy} onClick={() => void run(async () => {
        await api('/auth/logout', { method: 'POST' }); setUser(null); setFarms([])
      })}>Sign out</button>
      <h3>Your farms</h3>
      <ul>{farms.map((farm) => <li key={farm.id}>{farm.name}</li>)}</ul>
      {!farms.length && <p>No farms yet.</p>}
      <form onSubmit={(event) => {
        event.preventDefault()
        const form = event.currentTarget
        const name = new FormData(form).get('name')
        void run(async () => {
          await api('/farms', { method: 'POST', body: { name } })
          setFarms((await api('/farms')).data); form.reset()
        })
      }}>
        <label>Farm name<input name="name" required maxLength={255} /></label>
        <button disabled={busy}>Add farm</button>
      </form>
      <p>Showing up to 25 farms.</p>
    </> : <>
      <form onSubmit={authenticate}>
        {register && <label>Name<input name="name" autoComplete="name" required maxLength={255} /></label>}
        <label>Email<input name="email" type="email" autoComplete="username" required maxLength={255} /></label>
        <label>Password<input name="password" type="password" autoComplete={register ? 'new-password' : 'current-password'} required minLength={register ? 12 : undefined} maxLength={register ? 72 : undefined} /></label>
        {register && <label>Confirm password<input name="password_confirmation" type="password" autoComplete="new-password" required /></label>}
        <button disabled={busy}>{busy ? 'Please wait…' : register ? 'Create account' : 'Sign in'}</button>
      </form>
      <button disabled={busy} onClick={() => { setRegister(!register); setError('') }}>{register ? 'Already have an account? Sign in' : 'Create an account'}</button>
    </>}
  </section>
}
