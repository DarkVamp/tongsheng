import { useState, useEffect, useRef } from 'react'
import fixWebmDuration from 'fix-webm-duration'
import { Mic, StopCircle, Upload, Trash2, LogOut, Send } from 'lucide-react'
import Icon from '../components/Icon'
import { getRecordings, uploadRecording, deleteRecording, fetchAudioBlob, getComments, addComment, reactToComment } from '../api/recordings'
import { getLatestHomework, uploadHomeworkImage, deleteHomeworkImage, fetchHomeworkImageBlob } from '../api/homework'
import { logout, updateLocale } from '../api/auth'
import { useAuth } from '../context/AuthContext'
import { useLocale } from '../context/LocaleContext'
import { useNavigate } from 'react-router-dom'

function AuthImage({ id, alt, className, onZoom }) {
  const [src, setSrc] = useState(null)
  useEffect(() => {
    let url
    fetchHomeworkImageBlob(id).then(u => { url = u; setSrc(u) })
    return () => { if (url) URL.revokeObjectURL(url) }
  }, [id])
  if (!src) return <div className="hw-img-placeholder" />
  return (
    <img
      src={src}
      alt={alt || ''}
      className={className}
      onClick={onZoom ? () => onZoom(src) : undefined}
      style={onZoom ? { cursor: 'zoom-in' } : undefined}
    />
  )
}

function Lightbox({ src, onClose }) {
  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose() }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [onClose])
  return (
    <div className="lightbox-overlay" onClick={onClose}>
      <img src={src} alt="" className="lightbox-img" onClick={e => e.stopPropagation()} />
      <button className="lightbox-close" onClick={onClose}>✕</button>
    </div>
  )
}

function AuthAudio({ id, className }) {
  const [src, setSrc] = useState(null)
  useEffect(() => {
    let url
    fetchAudioBlob(id).then((u) => { url = u; setSrc(u) })
    return () => { if (url) URL.revokeObjectURL(url) }
  }, [id])
  const { t } = useLocale()
  if (!src) return <span className="audio-loading">{t('common.loading')}</span>
  return <audio controls src={src} className={className} />
}

