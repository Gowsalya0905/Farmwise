import { useEffect, useRef, useState, useSyncExternalStore } from 'react'
import { api, checkApi } from './lib/api'
import AuthPanel from './features/AuthPanel'
import Farms from './features/Farms'
import Cultivation from './features/Cultivation'
import Records from './features/Records'
import Reports from './features/Reports'
import Dashboard from './features/Dashboard'
import { PasswordRecovery, Profile } from './features/AccountForms'
import DashboardLayout from './components/DashboardLayout'
import './App.css'

const modules = ['Dashboard', 'Farms', 'Plots', 'Crops', 'Seasons', 'Activities', 'Expenses', 'Harvests', 'Sales', 'Reports']
const routeFor = (name) => name === 'Dashboard' ? '/dashboard' : `/dashboard/${name.toLowerCase()}`
const publicRoutes = ['/login', '/register', '/forgot-password', '/reset-password']
function subscribe(listener) {
  window.addEventListener('popstate', listener)
  window.addEventListener('farmwise:navigate', listener)
  return () => {
    window.removeEventListener('popstate', listener)
    window.removeEventListener('farmwise:navigate', listener)
  }
}
const getLocation = () => window.location.pathname + window.location.search + window.location.hash
function go(to, replace = false) {
  window.history[replace ? 'replaceState' : 'pushState']({}, '', to)
  window.dispatchEvent(new Event('farmwise:navigate'))
}
export default function App() {
  const location = useSyncExternalStore(subscribe, getLocation)
  const path = location.split(/[?#]/)[0]
  const [user, setUser] = useState(null)
  const [session, setSession] = useState('loading')
  const [error, setError] = useState('')
  const [attempt, setAttempt] = useState(0)
  const [busy, setBusy] = useState(false)
  const heading = useRef(null)
  function navigate(event, to) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return
    event.preventDefault(); go(to)
  }
  function clearSession() { setUser(null); setSession('ready'); setError(''); go('/login', true) }
  useEffect(() => {
    const controller = new AbortController()
    api('/auth/user', { signal: controller.signal }).then(({ user: current }) => {
      if (!controller.signal.aborted) { setUser(current); setSession('ready') }
    }).catch((err) => {
      if (controller.signal.aborted) return
      if (err.status === 401) { setUser(null); setSession('ready') }
      else { setError(err.message); setSession('error') }
    })
    return () => controller.abort()
  }, [attempt])
  useEffect(() => {
    if (session !== 'ready') return
    if (!user && !publicRoutes.includes(path)) go('/login', true)
    else if (user && path !== '/reset-password' && path !== '/profile' && !modules.some((name) => routeFor(name) === path)) go('/dashboard', true)
  }, [user, session, path])
  useEffect(() => { heading.current?.focus() }, [path, user?.id])
  async function logout() {
    setBusy(true); setError('')
    try { await api('/auth/logout', { method: 'POST' }); clearSession() }
    catch (err) { if (err.status === 401) clearSession(); else setError('Could not sign out. Please retry.') }
    finally { setBusy(false) }
  }
  if (session === 'loading') return <main className="auth-shell"><p role="status">Restoring your session...</p></main>
  if (session === 'error') return <main className="auth-shell"><h1>Farmwise</h1><p role="alert">Could not restore your session: {error}</p><button onClick={() => { setSession('loading'); setAttempt(attempt + 1) }}>Retry</button></main>
  if (!user || path === '/reset-password') return <main className="auth-shell"><p className="eyebrow">Your farming records, together</p><h1 ref={heading} tabIndex={-1}>Farmwise</h1><p>Keep track of your farms and plan your next steps.</p>
    {path === '/forgot-password' || path === '/reset-password' ? <PasswordRecovery key={path} reset={path === '/reset-password'} navigate={navigate} onReset={() => { setUser(null); setError('') }} /> : <AuthPanel key={path} register={path === '/register'} navigate={navigate} onAuthenticated={(current) => { setUser(current); go('/dashboard', true) }} />}
    <Connection />
  </main>
  const active = path === '/profile' ? 'Profile' : modules.find((name) => routeFor(name) === path) || 'Dashboard'
  return <DashboardLayout user={user} active={active} navigate={navigate} busy={busy} logout={logout}>
    <main id="dashboard-content" className="dashboard-content" tabIndex={-1}>{error && <p className="error" role="alert">{error}</p>}<p className="eyebrow">{active === 'Dashboard' ? 'Your farm, at a glance' : 'Your workspace'}</p><h1 ref={heading} tabIndex={-1}>{active === 'Dashboard' ? `Welcome, ${user.name}` : active}</h1>
      {active === 'Profile' ? <Profile key={user.id} user={user} onUpdated={setUser} onSessionExpired={clearSession} /> : active === 'Dashboard' ? <Dashboard key={user.id} navigate={navigate} onSessionExpired={clearSession} /> : active === 'Farms' ? <Farms key={user.id} onSessionExpired={clearSession} /> : ['Plots', 'Crops', 'Seasons'].includes(active) ? <Cultivation key={`${user.id}-${active}`} module={active} onSessionExpired={clearSession} /> : ['Activities', 'Expenses', 'Harvests', 'Sales'].includes(active) ? <Records key={`${user.id}-${active}`} module={active} onSessionExpired={clearSession} /> : active === 'Reports' ? <Reports key={user.id} onSessionExpired={clearSession} /> : <section className="card"><h2>Coming soon</h2><p>{active} will be available in a future update.</p><a href="/dashboard/farms" onClick={(event) => navigate(event, '/dashboard/farms')}>Manage your farms</a></section>}
    </main>
  </DashboardLayout>
}
function Connection() {
  const [status, setStatus] = useState('')
  async function check() {
    setStatus('Checking connection...')
    try { await checkApi(AbortSignal.timeout(5000)); setStatus('Connected - the Farmwise API is reachable.') }
    catch { setStatus('API unavailable. Check the backend and API URL.') }
  }
  return <div className="connection"><button className="secondary" disabled={status === 'Checking connection...'} onClick={check}>Check API connection</button><p role="status">{status}</p></div>
}
