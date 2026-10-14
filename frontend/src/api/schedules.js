import { initializeCsrfProtection, request } from './client'

export function getSchedules(start, end, signal) {
  const query = new URLSearchParams({ start, end })

  return request(`/api/schedules?${query}`, { signal })
}

export async function createSchedule(schedule) {
  await initializeCsrfProtection()

  return request('/api/schedules', {
    method: 'POST',
    body: schedule,
    requiresCsrf: true,
  })
}
