import { toDateKey } from '../utils/date'

export function createMockSchedules(baseDate) {
  const year = baseDate.getFullYear()
  const month = baseDate.getMonth()

  return [
    { id: 1, date: toDateKey(new Date(year, month, 3)), title: 'テストターゲットの誕生日', target: 'テストターゲット', time: null, icon: '🎂' },
    { id: 2, date: toDateKey(new Date(year, month, 8)), title: '活動周年のお祝い', target: 'テストターゲット', time: null, icon: '🎉' },
    { id: 3, date: toDateKey(new Date(year, month, 15)), title: '記念ライブ配信', target: 'テストターゲット', time: '20:00', icon: '📅' },
    { id: 4, date: toDateKey(new Date(year, month, 15)), title: '配信を視聴する', target: null, time: '19:45', icon: '📌' },
    { id: 5, date: toDateKey(new Date(year, month, 23)), title: 'グッズの予約締切', target: null, time: '23:59', icon: '📌' },
  ]
}
