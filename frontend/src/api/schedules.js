import { request } from './client'

export function getSchedules(start, end, signal) {
  const query = new URLSearchParams({ start, end })

  return request(`/api/schedules?${query}`, { signal })
}
