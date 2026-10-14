import { useCallback, useEffect, useState } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import { getCurrentUser, login, logout } from './api/auth'
import { ApiError } from './api/client'
import HomePage from './pages/HomePage.jsx'
import LoginPage from './pages/LoginPage.jsx'

function App() {
  const [authStatus, setAuthStatus] = useState('checking')
  const [user, setUser] = useState(null)
  const [authError, setAuthError] = useState('')
  const [isLoggingOut, setIsLoggingOut] = useState(false)
  const [logoutError, setLogoutError] = useState('')

  const checkAuthentication = useCallback(async () => {
    setAuthStatus('checking')
    setAuthError('')

    try {
      const authenticatedUser = await getCurrentUser()
      setUser(authenticatedUser)
      setAuthStatus('authenticated')
    } catch (error) {
      setUser(null)

      if (error instanceof ApiError && error.status === 401) {
        setAuthStatus('guest')
        return
      }

      setAuthError('認証状態を確認できませんでした。Laravelが起動しているか確認してください。')
      setAuthStatus('error')
    }
  }, [])

  useEffect(() => {
    checkAuthentication()
  }, [checkAuthentication])

  const handleLogin = async (credentials) => {
    await login(credentials)
    const authenticatedUser = await getCurrentUser()
    setUser(authenticatedUser)
    setAuthStatus('authenticated')
    setLogoutError('')
  }

  const handleLogout = async () => {
    setIsLoggingOut(true)
    setLogoutError('')

    try {
      await logout()
      setUser(null)
      setAuthStatus('guest')
    } catch {
      setLogoutError('ログアウトできませんでした。通信状況を確認して、もう一度お試しください。')
    } finally {
      setIsLoggingOut(false)
    }
  }

  if (authStatus === 'checking') {
    return <StatusScreen title="ログイン状態を確認しています" message="少々お待ちください。" />
  }

  if (authStatus === 'error') {
    return (
      <StatusScreen title="接続できませんでした" message={authError}>
        <button className="primary-button" type="button" onClick={checkAuthentication}>
          もう一度試す
        </button>
      </StatusScreen>
    )
  }

  return (
    <Routes>
      <Route
        path="/login"
        element={
          authStatus === 'authenticated'
            ? <Navigate to="/" replace />
            : <LoginPage onLogin={handleLogin} />
        }
      />
      <Route
        path="/"
        element={
          authStatus === 'authenticated'
            ? (
              <HomePage
                user={user}
                onLogout={handleLogout}
                isLoggingOut={isLoggingOut}
                logoutError={logoutError}
              />
            )
            : <Navigate to="/login" replace />
        }
      />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

function StatusScreen({ title, message, children }) {
  return (
    <main className="status-screen">
      <section className="status-card" role="status">
        <p className="eyebrow">好きなもの管理帳</p>
        <h1>推しカレ</h1>
        <h2>{title}</h2>
        <p>{message}</p>
        {children}
      </section>
    </main>
  )
}

export default App
