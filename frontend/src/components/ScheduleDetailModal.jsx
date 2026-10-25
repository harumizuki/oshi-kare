import { useEffect, useRef, useState } from 'react'
import { ApiError } from '../api/client'
import { getSchedule } from '../api/schedules'

const tokyoDateTimeFormatter = new Intl.DateTimeFormat('ja-JP', {
  timeZone: 'Asia/Tokyo',
  year: 'numeric',
  month: 'long',
  day: 'numeric',
  weekday: 'short',
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
})

function formatAllDayDate(date) {
  const [year, month, day] = date.split('-').map(Number)
  return new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    weekday: 'short',
  }).format(new Date(year, month - 1, day))
}

function formatPeriod(schedule) {
  if (schedule.is_all_day) {
    const start = formatAllDayDate(schedule.starts_at)
    const end = formatAllDayDate(schedule.ends_at ?? schedule.starts_at)
    return start === end ? start : `${start} 〜 ${end}`
  }

  const start = tokyoDateTimeFormatter.format(new Date(schedule.starts_at))
  const end = schedule.ends_at
    ? tokyoDateTimeFormatter.format(new Date(schedule.ends_at))
    : null

  return end ? `${start} 〜 ${end}` : `${start} 〜 終了時刻未設定`
}

function ScheduleDetailModal({ scheduleId, onClose, onEdit, onSessionExpired }) {
  const closeButtonRef = useRef(null)
  const [schedule, setSchedule] = useState(null)
  const [status, setStatus] = useState('loading')
  const [retryCount, setRetryCount] = useState(0)

  useEffect(() => {
    closeButtonRef.current?.focus()

    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    return () => {
      document.body.style.overflow = previousOverflow
    }
  }, [])

  useEffect(() => {
    const controller = new AbortController()

    setStatus('loading')
    getSchedule(scheduleId, controller.signal)
      .then((response) => {
        setSchedule(response.data)
        setStatus('success')
      })
      .catch((error) => {
        if (error.name === 'AbortError') return

        if (error instanceof ApiError && error.status === 401) {
          onSessionExpired()
          return
        }

        setStatus('error')
      })

    return () => controller.abort()
  }, [onSessionExpired, retryCount, scheduleId])

  useEffect(() => {
    const handleKeyDown = (event) => {
      if (event.key === 'Escape') onClose()
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [onClose])

  const handleBackdropClick = (event) => {
    if (event.target === event.currentTarget) onClose()
  }

  return (
    <div className="modal-backdrop" onMouseDown={handleBackdropClick}>
      <section className="schedule-modal detail-modal" role="dialog" aria-modal="true" aria-labelledby="schedule-detail-title">
        <div className="modal-header">
          <div>
            <p className="section-label">予定の詳細</p>
            <h2 id="schedule-detail-title">{schedule?.title ?? '予定を読み込み中'}</h2>
          </div>
          <button ref={closeButtonRef} className="modal-close" type="button" onClick={onClose} aria-label="閉じる">×</button>
        </div>

        {status === 'loading' && (
          <div className="detail-state" role="status">
            <span className="loading-spinner" aria-hidden="true" />
            <p>予定の詳細を読み込んでいます…</p>
          </div>
        )}

        {status === 'error' && (
          <div className="detail-state" role="alert">
            <p>予定の詳細を取得できませんでした。</p>
            <button className="retry-button" type="button" onClick={() => setRetryCount((count) => count + 1)}>再試行</button>
          </div>
        )}

        {status === 'success' && schedule && (
          <>
            <dl className="schedule-details">
              <DetailItem label="予定の種類" value={schedule.schedule_type} />
              <DetailItem label="日時" value={formatPeriod(schedule)} />
              <DetailItem label="終日" value={schedule.is_all_day ? 'はい' : 'いいえ'} />
              <DetailItem label="対象" value={schedule.target?.name ?? '個人予定'} />
              <DetailItem label="場所" value={schedule.location || '未設定'} />
              <div className="detail-item">
                <dt>URL</dt>
                <dd>
                  {schedule.url
                    ? <a href={schedule.url} target="_blank" rel="noreferrer">{schedule.url}</a>
                    : '未設定'}
                </dd>
              </div>
              <DetailItem label="メモ" value={schedule.memo || '未設定'} isMultiline />
            </dl>

            <div className="modal-actions">
              <button className="secondary-button" type="button" onClick={onClose}>閉じる</button>
              <button className="primary-button" type="button" onClick={() => onEdit(schedule)}>編集する</button>
            </div>
          </>
        )}
      </section>
    </div>
  )
}

function DetailItem({ label, value, isMultiline = false }) {
  return (
    <div className="detail-item">
      <dt>{label}</dt>
      <dd className={isMultiline ? 'detail-multiline' : undefined}>{value}</dd>
    </div>
  )
}

export default ScheduleDetailModal
