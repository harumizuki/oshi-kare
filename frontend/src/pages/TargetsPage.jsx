import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ApiError } from '../api/client'
import { getTargets } from '../api/targets'

function TargetsPage({ user, onLogout, isLoggingOut, logoutError, onSessionExpired }) {
  const [targets, setTargets] = useState([])
  const [targetStatus, setTargetStatus] = useState('loading')
  const [retryCount, setRetryCount] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    let isCurrentRequest = true

    setTargetStatus('loading')

    getTargets(controller.signal)
      .then((response) => {
        if (!isCurrentRequest) return

        setTargets(response.data)
        setTargetStatus('success')
      })
      .catch((error) => {
        if (!isCurrentRequest || error.name === 'AbortError') return

        if (error instanceof ApiError && error.status === 401) {
          onSessionExpired()
          return
        }

        setTargetStatus('error')
      })

    return () => {
      isCurrentRequest = false
      controller.abort()
    }
  }, [onSessionExpired, retryCount])

  return (
    <main className="app-shell">
      <header className="app-header target-page-header">
        <div>
          <p className="eyebrow">好きなもの管理帳</p>
          <h1>推しカレ</h1>
        </div>

        <div className="header-actions">
          <div className="user-area">
            <span>{user.name}</span>
            <button type="button" onClick={onLogout} disabled={isLoggingOut}>
              {isLoggingOut ? 'ログアウト中…' : 'ログアウト'}
            </button>
          </div>
          <Link className="navigation-link" to="/">カレンダーに戻る</Link>
          {logoutError && <p className="header-error" role="alert">{logoutError}</p>}
        </div>
      </header>

      <section className="panel targets-panel" aria-labelledby="targets-title">
        <div className="targets-heading">
          <div>
            <p className="section-label">登録済みの推し</p>
            <h2 id="targets-title">推し一覧</h2>
          </div>
          {targetStatus === 'success' && <p>{targets.length}件</p>}
        </div>

        {targetStatus === 'loading' && (
          <div className="target-state" role="status">
            <span className="loading-spinner" aria-hidden="true" />
            <p>推しを読み込んでいます…</p>
          </div>
        )}

        {targetStatus === 'error' && (
          <div className="target-state" role="alert">
            <span aria-hidden="true">⚠️</span>
            <h3>推しを取得できませんでした</h3>
            <p>通信状況を確認して、もう一度お試しください。</p>
            <button className="retry-button" type="button" onClick={() => setRetryCount((count) => count + 1)}>
              再試行
            </button>
          </div>
        )}

        {targetStatus === 'success' && targets.length === 0 && (
          <div className="empty-state">
            <span aria-hidden="true">🌱</span>
            <h3>登録されている推しはありません</h3>
            <p>推しを登録すると、ここで確認できるようになります。</p>
          </div>
        )}

        {targetStatus === 'success' && targets.length > 0 && (
          <ul className="target-list">
            {targets.map((target) => (
              <li className="target-card" key={target.id}>
                <div className="target-card-heading">
                  <h3>{target.name}</h3>
                  <span className="target-category">{target.category.name}</span>
                </div>
                <p className="target-type">{target.target_type}</p>
                <p className={target.memo ? 'target-memo' : 'target-memo is-empty'}>
                  {target.memo || 'メモはありません'}
                </p>
              </li>
            ))}
          </ul>
        )}
      </section>
    </main>
  )
}

export default TargetsPage