export default function FamilyDashboard() {
  const { user, signOut } = useAuth()
  const { locale, setLocale, t } = useLocale()
  const navigate = useNavigate()
  const [recordings, setRecordings] = useState([])
  const [uploading, setUploading] = useState(false)
  const [uploadError, setUploadError] = useState('')
  const [deleteError, setDeleteError] = useState('')
  const [recording, setRecording] = useState(false)
  const [activeId, setActiveId] = useState(null)
  const [comments, setComments] = useState({})
  const [newComment, setNewComment] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [homeworkData, setHomeworkData] = useState(undefined)
  const [hwUploading, setHwUploading] = useState(false)
  const [hwError, setHwError] = useState('')
  const [lightboxSrc, setLightboxSrc] = useState(null)
  const mediaRecorderRef = useRef(null)
  const chunksRef = useRef([])
  const recordingStartRef = useRef(null)

  const hasUploadedToday = recordings.some(r => {
    if (r.uploaderId !== undefined && r.uploaderId !== null) {
      if (r.uploaderId !== (user?.id ?? -1)) return false
    }
    const d = new Date(r.recordedAt)
    return d.toDateString() === new Date().toDateString()
  })

  useEffect(() => {
    getRecordings().then(setRecordings)
    getLatestHomework().then(d => setHomeworkData(d)).catch(() => setHomeworkData(null))
  }, [])

  const switchLocale = async (l) => {
    setLocale(l)
    await updateLocale(l).catch(() => {})
  }

  const startRecording = async () => {
    setUploadError('')
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      const mimeType = ['audio/webm', 'audio/mp4', 'audio/ogg'].find(t => MediaRecorder.isTypeSupported(t)) || ''
      const mr = new MediaRecorder(stream, mimeType ? { mimeType } : {})
      mediaRecorderRef.current = mr
      chunksRef.current = []
      mr.ondataavailable = (e) => chunksRef.current.push(e.data)
      mr.onstop = () => stream.getTracks().forEach(t => t.stop())
      mr.start()
      recordingStartRef.current = Date.now()
      setRecording(true)
    } catch {
      setUploadError(t('family.micDenied'))
    }
  }

  const stopAndUpload = async () => {
    const mr = mediaRecorderRef.current
    if (!mr) return
    const duration = recordingStartRef.current ? Date.now() - recordingStartRef.current : undefined
    mr.stop()
    setRecording(false)
    await new Promise(r => setTimeout(r, 300))
    const type = mr.mimeType || 'audio/webm'
    const ext = type.includes('mp4') ? 'mp4' : type.includes('ogg') ? 'ogg' : 'webm'
    let blob = new Blob(chunksRef.current, { type })
    if (type.includes('webm') && duration) {
      blob = await fixWebmDuration(blob, duration, { logger: false })
    }
    const file = new File([blob], `aufnahme.${ext}`, { type })
    await doUpload(file)
  }

  const handleFileInput = (e) => {
    const file = e.target.files[0]
    if (file) doUpload(file)
  }

  const doUpload = async (file) => {
    setUploading(true)
    setUploadError('')
    try {
      const rec = await uploadRecording(file)
      setRecordings(prev => [rec, ...prev])
    } catch (err) {
      setUploadError(err.response?.data?.error ?? t('family.uploadFailed'))
    } finally {
      setUploading(false)
    }
  }

  const handleLogout = async () => {
    await logout().catch(() => {})
    signOut()
    navigate('/login')
  }

  const handleHwUpload = async (e) => {
    const file = e.target.files[0]
    e.target.value = ''
    if (!file || !homeworkData?.lesson) return
    setHwUploading(true)
    setHwError('')
    try {
      const img = await uploadHomeworkImage(homeworkData.lesson.id, file)
      setHomeworkData(prev => ({ ...prev, images: [...prev.images, img] }))
    } catch (err) {
      setHwError(err.response?.data?.error ?? t('homework.uploadFailed'))
    } finally {
      setHwUploading(false)
    }
  }

  const handleHwDelete = async (imgId) => {
    try {
      await deleteHomeworkImage(imgId)
      setHomeworkData(prev => ({ ...prev, images: prev.images.filter(i => i.id !== imgId) }))
    } catch {}
  }

  const handleDelete = async (id) => {
    if (!confirm(t('common.confirmDelete'))) return
    setDeleteError('')
    try {
      await deleteRecording(id)
    } catch (err) {
      const isAlreadyGone = err.response?.status === 404 && err.response?.data?.error === 'Not found.'
      if (!isAlreadyGone) {
        setDeleteError(err.response?.data?.error ?? t('family.deleteFailed'))
        return
      }
    }
    setRecordings(prev => prev.filter(r => r.id !== id))
  }

  const toggleComments = async (id) => {
    if (activeId === id) { setActiveId(null); return }
    setActiveId(id)
    if (!comments[id]) {
      const c = await getComments(id)
      setComments(prev => ({ ...prev, [id]: c }))
    }
  }

  const submitComment = async (recordingId) => {
    const text = (newComment[recordingId] ?? '').trim()
    if (!text) return
    setSubmitting(true)
    try {
      const c = await addComment(recordingId, text)
      setComments(prev => ({ ...prev, [recordingId]: [...(prev[recordingId] ?? []), c] }))
      setNewComment(prev => ({ ...prev, [recordingId]: '' }))
      setRecordings(prev => prev.map(r =>
        r.id === recordingId ? { ...r, commentCount: r.commentCount + 1 } : r
      ))
    } finally {
      setSubmitting(false)
    }
  }

  const handleReact = async (commentId, type, recordingId) => {
    try {
      const updatedReactions = await reactToComment(commentId, type)
      setComments(prev => ({
        ...prev,
        [recordingId]: (prev[recordingId] ?? []).map(c =>
          c.id === commentId ? { ...c, reactions: updatedReactions } : c
        ),
      }))
    } catch {}
  }

  const isOwn = (r) => {
    if (r.uploaderId === undefined || r.uploaderId === null) return true
    return r.uploaderId === user?.id
  }

  const dateLocale = t('date.locale')

  return (
    <div className="dashboard">
      <header className="dashboard-header">
        <h1>童声</h1>
        <div className="header-right">
          <div className="locale-switcher">
            <button className={locale === 'zh' ? 'active' : ''} onClick={() => switchLocale('zh')} title="中文">🇨🇳</button>
            <button className={locale === 'de' ? 'active' : ''} onClick={() => switchLocale('de')} title="Deutsch">🇩🇪</button>
          </div>
          <span className="user-name">{user?.name}</span>
          <button className="btn-icon-text btn-ghost" onClick={handleLogout} title={t('common.logout')}>
            <LogOut size={16} />
            <span>{t('common.logout')}</span>
          </button>
        </div>
      </header>

      <main className="dashboard-main">
        {homeworkData !== undefined && (
          <section className="upload-section homework-section">
            <h2>{t('homework.title')} 📚</h2>
            {homeworkData === null || homeworkData?.lesson === null ? (
              <p className="empty">{t('homework.noActive')}</p>
            ) : (
              <>
                <p className="homework-lesson-info">
                  {new Date(homeworkData.lesson.date + 'T00:00:00').toLocaleDateString(dateLocale, { weekday: 'long', day: 'numeric', month: 'long' })}
                  {homeworkData.lesson.title && <span className="homework-lesson-title"> — {homeworkData.lesson.title}</span>}
                </p>

                {homeworkData.images.length > 0 ? (
                  <div className="homework-gallery">
                    {homeworkData.images.map(img => (
                      <div key={img.id} className="homework-thumb">
                        <AuthImage id={img.id} alt={img.originalFilename} className="hw-thumb-img" onZoom={setLightboxSrc} />
                        <button
                          className="btn-icon btn-delete hw-thumb-del"
                          onClick={() => handleHwDelete(img.id)}
                          title={t('common.delete')}
                        >
                          <Trash2 size={11} />
                        </button>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="empty">{t('homework.noSubmissions')}</p>
                )}

                <label className="btn-file hw-upload-btn">
                  <Upload size={15} />
                  <span>{t('homework.addImage')}</span>
                  <input type="file" accept="image/*" onChange={handleHwUpload} disabled={hwUploading} />
                </label>
                {hwUploading && <p className="status">{t('homework.uploading')}</p>}
                {hwError && <p className="error">{hwError}</p>}
              </>
            )}
          </section>
        )}

        <section className="upload-section">
          <h2>{t('family.todayRecording')}</h2>
          {hasUploadedToday ? (
            <p className="success-msg">{t('family.alreadyUploaded')}</p>
          ) : (
            <div className="upload-controls">
              <button
                className={`btn-record ${recording ? 'recording' : ''}`}
                onClick={recording ? stopAndUpload : startRecording}
                disabled={uploading}
              >
                {recording ? <><StopCircle size={18} /><span>{t('family.stopUpload')}</span></> : <><Mic size={18} /><span>{t('family.record')}</span></>}
              </button>
              <span className="or">{t('family.or')}</span>
              <label className="btn-file">
                <Upload size={16} />
                <span>{t('family.chooseFile')}</span>
                <input type="file" accept="audio/*" onChange={handleFileInput} disabled={uploading} />
              </label>
              {uploading && <p className="status">{t('family.uploading')}</p>}
              {uploadError && <p className="error">{uploadError}</p>}
            </div>
          )}
        </section>

        <section className="recordings-section">
          <h2>{t('family.allRecordings')}</h2>
          {deleteError && <p className="error">{deleteError}</p>}
          {recordings.length === 0 ? (
            <p className="empty">{t('family.noRecordings')}</p>
          ) : (
            <ul className="recording-list">
              {recordings.map(r => (
                <li key={r.id} className={`recording-item ${activeId === r.id ? 'active' : ''}`}>
                  <div className="recording-meta">
                    <div>
                      <span className="recording-date">
                        {new Date(r.recordedAt).toLocaleDateString(dateLocale, { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' })}
                      </span>
                      {r.uploaderName && (
                        <span className="uploader-badge"> · {r.uploaderName}</span>
                      )}
                    </div>
                    <div className="recording-actions">
                      <button className="btn-icon btn-toggle" onClick={() => toggleComments(r.id)} title={activeId === r.id ? t('teacher.close') : t('teacher.open')}>
                        <Icon name="chevron-down" size={16} style={activeId === r.id ? { transform: 'rotate(180deg)' } : {}} />
                        {r.commentCount > 0 && activeId !== r.id && <span className="comment-count-badge">{r.commentCount}</span>}
                      </button>
                      {isOwn(r) && (
                        <button className="btn-icon btn-delete" onClick={() => handleDelete(r.id)} title={t('common.delete')}>
                          <Trash2 size={15} />
                        </button>
                      )}
                    </div>
                  </div>

                  {activeId === r.id && (
                    <div className="recording-detail">
                      <AuthAudio id={r.id} className="audio-player" />
                      <div className="comments-section">
                        <h3>{t('teacher.comments')}</h3>
                        {(comments[r.id] ?? []).length === 0 ? (
                          <p className="empty">{t('teacher.noComments')}</p>
                        ) : (
                          <ul className="comment-list">
                            {(comments[r.id] ?? []).map(c => (
                              <li key={c.id} className="comment">
                                <div className="comment-header">
                                  {c.authorName && (
                                    <span className={`comment-author${c.authorRole === 'teacher' ? ' comment-author-teacher' : ''}`}>
                                      {c.authorName}
                                      {c.authorRole === 'teacher' && <span className="comment-role-badge">{t('comment.teacherBadge')}</span>}
                                    </span>
                                  )}
                                  <span className="comment-date">{new Date(c.createdAt).toLocaleDateString(dateLocale)}</span>
                                </div>
                                <p>{c.content}</p>
                                <div className="comment-reactions">
                                  {[['thumbs_up', '👍'], ['heart', '❤️'], ['thumbs_down', '👎']].map(([type, emoji]) => (
                                    <button
                                      key={type}
                                      className={`btn-reaction${c.reactions?.mine === type ? ' active' : ''}`}
                                      onClick={() => handleReact(c.id, type, r.id)}
                                      title={c.reactions?.users?.[type]?.length > 0 ? c.reactions.users[type].join(', ') : t(`comment.reaction.${type}`)}
                                    >
                                      {emoji}{c.reactions?.[type] > 0 && <span className="reaction-count">{c.reactions[type]}</span>}
                                    </button>
                                  ))}
                                </div>
                              </li>
                            ))}
                          </ul>
                        )}
                        <div className="comment-form">
                          <textarea
                            rows={2}
                            placeholder={t('teacher.commentPlaceholder')}
                            value={newComment[r.id] ?? ''}
                            onChange={e => setNewComment(prev => ({ ...prev, [r.id]: e.target.value }))}
                            onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitComment(r.id) } }}
                          />
                          <button
                            className="btn-icon btn-send"
                            onClick={() => submitComment(r.id)}
                            disabled={submitting || !(newComment[r.id] ?? '').trim()}
                            title={t('common.send')}
                          >
                            <Send size={16} />
                          </button>
                        </div>
                      </div>
                    </div>
                  )}
                </li>
              ))}
            </ul>
          )}
        </section>
      </main>
      {lightboxSrc && <Lightbox src={lightboxSrc} onClose={() => setLightboxSrc(null)} />}
    </div>
  )
}
