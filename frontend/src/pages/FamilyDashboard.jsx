import { useState, useEffect, useRef } from 'react'
import fixWebmDuration from 'fix-webm-duration'
import { getRecordings, uploadRecording, deleteRecording, fetchAudioBlob, getComments } from '../api/recordings'
import { logout, updateLocale } from '../api/auth'
import { useAuth } from '../context/AuthContext'
import { useLocale } from '../context/LocaleContext'
import { useNavigate } from 'react-router-dom'

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
  const [activeComments, setActiveComments] = useState(null)
  const [comments, setComments] = useState([])
  const mediaRecorderRef = useRef(null)
  const chunksRef = useRef([])
  const recordingStartRef = useRef(null)

  const hasUploadedToday = recordings.some(r => {
    const d = new Date(r.recordedAt)
    const today = new Date()
    return d.toDateString() === today.toDateString()
  })

  useEffect(() => {
    getRecordings().then(setRecordings)
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
      const msg = err.response?.data?.error ?? t('family.uploadFailed')
      setUploadError(msg)
    } finally {
      setUploading(false)
    }
  }

  const handleLogout = async () => {
    await logout().catch(() => {})
    signOut()
    navigate('/login')
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

  const openComments = async (id) => {
    if (activeComments === id) { setActiveComments(null); return }
    setActiveComments(id)
    const c = await getComments(id)
    setComments(c)
  }

  const dateLocale = t('date.locale')

  return (
    <div className="dashboard">
      <header className="dashboard-header">
        <h1>同声</h1>
        <div className="header-right">
          <div className="locale-switcher">
            <button className={locale === 'zh' ? 'active' : ''} onClick={() => switchLocale('zh')}>中文</button>
            <button className={locale === 'de' ? 'active' : ''} onClick={() => switchLocale('de')}>DE</button>
          </div>
          <span>{user?.name}</span>
          <button className="btn-ghost" onClick={handleLogout}>{t('common.logout')}</button>
        </div>
      </header>

      <main className="dashboard-main">
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
                {recording ? t('family.stopUpload') : t('family.record')}
              </button>
              <span className="or">{t('family.or')}</span>
              <label className="btn-file">
                {t('family.chooseFile')}
                <input type="file" accept="audio/*" onChange={handleFileInput} disabled={uploading} />
              </label>
              {uploading && <p className="status">{t('family.uploading')}</p>}
              {uploadError && <p className="error">{uploadError}</p>}
            </div>
          )}
        </section>

        <section className="recordings-section">
          <h2>{t('family.myRecordings')}</h2>
          {deleteError && <p className="error">{deleteError}</p>}
          {recordings.length === 0 ? (
            <p className="empty">{t('family.noRecordings')}</p>
          ) : (
            <ul className="recording-list">
              {recordings.map(r => (
                <li key={r.id} className="recording-item">
                  <div className="recording-meta">
                    <span className="recording-date">
                      {new Date(r.recordedAt).toLocaleDateString(dateLocale, { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' })}
                    </span>
                    {r.commentCount > 0 && (
                      <button className="btn-comments" onClick={() => openComments(r.id)}>
                        {t('family.comments', r.commentCount)}
                      </button>
                    )}
                  </div>
                  <AuthAudio id={r.id} className="audio-player" />
                  <button className="btn-delete" onClick={() => handleDelete(r.id)}>{t('common.delete')}</button>
                  {activeComments === r.id && (
                    <ul className="comment-list">
                      {comments.map(c => (
                        <li key={c.id} className="comment">
                          <p>{c.content}</p>
                          <span className="comment-date">{new Date(c.createdAt).toLocaleDateString(dateLocale)}</span>
                        </li>
                      ))}
                    </ul>
                  )}
                </li>
              ))}
            </ul>
          )}
        </section>
      </main>
    </div>
  )
}
