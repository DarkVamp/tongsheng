import { useState, useEffect, useRef } from 'react'
import fixWebmDuration from 'fix-webm-duration'
import { Upload, Trash2, LogOut, Mic, StopCircle } from 'lucide-react'
import {
  getLessonFamilyHomework,
  uploadHomeworkImage, deleteHomeworkImage, fetchHomeworkImageBlob,
  uploadHomeworkAudio, deleteHomeworkAudio, fetchHomeworkAudioBlob,
} from '../api/homework'
import { getLessons } from '../api/lessons'
import { getLessonFeedback, fetchFeedbackAttachmentBlob } from '../api/feedback'
import { logout, updateLocale } from '../api/auth'
import { useAuth } from '../context/AuthContext'
import { useLocale } from '../context/LocaleContext'
import { useNavigate } from 'react-router-dom'

const PHOTO_TYPES = ['schreiben', 'schriftlich', 'malen', 'sonstiges']
const AUDIO_TYPES = ['lesen', 'sonstiges']

const HW_TYPE_ICONS = {
  lesen: '🎙️',
  schreiben: '📷',
  schriftlich: '📝',
  malen: '🎨',
  sonstiges: '🎙️📷',
}

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

function AuthHomeworkAudio({ id, className }) {
  const [src, setSrc] = useState(null)
  const { t } = useLocale()
  useEffect(() => {
    let url
    fetchHomeworkAudioBlob(id).then(u => { url = u; setSrc(u) })
    return () => { if (url) URL.revokeObjectURL(url) }
  }, [id])
  if (!src) return <span className="audio-loading">{t('common.loading')}</span>
  return <audio controls src={src} className={className} />
}

function AuthFeedbackAudio({ id }) {
  const [src, setSrc] = useState(null)
  const { t } = useLocale()
  useEffect(() => {
    let url
    fetchFeedbackAttachmentBlob(id).then(u => { url = u; setSrc(u) })
    return () => { if (url) URL.revokeObjectURL(url) }
  }, [id])
  if (!src) return <span className="audio-loading">{t('common.loading')}</span>
  return <audio controls src={src} className="hw-audio-player" />
}

