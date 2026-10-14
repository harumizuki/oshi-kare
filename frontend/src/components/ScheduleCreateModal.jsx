import { useEffect, useRef, useState } from 'react'
import { createSchedule } from '../api/schedules'
import { getTargets } from '../api/targets'
import { ApiError } from '../api/client'
import {
  buildSchedulePayload,
  mapScheduleApiErrors,
  scheduleTypeOptions,
} from '../utils/scheduleForm'

function ScheduleCreateModal({ initialDate, onClose, onCreated, onSessionExpired }) {
  const titleInputRef = useRef(null)
  const [values, setValues] = useState({
    title: '',
    scheduleType: '通常予定',
    startDate: initialDate,
    endDate: '',
    isAllDay: true,
    startTime: '09:00',
    endTime: '',
    targetId: '',
    memo: '',
  })
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
        setTargets(response.data)
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
  }, [onSessionExpired, targetRetryCount])

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

  const handleSubmit = async (event) => {
    event.preventDefault()
    if (isSaving) return

    const { errors, payload } = buildSchedulePayload(values)
    setFieldErrors(errors)
    setSubmitError('')

    if (!payload) return

    setIsSaving(true)

    try {
      await createSchedule(payload)
      onCreated(values.startDate)
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        onSessionExpired()
        return
      }

      if (error instanceof ApiError && error.status === 422) {
        setFieldErrors(mapScheduleApiErrors(error.data?.errors))
      } else {
        setSubmitError('予定を登録できませんでした。通信状況を確認して、もう一度お試しください。')
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
            <p className="section-label">新しい予定</p>
            <h2 id="schedule-modal-title">予定を追加</h2>
          </div>
          <button className="modal-close" type="button" onClick={onClose} disabled={isSaving} aria-label="閉じる">×</button>
        </div>

        <form className="schedule-form" onSubmit={handleSubmit}>
          <fieldset disabled={isSaving} aria-busy={isSaving}>
            <label htmlFor="schedule-title">タイトル <span>必須</span></label>
            <input ref={titleInputRef} id="schedule-title" value={values.title} onChange={(event) => updateValue('title', event.target.value)} maxLength="255" required />
            <FieldError message={fieldErrors.title} />

            <label htmlFor="schedule-type">予定の種類 <span>必須</span></label>
            <select id="schedule-type" value={values.scheduleType} onChange={(event) => updateValue('scheduleType', event.target.value)} required>
              {scheduleTypeOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
            </select>
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

            <label htmlFor="schedule-target">対象</label>
            <select id="schedule-target" value={values.targetId} onChange={(event) => updateValue('targetId', event.target.value)}>
              <option value="">個人予定</option>
              {targets.map((target) => <option key={target.id} value={target.id}>{target.name}</option>)}
            </select>
            {targetStatus === 'loading' && <p className="field-note">登録済みの推しを読み込んでいます…</p>}
            {targetStatus === 'error' && (
              <div className="target-load-error">
                <p>推しを取得できませんでした。個人予定は登録できます。</p>
                <button type="button" onClick={() => setTargetRetryCount((count) => count + 1)}>再試行</button>
              </div>
            )}
            <FieldError message={fieldErrors.targetId} />

            <label htmlFor="schedule-memo">メモ</label>
            <textarea id="schedule-memo" rows="3" value={values.memo} onChange={(event) => updateValue('memo', event.target.value)} />
            <FieldError message={fieldErrors.memo} />
          </fieldset>

          {submitError && <p className="form-error" role="alert">{submitError}</p>}

          <div className="modal-actions">
            <button className="secondary-button" type="button" onClick={onClose} disabled={isSaving}>キャンセル</button>
            <button className="primary-button" type="submit" disabled={isSaving}>{isSaving ? '保存中…' : '保存する'}</button>
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
