import { enumerateDateKeys } from './date.js'

const tokyoDateFormatter = new Intl.DateTimeFormat('en-US', {
  timeZone: 'Asia/Tokyo',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
})

const tokyoTimeFormatter = new Intl.DateTimeFormat('ja-JP', {
  timeZone: 'Asia/Tokyo',
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
})

function getTokyoDateKey(value) {
  const parts = Object.fromEntries(
    tokyoDateFormatter
      .formatToParts(new Date(value))
      .filter((part) => part.type !== 'literal')
      .map((part) => [part.type, part.value]),
  )

  return `${parts.year}-${parts.month}-${parts.day}`
}

function getScheduleIcon(scheduleType) {
  const type = scheduleType.toLowerCase()

  if (type.includes('誕生日') || type.includes('birthday')) return '🎂'
  if (type.includes('周年') || type.includes('記念日') || type.includes('anniversary')) return '🎉'
  if (type.includes('配信') || type.includes('ライブ') || type.includes('stream')) return '📅'

  return '📌'
}

export function adaptSchedule(schedule, rangeStart, rangeEnd) {
  const startDate = schedule.is_all_day
    ? schedule.starts_at
    : getTokyoDateKey(schedule.starts_at)
  const endDate = schedule.ends_at === null
    ? startDate
    : schedule.is_all_day
      ? schedule.ends_at
      : getTokyoDateKey(schedule.ends_at)
  const visibleStart = startDate < rangeStart ? rangeStart : startDate
  const visibleEnd = endDate > rangeEnd ? rangeEnd : endDate

  return {
    ...schedule,
    startDate,
    endDate,
    dateKeys: visibleStart <= visibleEnd ? enumerateDateKeys(visibleStart, visibleEnd) : [],
    time: schedule.is_all_day ? null : tokyoTimeFormatter.format(new Date(schedule.starts_at)),
    icon: getScheduleIcon(schedule.schedule_type),
  }
}

export function adaptSchedules(schedules, rangeStart, rangeEnd) {
  return schedules.map((schedule) => adaptSchedule(schedule, rangeStart, rangeEnd))
}
