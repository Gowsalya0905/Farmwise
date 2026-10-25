import { useEffect, useRef, useState } from 'react'
import { api } from '../lib/api'
import Icon from '../components/Icon'

export default function Dashboard({ navigate, onSessionExpired }) {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')
  const [attempt, setAttempt] = useState(0)
  const expire = useRef(onSessionExpired)
  useEffect(() => { expire.current = onSessionExpired }, [onSessionExpired])
  useEffect(() => {
    const controller = new AbortController()
    api('/dashboard', { signal: controller.signal }).then((result) => { if (!controller.signal.aborted) setData(result) }).catch((err) => {
      if (controller.signal.aborted) return
      if ([401, 419].includes(err.status)) expire.current(); else setError(err.message)
    })
    return () => controller.abort()
  }, [attempt])
  const cards = [
    ['Farms', 'farms', 'Farms you manage'], ['Plots', 'plots', 'Plots across your farms'],
    ['Crops', 'crops', 'Your crop catalogue'], ['Seasons', 'active_seasons', 'Active cultivation seasons'],
  ]
  return <>
    <p className="muted">Your farming workspace at a glance. Open a module to manage its records.</p>
    {error ? <p className="error" role="alert">{error} <button onClick={() => { setError(''); setAttempt((value) => value + 1) }}>Retry</button></p> : !data ? <p role="status">Loading your overview...</p> : <>
      <div className="overview-grid">{cards.map(([module, key, description]) => <a className="card overview-card" key={key} href={`/dashboard/${module.toLowerCase()}`} onClick={(event) => navigate(event, `/dashboard/${module.toLowerCase()}`)}><Icon name={module} /><span>{module === 'Seasons' ? 'Active seasons' : module}</span><strong>{data.counts[key]}</strong><small className="muted">{description}</small></a>)}</div>
      <div className="overview-grid overview-secondary"><section className="card"><h2>Season planning</h2><p><strong>{data.counts.planned_seasons}</strong> planned seasons</p><p><strong>{data.counts.completed_seasons}</strong> completed seasons</p><a href="/dashboard/seasons" onClick={(event) => navigate(event, '/dashboard/seasons')}>Review your seasons →</a></section><section className="card"><h2>Work recorded this month</h2><p><strong>{data.counts.month_activities}</strong> activities</p><p><strong>{data.counts.month_harvests}</strong> harvest records</p><p className="muted">{data.month_label}</p><a href="/dashboard/activities" onClick={(event) => navigate(event, '/dashboard/activities')}>View farm activities →</a></section></div>
      <section className="card overview-finances"><h2>Finances this month</h2><p className="muted">Recorded sales minus expenses. Totals stay separate by currency.</p>{data.financials.length ? <div className="report-totals">{data.financials.map((row) => <div key={row.currency}><h3>{row.currency}</h3><p>Expenses: {row.expenses}</p><p>Sales: {row.sales}</p><strong>Net: {row.net}</strong></div>)}</div> : <p>No expenses or sales recorded this month.</p>}<a href="/dashboard/reports" onClick={(event) => navigate(event, '/dashboard/reports')}>Open reports →</a></section>
    </>}
  </>
}
