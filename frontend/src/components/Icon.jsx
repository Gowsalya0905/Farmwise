const paths = {
  Dashboard: 'M3 3h8v8H3zM15 3h6v5h-6zM3 15h8v6H3zM15 12h6v9h-6z',
  Farms: 'M3 21V10l9-7 9 7v11M8 21v-7h8v7M9 9h6',
  Plots: 'M3 3h8v8H3zM15 3h6v8h-6zM3 15h8v6H3zM15 15h6v6h-6z',
  Crops: 'M12 21V10M12 14C3 14 3 5 3 5s9 0 9 9ZM12 10c0-7 9-7 9-7s0 7-9 7Z',
  Seasons: 'M7 3v4M17 3v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2M7 14h3M14 14h3',
  Activities: 'M9 5H5v16h14V5h-4M9 3h6v4H9zM7 12l2 2 3-3M13 16h3',
  Expenses: 'M4 5h16v15H4zM4 9h16M15 14h5M7 3v2',
  Harvests: 'M3 10h18l-3 11H6zM7 10l5-7 5 7M9 14v3M15 14v3',
  Sales: 'M3 3h3l3 12h10l2-8H7M10 19h1M17 19h1',
  Reports: 'M4 3v18h17M8 17v-5M13 17V7M18 17v-8',
  Profile: 'M8 7a4 4 0 1 0 8 0 4 4 0 1 0-8 0M4 21v-3a5 5 0 0 1 5-5h6a5 5 0 0 1 5 5v3',
  Menu: 'M4 6h16M4 12h16M4 18h16',
  Close: 'm6 6 12 12M6 18 18 6',
  Plus: 'M12 5v14M5 12h14',
  Chevron: 'm6 9 6 6 6-6',
  Logout: 'M9 3H4v18h5M9 12h12m-5-5 5 5-5 5',
}
export default function Icon({ name }) {
  return <svg className="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d={paths[name] || paths.Farms} /></svg>
}
