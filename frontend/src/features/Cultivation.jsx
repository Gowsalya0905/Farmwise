import { useEffect, useRef, useState } from 'react'
import { api } from '../lib/api'
import SearchSelect from '../components/SearchSelect'

const definitions = {
  Plots: { endpoint: 'plots', singular: 'plot', defaults: { name: '', farm_id: '', area: '', area_unit: 'acre' } },
  Crops: { endpoint: 'crops', singular: 'crop', defaults: { name: '', variety: '' } },
  Seasons: { endpoint: 'seasons', singular: 'season', defaults: { name: '', plot_id: '', crop_id: '', start_date: '', end_date: '', status: 'planned' } },
}

async function allRecords(endpoint, signal) {
  const records = []
  let page = 1
  let result
  do {
    result = await api(`/${endpoint}?page=${page++}`, { signal })
    records.push(...result.data)
  } while (page <= result.last_page)
  return records
}

export default function Cultivation({ module, onSessionExpired }) {
  const config = definitions[module]
  const [result, setResult] = useState(null)
  const [choices, setChoices] = useState({})
  const [form, setForm] = useState(config.defaults)
  const [editing, setEditing] = useState(null)
  const [page, setPage] = useState(1)
  const [version, setVersion] = useState(0)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const mutation = useRef(null)
  const expire = useRef(onSessionExpired)
  useEffect(() => { expire.current = onSessionExpired }, [onSessionExpired])
  useEffect(() => () => mutation.current?.abort(), [])
  useEffect(() => {
    const controller = new AbortController()
    const needed = module === 'Plots' ? ['farms'] : module === 'Seasons' ? ['plots', 'crops'] : []
    Promise.all([api(`/${config.endpoint}?page=${page}`, { signal: controller.signal }), ...needed.map(async (name) => [name, await allRecords(name, controller.signal)])])
      .then(([data, ...lists]) => { if (!controller.signal.aborted) { setResult(data); setChoices(Object.fromEntries(lists)) } })
      .catch((err) => { if (!controller.signal.aborted) { if ([401, 419].includes(err.status)) expire.current(); else setError(err.message) } })
      .finally(() => { if (!controller.signal.aborted) setLoading(false) })
    return () => controller.abort()
  }, [module, config.endpoint, page, version])

  const missing = module === 'Plots' ? !choices.farms?.length : module === 'Seasons' ? !choices.plots?.length || !choices.crops?.length : false
  function field(name, value) { setForm((current) => ({ ...current, [name]: value })) }
  function reset() { setEditing(null); setForm(config.defaults) }
  async function mutate(method, id, body) {
    const controller = new AbortController()
    mutation.current = controller
    setBusy(true); setError(''); setNotice('')
    try {
      await api(`/${config.endpoint}${id ? `/${id}` : ''}`, { method, body, signal: controller.signal })
      if (controller.signal.aborted) return
      reset(); setNotice(method === 'DELETE' ? 'Record deleted.' : 'Record saved.'); setLoading(true); setPage(1); setVersion((value) => value + 1)
    } catch (err) {
      if (controller.signal.aborted) return
      if ([401, 419].includes(err.status)) expire.current(); else setError(err.message)
    } finally { if (!controller.signal.aborted) setBusy(false) }
  }
  function select(name, label, options) {
    return <SearchSelect label={label} required value={form[name]} disabled={busy} onChange={(value) => field(name, value)} placeholder={`Select ${label.toLowerCase()}`} options={options.map((item) => ({ value: item.id, label: name === 'crop_id' ? `${item.name} - ${item.variety || 'No variety specified'}` : `${item.name}${item.farm ? ` — ${item.farm.name}` : ''}` }))} />
  }
  function input(name, label, type = 'text', required = false) {
    return <label>{label}<input type={type} value={form[name] ?? ''} required={required} maxLength={type === 'text' ? 255 : undefined} min={name === 'area' ? '0.0001' : name === 'end_date' ? form.start_date : undefined} max={name === 'area' ? '99999999.9999' : undefined} step={name === 'area' ? '0.0001' : undefined} disabled={busy} onChange={(event) => field(name, event.target.value)} /></label>
  }
  return <>
    <p className="muted">{module === 'Plots' ? 'Divide your farms into plots and record their area.' : module === 'Crops' ? 'Keep your own crop and variety catalogue.' : 'Plan cultivation by connecting a plot, crop and dates.'}</p>
    {error && <p className="error" role="alert">{error}</p>}<p role="status">{notice}</p>
    <div className="farm-grid"><section className="card" aria-busy={loading}><h2>Your {config.endpoint}</h2>
      {loading ? <p role="status">Loading...</p> : <>
        {!result ? <button onClick={() => { setLoading(true); setError(''); setVersion((value) => value + 1) }}>Retry</button> : !result.total ? <p>No {config.endpoint} yet. Add your first {config.singular}.</p> : <ul className="cultivation-list">{result.data.map((record) => <li key={record.id}><div><strong>{record.name}</strong><p className="muted">{module === 'Plots' ? `${record.farm?.name}${record.area ? ` · ${record.area} ${record.area_unit}` : ''}` : module === 'Crops' ? record.variety || 'No variety specified' : `${record.plot?.name} · ${record.crop?.name} · ${record.status}`} {module === 'Seasons' && <><br />{record.start_date} — {record.end_date || 'End date pending'}</>}</p></div><div className="record-actions"><button className="secondary" disabled={busy} onClick={() => { setEditing(record.id); setForm(Object.fromEntries(Object.keys(config.defaults).map((name) => [name, record[name] ?? '']))); setNotice('') }}>Edit</button><button className="secondary" disabled={busy} onClick={() => { if (window.confirm(`Delete ${record.name}? This cannot be undone.`)) mutate('DELETE', record.id) }}>Delete</button></div></li>)}</ul>}
        {result?.last_page > 1 && <div className="pagination"><button disabled={busy || page <= 1} onClick={() => { setLoading(true); setError(''); setPage(page - 1) }}>Previous</button><span>Page {page} of {result.last_page}</span><button disabled={busy || page >= result.last_page} onClick={() => { setLoading(true); setError(''); setPage(page + 1) }}>Next</button></div>}
      </>}
    </section><section className="card"><h2>{editing ? 'Edit' : 'Add'} {config.singular}</h2>
      {!loading && missing && <p role="status">{module === 'Plots' ? 'Add a farm first from the Farms page.' : 'Add at least one plot and crop before creating a season.'}</p>}
      <form onSubmit={(event) => { event.preventDefault(); mutate(editing ? 'PUT' : 'POST', editing, Object.fromEntries(Object.entries(form).map(([key, value]) => [key, value === '' ? null : value]))) }}>
        {input('name', 'Name', 'text', true)}
        {module === 'Plots' && <>{select('farm_id', 'Farm', choices.farms || [])}{input('area', 'Area (optional)', 'number')}<SearchSelect label="Area unit" required value={form.area_unit} disabled={busy} onChange={(value) => field('area_unit', value)} options={[{ value: 'acre', label: 'Acres' }, { value: 'hectare', label: 'Hectares' }, { value: 'sqm', label: 'Square metres' }]} /></>}
        {module === 'Crops' && input('variety', 'Variety (optional)')}
        {module === 'Seasons' && <>{select('plot_id', 'Plot', choices.plots || [])}{select('crop_id', 'Crop', choices.crops || [])}{input('start_date', 'Planting / start date', 'date', true)}{input('end_date', 'Planned / end date (optional)', 'date')}<SearchSelect label="Status" required value={form.status} disabled={busy} onChange={(value) => field('status', value)} options={['planned', 'active', 'completed', 'cancelled'].map((value) => ({ value, label: value[0].toUpperCase() + value.slice(1) }))} /></>}
        <button disabled={busy || loading || missing}>{busy ? 'Saving...' : editing ? 'Save changes' : `Add ${config.singular}`}</button>{editing && <button type="button" className="secondary" disabled={busy} onClick={reset}>Cancel edit</button>}
      </form>
    </section></div>
  </>
}
