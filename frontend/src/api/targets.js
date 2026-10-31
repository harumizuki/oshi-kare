import { request } from './client'

export function getTargets(signal, keyword = '') {
  const query = new URLSearchParams()

  if (keyword) query.set('keyword', keyword)

  const queryString = query.toString()

  return request(`/api/targets${queryString ? `?${queryString}` : ''}`, { signal })
}
