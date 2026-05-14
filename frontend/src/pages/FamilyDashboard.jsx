import { useState, useEffect, useRef } from 'react'
import fixWebmDuration from 'fix-webm-duration'
import { Upload, Trash2, LogOut, Mic, StopCircle } from 'lucide-react'
import {
  getLatestHomework,
  uploadHomeworkImage, deleteHomeworkImage, fetchHomeworkImageBlob,
  uploadHomeworkAudio, deleteHomeworkAudio, fetchHomeworkAudioBlob,
} from '../api/homework'
import { getLessons } from '../api/lessons'
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
    getLessons().then(data => { setLessons(data); setLoaded(true) })
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
  const [homeworkData, setHomeworkData] = useState(undefined)
  const [imgUploading, setImgUploading] = useState({})
  const [audioUploading, setAudioUploading] = useState({})
  const [recordingType, setRecordingType] = useState(null)
  const [uploadError, setUploadError] = useState('')
  const [lightboxSrc, setLightboxSrc] = useState(null)
  const mediaRecorderRef = useRef(null)
  const chunksRef = useRef([])
  const recordingStartRef = useRef(null)

  useEffect(() => {
    getLatestHomework().then(d => setHomeworkData(d)).catch(() => setHomeworkData(null))
    return () => {
      if (mediaRecorderRef.current?.state === 'recording') {
        mediaRecorderRef.current.stop()
      }
    }
  }, [])

  const startRecording = async (type) => {
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
      setRecordingType(type)
    } catch {
      setUploadError(t('family.micDenied'))
    }
  }

  const stopAndUpload = async (lessonId, type) => {
    const mr = mediaRecorderRef.current
    if (!mr || mr.state !== 'recording') return
    const duration = recordingStartRef.current ? Date.now() - recordingStartRef.current : undefined

    setRecordingType(null)
    setAudioUploading(prev => ({ ...prev, [type]: true }))
    setUploadError('')

    try {
      // wait for the stop event — guarantees ondataavailable has fired
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
      setHomeworkData(prev => ({ ...prev, audio: [...(prev.audio ?? []), audio] }))
    } catch (err) {
      setUploadError(err.response?.data?.error ?? t('homework.uploadFailed'))
    } finally {
      setAudioUploading(prev => ({ ...prev, [type]: false }))
    }
  }

  const handleImgUpload = async (e, lessonId, type) => {
    const file = e.target.files[0]
    e.target.value = ''
    if (!file) return
    setImgUploading(prev => ({ ...prev, [type]: true }))
    setUploadError('')
    try {
      const img = await uploadHomeworkImage(lessonId, file, type)
      setHomeworkData(prev => ({ ...prev, images: [...prev.images, img] }))
    } catch (err) {
      setUploadError(err.response?.data?.error ?? t('homework.uploadFailed'))
    } finally {
      setImgUploading(prev => ({ ...prev, [type]: false }))
    }
  }

  const handleImgDelete = async (imgId) => {
    try {
      await deleteHomeworkImage(imgId)
      setHomeworkData(prev => ({ ...prev, images: prev.images.filter(i => i.id !== imgId) }))
    } catch {}
  }

  const handleAudioDelete = async (audioId) => {
    try {
      await deleteHomeworkAudio(audioId)
      setHomeworkData(prev => ({ ...prev, audio: prev.audio.filter(a => a.id !== audioId) }))
    } catch {}
  }

  const formatDate = (dateStr) => {
    const [y, m, d] = dateStr.split('-')
    return new Date(+y, +m - 1, +d).toLocaleDateString(dateLocale, { weekday: 'long', day: 'numeric', month: 'long' })
  }

  if (homeworkData === undefined) return <p className="empty">{t('common.loading')}</p>
  if (!homeworkData?.lesson) return <p className="empty">{t('homework.noActive')}</p>

  const { lesson, images, audio } = homeworkData
  const types = lesson.homeworkTypes ?? []

  const imagesByType = {}
  images.forEach(img => {
    if (!imagesByType[img.homeworkType]) imagesByType[img.homeworkType] = []
    imagesByType[img.homeworkType].push(img)
  })

  const audioByType = {}
  ;(audio ?? []).forEach(a => {
    if (!audioByType[a.homeworkType]) audioByType[a.homeworkType] = []
    audioByType[a.homeworkType].push(a)
  })

  return (
    <>
      <p className="homework-lesson-info">
        {formatDate(lesson.date)}
        {lesson.title && <span className="homework-lesson-title"> — {lesson.title}</span>}
      </p>

      {types.length === 0 && <p className="empty">{t('homework.noActive')}</p>}

      {types.map(type => {
        const needsPhoto = PHOTO_TYPES.includes(type)
        const needsAudio = AUDIO_TYPES.includes(type)
        const typeImages = imagesByType[type] ?? []
        const typeAudio  = audioByType[type] ?? []
        const isRecording = recordingType === type
        const anyRecording = recordingType !== null

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
                          onClick={() => handleImgDelete(img.id)}
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
                  <input type="file" accept="image/*" onChange={e => handleImgUpload(e, lesson.id, type)} disabled={!!imgUploading[type]} />
                </label>
                {imgUploading[type] && <p className="status">{t('homework.uploading')}</p>}
              </div>
            )}

            {needsAudio && (
              <div className="hw-type-audio-area">
                {typeAudio.length > 0 && (
                  <ul className="hw-audio-list">
                    {typeAudio.map((a, i) => (
                      <li key={a.id} className="hw-audio-item">
                        <AuthHomeworkAudio id={a.id} className="hw-audio-player" />
                        <button
                          className="btn-icon btn-delete"
                          onClick={() => handleAudioDelete(a.id)}
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
                  onClick={isRecording ? () => stopAndUpload(lesson.id, type) : () => startRecording(type)}
                  disabled={!isRecording && anyRecording || !!audioUploading[type]}
                >
                  {isRecording
                    ? <><StopCircle size={16} /><span>{t('family.stopUpload')}</span></>
                    : <><Mic size={16} /><span>{t('family.record')}</span></>
                  }
                </button>
                {audioUploading[type] && <p className="status">{t('homework.uploading')}</p>}
              </div>
            )}
          </div>
        )
      })}

      {uploadError && <p className="error">{uploadError}</p>}
      {lightboxSrc && <Lightbox src={lightboxSrc} onClose={() => setLightboxSrc(null)} />}
    </>
  )
}

// ── Kommunikation Tab ──────────────────────────────────────────────────────

function CommunicationSection({ t }) {
  return (
    <p className="empty communication-placeholder">{t('family.communicationComingSoon')}</p>
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
        <button className={tab === 'homework' ? 'active' : ''} onClick={() => setTab('homework')}>
          📚 {t('family.tabHomework')}
        </button>
        <button className={tab === 'report' ? 'active' : ''} onClick={() => setTab('report')}>
          📋 {t('family.tabReport')}
        </button>
        <button className={tab === 'communication' ? 'active' : ''} onClick={() => setTab('communication')}>
          💬 {t('family.tabCommunication')}
        </button>
      </nav>

      <main className="dashboard-main">
        {tab === 'homework'      && <HomeworkSection t={t} dateLocale={dateLocale} />}
        {tab === 'report'        && <ReportSection t={t} dateLocale={dateLocale} />}
        {tab === 'communication' && <CommunicationSection t={t} />}
      </main>
    </div>
  )
}
