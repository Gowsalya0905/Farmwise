import { useEffect, useRef, useState } from 'react'
import { api } from '../lib/api'
import { loadAll } from '../lib/records'
import SearchSelect from '../components/SearchSelect'

const configs = {
  Activities: { endpoint: 'activities', singular: 'activity', parent: 'seasons', parentKey: 'season_id', defaults: { season_id: '', date: '', type: '', notes: '' } },
  Expenses: { endpoint: 'expenses', singular: 'expense', parent: 'seasons', parentKey: 'season_id', defaults: { season_id: '', date: '', category: '', amount: '', currency: 'INR', notes: '' } },
  Harvests: { endpoint: 'harvests', singular: 'harvest', parent: 'seasons', parentKey: 'season_id', defaults: { season_id: '', date: '', quantity: '', unit: 'kg', notes: '' } },
  Sales: { endpoint: 'sales', singular: 'sale', parent: 'harvests', parentKey: 'harvest_id', defaults: { harvest_id: '', date: '', quantity: '', unit: 'kg', unit_price: '', currency: 'INR', notes: '' } },
}
const parentLabel = (record) => record.name ? `${record.name} — ${record.plot?.farm?.name || ''} / ${record.plot?.name || ''}` : `${record.season?.name} — ${record.date} — ${record.quantity} ${record.unit}`

