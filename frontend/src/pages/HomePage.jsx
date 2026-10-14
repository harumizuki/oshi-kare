import { useMemo, useState } from 'react'
import Calendar from '../components/Calendar'
import ScheduleList from '../components/ScheduleList'
import { createMockSchedules } from '../data/mockSchedules'
import { formatDateLabel, formatMonthLabel, toDateKey } from '../utils/date'

function HomePage({ user, onLogout, isLoggingOut, logoutError }) {
  const today = useMemo(() => new Date(), [])
  const schedules = useMemo(() => createMockSchedules(today), [today])
  const [displayedMonth, setDisplayedMonth] = useState(
    () => new Date(today.getFullYear(), today.getMonth(), 1),
  )
  const [selectedDate, setSelectedDate] = useState(null)

  const displayedSchedules = useMemo(() => {
    const monthKey = `${displayedMonth.getFullYear()}-${String(displayedMonth.getMonth() + 1).padStart(2, '0')}`

    return schedules
      .filter((schedule) =>
        selectedDate
          ? schedule.date === selectedDate
          : schedule.date.startsWith(monthKey),
      )
      .sort(
        (first, second) =>
          first.date.localeCompare(second.date) ||
          (first.time ?? '').localeCompare(second.time ?? ''),
      )
  }, [displayedMonth, schedules, selectedDate])

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
            <button className="add-schedule-button" type="button" disabled>
              ＋ 予定を追加
              <span>準備中</span>
            </button>
            <p>予定登録は今後のアップデートで利用できます</p>
          </div>
          {logoutError && <p className="header-error" role="alert">{logoutError}</p>}
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

          <ScheduleList schedules={displayedSchedules} isDateSelected={Boolean(selectedDate)} />
        </section>
      </div>
    </main>
  )
}

export default HomePage
