import { api } from './api'

export async function loadAll(endpoint, signal) {
  const records = []
  let page = 1
  let result
  do {
    result = await api(`/${endpoint}?page=${page++}`, { signal })
    records.push(...result.data)
  } while (page <= result.last_page)
  return records
}