function AuthFeedbackImage({ id, onZoom }) {
  const [src, setSrc] = useState(null)
  useEffect(() => {
    let url
    fetchFeedbackAttachmentBlob(id).then(u => { url = u; setSrc(u) })
    return () => { if (url) URL.revokeObjectURL(url) }
  }, [id])
  if (!src) return <div className="hw-img-placeholder" />
  return (
    <img
      src={src}
      alt=""
      className="hw-thumb-img-sm"
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

// ── Report Tab ─────────────────────────────────────────────────────────────

function ReportSection({ t, dateLocale }) {
  const [lessons, setLessons] = useState([])
  const [loaded, setLoaded] = useState(false)

  useEffect(() => {
    getLessons().then(data => { setLessons(data); setLoaded(true) }).catch(() => setLoaded(true))
  }, [])

  const formatDate = (dateStr) => {
    const [y, m, d] = dateStr.split('-')
    return new Date(+y, +m - 1, +d).toLocaleDateString(dateLocale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  }

  if (!loaded) return <p className="empty">{t('common.loading')}</p>
  if (lessons.length === 0) return <p className="empty">{t('family.noLessons')}</p>

  return (
    <ul className="report-lesson-list">
      {lessons.map(l => (
        <li key={l.id} className="report-lesson-item">
          <div className="report-lesson-meta">
            <span className="report-lesson-date">{formatDate(l.date)}</span>
            {l.title && <span className="report-lesson-title">{l.title}</span>}
          </div>
          {l.summary
            ? <p className="report-summary">{l.summary}</p>
            : <p className="report-no-summary">{t('family.noSummary')}</p>
          }
        </li>
      ))}
    </ul>
  )
}

// ── Hausaufgaben Tab ───────────────────────────────────────────────────────

function HomeworkSection({ t, dateLocale }) {
  const [lessons, setLessons] = useState(undefined)
  const [openLessons, setOpenLessons] = useState({})
  const [lessonData, setLessonData] = useState({})   // { [lessonId]: { images, audio } }
  const [imgUploading, setImgUploading] = useState({})
  const [audioUploading, setAudioUploading] = useState({})
  const [recordingState, setRecordingState] = useState(null)  // { lessonId, type } | null
  const [uploadError, setUploadError] = useState('')
  const [lightboxSrc, setLightboxSrc] = useState(null)
  const mediaRecorderRef = useRef(null)
  const chunksRef = useRef([])
  const recordingStartRef = useRef(null)

  useEffect(() => {
    const today = new Date().toISOString().slice(0, 10)
    getLessons()
      .then(data => {
        const past = data.filter(l => l.date <= today && (l.homeworkTypes ?? []).length > 0)
        setLessons(past)
      })
      .catch(() => setLessons([]))
    return () => {
      if (mediaRecorderRef.current?.state === 'recording') mediaRecorderRef.current.stop()
    }
  }, [])

  const toggleLesson = async (lessonId) => {
    const isOpen = !!openLessons[lessonId]
    setOpenLessons(prev => ({ ...prev, [lessonId]: !isOpen }))
    if (!isOpen && !lessonData[lessonId]) {
      try {
        const data = await getLessonFamilyHomework(lessonId)
        setLessonData(prev => ({ ...prev, [lessonId]: data }))
      } catch {
        setLessonData(prev => ({ ...prev, [lessonId]: { images: [], audio: [] } }))
      }
    }
  }

  const startRecording = async (lessonId, type) => {
    setUploadError('')
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      const mimeType = ['audio/webm', 'audio/mp4', 'audio/ogg'].find(m => MediaRecorder.isTypeSupported(m)) || ''
      const mr = new MediaRecorder(stream, mimeType ? { mimeType } : {})
      mediaRecorderRef.current = mr
      chunksRef.current = []
      mr.ondataavailable = (e) => chunksRef.current.push(e.data)
      mr.onstop = () => stream.getTracks().forEach(t => t.stop())
      mr.start()
      recordingStartRef.current = Date.now()
      setRecordingState({ lessonId, type })
    } catch {
      setUploadError(t('family.micDenied'))
    }
  }

  const stopAndUpload = async (lessonId, type) => {
    const mr = mediaRecorderRef.current
    if (!mr || mr.state !== 'recording') return
    const duration = recordingStartRef.current ? Date.now() - recordingStartRef.current : undefined

    setRecordingState(null)
    const uploadKey = `${lessonId}-${type}`
    setAudioUploading(prev => ({ ...prev, [uploadKey]: true }))
    setUploadError('')

    try {
      await new Promise(resolve => {
        mr.addEventListener('stop', resolve, { once: true })
        mr.stop()
      })

      const mType = mr.mimeType || 'audio/webm'
      const ext = mType.includes('mp4') ? 'mp4' : mType.includes('ogg') ? 'ogg' : 'webm'
      let blob = new Blob(chunksRef.current, { type: mType })
      if (mType.includes('webm') && duration) {
        blob = await fixWebmDuration(blob, duration, { logger: false })
      }
      const file = new File([blob], `aufnahme.${ext}`, { type: mType })
      const audio = await uploadHomeworkAudio(lessonId, file, type)
      setLessonData(prev => ({
        ...prev,
        [lessonId]: { ...prev[lessonId], audio: [...(prev[lessonId]?.audio ?? []), audio] },
      }))
    } catch (err) {
      setUploadError(err.response?.data?.error ?? t('homework.uploadFailed'))
    } finally {
      setAudioUploading(prev => ({ ...prev, [uploadKey]: false }))
    }
  }

  const handleImgUpload = async (e, lessonId, type) => {
    const file = e.target.files[0]
    e.target.value = ''
    if (!file) return
    const uploadKey = `${lessonId}-${type}`
    setImgUploading(prev => ({ ...prev, [uploadKey]: true }))
    setUploadError('')
    try {
      const img = await uploadHomeworkImage(lessonId, file, type)
      setLessonData(prev => ({
        ...prev,
        [lessonId]: { ...prev[lessonId], images: [...(prev[lessonId]?.images ?? []), img] },
      }))
    } catch (err) {
      setUploadError(err.response?.data?.error ?? t('homework.uploadFailed'))
    } finally {
      setImgUploading(prev => ({ ...prev, [uploadKey]: false }))
    }
  }

  const handleImgDelete = async (imgId, lessonId) => {
    try {
      await deleteHomeworkImage(imgId)
      setLessonData(prev => ({
        ...prev,
        [lessonId]: { ...prev[lessonId], images: prev[lessonId].images.filter(i => i.id !== imgId) },
      }))
    } catch {}
  }

  const handleAudioDelete = async (audioId, lessonId) => {
    try {
      await deleteHomeworkAudio(audioId)
      setLessonData(prev => ({
        ...prev,
        [lessonId]: { ...prev[lessonId], audio: prev[lessonId].audio.filter(a => a.id !== audioId) },
      }))
    } catch {}
  }

  const formatDate = (dateStr) => {
    const [y, m, d] = dateStr.split('-')
    return new Date(+y, +m - 1, +d).toLocaleDateString(dateLocale, { weekday: 'long', day: 'numeric', month: 'long' })
  }

  if (lessons === undefined) return <p className="empty">{t('common.loading')}</p>
  if (lessons.length === 0) return <p className="empty">{t('homework.noActive')}</p>

  return (
    <>
      <ul className="hw-lessons-list">
        {lessons.map(lesson => {
          const isOpen = !!openLessons[lesson.id]
          const data = lessonData[lesson.id]
          const types = lesson.homeworkTypes ?? []

          const imagesByType = {}
          ;(data?.images ?? []).forEach(img => {
            if (!imagesByType[img.homeworkType]) imagesByType[img.homeworkType] = []
            imagesByType[img.homeworkType].push(img)
          })
          const audioByType = {}
          ;(data?.audio ?? []).forEach(a => {
            if (!audioByType[a.homeworkType]) audioByType[a.homeworkType] = []
            audioByType[a.homeworkType].push(a)
          })

          return (
            <li key={lesson.id} className="hw-lesson-card">
              <button className={`hw-lesson-card-header${isOpen ? ' open' : ''}`} onClick={() => toggleLesson(lesson.id)}>
                <span className="hw-lesson-card-date">{formatDate(lesson.date)}</span>
                {lesson.title && <span className="hw-lesson-card-title">{lesson.title}</span>}
                <span className="hw-lesson-card-chevron">{isOpen ? '▲' : '▼'}</span>
              </button>

              {isOpen && (
                <div className="hw-lesson-card-body">
                  {!data ? (
                    <p className="empty">{t('common.loading')}</p>
                  ) : (
                    types.map(type => {
                      const needsPhoto = PHOTO_TYPES.includes(type)
                      const needsAudio = AUDIO_TYPES.includes(type)
                      const typeImages = imagesByType[type] ?? []
                      const typeAudio  = audioByType[type] ?? []
                      const uploadKey  = `${lesson.id}-${type}`
                      const isRecording = recordingState?.lessonId === lesson.id && recordingState?.type === type
                      const anyRecording = recordingState !== null

                      return (
                        <div key={type} className="hw-type-section">
                          <h3 className="hw-type-section-title">
                            {HW_TYPE_ICONS[type]} {t(`homework.type.${type}`)}
                          </h3>

                          {needsPhoto && (
                            <div className="hw-type-photo-area">
                              {typeImages.length > 0 ? (
                                <div className="homework-gallery">
                                  {typeImages.map(img => (
                                    <div key={img.id} className="homework-thumb">
                                      <AuthImage id={img.id} alt={img.originalFilename} className="hw-thumb-img" onZoom={setLightboxSrc} />
                                      <button
                                        className="btn-icon btn-delete hw-thumb-del"
                                        onClick={() => handleImgDelete(img.id, lesson.id)}
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
                                <input type="file" accept="image/*" onChange={e => handleImgUpload(e, lesson.id, type)} disabled={!!imgUploading[uploadKey]} />
                              </label>
                              {imgUploading[uploadKey] && <p className="status">{t('homework.uploading')}</p>}
                            </div>
                          )}

                          {needsAudio && (
                            <div className="hw-type-audio-area">
                              {typeAudio.length > 0 && (
                                <ul className="hw-audio-list">
                                  {typeAudio.map(a => (
                                    <li key={a.id} className="hw-audio-item">
                                      <AuthHomeworkAudio id={a.id} className="hw-audio-player" />
                                      <button
                                        className="btn-icon btn-delete"
                                        onClick={() => handleAudioDelete(a.id, lesson.id)}
                                        title={t('common.delete')}
                                      >
                                        <Trash2 size={13} />
                                      </button>
                                    </li>
                                  ))}
                                </ul>
                              )}
                              <button
                                className={`btn-record${isRecording ? ' recording' : ''}`}
                                onClick={isRecording ? () => stopAndUpload(lesson.id, type) : () => startRecording(lesson.id, type)}
                                disabled={(!isRecording && anyRecording) || !!audioUploading[uploadKey]}
                              >
                                {isRecording
                                  ? <><StopCircle size={16} /><span>{t('family.stopUpload')}</span></>
                                  : <><Mic size={16} /><span>{t('family.record')}</span></>
                                }
                              </button>
                              {audioUploading[uploadKey] && <p className="status">{t('homework.uploading')}</p>}
                            </div>
                          )}
                        </div>
                      )
                    })
                  )}
                  {uploadError && <p className="error">{uploadError}</p>}
                </div>
              )}
            </li>
          )
        })}
      </ul>
      {lightboxSrc && <Lightbox src={lightboxSrc} onClose={() => setLightboxSrc(null)} />}
    </>
  )
}

// ── Kommunikation Tab ──────────────────────────────────────────────────────

function CommunicationSection({ t, dateLocale }) {
  const [lessons, setLessons] = useState([])
  const [loaded, setLoaded] = useState(false)
  const [openLessons, setOpenLessons] = useState({})
  const [feedbackData, setFeedbackData] = useState({}) // { lessonId: messages[] }
  const [lightboxSrc, setLightboxSrc] = useState(null)

  useEffect(() => {
    getLessons().then(data => { setLessons(data); setLoaded(true) }).catch(() => setLoaded(true))
  }, [])

  const formatDate = (dateStr) => {
    const [y, m, d] = dateStr.split('-')
    return new Date(+y, +m - 1, +d).toLocaleDateString(dateLocale, { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' })
  }

  const toggleLesson = async (lessonId) => {
    const isOpen = !!openLessons[lessonId]
    setOpenLessons(prev => ({ ...prev, [lessonId]: !isOpen }))
    if (!isOpen && feedbackData[lessonId] === undefined) {
      const messages = await getLessonFeedback(lessonId).catch(() => [])
      setFeedbackData(prev => ({ ...prev, [lessonId]: messages }))
    }
  }

  if (!loaded) return <p className="empty">{t('common.loading')}</p>
  if (lessons.length === 0) return <p className="empty">{t('family.comm.noLessons')}</p>

  return (
    <>
      <ul className="hw-lesson-list">
        {lessons.map(l => {
          const isOpen = !!openLessons[l.id]
          const messages = feedbackData[l.id]

          // Group messages by student
          const byStudent = {}
          if (messages) {
            messages.forEach(msg => {
              if (!byStudent[msg.studentId]) {
                byStudent[msg.studentId] = { name: msg.studentName, msgs: [] }
              }
              byStudent[msg.studentId].msgs.push(msg)
            })
          }
          const studentGroups = Object.values(byStudent)

          return (
            <li key={l.id} className="hw-lesson-item">
              <button className="hw-lesson-header comm-lesson-toggle" onClick={() => toggleLesson(l.id)}>
                <span className="hw-lesson-date">{formatDate(l.date)}</span>
                {l.title && <span className="hw-lesson-title">{l.title}</span>}
                <span className="comm-lesson-chevron">{isOpen ? '▲' : '▼'}</span>
              </button>

              {isOpen && (
                <div className="comm-lesson-body">
                  {!messages ? (
                    <p className="empty">{t('common.loading')}</p>
                  ) : studentGroups.length === 0 ? (
                    <p className="empty">{t('family.comm.noMessages')}</p>
                  ) : (
                    studentGroups.map(group => (
                      <div key={group.name} className="comm-student-block">
                        <div className="comm-student-header">
                          <span className="comm-student-name">{group.name}</span>
                          <span className="comm-from-label">{t('family.comm.from')}</span>
                        </div>
                        {group.msgs.map(msg => (
                          <div key={msg.id} className="comm-message-card comm-message-readonly">
                            {msg.text && <p className="comm-message-text">{msg.text}</p>}
                            {msg.attachments.length > 0 && (
                              <div className="comm-attachments">
                                {msg.attachments.map(att => (
                                  <div key={att.id} className="comm-attachment">
                                    {att.type === 'audio'
                                      ? <AuthFeedbackAudio id={att.id} />
                                      : <AuthFeedbackImage id={att.id} onZoom={setLightboxSrc} />
                                    }
                                  </div>
                                ))}
                              </div>
                            )}
                          </div>
                        ))}
                      </div>
                    ))
                  )}
                </div>
              )}
            </li>
          )
        })}
      </ul>
      {lightboxSrc && <Lightbox src={lightboxSrc} onClose={() => setLightboxSrc(null)} />}
    </>
  )
}

// ── Main Dashboard ─────────────────────────────────────────────────────────

export default function FamilyDashboard() {
  const { user, signOut } = useAuth()
  const { locale, setLocale, t } = useLocale()
  const navigate = useNavigate()
  const [tab, setTab] = useState('homework')

  const switchLocale = async (l) => {
    setLocale(l)
    await updateLocale(l).catch(() => {})
  }

  const handleLogout = async () => {
    await logout().catch(() => {})
    signOut()
    navigate('/login')
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

      <nav className="tab-nav">
        <button className={tab === 'homework' ? 'active' : ''} onClick={() => setTab('homework')} title={t('family.tabHomework')}>
          <span className="tab-emoji">📚</span>
        </button>
        <button className={tab === 'report' ? 'active' : ''} onClick={() => setTab('report')} title={t('family.tabReport')}>
          <span className="tab-emoji">📋</span>
        </button>
        <button className={tab === 'communication' ? 'active' : ''} onClick={() => setTab('communication')} title={t('family.tabCommunication')}>
          <span className="tab-emoji">💬</span>
        </button>
      </nav>

      <main className="dashboard-main">
        {tab === 'homework'      && <HomeworkSection t={t} dateLocale={dateLocale} />}
        {tab === 'report'        && <ReportSection t={t} dateLocale={dateLocale} />}
        {tab === 'communication' && <CommunicationSection t={t} dateLocale={dateLocale} />}
      </main>
    </div>
  )
}
