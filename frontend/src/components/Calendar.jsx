import { createCalendarDays, toDateKey } from '../utils/date'
import { getJapaneseHoliday } from '../utils/holidays'

const weekDays = ['日', '月', '火', '水', '木', '金', '土']

function Calendar({ displayedMonth, schedules, selectedDate, today, onSelectDate }) {
  const calendarDays = createCalendarDays(displayedMonth)
  const scheduleDates = new Set(schedules.flatMap((schedule) => schedule.dateKeys))
  const todayKey = toDateKey(today)

  return (
    <div className="calendar">
      <div className="weekday-row" aria-hidden="true">
        {weekDays.map((weekDay, index) => (
          <span className={index === 0 ? 'sunday' : index === 6 ? 'saturday' : ''} key={weekDay}>{weekDay}</span>
        ))}
      </div>

      <div className="calendar-grid">
        {calendarDays.map((date, index) => {
          if (!date) return <span className="calendar-blank" key={`blank-${index}`} />

          const dateKey = toDateKey(date)
          const isToday = dateKey === todayKey
          const isSelected = dateKey === selectedDate
          const hasSchedule = scheduleDates.has(dateKey)
          const dayOfWeek = date.getDay()
          const holiday = getJapaneseHoliday(date)
          const dayColorClass = holiday
            ? 'holiday'
            : dayOfWeek === 0
              ? 'sunday'
              : dayOfWeek === 6
                ? 'saturday'
                : ''
          const dayDescription = [
            `${date.getMonth() + 1}月${date.getDate()}日`,
            holiday?.name,
            hasSchedule ? '予定あり' : '予定なし',
          ].filter(Boolean).join('、')

          return (
            <button
              className={`calendar-day${isToday ? ' is-today' : ''}${isSelected ? ' is-selected' : ''}`}
              type="button"
              key={dateKey}
              onClick={() => onSelectDate(date)}
              aria-label={dayDescription}
              aria-pressed={isSelected}
              title={holiday?.name}
            >
              <span className={dayColorClass}>{date.getDate()}</span>
              {hasSchedule && <i className="schedule-dot" aria-hidden="true" />}
            </button>
          )
        })}
      </div>
    </div>
  )
}

export default Calendar
