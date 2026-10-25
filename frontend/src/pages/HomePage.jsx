import { useEffect, useMemo, useState } from 'react'
import { ApiError } from '../api/client'
import { getSchedules } from '../api/schedules'
import Calendar from '../components/Calendar'
import ScheduleCreateModal from '../components/ScheduleCreateModal'
import ScheduleDetailModal from '../components/ScheduleDetailModal'
import ScheduleList from '../components/ScheduleList'
import { formatDateLabel, formatMonthLabel, getMonthRange, toDateKey } from '../utils/date'
import { adaptSchedules } from '../utils/schedules'

function HomePage({ user, onLogout, isLoggingOut, logoutError, onSessionExpired }) {
  const today = useMemo(() => new Date(), [])
  const [displayedMonth, setDisplayedMonth] = useState(
    () => new Date(today.getFullYear(), today.getMonth(), 1),
  )
  const [selectedDate, setSelectedDate] = useState(null)
  const [schedules, setSchedules] = useState([])
  const [scheduleStatus, setScheduleStatus] = useState('loading')
  const [retryCount, setRetryCount] = useState(0)
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
  const [detailScheduleId, setDetailScheduleId] = useState(null)
  const [editingSchedule, setEditingSchedule] = useState(null)
  const [successMessage, setSuccessMessage] = useState('')

  const monthRange = useMemo(() => getMonthRange(displayedMonth), [displayedMonth])

  useEffect(() => {
    const controller = new AbortController()
    let isCurrentRequest = true

    setSchedules([])
    setScheduleStatus('loading')

    getSchedules(monthRange.start, monthRange.end, controller.signal)
      .then((response) => {
        if (!isCurrentRequest) return

        setSchedules(adaptSchedules(response.data, monthRange.start, monthRange.end))
        setScheduleStatus('success')
      })
      .catch((error) => {
        if (!isCurrentRequest || error.name === 'AbortError') return

        if (error instanceof ApiError && error.status === 401) {
          onSessionExpired()
          return
        }

        setScheduleStatus('error')
      })

    return () => {
      isCurrentRequest = false
      controller.abort()
    }
  }, [monthRange, onSessionExpired, retryCount])

  useEffect(() => {
    if (!successMessage) return undefined

    const timer = window.setTimeout(() => setSuccessMessage(''), 4000)
    return () => window.clearTimeout(timer)
  }, [successMessage])

  const displayedSchedules = useMemo(() => {
    return schedules
      .filter((schedule) =>
        selectedDate
          ? schedule.dateKeys.includes(selectedDate)
          : true,
      )
      .sort(
        (first, second) =>
          first.startDate.localeCompare(second.startDate) ||
          (first.time ?? '').localeCompare(second.time ?? ''),
      )
  }, [schedules, selectedDate])

  const changeMonth = (offset) => {
    setDisplayedMonth(
      (currentMonth) =>
        new Date(currentMonth.getFullYear(), currentMonth.getMonth() + offset, 1),
    )
    setSelectedDate(null)
  }

  const selectDate = (date) => {
    const dateKey = toDateKey(date)
    setSelectedDate((currentDate) => currentDate === dateKey ? null : dateKey)
  }

  const listTitle = selectedDate
    ? `${formatDateLabel(selectedDate)}の予定`
    : `${formatMonthLabel(displayedMonth)}の予定`

  const modalInitialDate = selectedDate
    ?? (today.getFullYear() === displayedMonth.getFullYear()
      && today.getMonth() === displayedMonth.getMonth()
      ? toDateKey(today)
      : monthRange.start)

  const openCreateModal = () => {
    setSuccessMessage('')
    setIsCreateModalOpen(true)
  }

  const handleScheduleCreated = (startDate) => {
    const [year, month] = startDate.split('-').map(Number)

    setDisplayedMonth(new Date(year, month - 1, 1))
    setSelectedDate(startDate)
    setIsCreateModalOpen(false)
    setSuccessMessage('予定を登録しました。')
    setRetryCount((count) => count + 1)
  }

  const handleScheduleUpdated = () => {
    setEditingSchedule(null)
    setSuccessMessage('予定を更新しました。')
    setRetryCount((count) => count + 1)
  }

  const handleScheduleDeleted = () => {
    setDetailScheduleId(null)
    setSuccessMessage('予定を削除しました。')
    setRetryCount((count) => count + 1)
  }

  const startEditing = (schedule) => {
    setDetailScheduleId(null)
    setEditingSchedule(schedule)
  }

  return (
    <main className="app-shell">
      <header className="app-header">
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
          <div className="add-schedule-area">
            <button className="add-schedule-button" type="button" onClick={openCreateModal}>
              ＋ 予定を追加
            </button>
          </div>
          {logoutError && <p className="header-error" role="alert">{logoutError}</p>}
          {successMessage && <p className="success-message" role="status">{successMessage}</p>}
        </div>
      </header>

      <div className="home-grid">
        <section className="panel calendar-panel" aria-labelledby="calendar-title">
          <div className="calendar-toolbar">
            <button className="month-navigation" type="button" onClick={() => changeMonth(-1)} aria-label="前月を表示">‹</button>
            <h2 id="calendar-title">{formatMonthLabel(displayedMonth)}</h2>
            <button className="month-navigation" type="button" onClick={() => changeMonth(1)} aria-label="翌月を表示">›</button>
          </div>

          <Calendar displayedMonth={displayedMonth} schedules={schedules} selectedDate={selectedDate} today={today} onSelectDate={selectDate} />

          <div className="calendar-legend">
            <span><i className="today-legend" />今日</span>
            <span><i className="schedule-legend" />予定あり</span>
          </div>
        </section>

        <section className="panel schedule-panel" aria-labelledby="schedule-title">
          <div className="schedule-heading">
            <div>
              <p className="section-label">{selectedDate ? '選択した日' : '月間スケジュール'}</p>
              <h2 id="schedule-title">{listTitle}</h2>
            </div>
            {selectedDate && (
              <button className="clear-selection" type="button" onClick={() => setSelectedDate(null)}>
                月別一覧に戻る
              </button>
            )}
          </div>

          {scheduleStatus === 'loading' && (
            <div className="schedule-state" role="status">
              <span className="loading-spinner" aria-hidden="true" />
              <p>予定を読み込んでいます…</p>
            </div>
          )}
          {scheduleStatus === 'error' && (
            <div className="schedule-state" role="alert">
              <span aria-hidden="true">⚠️</span>
              <h3>予定を取得できませんでした</h3>
              <p>通信状況を確認して、もう一度お試しください。</p>
              <button className="retry-button" type="button" onClick={() => setRetryCount((count) => count + 1)}>
                再試行
              </button>
            </div>
          )}
          {scheduleStatus === 'success' && (
            <ScheduleList
              schedules={displayedSchedules}
              isDateSelected={Boolean(selectedDate)}
              onSelectSchedule={setDetailScheduleId}
            />
          )}
        </section>
      </div>

      {isCreateModalOpen && (
        <ScheduleCreateModal
          initialDate={modalInitialDate}
          onClose={() => setIsCreateModalOpen(false)}
          onCreated={handleScheduleCreated}
          onSessionExpired={onSessionExpired}
        />
      )}

      {detailScheduleId !== null && (
        <ScheduleDetailModal
          scheduleId={detailScheduleId}
          onClose={() => setDetailScheduleId(null)}
          onDeleted={handleScheduleDeleted}
          onEdit={startEditing}
          onSessionExpired={onSessionExpired}
        />
      )}

      {editingSchedule !== null && (
        <ScheduleCreateModal
          initialDate={editingSchedule.starts_at}
          schedule={editingSchedule}
          onClose={() => setEditingSchedule(null)}
          onUpdated={handleScheduleUpdated}
          onSessionExpired={onSessionExpired}
        />
      )}
    </main>
  )
}

export default HomePage
