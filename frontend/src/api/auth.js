import { initializeCsrfProtection, request } from './client'

export function getCurrentUser() {
  return request('/api/user')
}

export async function login(credentials) {
  await initializeCsrfProtection()
  await request('/login', {
    method: 'POST',
    body: credentials,
    requiresCsrf: true,
  })
}

export async function logout() {
  await initializeCsrfProtection()
  await request('/logout', {
    method: 'POST',
    requiresCsrf: true,
  })
}
