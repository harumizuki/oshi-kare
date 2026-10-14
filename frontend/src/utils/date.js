const padNumber = (number) => String(number).padStart(2, '0')

export function toDateKey(date) {
  return `${date.getFullYear()}-${padNumber(date.getMonth() + 1)}-${padNumber(date.getDate())}`
}

export function createCalendarDays(displayedMonth) {
  const year = displayedMonth.getFullYear()
  const month = displayedMonth.getMonth()
  const firstDayOfWeek = new Date(year, month, 1).getDay()
  const lastDate = new Date(year, month + 1, 0).getDate()
  const days = Array(firstDayOfWeek).fill(null)

  for (let day = 1; day <= lastDate; day += 1) days.push(new Date(year, month, day))
  while (days.length % 7 !== 0) days.push(null)

  return days
}

export function formatMonthLabel(date) {
  return `${date.getFullYear()}年${date.getMonth() + 1}月`
}

export function formatDateLabel(dateKey) {
  const [year, month, day] = dateKey.split('-').map(Number)
  const weekDay = ['日', '月', '火', '水', '木', '金', '土'][new Date(year, month - 1, day).getDay()]

  return `${month}月${day}日（${weekDay}）`
}

export function getMonthRange(date) {
  const year = date.getFullYear()
  const month = date.getMonth()

  return {
    start: toDateKey(new Date(year, month, 1)),
    end: toDateKey(new Date(year, month + 1, 0)),
  }
}

export function enumerateDateKeys(start, end) {
  const [startYear, startMonth, startDay] = start.split('-').map(Number)
  const [endYear, endMonth, endDay] = end.split('-').map(Number)
  const current = new Date(Date.UTC(startYear, startMonth - 1, startDay))
  const last = new Date(Date.UTC(endYear, endMonth - 1, endDay))
  const dateKeys = []

  while (current <= last) {
    dateKeys.push(
      `${current.getUTCFullYear()}-${padNumber(current.getUTCMonth() + 1)}-${padNumber(current.getUTCDate())}`,
    )
    current.setUTCDate(current.getUTCDate() + 1)
  }

  return dateKeys
}
