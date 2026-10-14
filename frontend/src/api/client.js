const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080')
  .replace(/\/$/, '')

export class ApiError extends Error {
  constructor(message, status = 0, data = null) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.data = data
  }
}

function getCookie(name) {
  const cookie = document.cookie
    .split('; ')
    .find((item) => item.startsWith(`${name}=`))

  return cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : null
}

export async function initializeCsrfProtection() {
  await request('/sanctum/csrf-cookie')
}

export async function request(path, options = {}) {
  const { method = 'GET', body, requiresCsrf = false } = options
  const headers = new Headers({
    Accept: 'application/json',
  })

  if (body !== undefined) {
    headers.set('Content-Type', 'application/json')
  }

  if (requiresCsrf) {
    const csrfToken = getCookie('XSRF-TOKEN')

    if (!csrfToken) {
      throw new ApiError('CSRFトークンを取得できませんでした。')
    }

    headers.set('X-XSRF-TOKEN', csrfToken)
  }

  let response

  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      method,
      headers,
      credentials: 'include',
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch {
    throw new ApiError('サーバーと通信できませんでした。')
  }

  const isJson = response.headers.get('content-type')?.includes('application/json')
  const data = isJson ? await response.json() : null

  if (!response.ok) {
    throw new ApiError(data?.message ?? 'リクエストに失敗しました。', response.status, data)
  }

  return data
}
