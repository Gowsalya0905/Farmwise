import { useEffect, useRef, useState } from 'react'
import { api } from '../lib/api'
import { loadAll } from '../lib/records'
import SearchSelect from '../components/SearchSelect'

export default function Reports({ onSessionExpired }) {
  const [filters, setFilters] = useState({ year: '', farm_id: '', season_id: '' })
  const [applied, setApplied] = useState({})
  const [choices, setChoices] = useState({ farms: [], seasons: [] })
  const [result, setResult] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const expire = useRef(onSessionExpired)
  useEffect(() => { expire.current = onSessionExpired }, [onSessionExpired])
  useEffect(() => {
    const controller = new AbortController()
    const query = new URLSearchParams(Object.entries(applied).filter(([, value]) => value !== ''))
    Promise.all([api(`/reports?${query}`, { signal: controller.signal }), loadAll('farms', controller.signal), loadAll('seasons', controller.signal)])
      .then(([data, farms, seasons]) => { if (!controller.signal.aborted) { setResult(data); setChoices({ farms, seasons }) } })
      .catch((err) => { if (!controller.signal.aborted) { if ([401, 419].includes(err.status)) expire.current(); else setError(err.message) } })
      .finally(() => { if (!controller.signal.aborted) setLoading(false) })
    return () => controller.abort()
  }, [applied, version])
  function apply(event) { event.preventDefault(); setLoading(true); setError(''); setApplied({ ...filters }); setVersion((value) => value + 1) }
  function clearFilters() {
    setFilters({ year: '', farm_id: '', season_id: '' }); setApplied({}); setLoading(true); setError(''); setVersion((value) => value + 1)
  }
  function change(key, value) { setFilters((current) => ({ ...current, [key]: value, ...(key === 'farm_id' ? { season_id: '' } : {}) })) }
  return <>
    <p className="muted">Review dated expenses, sales and harvests by farm, season and calendar year. Currencies and quantity units stay separate. Net is recorded sales minus recorded expenses; it excludes unsold stock and unrecorded costs.</p>
    <section className="card"><h2>Report filters</h2><form className="report-filters" onSubmit={apply}><label>Calendar year<input type="number" min="1900" max="2100" placeholder="All years" value={filters.year} onChange={(event) => change('year', event.target.value)} /></label>{['farm_id', 'season_id'].map((key) => <SearchSelect key={key} label={key === 'farm_id' ? 'Farm' : 'Season'} value={filters[key]} onChange={(value) => change(key, value)} options={[{ value: '', label: `All ${key === 'farm_id' ? 'farms' : 'seasons'}` }, ...choices[key === 'farm_id' ? 'farms' : 'seasons'].filter((record) => key === 'farm_id' || !filters.farm_id || String(record.plot?.farm_id) === filters.farm_id).map((record) => ({ value: record.id, label: record.name }))]} />)}<button disabled={loading}>Apply filters</button><button type="button" className="secondary" disabled={loading} onClick={clearFilters}>Clear filters</button></form></section>
    {error && <p role="alert" className="error">{error}<button onClick={() => { setLoading(true); setError(''); setVersion((value) => value + 1) }}>Retry</button></p>}
    {loading ? <p role="status">Loading report...</p> : result && <>
      <section className="card"><h2>Financial totals</h2>{!result.totals.length ? <p>No expenses or sales match these filters.</p> : <div className="report-totals">{result.totals.map((row) => <div key={row.currency}><h3>{row.currency}</h3><p>Expenses: {row.expenses}</p><p>Sales: {row.sales}</p><strong>Net: {row.net}</strong></div>)}</div>}</section>
      <section className="card report-table"><h2>Season finances</h2><table><thead><tr>{['Farm', 'Season', 'Year', 'Currency', 'Expenses', 'Sales', 'Net'].map((label) => <th key={label} scope="col">{label}</th>)}</tr></thead><tbody>{result.financials.map((row) => <tr key={`${row.season_id}-${row.year}-${row.currency}`}>{['farm', 'season', 'year', 'currency', 'expenses', 'sales', 'net'].map((key) => <td key={key}>{row[key]}</td>)}</tr>)}</tbody></table></section>
      <section className="card report-table"><h2>Harvest quantities</h2><p className="muted">Area is the current plot area. Yield per area is not calculated.</p>{!result.harvests.length ? <p>No harvests match these filters.</p> : <table><thead><tr>{['Farm', 'Season', 'Year', 'Quantity', 'Unit', 'Plot area'].map((label) => <th key={label} scope="col">{label}</th>)}</tr></thead><tbody>{result.harvests.map((row) => <tr key={`${row.season_id}-${row.year}-${row.unit}`}><td>{row.farm}</td><td>{row.season}</td><td>{row.year}</td><td>{row.quantity}</td><td>{row.unit}</td><td>{row.plot_area ? `${row.plot_area} ${row.area_unit}` : 'Not recorded'}</td></tr>)}</tbody></table>}</section>
    </>}
  </>
}
