import { useEffect, useRef, useState } from 'react'
import { getCategories } from '../api/categories'
import { ApiError } from '../api/client'
import { createTarget, updateTarget } from '../api/targets'

function TargetCreateModal({ target = null, onClose, onCreated, onUpdated, onSessionExpired }) {
  const nameInputRef = useRef(null)
  const isSavingRef = useRef(false)
  const isEditing = target !== null
  const [values, setValues] = useState({
    name: target?.name ?? '',
    categoryId: target?.category?.id ? String(target.category.id) : '',
    targetType: target?.target_type ?? '',
    memo: target?.memo ?? '',
  })
  const [categories, setCategories] = useState([])
  const [categoryStatus, setCategoryStatus] = useState('loading')
  const [categoryRetryCount, setCategoryRetryCount] = useState(0)
  const [fieldErrors, setFieldErrors] = useState({})
  const [submitError, setSubmitError] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    nameInputRef.current?.focus()

    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    return () => {
      document.body.style.overflow = previousOverflow
    }
  }, [])

  useEffect(() => {
    const controller = new AbortController()

    setCategoryStatus('loading')
    getCategories(controller.signal)
      .then((response) => {
        setCategories(response.data)
        setCategoryStatus('success')
      })
      .catch((error) => {
        if (error.name === 'AbortError') return

        if (error instanceof ApiError && error.status === 401) {
          onSessionExpired()
          return
        }

        setCategoryStatus('error')
      })

    return () => controller.abort()
  }, [categoryRetryCount, onSessionExpired])

  useEffect(() => {
    const handleKeyDown = (event) => {
      if (event.key === 'Escape' && !isSavingRef.current) onClose()
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [onClose])

  const updateValue = (name, value) => {
    setValues((current) => ({ ...current, [name]: value }))
    setFieldErrors((current) => ({ ...current, [name]: undefined }))
    setSubmitError('')
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    if (isSavingRef.current || categoryStatus !== 'success' || categories.length === 0) return

    const errors = {}
    const name = values.name.trim()
    const targetType = values.targetType.trim()

    if (!name) errors.name = '名前を入力してください。'
    if (!values.categoryId) errors.categoryId = 'カテゴリを選択してください。'
    if (!targetType) errors.targetType = '種別を入力してください。'

    setFieldErrors(errors)
    setSubmitError('')

    if (Object.keys(errors).length > 0) return

    isSavingRef.current = true
    setIsSaving(true)

    try {
      const payload = {
        category_id: Number(values.categoryId),
        name,
        target_type: targetType,
        memo: values.memo.trim() || null,
      }

      if (isEditing) {
        await updateTarget(target.id, payload)
        onUpdated()
      } else {
        await createTarget(payload)
        onCreated()
      }
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        onSessionExpired()
        return
      }

      if (error instanceof ApiError && error.status === 422) {
        setFieldErrors({
          categoryId: error.data?.errors?.category_id?.[0],
          name: error.data?.errors?.name?.[0],
          targetType: error.data?.errors?.target_type?.[0],
          memo: error.data?.errors?.memo?.[0],
        })
      } else if (isEditing && error instanceof ApiError && error.status === 403) {
        setSubmitError('この登録情報を編集する権限がありません。')
      } else if (isEditing && error instanceof ApiError && error.status === 404) {
        setSubmitError('編集対象が見つかりません。一覧を再読み込みしてください。')
      } else {
        setSubmitError(`${isEditing ? '変更を保存' : '登録'}できませんでした。通信状況を確認して、もう一度お試しください。`)
      }
    } finally {
      isSavingRef.current = false
      setIsSaving(false)
    }
  }

  const handleBackdropClick = (event) => {
    if (event.target === event.currentTarget && !isSavingRef.current) onClose()
  }

  const canSubmit = categoryStatus === 'success' && categories.length > 0

  return (
    <div className="modal-backdrop" onMouseDown={handleBackdropClick}>
      <section className="schedule-modal target-create-modal" role="dialog" aria-modal="true" aria-labelledby="target-modal-title">
        <div className="modal-header">
          <div>
            {isEditing && <p className="section-label">登録内容</p>}
            <h2 id="target-modal-title">{isEditing ? '登録情報を編集' : '新規登録'}</h2>
          </div>
          <button className="modal-close" type="button" onClick={onClose} disabled={isSaving} aria-label="閉じる">×</button>
        </div>

        <form className="schedule-form" onSubmit={handleSubmit}>
          <fieldset disabled={isSaving} aria-busy={isSaving}>
            <label htmlFor="target-name">名前 <span>必須</span></label>
            <input ref={nameInputRef} id="target-name" value={values.name} onChange={(event) => updateValue('name', event.target.value)} maxLength="255" required />
            <FieldError message={fieldErrors.name} />

            <label htmlFor="target-category">カテゴリ <span>必須</span></label>
            <select id="target-category" value={values.categoryId} onChange={(event) => updateValue('categoryId', event.target.value)} disabled={categoryStatus !== 'success' || categories.length === 0} required>
              <option value="">選択してください</option>
              {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
            </select>
            {categoryStatus === 'loading' && <p className="field-note">カテゴリを読み込んでいます…</p>}
            {categoryStatus === 'error' && (
              <div className="target-load-error" role="alert">
                <p>カテゴリを取得できないため{isEditing ? '更新' : '登録'}できません。</p>
                <button type="button" onClick={() => setCategoryRetryCount((count) => count + 1)}>再試行</button>
              </div>
            )}
            {categoryStatus === 'success' && categories.length === 0 && <p className="form-error" role="alert">利用できるカテゴリがありません。</p>}
            <FieldError message={fieldErrors.categoryId} />

            <label htmlFor="target-type">種別 <span>必須</span></label>
            <input id="target-type" value={values.targetType} onChange={(event) => updateValue('targetType', event.target.value)} maxLength="100" placeholder="例：個人、グループ、チーム" required />
            <FieldError message={fieldErrors.targetType} />

            <label htmlFor="target-memo">メモ</label>
            <textarea id="target-memo" rows="4" value={values.memo} onChange={(event) => updateValue('memo', event.target.value)} />
            <FieldError message={fieldErrors.memo} />
          </fieldset>

          {submitError && <p className="form-error" role="alert">{submitError}</p>}

          <div className="modal-actions">
            <button className="secondary-button" type="button" onClick={onClose} disabled={isSaving}>キャンセル</button>
            <button className="primary-button" type="submit" disabled={isSaving || !canSubmit}>
              {isSaving ? (isEditing ? '保存中…' : '登録中…') : (isEditing ? '変更を保存' : '登録する')}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

function FieldError({ message }) {
  return message ? <p className="field-error" role="alert">{message}</p> : null
}

export default TargetCreateModal
