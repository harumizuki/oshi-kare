import { initializeCsrfProtection, request } from './client'

export function getSchedules(start, end, signal) {
  const query = new URLSearchParams({ start, end })

  return request(`/api/schedules?${query}`, { signal })
}

export function getSchedule(scheduleId, signal) {
  return request(`/api/schedules/${scheduleId}`, { signal })
}

export async function createSchedule(schedule) {
  await initializeCsrfProtection()

  return request('/api/schedules', {
    method: 'POST',
    body: schedule,
    requiresCsrf: true,
  })
}

export async function updateSchedule(scheduleId, schedule) {
  await initializeCsrfProtection()

  return request(`/api/schedules/${scheduleId}`, {
    method: 'PATCH',
    body: schedule,
    requiresCsrf: true,
  })
}
