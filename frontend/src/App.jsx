import { useEffect, useState } from 'react'
import { checkApi } from './lib/api'
import './App.css'
import AuthPanel from './features/AuthPanel'

function App() {
  const [status, setStatus] = useState('checking')
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    let active = true
    const controller = new AbortController()
    const timeout = window.setTimeout(() => controller.abort(), 5000)
    void checkApi(controller.signal)
      .then(() => { if (active) setStatus('connected') })
      .catch(() => { if (active) setStatus('unavailable') })
      .finally(() => window.clearTimeout(timeout))
    return () => { active = false; controller.abort(); window.clearTimeout(timeout) }
  }, [attempt])

  return (
    <main>
      <p className="eyebrow">Your farming records, together</p>
      <h1>Farmwise</h1>
      <p>Keep track of your farm, learn from each season, and plan your next steps.</p>
      <section aria-labelledby="connection-heading">
        <h2 id="connection-heading">API connection</h2>
        <p role="status" className={`status ${status}`}>
          {status === 'checking' && 'Checking the connection…'}
          {status === 'connected' && 'Connected — the Farmwise API is reachable.'}
          {status === 'unavailable' && 'API unavailable. Start the backend and check your API URL.'}
        </p>
        <button onClick={() => { setStatus('checking'); setAttempt(attempt + 1) }} disabled={status === 'checking'}>Check again</button>
      </section>
      <AuthPanel />
    </main>
  )
}

export default App
