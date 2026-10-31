import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { ApiError } from '../api/client'
import { getTargets } from '../api/targets'

function TargetsPage({ user, onLogout, isLoggingOut, logoutError, onSessionExpired }) {
  const [targets, setTargets] = useState([])
  const [targetStatus, setTargetStatus] = useState('loading')
  const [retryCount, setRetryCount] = useState(0)
  const [keyword, setKeyword] = useState('')
  const [debouncedKeyword, setDebouncedKeyword] = useState('')
  const requestVersionRef = useRef(0)
  const normalizedKeyword = keyword.trim()
  const isWaitingForSearch = normalizedKeyword !== debouncedKeyword
  const isSearching = debouncedKeyword !== ''

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setDebouncedKeyword(normalizedKeyword)
    }, 300)

    return () => window.clearTimeout(timer)
  }, [normalizedKeyword])

  useEffect(() => {
    const controller = new AbortController()
    const requestVersion = requestVersionRef.current + 1
    let isCurrentRequest = true

    requestVersionRef.current = requestVersion
    setTargetStatus('loading')

    getTargets(controller.signal, debouncedKeyword)
      .then((response) => {
        if (!isCurrentRequest || requestVersion !== requestVersionRef.current) return

        setTargets(response.data)
        setTargetStatus('success')
      })
      .catch((error) => {
        if (
          !isCurrentRequest
          || requestVersion !== requestVersionRef.current
          || error.name === 'AbortError'
        ) return

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
  }, [debouncedKeyword, onSessionExpired, retryCount])

  const isLoading = isWaitingForSearch || targetStatus === 'loading'

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
          {!isWaitingForSearch && targetStatus === 'success' && <p>{targets.length}件</p>}
        </div>

        <div className="target-search">
          <label htmlFor="target-keyword">名前で検索</label>
          <div className="target-search-control">
            <input
              id="target-keyword"
              type="search"
              value={keyword}
              onChange={(event) => setKeyword(event.target.value)}
              maxLength="255"
              placeholder="推しの名前を入力"
            />
            {keyword && (
              <button type="button" onClick={() => setKeyword('')} aria-label="検索文字を消去">
                クリア
              </button>
            )}
          </div>
        </div>

        {isLoading && (
          <div className="target-state" role="status">
            <span className="loading-spinner" aria-hidden="true" />
            <p>{normalizedKeyword ? '検索しています…' : '推しを読み込んでいます…'}</p>
          </div>
        )}

        {!isWaitingForSearch && targetStatus === 'error' && (
          <div className="target-state" role="alert">
            <span aria-hidden="true">⚠️</span>
            <h3>{isSearching ? '検索できませんでした' : '推しを取得できませんでした'}</h3>
            <p>通信状況を確認して、もう一度お試しください。</p>
            <button className="retry-button" type="button" onClick={() => setRetryCount((count) => count + 1)}>
              再試行
            </button>
          </div>
        )}

        {!isWaitingForSearch && targetStatus === 'success' && targets.length === 0 && (
          <div className="empty-state">
            <span aria-hidden="true">{isSearching ? '🔍' : '🌱'}</span>
            <h3>{isSearching ? '一致する推しはありません' : '登録されている推しはありません'}</h3>
            <p>
              {isSearching
                ? '別の名前でもう一度お試しください。'
                : '推しを登録すると、ここで確認できるようになります。'}
            </p>
          </div>
        )}

        {!isWaitingForSearch && targetStatus === 'success' && targets.length > 0 && (
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
