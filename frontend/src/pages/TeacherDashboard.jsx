import { useState, useEffect } from 'react'
import { getRecordings, deleteRecording, fetchAudioBlob, getComments, addComment } from '../api/recordings'
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

export default function TeacherDashboard() {
  const { user, signOut } = useAuth()
  const { locale, setLocale, t } = useLocale()
  const navigate = useNavigate()
  const [recordings, setRecordings] = useState([])
  const [filter, setFilter] = useState('')
  const [activeId, setActiveId] = useState(null)
  const [comments, setComments] = useState({})
  const [newComment, setNewComment] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [deleteError, setDeleteError] = useState('')

  useEffect(() => {
    getRecordings().then(setRecordings)
  }, [])

  const switchLocale = async (l) => {
    setLocale(l)
    await updateLocale(l).catch(() => {})
  }

  const handleLogout = async () => {
    await logout().catch(() => {})
    signOut()
    navigate('/login')
  }

  const toggleRecording = async (id) => {
    if (activeId === id) { setActiveId(null); return }
    setActiveId(id)
    if (!comments[id]) {
      const c = await getComments(id)
      setComments(prev => ({ ...prev, [id]: c }))
    }
  }

  const handleDelete = async (id) => {
    if (!confirm(t('common.confirmDelete'))) return
    setDeleteError('')
    try {
      await deleteRecording(id)
    } catch (err) {
      const isAlreadyGone = err.response?.status === 404 && err.response?.data?.error === 'Not found.'
      if (!isAlreadyGone) {
        setDeleteError(err.response?.data?.error ?? t('teacher.deleteFailed'))
        return
      }
    }
    setRecordings(prev => prev.filter(r => r.id !== id))
    if (activeId === id) setActiveId(null)
  }

  const submitComment = async (recordingId) => {
    if (!newComment.trim()) return
    setSubmitting(true)
    try {
      const c = await addComment(recordingId, newComment.trim())
      setComments(prev => ({ ...prev, [recordingId]: [...(prev[recordingId] ?? []), c] }))
      setNewComment('')
      setRecordings(prev => prev.map(r =>
        r.id === recordingId ? { ...r, commentCount: r.commentCount + 1 } : r
      ))
    } finally {
      setSubmitting(false)
    }
  }

  const filtered = recordings.filter(r =>
    !filter || r.family?.toLowerCase().includes(filter.toLowerCase())
  )

  const grouped = filtered.reduce((acc, r) => {
    const key = r.family ?? 'Unbekannt'
    ;(acc[key] = acc[key] ?? []).push(r)
    return acc
  }, {})

  const dateLocale = t('date.locale')

  return (
    <div className="dashboard">
      <header className="dashboard-header">
        <h1>同声 <span className="role-badge">{t('teacher.badge')}</span></h1>
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
        {deleteError && <p className="error">{deleteError}</p>}
        <div className="filter-bar">
          <input
            type="search"
            placeholder={t('teacher.filterPlaceholder')}
            value={filter}
            onChange={e => setFilter(e.target.value)}
          />
          <span className="count">{t('teacher.recordingCount', filtered.length)}</span>
        </div>

        {Object.entries(grouped).map(([family, recs]) => (
          <section key={family} className="family-section">
            <h2 className="family-name">{family}</h2>
            <ul className="recording-list">
              {recs.map(r => (
                <li key={r.id} className={`recording-item ${activeId === r.id ? 'active' : ''}`}>
                  <div className="recording-meta">
                    <span className="recording-date">
                      {new Date(r.recordedAt).toLocaleDateString(dateLocale, { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' })}
                    </span>
                    <div className="recording-actions">
                      <button className="btn-toggle" onClick={() => toggleRecording(r.id)}>
                        {activeId === r.id
                          ? t('teacher.close')
                          : r.commentCount
                            ? t('teacher.openWithComments', r.commentCount)
                            : t('teacher.open')}
                      </button>
                      <button className="btn-delete" onClick={() => handleDelete(r.id)}>{t('common.delete')}</button>
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
                                <p>{c.content}</p>
                                <span className="comment-date">{new Date(c.createdAt).toLocaleDateString(dateLocale)}</span>
                              </li>
                            ))}
                          </ul>
                        )}
                        <div className="comment-form">
                          <textarea
                            rows={2}
                            placeholder={t('teacher.commentPlaceholder')}
                            value={newComment}
                            onChange={e => setNewComment(e.target.value)}
                            onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitComment(r.id) } }}
                          />
                          <button onClick={() => submitComment(r.id)} disabled={submitting || !newComment.trim()}>
                            {submitting ? t('common.sending') : t('common.send')}
                          </button>
                        </div>
                      </div>
                    </div>
                  )}
                </li>
              ))}
            </ul>
          </section>
        ))}

        {filtered.length === 0 && <p className="empty">{t('teacher.noRecordings')}</p>}
      </main>
    </div>
  )
}
