import { useEffect, useRef, useState } from 'react'
import { createSchedule, updateSchedule } from '../api/schedules'
import { getTargets } from '../api/targets'
import { ApiError } from '../api/client'
import {
  buildSchedulePayload,
  CUSTOM_SCHEDULE_TYPE_OPTION,
  isPresetScheduleType,
  mapScheduleApiErrors,
  scheduleToFormValues,
  scheduleTypeOptions,
} from '../utils/scheduleForm'

function ScheduleCreateModal({ initialDate, schedule = null, onClose, onCreated, onUpdated, onSessionExpired }) {
  const titleInputRef = useRef(null)
  const isEditing = schedule !== null
  const initialScheduleType = schedule?.schedule_type ?? '通常予定'
  const [values, setValues] = useState(() => (
    schedule
      ? scheduleToFormValues(schedule)
      : {
          title: '',
          scheduleType: '通常予定',
          startDate: initialDate,
          endDate: '',
          isAllDay: true,
          startTime: '09:00',
          endTime: '',
          targetIds: [],
          location: '',
          url: '',
          memo: '',
        }
  ))
  const initialTargetIdsRef = useRef(values.targetIds)
  const [scheduleTypeChoice, setScheduleTypeChoice] = useState(() => (
    isPresetScheduleType(initialScheduleType)
      ? initialScheduleType
      : CUSTOM_SCHEDULE_TYPE_OPTION
  ))
  const [targets, setTargets] = useState([])
  const [targetStatus, setTargetStatus] = useState('loading')
  const [targetRetryCount, setTargetRetryCount] = useState(0)
  const [fieldErrors, setFieldErrors] = useState({})
  const [submitError, setSubmitError] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    titleInputRef.current?.focus()

    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    return () => {
      document.body.style.overflow = previousOverflow
    }
  }, [])

  useEffect(() => {
    const controller = new AbortController()

    setTargetStatus('loading')
    getTargets(controller.signal)
      .then((response) => {
        const fetchedTargets = response.data
        const fetchedTargetIds = new Set(
          fetchedTargets.map((target) => String(target.id)),
        )
        const hasMissingTarget = initialTargetIdsRef.current
          .some((targetId) => !fetchedTargetIds.has(targetId))

        setTargets(fetchedTargets)

        if ((isEditing && !Array.isArray(schedule.targets)) || hasMissingTarget) {
          setTargetStatus('inconsistent')
          return
        }

        setTargetStatus('success')
      })
      .catch((error) => {
        if (error.name === 'AbortError') return

        if (error instanceof ApiError && error.status === 401) {
          onSessionExpired()
          return
        }

        setTargetStatus('error')
      })

    return () => controller.abort()
  }, [isEditing, onSessionExpired, schedule, targetRetryCount])

  useEffect(() => {
    const handleKeyDown = (event) => {
      if (event.key === 'Escape' && !isSaving) onClose()
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isSaving, onClose])

  const updateValue = (name, value) => {
    setValues((current) => ({ ...current, [name]: value }))
    setFieldErrors((current) => ({ ...current, [name]: undefined }))
    setSubmitError('')
  }

  const updateScheduleTypeChoice = (nextChoice) => {
    setScheduleTypeChoice(nextChoice)
    updateValue(
      'scheduleType',
      nextChoice === CUSTOM_SCHEDULE_TYPE_OPTION ? '' : nextChoice,
    )
  }

  const updateTargetSelection = (targetId, isSelected) => {
    setValues((current) => ({
      ...current,
      targetIds: isSelected
        ? [...new Set([...current.targetIds, targetId])]
        : current.targetIds.filter((currentTargetId) => currentTargetId !== targetId),
    }))
    setFieldErrors((current) => ({ ...current, targetIds: undefined }))
    setSubmitError('')
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    if (isSaving) return

    if (targetStatus !== 'success') {
      setFieldErrors((current) => ({
        ...current,
        targetIds: '対象の情報を確認できるまで保存できません。再試行してください。',
      }))
      return
    }

    const { errors, payload } = buildSchedulePayload(values)
    setFieldErrors(errors)
    setSubmitError('')

    if (!payload) return

    setIsSaving(true)

    try {
      if (isEditing) {
        await updateSchedule(schedule.id, payload)
        onUpdated(values.startDate)
      } else {
        await createSchedule(payload)
        onCreated(values.startDate)
      }
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        onSessionExpired()
        return
      }

      if (error instanceof ApiError && error.status === 422) {
        setFieldErrors(mapScheduleApiErrors(error.data?.errors))
      } else {
        setSubmitError(`予定を${isEditing ? '更新' : '登録'}できませんでした。通信状況を確認して、もう一度お試しください。`)
      }
    } finally {
      setIsSaving(false)
    }
  }

  const handleBackdropClick = (event) => {
    if (event.target === event.currentTarget && !isSaving) onClose()
  }

  return (
    <div className="modal-backdrop" onMouseDown={handleBackdropClick}>
      <section className="schedule-modal" role="dialog" aria-modal="true" aria-labelledby="schedule-modal-title">
        <div className="modal-header">
          <div>
            <p className="section-label">{isEditing ? '予定の編集' : '新しい予定'}</p>
            <h2 id="schedule-modal-title">予定を{isEditing ? '編集' : '追加'}</h2>
          </div>
          <button className="modal-close" type="button" onClick={onClose} disabled={isSaving} aria-label="閉じる">×</button>
        </div>

        <form className="schedule-form" onSubmit={handleSubmit}>
          <fieldset disabled={isSaving} aria-busy={isSaving}>
            <label htmlFor="schedule-title">タイトル <span>必須</span></label>
            <input ref={titleInputRef} id="schedule-title" value={values.title} onChange={(event) => updateValue('title', event.target.value)} maxLength="255" required />
            <FieldError message={fieldErrors.title} />

            <label htmlFor="schedule-type">予定の種類 <span>必須</span></label>
            <select id="schedule-type" value={scheduleTypeChoice} onChange={(event) => updateScheduleTypeChoice(event.target.value)} required>
              {scheduleTypeOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
              <option value={CUSTOM_SCHEDULE_TYPE_OPTION}>その他（自由入力）</option>
            </select>
            {scheduleTypeChoice === CUSTOM_SCHEDULE_TYPE_OPTION && (
              <>
                <label htmlFor="custom-schedule-type">予定の種類を入力 <span>必須</span></label>
                <input
                  id="custom-schedule-type"
                  value={values.scheduleType}
                  onChange={(event) => updateValue('scheduleType', event.target.value)}
                  maxLength="100"
                  placeholder="例：試合、発売日、コンサート"
                  required
                />
              </>
            )}
            <FieldError message={fieldErrors.scheduleType} />

            <label className="all-day-toggle">
              <input type="checkbox" checked={values.isAllDay} onChange={(event) => updateValue('isAllDay', event.target.checked)} />
              <span>終日予定</span>
            </label>

            <div className="form-row">
              <div>
                <label htmlFor="start-date">開始日 <span>必須</span></label>
                <input id="start-date" type="date" value={values.startDate} onChange={(event) => updateValue('startDate', event.target.value)} required />
                <FieldError message={fieldErrors.startDate} />
              </div>
              <div>
                <label htmlFor="end-date">終了日</label>
                <input id="end-date" type="date" min={values.startDate} value={values.endDate} onChange={(event) => updateValue('endDate', event.target.value)} />
                <FieldError message={fieldErrors.endDate} />
              </div>
            </div>

            {!values.isAllDay && (
              <div className="form-row">
                <div>
                  <label htmlFor="start-time">開始時刻 <span>必須</span></label>
                  <input id="start-time" type="time" value={values.startTime} onChange={(event) => updateValue('startTime', event.target.value)} required />
                  <FieldError message={fieldErrors.startTime} />
                </div>
                <div>
                  <label htmlFor="end-time">終了時刻</label>
                  <input id="end-time" type="time" value={values.endTime} onChange={(event) => updateValue('endTime', event.target.value)} />
                  <FieldError message={fieldErrors.endTime} />
                </div>
              </div>
            )}

            <div className="schedule-target-selector" role="group" aria-labelledby="schedule-target-label">
              <div className="schedule-target-heading">
                <p id="schedule-target-label">対象</p>
                <span>{values.targetIds.length > 0 ? `${values.targetIds.length}件選択中` : '個人予定'}</span>
              </div>

              {targetStatus === 'success' && targets.length > 0 && (
                <div className="schedule-target-options">
                  {targets.map((target) => {
                    const targetId = String(target.id)

                    return (
                      <label className="schedule-target-option" key={target.id}>
                        <input
                          type="checkbox"
                          checked={values.targetIds.includes(targetId)}
                          onChange={(event) => updateTargetSelection(targetId, event.target.checked)}
                        />
                        <span>{target.name}</span>
                      </label>
                    )
                  })}
                </div>
              )}

              {targetStatus === 'success' && targets.length === 0 && (
                <p className="field-note">登録済みの対象がないため、個人予定として登録されます。</p>
              )}

              {targetStatus === 'success' && values.targetIds.length > 0 && (
                <button
                  className="clear-target-selection"
                  type="button"
                  onClick={() => updateValue('targetIds', [])}
                >
                  すべて解除して個人予定にする
                </button>
              )}
            </div>
            {targetStatus === 'loading' && <p className="field-note">登録済みの推しを読み込んでいます…</p>}
            {targetStatus === 'error' && (
              <div className="target-load-error">
                <p>対象を取得できませんでした。選択状態を保持したまま保存を停止しています。</p>
                <button type="button" onClick={() => setTargetRetryCount((count) => count + 1)}>再試行</button>
              </div>
            )}
            {targetStatus === 'inconsistent' && (
              <div className="target-load-error">
                <p>予定の関連付けと現在の対象一覧が一致しません。保存せず、再読み込みしてください。</p>
                <button type="button" onClick={() => setTargetRetryCount((count) => count + 1)}>再試行</button>
              </div>
            )}
            <FieldError message={fieldErrors.targetIds} />

            <label htmlFor="schedule-location">場所</label>
            <input id="schedule-location" value={values.location} onChange={(event) => updateValue('location', event.target.value)} maxLength="255" />
            <FieldError message={fieldErrors.location} />

            <label htmlFor="schedule-url">URL</label>
            <input id="schedule-url" type="url" value={values.url} onChange={(event) => updateValue('url', event.target.value)} maxLength="2048" placeholder="https://example.com" />
            <FieldError message={fieldErrors.url} />

            <label htmlFor="schedule-memo">メモ</label>
            <textarea id="schedule-memo" rows="3" value={values.memo} onChange={(event) => updateValue('memo', event.target.value)} />
            <FieldError message={fieldErrors.memo} />
          </fieldset>

          {submitError && <p className="form-error" role="alert">{submitError}</p>}

          <div className="modal-actions">
            <button className="secondary-button" type="button" onClick={onClose} disabled={isSaving}>キャンセル</button>
            <button className="primary-button" type="submit" disabled={isSaving || targetStatus !== 'success'}>{isSaving ? '保存中…' : isEditing ? '変更を保存' : '保存する'}</button>
          </div>
        </form>
      </section>
    </div>
  )
}

function FieldError({ message }) {
  return message ? <p className="field-error" role="alert">{message}</p> : null
}

export default ScheduleCreateModal
