import { request } from './client'

export function getTargets(signal) {
  return request('/api/targets', { signal })
}
