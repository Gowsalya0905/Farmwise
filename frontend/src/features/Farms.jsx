import { useEffect, useRef, useState } from 'react'
import { api } from '../lib/api'

export default function Farms({ onSessionExpired }) {
  const [result, setResult] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [formError, setFormError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const [page, setPage] = useState(1)
  const [version, setVersion] = useState(0)
  const input = useRef(null)
  const [editing, setEditing] = useState(null)
  const [name, setName] = useState('')
  const mutation = useRef(null)
  const expire = useRef(onSessionExpired)
  useEffect(() => { expire.current = onSessionExpired }, [onSessionExpired])
  useEffect(() => () => mutation.current?.abort(), [])
  useEffect(() => {
    const controller = new AbortController()
    api(`/farms?page=${page}`, { signal: controller.signal }).then((data) => { if (!controller.signal.aborted) setResult(data) }).catch((err) => {
      if (controller.signal.aborted) return
      if (err.status === 401 || err.status === 419) expire.current()
      else setError(err.message)
    }).finally(() => { if (!controller.signal.aborted) setLoading(false) })
    return () => controller.abort()
  }, [page, version])
  async function saveFarm(method, id, body) {
    const controller = new AbortController()
    mutation.current = controller
    setBusy(true); setFormError(''); setNotice('')
    try {
      await api(`/farms${id ? `/${id}` : ''}`, { method, body, signal: controller.signal })
      if (controller.signal.aborted) return
      setName(''); setEditing(null); setNotice(method === 'DELETE' ? 'Farm deleted.' : id ? 'Farm updated.' : 'Farm added.'); setLoading(true); setError(''); setPage(1); setVersion((value) => value + 1)
    } catch (err) {
      if (controller.signal.aborted) return
      if (err.status === 401 || err.status === 419) expire.current()
      else setFormError(err.message)
    } finally { if (!controller.signal.aborted) setBusy(false) }
  }
  return <>
    <div className="page-intro"><p className="muted">Manage your farms in one place. Farms with plots cannot be deleted until those plots are removed.</p></div>
    {formError && <p className="error" role="alert">{formError}</p>}<p role="status">{notice}</p>
    <div className="farm-grid"><section className="card" aria-labelledby="farms-heading" aria-busy={loading}><h2 id="farms-heading">Your farms</h2>
      {loading ? <p role="status">Loading your farms…</p> : error ? <><p className="error" role="alert">Could not load farms: {error}</p><button onClick={() => { setLoading(true); setError(''); setVersion(version + 1) }}>Retry</button></> : <>
        {result?.total === 0 ? <p>You haven’t added any farms yet. Add your first farm to get started.</p> : <ul className="cultivation-list">{result?.data.map((farm) => <li key={farm.id}><strong>{farm.name}</strong><div className="record-actions"><button className="secondary" disabled={busy} onClick={() => { setEditing(farm.id); setName(farm.name); setFormError(''); setNotice(''); input.current?.focus(); input.current?.scrollIntoView({ block: 'center', behavior: 'smooth' }) }}>Edit</button><button className="secondary" disabled={busy} onClick={() => { if (window.confirm(`Delete ${farm.name}? This cannot be undone.`)) saveFarm('DELETE', farm.id) }}>Delete</button></div></li>)}</ul>}
        {result?.total > 0 && <div className="pagination"><p className="muted">Showing {result.from}–{result.to} of {result.total} farms</p>{result.last_page > 1 && <div><button className="secondary" disabled={page <= 1} onClick={() => { setLoading(true); setError(''); setPage(page - 1) }}>Previous</button><span>Page {result.current_page} of {result.last_page}</span><button className="secondary" disabled={page >= result.last_page} onClick={() => { setLoading(true); setError(''); setPage(page + 1) }}>Next</button></div>}</div>}
      </>}
    </section><section className="card" aria-labelledby="add-farm-heading"><h2 id="add-farm-heading">{editing ? 'Edit farm' : 'Add a farm'}</h2><p className="muted">Give your farm a name you’ll recognize.</p><form onSubmit={(event) => { event.preventDefault(); saveFarm(editing ? 'PUT' : 'POST', editing, { name }) }}><label htmlFor="farm-name">Farm name</label><input id="farm-name" ref={input} name="name" value={name} onChange={(event) => setName(event.target.value)} required maxLength={255} disabled={busy} placeholder="e.g. Riverside Farm" /><button disabled={busy}>{busy ? 'Saving...' : editing ? 'Save changes' : 'Add farm'}</button>{editing && <button type="button" className="secondary" disabled={busy} onClick={() => { setEditing(null); setName(''); setFormError('') }}>Cancel edit</button>}</form></section></div>
  </>
}