export default function Records({ module, onSessionExpired }) {
  const config = configs[module]
  const [result, setResult] = useState(null)
  const [parents, setParents] = useState([])
  const [form, setForm] = useState(config.defaults)
  const [editing, setEditing] = useState(null)
  const [page, setPage] = useState(1)
  const [version, setVersion] = useState(0)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [today, setToday] = useState(() => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date()))
  useEffect(() => {
    const timer = window.setInterval(() => setToday(new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date())), 60000)
    return () => window.clearInterval(timer)
  }, [])
  const mutation = useRef(null)
  const expire = useRef(onSessionExpired)
  useEffect(() => { expire.current = onSessionExpired }, [onSessionExpired])
  useEffect(() => () => mutation.current?.abort(), [])
  useEffect(() => {
    const controller = new AbortController()
    Promise.all([api(`/${config.endpoint}?page=${page}`, { signal: controller.signal }), loadAll(config.parent, controller.signal)])
      .then(([data, choices]) => { if (!controller.signal.aborted) { setResult(data); setParents(choices) } })
      .catch((err) => { if (!controller.signal.aborted) { if ([401, 419].includes(err.status)) expire.current(); else setError(err.message) } })
      .finally(() => { if (!controller.signal.aborted) setLoading(false) })
    return () => controller.abort()
  }, [config.endpoint, config.parent, page, version])
  function reload(nextPage = page) { setLoading(true); setError(''); setPage(nextPage); setVersion((value) => value + 1) }
  function reset() { setEditing(null); setForm(config.defaults) }
  function change(name, value) {
    setForm((current) => ({ ...current, [name]: value, ...(name === 'harvest_id' ? { unit: parents.find((parent) => String(parent.id) === value)?.unit || 'kg' } : {}) }))
  }
  async function save(method, id, body) {
    const controller = new AbortController()
    mutation.current = controller
    setBusy(true); setError(''); setNotice('')
    try {
      await api(`/${config.endpoint}${id ? `/${id}` : ''}`, { method, body, signal: controller.signal })
      if (controller.signal.aborted) return
      reset(); setNotice(method === 'DELETE' ? 'Record deleted.' : 'Record saved.'); reload(1)
    } catch (err) {
      if (controller.signal.aborted) return
      if ([401, 419].includes(err.status)) expire.current(); else setError(err.message)
    } finally { if (!controller.signal.aborted) setBusy(false) }
  }
  function field(name, label, type = 'text') {
    const numeric = type === 'number'
    const quantity = name === 'quantity'
    return <label>{label}<input value={form[name]} type={type} required disabled={busy} maxLength={type === 'text' ? 100 : undefined} min={numeric ? quantity ? '0.0001' : '0.01' : name === 'date' && module === 'Sales' ? parents.find((parent) => String(parent.id) === String(form.harvest_id))?.date : undefined} max={numeric ? quantity ? '999999.9999' : name === 'amount' ? '9999999999.99' : '999999.99' : name === 'date' && module !== 'Activities' ? today : undefined} step={numeric ? quantity ? '0.0001' : '0.01' : undefined} onChange={(event) => change(name, event.target.value)} /></label>
  }
  function options(name, label, values, disabled = false) {
    return <SearchSelect label={label} required value={form[name]} disabled={busy || disabled} onChange={(value) => change(name, value)} options={values.map((value) => ({ value, label: value }))} />
  }
  return <>
    <p className="muted">{module === 'Activities' ? 'Record dated farm work for each season.' : module === 'Expenses' ? 'Track season costs with an explicit currency.' : module === 'Harvests' ? 'Record harvested quantities and units.' : 'Record sales against a harvest. The server calculates totals and checks remaining stock.'}</p>
    {error && <p className="error" role="alert">{error}</p>}<p role="status">{notice}</p>
    <div className="farm-grid"><section className="card" aria-busy={loading}><h2>Your {config.endpoint}</h2>
      {loading ? <p role="status">Loading...</p> : !result ? <button onClick={() => reload()}>Retry</button> : <>
        {!result.total ? <p>No {config.endpoint} recorded yet.</p> : <ul className="cultivation-list">{result.data.map((record) => <li key={record.id}><div><strong>{record.type || record.category || (module === 'Sales' ? 'Sale' : 'Harvest')}</strong><p className="muted">{record.date} · {record.season?.name || record.harvest?.season?.name}<br />{module === 'Expenses' ? `${record.amount} ${record.currency}` : module === 'Activities' ? '' : `${record.quantity} ${record.unit}`}{module === 'Sales' && ` · ${record.unit_price} ${record.currency} per ${record.unit} · Total ${record.total} ${record.currency}`}</p>{record.notes && <p className="record-notes">{record.notes}</p>}</div><div className="record-actions"><button className="secondary" disabled={busy} onClick={() => { setEditing(record.id); setForm(Object.fromEntries(Object.keys(config.defaults).map((key) => [key, record[key] ?? '']))); setNotice('') }}>Edit</button><button className="secondary" disabled={busy} onClick={() => { if (window.confirm('Delete this record? This cannot be undone.')) save('DELETE', record.id) }}>Delete</button></div></li>)}</ul>}
        {result.last_page > 1 && <div className="pagination"><button disabled={busy || page <= 1} onClick={() => reload(page - 1)}>Previous</button><span>Page {page} of {result.last_page}</span><button disabled={busy || page >= result.last_page} onClick={() => reload(page + 1)}>Next</button></div>}
      </>}
    </section><section className="card"><h2>{editing ? 'Edit' : 'Add'} {config.singular}</h2>
      {!loading && !parents.length && <p>Add a {config.parent === 'harvests' ? 'harvest' : 'season'} first.</p>}
      <form onSubmit={(event) => { event.preventDefault(); save(editing ? 'PUT' : 'POST', editing, { ...form, notes: form.notes || null }) }}>
        <SearchSelect label={module === 'Sales' ? 'Harvest' : 'Season'} required value={form[config.parentKey]} disabled={busy || module === 'Sales' && !!editing} onChange={(value) => change(config.parentKey, value)} placeholder={`Select ${module === 'Sales' ? 'harvest' : 'season'}`} options={parents.map((parent) => ({ value: parent.id, label: parentLabel(parent) }))} />
        {field('date', module === 'Sales' ? 'Sale date (today or earlier)' : module === 'Activities' ? 'Date' : 'Date (today or earlier)', 'date')}
        {module === 'Activities' && field('type', 'Activity type (e.g. irrigation)')}
        {module === 'Expenses' && <>{field('category', 'Category (e.g. seeds)')}{field('amount', 'Amount', 'number')}</>}
        {['Harvests', 'Sales'].includes(module) && <>{field('quantity', 'Quantity', 'number')}{options('unit', 'Unit', ['kg', 'tonne', 'litre', 'piece'], module === 'Sales')}</>}
        {module === 'Sales' && field('unit_price', 'Price per unit', 'number')}
        {['Expenses', 'Sales'].includes(module) && options('currency', 'Currency', ['INR', 'USD', 'EUR', 'GBP'])}
        <label>Notes (optional)<textarea value={form.notes} maxLength={5000} disabled={busy} onChange={(event) => change('notes', event.target.value)} /></label>
        <button disabled={busy || loading || !parents.length}>{busy ? 'Saving...' : editing ? 'Save changes' : `Add ${config.singular}`}</button>{editing && <button type="button" className="secondary" disabled={busy} onClick={reset}>Cancel edit</button>}
      </form>
    </section></div>
  </>
}
