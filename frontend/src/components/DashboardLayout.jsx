import { useEffect, useRef, useState } from 'react'
import Icon from './Icon'

const modules = ['Dashboard', 'Farms', 'Plots', 'Crops', 'Seasons', 'Activities', 'Expenses', 'Harvests', 'Sales', 'Reports']
const routeFor = (name) => name === 'Dashboard' ? '/dashboard' : `/dashboard/${name.toLowerCase()}`

export default function DashboardLayout({ user, active, navigate, busy, logout, children }) {
  const [drawer, setDrawer] = useState(false)
  const [account, setAccount] = useState(false)
  const [year] = useState(() => new Date().getFullYear())
  const menuButton = useRef(null)
  const drawerPanel = useRef(null)
  const accountButton = useRef(null)
  const accountPanel = useRef(null)
  function closeDrawer() { setDrawer(false); menuButton.current?.focus() }
  function link(event, to) {
    const ordinaryClick = event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey
    setDrawer(false); setAccount(false); navigate(event, to)
    if (ordinaryClick) window.requestAnimationFrame(() => document.getElementById('dashboard-content')?.focus())
  }
  useEffect(() => {
    const close = () => { setDrawer(false); setAccount(false) }
    window.addEventListener('popstate', close)
    window.addEventListener('farmwise:navigate', close)
    const media = window.matchMedia('(min-width: 761px)')
    media.addEventListener('change', close)
    return () => {
      window.removeEventListener('popstate', close)
      window.removeEventListener('farmwise:navigate', close)
      media.removeEventListener('change', close)
    }
  }, [])
  useEffect(() => {
    if (!drawer) return
    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    drawerPanel.current?.querySelector('button')?.focus()
    function key(event) {
      if (event.key === 'Escape') { event.preventDefault(); setDrawer(false); menuButton.current?.focus() }
      if (event.key === 'Tab') {
        const items = drawerPanel.current?.querySelectorAll('a[href], button:not(:disabled)')
        if (!items?.length) return
        const first = items[0], last = items[items.length - 1]
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
      }
    }
    window.addEventListener('keydown', key)
    return () => { document.body.style.overflow = previousOverflow; window.removeEventListener('keydown', key) }
  }, [drawer])
  useEffect(() => {
    if (!account) return
    accountPanel.current?.querySelector('a')?.focus()
    function key(event) {
      if (event.key === 'Escape') { event.preventDefault(); setAccount(false); accountButton.current?.focus() }
    }
    function outside(event) { if (!accountPanel.current?.contains(event.target)) setAccount(false) }
    window.addEventListener('keydown', key)
    window.addEventListener('pointerdown', outside)
    return () => { window.removeEventListener('keydown', key); window.removeEventListener('pointerdown', outside) }
  }, [account])
  const initials = user.name.trim().split(/\s+/).slice(0, 2).map((part) => [...part][0] || '').join('').toUpperCase()
  return <div className="dashboard">
    <a className="skip-link" href="#dashboard-content">Skip to content</a>
    {drawer && <div className="drawer-backdrop" onClick={closeDrawer} aria-hidden="true" />}
    <aside ref={drawerPanel} id="sidebar" className={`sidebar ${drawer ? 'open' : ''}`} role={drawer ? 'dialog' : undefined} aria-modal={drawer ? true : undefined} aria-label="Farmwise navigation">
      <div className="sidebar-brand"><a className="brand" href="/dashboard" onClick={(event) => link(event, '/dashboard')}><span className="brand-symbol"><Icon name="Crops" /></span><span>Farmwise<small>Your farming workspace</small></span></a><button className="drawer-close secondary" aria-label="Close navigation" onClick={closeDrawer}><Icon name="Close" /></button></div>
      <p className="eyebrow">WORKSPACE</p><nav aria-label="Main navigation">{modules.map((name) => <a key={name} href={routeFor(name)} aria-current={active === name ? 'page' : undefined} onClick={(event) => link(event, routeFor(name))}><Icon name={name} /><span>{name}</span></a>)}</nav>
      <p className="sidebar-note">A place for every farm.<br />A record for every season.</p>
    </aside>
    <div className="workspace" inert={drawer ? true : undefined}>
      <header className="topbar"><button className="menu-button secondary" ref={menuButton} aria-expanded={drawer} aria-controls="sidebar" aria-label="Open navigation" onClick={() => { setAccount(false); setDrawer(true) }}><Icon name="Menu" /></button><span className="header-title">{active}</span>
        <div className="account" ref={accountPanel} onBlur={(event) => { if (!event.currentTarget.contains(event.relatedTarget)) setAccount(false) }}>
          <button className="account-trigger" ref={accountButton} aria-expanded={account} aria-controls="account-options" aria-label={`Account for ${user.name}`} onClick={() => setAccount(!account)} onKeyDown={(event) => { if (event.key === 'ArrowDown') { event.preventDefault(); setAccount(true) } }}><span className="avatar" aria-hidden="true">{initials}</span><span className="account-name">{user.name}</span><Icon name="Chevron" /></button>
          {account && <div className="account-options" id="account-options"><p>{user.email}</p><a href="/profile" aria-current={active === 'Profile' ? 'page' : undefined} onClick={(event) => link(event, '/profile')}><Icon name="Profile" />Profile</a><button disabled={busy} onClick={logout}><Icon name="Logout" />{busy ? 'Signing out...' : 'Log out'}</button></div>}
        </div>
      </header>
      {children}
      <footer className="workspace-footer"><span>© {year} Farmwise. All rights reserved.</span><span className="beta-badge">Version beta</span></footer>
    </div>
  </div>
}
