import { useEffect, useId, useRef, useState } from 'react'
import { createPortal } from 'react-dom'

export default function SearchSelect({ label, value, options, onChange, placeholder = 'Select an option', required = false, disabled = false }) {
  const id = useId()
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [active, setActive] = useState(0)
  const [position, setPosition] = useState({})
  const root = useRef(null)
  const menu = useRef(null)
  const input = useRef(null)
  const selected = options.find((option) => String(option.value) === String(value))
  const filtered = options.filter((option) => option.label.toLowerCase().includes(query.toLowerCase()))
  function place() {
    const rect = root.current.getBoundingClientRect()
    const below = window.innerHeight - rect.bottom
    const above = rect.top
    const upwards = below < 200 && above > below
    setPosition({ left: rect.left, width: rect.width, ...(upwards ? { bottom: window.innerHeight - rect.top + 4 } : { top: rect.bottom + 4 }), maxHeight: Math.max(100, Math.min(260, (upwards ? above : below) - 12)) })
  }
  function show() { if (!disabled) { place(); setQuery(''); setActive(0); setOpen(true) } }
  function choose(option) { onChange(String(option.value)); setOpen(false); setQuery('') }
  useEffect(() => {
    input.current?.setCustomValidity(required && !selected ? 'Please select an option from the list.' : '')
  }, [required, selected])
  useEffect(() => {
    if (!open) return
    function outside(event) { if (!root.current?.contains(event.target) && !menu.current?.contains(event.target)) setOpen(false) }
    function reposition(event) { if (!menu.current?.contains(event.target)) place() }
    window.addEventListener('pointerdown', outside)
    window.addEventListener('resize', reposition)
    window.addEventListener('scroll', reposition, true)
    return () => {
      window.removeEventListener('pointerdown', outside)
      window.removeEventListener('resize', reposition)
      window.removeEventListener('scroll', reposition, true)
    }
  }, [open])
  function key(event) {
    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); setOpen(false) }
    if (event.key === 'Tab') setOpen(false)
    if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
      event.preventDefault()
      if (!open) { show(); return }
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? filtered.length - 1 : Math.max(0, Math.min(filtered.length - 1, active + (event.key === 'ArrowDown' ? 1 : -1)))
      setActive(next)
      document.getElementById(`${id}-option-${next}`)?.scrollIntoView({ block: 'nearest' })
    }
    if (event.key === 'Enter' && open) { event.preventDefault(); if (filtered[active]) choose(filtered[active]) }
  }
  return <div className="select-field"><label htmlFor={id}>{label}{required && <span className="sr-only"> (required)</span>}</label>
    <div className={`search-select ${open ? 'is-open' : ''}`} ref={root}>
      <input ref={input} id={id} role="combobox" aria-required={required} aria-autocomplete="list" aria-expanded={open && !disabled} aria-controls={`${id}-list`} aria-activedescendant={open && filtered[active] ? `${id}-option-${active}` : undefined} autoComplete="off" disabled={disabled} placeholder={placeholder} value={open ? query : selected?.label || ''} onFocus={show} onClick={() => { if (!open) show() }} onChange={(event) => { setQuery(event.target.value); setActive(0); setOpen(true); if (value !== '') onChange('') }} onKeyDown={key} onBlur={() => setOpen(false)} />
      <span className="select-chevron" aria-hidden="true">⌄</span>
    </div>
    {open && !disabled && createPortal(<div ref={menu} className="select-menu" style={position} id={`${id}-list`} role="listbox" aria-label={label} onMouseDown={(event) => event.preventDefault()}>
      {filtered.length ? filtered.map((option, index) => <div key={option.value} id={`${id}-option-${index}`} role="option" aria-selected={String(option.value) === String(value)} className={`select-option ${index === active ? 'is-active' : ''}`} onPointerMove={() => setActive(index)} onClick={() => choose(option)}>{option.label}</div>) : <div className="select-empty" role="status">No matching options</div>}
    </div>, document.body)}
  </div>
}
