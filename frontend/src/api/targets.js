import { initializeCsrfProtection, request } from './client'

export function getTargets(signal, keyword = '') {
  const query = new URLSearchParams()

  if (keyword) query.set('keyword', keyword)

  const queryString = query.toString()

  return request(`/api/targets${queryString ? `?${queryString}` : ''}`, { signal })
}

export async function createTarget(target) {
  await initializeCsrfProtection()

  return request('/api/targets', {
    method: 'POST',
    body: target,
    requiresCsrf: true,
  })
}

export async function updateTarget(targetId, target) {
  await initializeCsrfProtection()

  return request(`/api/targets/${targetId}`, {
    method: 'PATCH',
    body: target,
    requiresCsrf: true,
  })
}
