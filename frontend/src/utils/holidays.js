import holidayJp from '@holiday-jp/holiday_jp'
import { toDateKey } from './date.js'

export function getJapaneseHoliday(date) {
  return holidayJp.holidays[toDateKey(date)] ?? null
}
