import { useState } from 'react'
import { ApiError } from '../api/client'

function LoginPage({ onLogin }) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [errorMessage, setErrorMessage] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()

    if (isSubmitting) return

    setIsSubmitting(true)
    setErrorMessage('')

    try {
      await onLogin({ email, password })
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setErrorMessage(error.data?.errors?.email?.[0] ?? '入力内容を確認してください。')
      } else {
        setErrorMessage('サーバーと通信できませんでした。Laravelが起動しているか確認してください。')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <main className="login-page">
      <section className="login-card">
        <div className="login-heading">
          <p className="eyebrow">好きなもの管理帳</p>
          <h1>推しカレ</h1>
          <p>あなたの予定を見るためにログインしてください。</p>
        </div>

        <form className="login-form" onSubmit={handleSubmit}>
          <label htmlFor="email">メールアドレス</label>
          <input
            id="email"
            name="email"
            type="email"
            autoComplete="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
            disabled={isSubmitting}
          />

          <label htmlFor="password">パスワード</label>
          <input
            id="password"
            name="password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            required
            disabled={isSubmitting}
          />

          {errorMessage && <p className="form-error" role="alert">{errorMessage}</p>}

          <button className="primary-button" type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'ログイン中…' : 'ログイン'}
          </button>
        </form>
      </section>
    </main>
  )
}

export default LoginPage
