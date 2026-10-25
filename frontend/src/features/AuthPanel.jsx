import { useState } from 'react'
import { api } from '../lib/api'

export default function AuthPanel({ register, onAuthenticated, navigate }) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  async function submit(event) {
    event.preventDefault()
    const form = event.currentTarget
    setBusy(true); setError('')
    try {
      const { user } = await api(`/auth/${register ? 'register' : 'login'}`, { method: 'POST', body: Object.fromEntries(new FormData(form)) })
      form.reset(); onAuthenticated(user)
    } catch (err) { setError(err.message) }
    finally { setBusy(false) }
  }
  return <section className="card" aria-labelledby="account-heading">
    <h2 id="account-heading">{register ? 'Create your farmer account' : 'Sign in'}</h2>
    {error && <p role="alert" className="error">{error}</p>}
    <form onSubmit={submit}><fieldset disabled={busy}>
      {register && <label>Name<input name="name" autoComplete="name" required maxLength={255} /></label>}
      <label>Email<input name="email" type="email" autoComplete="username" required maxLength={255} /></label>
      <label>Password<input name="password" type="password" autoComplete={register ? 'new-password' : 'current-password'} required minLength={register ? 12 : undefined} maxLength={register ? 72 : undefined} /></label>
      {register && <><p>Use at least 12 characters.</p><label>Confirm password<input name="password_confirmation" type="password" autoComplete="new-password" required /></label></>}
      <button>{busy ? 'Please wait…' : register ? 'Create account' : 'Sign in'}</button>
    </fieldset></form>
    {!register && <p><a href="/forgot-password" onClick={(event) => navigate(event, '/forgot-password')}>Forgot password?</a></p>}
    <a href={register ? '/login' : '/register'} onClick={(event) => navigate(event, register ? '/login' : '/register')}>{register ? 'Already have an account? Sign in' : 'Create an account'}</a>
  </section>
}
