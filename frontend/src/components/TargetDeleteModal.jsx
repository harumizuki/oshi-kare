import { useEffect, useRef, useState } from 'react'
import { ApiError } from '../api/client'
import { deleteTarget, getTargetDeletionPreview } from '../api/targets'

const DELETION_MODES = {
  DELETE_ALL: 'delete_all',
  KEEP_ALL: 'keep_all',
  DELETE_SELECTED: 'delete_selected',
}

const tokyoDateTimeFormatter = new Intl.DateTimeFormat('ja-JP', {
  timeZone: 'Asia/Tokyo',
  year: 'numeric',
  month: 'numeric',
  day: 'numeric',
  weekday: 'short',
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
})

function formatAllDayDate(date) {
  const [year, month, day] = date.split('-').map(Number)

  return new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    weekday: 'short',
  }).format(new Date(year, month - 1, day))
}

function formatSchedulePeriod(schedule) {
  if (schedule.is_all_day) {
    const start = formatAllDayDate(schedule.starts_at)
    const end = formatAllDayDate(schedule.ends_at ?? schedule.starts_at)

    return start === end ? start : `${start} 〜 ${end}`
  }

  const start = tokyoDateTimeFormatter.format(new Date(schedule.starts_at))
  const end = schedule.ends_at
    ? tokyoDateTimeFormatter.format(new Date(schedule.ends_at))
    : null

  return end ? `${start} 〜 ${end}` : `${start} 〜 終了時刻未設定`
}

function getPreviewErrorMessage(error) {
  if (error instanceof ApiError && error.status === 403) {
    return 'この登録情報の削除内容は確認できません。'
  }

  if (error instanceof ApiError && error.status === 404) {
    return '登録情報が見つかりません。すでに削除された可能性があります。'
  }

  return '削除内容を取得できませんでした。通信状況を確認して、もう一度お試しください。'
}

function getDeleteErrorMessage(error) {
  if (error instanceof ApiError && error.status === 403) {
    return 'この登録情報を削除する権限がありません。'
  }

  if (error instanceof ApiError && error.status === 404) {
    return '登録情報が見つかりません。すでに削除された可能性があります。'
  }

  if (error instanceof ApiError && error.status === 422) {
    const validationMessage = Object.values(error.data?.errors ?? {}).flat()[0]
    return validationMessage ?? '削除内容を確認できませんでした。もう一度内容をご確認ください。'
  }

  return '削除できませんでした。通信状況を確認して、もう一度お試しください。'
}

