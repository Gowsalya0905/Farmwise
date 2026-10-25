import { useEffect, useRef, useState } from 'react'
import { api } from '../lib/api'

function useAccountRequest(onSessionExpired) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [errors, setErrors] = useState({})
  const [success, setSuccess] = useState('')
  const controller = useRef(null)
  useEffect(() => () => controller.current?.abort(), [])
  async function submit(path, method, body, onSuccess) {
    controller.current?.abort()
    const request = new AbortController()
    controller.current = request
    setBusy(true); setError(''); setErrors({}); setSuccess('')
    try {
      const result = await api(path, { method, body, signal: request.signal })
      if (request.signal.aborted) return
      setSuccess(result.message)
      onSuccess?.(result)
    } catch (err) {
      if (request.signal.aborted) return
      if (onSessionExpired && (err.status === 401 || err.status === 419)) onSessionExpired()
      else { setError(err.message); setErrors(err.errors || {}) }
    } finally { if (!request.signal.aborted) setBusy(false) }
  }
  return { busy, error, errors, success, submit }
}

export function Messages({ request }) {
  return <>{request.error && <p role="alert" className="error">{request.error}</p>}<p role="status" className={request.success ? 'success' : undefined}>{request.success}</p></>
}

export function Field({ label, name, errors = {}, id, ...props }) {
  const fieldId = id || name
  return <label htmlFor={fieldId}>{label}<input id={fieldId} name={name} aria-invalid={!!errors[name]} aria-describedby={errors[name] ? `${fieldId}-error` : undefined} {...props} />{errors[name] && <span id={`${fieldId}-error`} className="field-error">{errors[name].join(' ')}</span>}</label>
}

export function Profile({ user, onUpdated, onSessionExpired }) {
  const request = useAccountRequest(onSessionExpired)
  const [name, setName] = useState(user.name)
  const [email, setEmail] = useState(user.email)
  const emailChanged = email.trim().toLowerCase() !== user.email
  function submit(event) {
    event.preventDefault()
    const form = event.currentTarget
    void request.submit('/auth/profile', 'PATCH', Object.fromEntries(new FormData(form)), ({ user: current }) => {
      onUpdated(current); setName(current.name); setEmail(current.email)
      form.elements.current_password.value = ''
    })
  }
  return <div className="profile-grid"><section className="card" aria-labelledby="profile-heading"><h2 id="profile-heading">Farmer profile</h2><p className="muted">Keep your account details up to date.</p><Messages request={request} /><form onSubmit={submit}><fieldset disabled={request.busy}>
    <Field label="Name" name="name" required maxLength={255} autoComplete="name" value={name} onChange={(event) => setName(event.target.value)} errors={request.errors} />
    <Field label="Email" name="email" type="email" required maxLength={255} autoComplete="email" value={email} onChange={(event) => setEmail(event.target.value)} errors={request.errors} />
    <Field label="Current password (required to change email)" name="current_password" type="password" autoComplete="current-password" required={emailChanged} errors={request.errors} />
    <button>{request.busy ? 'Saving...' : 'Save profile'}</button>
  </fieldset></form></section><ChangePassword onSessionExpired={onSessionExpired} /></div>
}

function ChangePassword({ onSessionExpired }) {
  const request = useAccountRequest(onSessionExpired)
  function submit(event) {
    event.preventDefault()
    const form = event.currentTarget
    void request.submit('/auth/password', 'PUT', Object.fromEntries(new FormData(form)), () => form.reset())
  }
  return <section className="card" aria-labelledby="password-heading"><h2 id="password-heading">Change password</h2><p className="muted">Use at least 12 characters. Other sessions will be signed out.</p><Messages request={request} /><form onSubmit={submit}><fieldset disabled={request.busy}>
    <Field id="change-current-password" label="Current password" name="current_password" type="password" required autoComplete="current-password" errors={request.errors} />
    <Field label="New password" name="password" type="password" required minLength={12} maxLength={72} autoComplete="new-password" errors={request.errors} />
    <Field label="Confirm new password" name="password_confirmation" type="password" required autoComplete="new-password" errors={request.errors} />
    <button>{request.busy ? 'Changing...' : 'Change password'}</button>
  </fieldset></form></section>
}

export function PasswordRecovery({ reset, navigate, onReset }) {
  const request = useAccountRequest()
  const [credentials] = useState(() => {
    const query = new URLSearchParams(window.location.search)
    return { token: query.get('token') || '', email: query.get('email') || '' }
  })
  const missingToken = reset && !credentials.token
  function submit(event) {
    event.preventDefault()
    const form = event.currentTarget
    const body = Object.fromEntries(new FormData(form))
    if (reset) body.token = credentials.token
    void request.submit(`/auth/${reset ? 'reset-password' : 'forgot-password'}`, 'POST', body, () => {
      form.reset()
      if (reset) {
        // Remove the used credential from history without storing it in browser storage.
        window.history.replaceState({}, '', '/reset-password')
        onReset()
      }
    })
  }
  return <section className="card" aria-labelledby="recovery-heading"><h2 id="recovery-heading">{reset ? 'Reset your password' : 'Forgot your password?'}</h2><p className="muted">{reset ? 'Choose a new password with at least 12 characters.' : 'Enter your email to request a password reset link.'}</p><Messages request={request} />
    {missingToken && !request.success && <p role="alert" className="error">This link is missing its reset token. Request a new link.</p>}
    {!request.success && <form onSubmit={submit}><fieldset disabled={request.busy || missingToken}>
      <Field label="Email" name="email" type="email" required maxLength={255} autoComplete="email" defaultValue={credentials.email} errors={request.errors} />
      {reset && <><Field label="New password" name="password" type="password" required minLength={12} maxLength={72} autoComplete="new-password" errors={request.errors} /><Field label="Confirm new password" name="password_confirmation" type="password" required autoComplete="new-password" errors={request.errors} /></>}
      <button>{request.busy ? 'Please wait...' : reset ? 'Reset password' : 'Send reset link'}</button>
    </fieldset></form>}
    {reset && <p><a href="/forgot-password" onClick={(event) => navigate(event, '/forgot-password')}>Request a new reset link</a></p>}
    <a href="/login" onClick={(event) => navigate(event, '/login')}>Back to sign in</a>
  </section>
}
