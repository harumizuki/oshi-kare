import { request } from './client'

export function getCategories(signal) {
  return request('/api/categories', { signal })
}
