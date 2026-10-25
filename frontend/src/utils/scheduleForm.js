export const scheduleTypeOptions = [
  { value: '通常予定', label: '通常の予定' },
  { value: '誕生日', label: '誕生日' },
  { value: '活動周年', label: '活動周年' },
  { value: 'ライブ配信', label: 'ライブ配信' },
]

function toJapanIso(date, time) {
  return `${date}T${time}:00+09:00`
}

const tokyoDateTimePartsFormatter = new Intl.DateTimeFormat('en-US', {
  timeZone: 'Asia/Tokyo',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
})

function getTokyoDateTimeParts(value) {
  if (!value) return { date: '', time: '' }

  const parts = Object.fromEntries(
    tokyoDateTimePartsFormatter
      .formatToParts(new Date(value))
      .filter((part) => part.type !== 'literal')
      .map((part) => [part.type, part.value]),
  )

  return {
    date: `${parts.year}-${parts.month}-${parts.day}`,
    time: `${parts.hour}:${parts.minute}`,
  }
}

export function scheduleToFormValues(schedule) {
  if (schedule.is_all_day) {
    return {
      title: schedule.title,
      scheduleType: schedule.schedule_type,
      startDate: schedule.starts_at,
      endDate: schedule.ends_at ?? schedule.starts_at,
      isAllDay: true,
      startTime: '09:00',
      endTime: '',
      targetId: schedule.target?.id ? String(schedule.target.id) : '',
      location: schedule.location ?? '',
      url: schedule.url ?? '',
      memo: schedule.memo ?? '',
    }
  }

  const start = getTokyoDateTimeParts(schedule.starts_at)
  const end = getTokyoDateTimeParts(schedule.ends_at)

  return {
    title: schedule.title,
    scheduleType: schedule.schedule_type,
    startDate: start.date,
    endDate: end.date,
    isAllDay: false,
    startTime: start.time,
    endTime: end.time,
    targetId: schedule.target?.id ? String(schedule.target.id) : '',
    location: schedule.location ?? '',
    url: schedule.url ?? '',
    memo: schedule.memo ?? '',
  }
}

export function buildSchedulePayload(values) {
  const errors = {}
  const title = values.title.trim()

  if (!title) errors.title = 'タイトルを入力してください。'
  if (!values.scheduleType) errors.scheduleType = '予定の種類を選択してください。'
  if (!values.startDate) errors.startDate = '開始日を入力してください。'

  let startsAt = values.startDate
  let endsAt = values.endDate || values.startDate

  if (values.isAllDay) {
    if (values.startDate && endsAt < values.startDate) {
      errors.endDate = '終了日は開始日以降にしてください。'
    }
  } else {
    if (!values.startTime) errors.startTime = '開始時刻を入力してください。'
    if (values.endDate && !values.endTime) {
      errors.endTime = '終了日を指定する場合は終了時刻も入力してください。'
    }

    startsAt = values.startDate && values.startTime
      ? toJapanIso(values.startDate, values.startTime)
      : ''

    if (values.endTime) {
      const endDate = values.endDate || values.startDate
      endsAt = endDate ? toJapanIso(endDate, values.endTime) : ''

      if (startsAt && endsAt < startsAt) {
        errors.endTime = '終了日時は開始日時以降にしてください。'
      }
    } else {
      endsAt = null
    }
  }

  if (Object.keys(errors).length > 0) {
    return { errors, payload: null }
  }

  return {
    errors: {},
    payload: {
      target_id: values.targetId ? Number(values.targetId) : null,
      title,
      schedule_type: values.scheduleType,
      is_all_day: values.isAllDay,
      starts_at: startsAt,
      ends_at: endsAt,
      location: (values.location ?? '').trim() || null,
      url: (values.url ?? '').trim() || null,
      memo: (values.memo ?? '').trim() || null,
    },
  }
}

export function mapScheduleApiErrors(apiErrors = {}) {
  return {
    targetId: apiErrors.target_id?.[0],
    title: apiErrors.title?.[0],
    scheduleType: apiErrors.schedule_type?.[0],
    startDate: apiErrors.starts_at?.[0],
    startTime: apiErrors.starts_at?.[0],
    endDate: apiErrors.ends_at?.[0],
    endTime: apiErrors.ends_at?.[0],
    location: apiErrors.location?.[0],
    url: apiErrors.url?.[0],
    memo: apiErrors.memo?.[0],
  }
}
