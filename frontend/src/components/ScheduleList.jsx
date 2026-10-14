import { formatDateLabel } from '../utils/date'

function ScheduleList({ schedules, isDateSelected }) {
  if (schedules.length === 0) {
    return (
      <div className="empty-state">
        <span aria-hidden="true">🗓️</span>
        <h3>予定はありません</h3>
        <p>{isDateSelected ? 'この日に登録されている予定はありません。' : 'この月に登録されている予定はありません。'}</p>
      </div>
    )
  }

  return (
    <ol className="schedule-list">
      {schedules.map((schedule) => (
        <li className="schedule-card" key={schedule.id}>
          <div className="schedule-icon" aria-hidden="true">{schedule.icon}</div>
          <div className="schedule-content">
            <div className="schedule-meta">
              <time dateTime={schedule.startDate}>
                {formatDateLabel(schedule.startDate)}
                {schedule.endDate !== schedule.startDate && `〜${formatDateLabel(schedule.endDate)}`}
              </time>
              <span>{schedule.time ?? '終日'}</span>
            </div>
            <h3>{schedule.title}</h3>
            <p>{schedule.target?.name ?? '個人予定'}</p>
          </div>
        </li>
      ))}
    </ol>
  )
}

export default ScheduleList