function TargetDeleteModal({ target, onClose, onDeleted, onSessionExpired }) {
  const closeButtonRef = useRef(null)
  const requestVersionRef = useRef(0)
  const isDeletingRef = useRef(false)
  const [preview, setPreview] = useState(null)
  const [previewStatus, setPreviewStatus] = useState('loading')
  const [previewError, setPreviewError] = useState('')
  const [retryCount, setRetryCount] = useState(0)
  const [deletionMode, setDeletionMode] = useState(DELETION_MODES.KEEP_ALL)
  const [selectedScheduleIds, setSelectedScheduleIds] = useState([])
  const [isScheduleListExpanded, setIsScheduleListExpanded] = useState(false)
  const [isFinalConfirmation, setIsFinalConfirmation] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState('')
  const [isPreviewStale, setIsPreviewStale] = useState(false)

  useEffect(() => {
    closeButtonRef.current?.focus()

    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    return () => {
      document.body.style.overflow = previousOverflow
    }
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    const requestVersion = requestVersionRef.current + 1
    let isCurrentRequest = true

    requestVersionRef.current = requestVersion
    setPreview(null)
    setPreviewStatus('loading')
    setPreviewError('')
    setDeletionMode(DELETION_MODES.KEEP_ALL)
    setSelectedScheduleIds([])
    setIsScheduleListExpanded(false)
    setIsFinalConfirmation(false)
    setDeleteError('')
    setIsPreviewStale(false)

    getTargetDeletionPreview(target.id, controller.signal)
      .then((response) => {
        if (!isCurrentRequest || requestVersion !== requestVersionRef.current) return

        setPreview(response.data)
        setPreviewStatus('success')
      })
      .catch((error) => {
        if (
          !isCurrentRequest
          || requestVersion !== requestVersionRef.current
          || error.name === 'AbortError'
        ) return

        if (error instanceof ApiError && error.status === 401) {
          onSessionExpired()
          return
        }

        setPreviewError(getPreviewErrorMessage(error))
        setPreviewStatus('error')
      })

    return () => {
      isCurrentRequest = false
      controller.abort()
    }
  }, [onSessionExpired, retryCount, target.id])

  useEffect(() => {
    const handleKeyDown = (event) => {
      if (event.key !== 'Escape' || isDeletingRef.current) return

      if (isFinalConfirmation) {
        setIsFinalConfirmation(false)
        setDeleteError('')
      } else {
        onClose()
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isFinalConfirmation, onClose])

  const schedules = preview?.schedules ?? []
  const totalScheduleCount = schedules.length
  const deleteScheduleCount = deletionMode === DELETION_MODES.DELETE_ALL
    ? totalScheduleCount
    : deletionMode === DELETION_MODES.DELETE_SELECTED
      ? selectedScheduleIds.length
      : 0
  const keepScheduleCount = totalScheduleCount - deleteScheduleCount

  const closeModal = () => {
    if (!isDeletingRef.current) onClose()
  }

  const handleBackdropClick = (event) => {
    if (event.target === event.currentTarget) closeModal()
  }

  const handleModeChange = (event) => {
    const nextMode = event.target.value

    setDeletionMode(nextMode)
    setIsScheduleListExpanded(nextMode === DELETION_MODES.DELETE_SELECTED)
    setDeleteError('')
  }

  const toggleSchedule = (scheduleId) => {
    setSelectedScheduleIds((currentIds) => (
      currentIds.includes(scheduleId)
        ? currentIds.filter((id) => id !== scheduleId)
        : [...currentIds, scheduleId]
    ))
  }

  const retryPreview = () => {
    setRetryCount((count) => count + 1)
  }

  const openFinalConfirmation = () => {
    if (previewStatus !== 'success' || isPreviewStale) return

    setDeleteError('')
    setIsFinalConfirmation(true)
  }

  const handleDelete = async () => {
    if (isDeletingRef.current || previewStatus !== 'success' || isPreviewStale) return

    isDeletingRef.current = true
    setIsDeleting(true)
    setDeleteError('')

    const deletion = {
      deletion_mode: deletionMode,
      expected_schedule_ids: schedules.map((schedule) => schedule.id),
    }

    if (deletionMode === DELETION_MODES.DELETE_SELECTED) {
      deletion.selected_schedule_ids = [...selectedScheduleIds].sort((a, b) => a - b)
    }

    try {
      await deleteTarget(target.id, deletion)
      onDeleted()
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        onSessionExpired()
        return
      }

      if (error instanceof ApiError && error.status === 409) {
        setDeleteError('関連予定が変更されています。最新の内容を再取得して、削除方法を選び直してください。')
        setIsPreviewStale(true)
        setIsFinalConfirmation(false)
        return
      }

      setDeleteError(getDeleteErrorMessage(error))
    } finally {
      isDeletingRef.current = false
      setIsDeleting(false)
    }
  }

  return (
    <div className="modal-backdrop" onMouseDown={handleBackdropClick}>
      <section className="schedule-modal target-delete-modal" role="dialog" aria-modal="true" aria-labelledby="target-delete-title">
        <div className="modal-header">
          <div>
            <p className="section-label">削除の確認</p>
            <h2 id="target-delete-title">登録情報を削除</h2>
          </div>
          <button
            ref={closeButtonRef}
            className="modal-close"
            type="button"
            onClick={closeModal}
            disabled={isDeleting}
            aria-label="閉じる"
          >
            ×
          </button>
        </div>

        {previewStatus === 'loading' && (
          <div className="detail-state" role="status">
            <span className="loading-spinner" aria-hidden="true" />
            <p>関連する予定を確認しています…</p>
          </div>
        )}

        {previewStatus === 'error' && (
          <div className="detail-state" role="alert">
            <p>{previewError}</p>
            <button className="retry-button" type="button" onClick={retryPreview}>再試行</button>
          </div>
        )}

        {previewStatus === 'success' && preview && (
          <>
            <div className="target-delete-heading">
              <div>
                <p>削除する登録情報</p>
                <h3>{preview.target.name}</h3>
              </div>
              <span>関連予定 {totalScheduleCount}件</span>
            </div>

            {isPreviewStale && (
              <div className="target-delete-alert" role="alert">
                <p>{deleteError}</p>
                <button className="retry-button" type="button" onClick={retryPreview}>関連予定を再取得</button>
              </div>
            )}

            {!isFinalConfirmation && (
              <>
                <fieldset className="target-delete-options" disabled={isPreviewStale}>
                  <legend>削除方法を選んでください</legend>
                  <label className={deletionMode === DELETION_MODES.DELETE_ALL ? 'is-selected' : undefined}>
                    <input
                      type="radio"
                      name="deletion-mode"
                      value={DELETION_MODES.DELETE_ALL}
                      checked={deletionMode === DELETION_MODES.DELETE_ALL}
                      onChange={handleModeChange}
                    />
                    <span><strong>A：関連予定をすべて削除</strong><small>登録情報と関連する予定をすべて削除します。</small></span>
                  </label>
                  <label className={deletionMode === DELETION_MODES.KEEP_ALL ? 'is-selected' : undefined}>
                    <input
                      type="radio"
                      name="deletion-mode"
                      value={DELETION_MODES.KEEP_ALL}
                      checked={deletionMode === DELETION_MODES.KEEP_ALL}
                      onChange={handleModeChange}
                    />
                    <span><strong>B：関連予定をすべて残す</strong><small>予定は個人予定として残ります。</small></span>
                  </label>
                  <label className={deletionMode === DELETION_MODES.DELETE_SELECTED ? 'is-selected' : undefined}>
                    <input
                      type="radio"
                      name="deletion-mode"
                      value={DELETION_MODES.DELETE_SELECTED}
                      checked={deletionMode === DELETION_MODES.DELETE_SELECTED}
                      onChange={handleModeChange}
                    />
                    <span><strong>C：削除する予定を選ぶ</strong><small>下の一覧でチェックした予定だけを削除します。</small></span>
                  </label>
                </fieldset>

                <section className="target-preview-section" aria-label="関連する予定">
                  {totalScheduleCount === 0 ? (
                    <p className="target-preview-empty">関連する予定はありません。登録情報だけを削除できます。</p>
                  ) : (
                    <>
                      {deletionMode !== DELETION_MODES.DELETE_SELECTED && (
                        <button
                          className="target-preview-toggle"
                          type="button"
                          aria-expanded={isScheduleListExpanded}
                          aria-controls="target-related-schedule-list"
                          onClick={() => setIsScheduleListExpanded((isExpanded) => !isExpanded)}
                        >
                          {isScheduleListExpanded
                            ? '関連予定を閉じる'
                            : `関連予定${totalScheduleCount}件を確認`}
                          <span aria-hidden="true">{isScheduleListExpanded ? '▲' : '▼'}</span>
                        </button>
                      )}

                      {(deletionMode === DELETION_MODES.DELETE_SELECTED || isScheduleListExpanded) && (
                        <>
                          <div className="target-preview-title">
                            <h3>関連する予定</h3>
                            <span>
                              {deletionMode === DELETION_MODES.DELETE_SELECTED
                                ? `${selectedScheduleIds.length}件選択中`
                                : `${totalScheduleCount}件`}
                            </span>
                          </div>
                          <ul id="target-related-schedule-list" className="target-preview-list">
                            {schedules.map((schedule) => (
                              <li key={schedule.id} className="target-preview-item">
                                {deletionMode === DELETION_MODES.DELETE_SELECTED && (
                                  <label className="target-schedule-checkbox">
                                    <input
                                      type="checkbox"
                                      checked={selectedScheduleIds.includes(schedule.id)}
                                      onChange={() => toggleSchedule(schedule.id)}
                                    />
                                    <span className="visually-hidden">{schedule.title}を削除対象にする</span>
                                  </label>
                                )}
                                <div>
                                  <p>{formatSchedulePeriod(schedule)}</p>
                                  <h4>{schedule.title}</h4>
                                  <span>{schedule.schedule_type}</span>
                                </div>
                              </li>
                            ))}
                          </ul>
                        </>
                      )}
                    </>
                  )}
                </section>

                <DeletionSummary
                  targetName={preview.target.name}
                  deleteScheduleCount={deleteScheduleCount}
                  keepScheduleCount={keepScheduleCount}
                />

                {deleteError && !isPreviewStale && <p className="form-error" role="alert">{deleteError}</p>}

                <div className="modal-actions">
                  <button className="secondary-button" type="button" onClick={closeModal}>キャンセル</button>
                  <button className="danger-button" type="button" onClick={openFinalConfirmation} disabled={isPreviewStale}>
                    削除内容を確認
                  </button>
                </div>
              </>
            )}

            {isFinalConfirmation && (
              <div className="target-final-confirmation" role="alertdialog" aria-labelledby="target-final-confirmation-title">
                <h3 id="target-final-confirmation-title">この内容で削除しますか？</h3>
                <DeletionSummary
                  targetName={preview.target.name}
                  deleteScheduleCount={deleteScheduleCount}
                  keepScheduleCount={keepScheduleCount}
                />
                <p className="target-irreversible-warning">
                  {deleteScheduleCount > 0
                    ? '登録情報と削除する予定は元に戻せません。'
                    : '削除する登録情報は元に戻せません。'}
                </p>
                {deleteError && <p className="form-error" role="alert">{deleteError}</p>}
                <div className="modal-actions">
                  <button
                    className="secondary-button"
                    type="button"
                    onClick={() => {
                      setIsFinalConfirmation(false)
                      setDeleteError('')
                    }}
                    disabled={isDeleting}
                  >
                    選択に戻る
                  </button>
                  <button className="danger-button" type="button" onClick={handleDelete} disabled={isDeleting}>
                    {isDeleting ? '削除中…' : '削除する'}
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </section>
    </div>
  )
}

function DeletionSummary({ targetName, deleteScheduleCount, keepScheduleCount }) {
  return (
    <div className="target-deletion-summary">
      <p><span>削除する登録情報</span><strong>{targetName}</strong></p>
      <div>
        <p><span>削除する予定</span><strong>{deleteScheduleCount}件</strong></p>
        <p><span>残す予定</span><strong>{keepScheduleCount}件</strong></p>
      </div>
      {keepScheduleCount > 0 && (
        <p className="target-keep-note">残す予定は登録情報との紐づけが外れ、個人予定として表示されます。</p>
      )}
    </div>
  )
}

export default TargetDeleteModal
