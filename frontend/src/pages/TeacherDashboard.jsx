import { useState, useEffect } from 'react'
import { Trash2, LogOut, Send, UserPlus, Users, X, Search, GraduationCap, Calendar, BookOpen, MessageSquare, ChevronDown } from 'lucide-react'
import Icon from '../components/Icon'
import LuckyWheel from '../components/LuckyWheel'
import { getRecordings, deleteRecording, fetchAudioBlob, getComments, addComment, reactToComment } from '../api/recordings'
import { createFamily, deleteFamily, getFamilyMembers, createMember, deleteMember } from '../api/families'
import { toggleStudent, getLessons, createLesson, deleteLesson, getLessonAttendance, setAttendance, patchLesson } from '../api/lessons'
import { getHomeworkAllForLesson, fetchHomeworkImageBlob, getHomeworkByType, fetchHomeworkAudioBlob } from '../api/homework'
import { getLessonFeedback, createFeedbackMessage, patchFeedbackMessage, uploadFeedbackAttachment, fetchFeedbackAttachmentBlob, deleteFeedbackMessage, deleteFeedbackAttachment } from '../api/feedback'
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

// ── Recordings Tab ─────────────────────────────────────────────────────────

function RecordingsTab({ t, dateLocale }) {
  const [recordings, setRecordings] = useState([])
  const [filter, setFilter] = useState('')
  const [activeId, setActiveId] = useState(null)
  const [comments, setComments] = useState({})
  const [newComment, setNewComment] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [deleteError, setDeleteError] = useState('')

  useEffect(() => { getRecordings().then(setRecordings) }, [])

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

  const filtered = recordings.filter(r =>
    !filter || r.family?.toLowerCase().includes(filter.toLowerCase())
  )

  const grouped = filtered.reduce((acc, r) => {
    const key = r.familyId ?? 'unknown'
    if (!acc[key]) acc[key] = { name: r.family ?? 'Unbekannt', recs: [] }
    acc[key].recs.push(r)
    return acc
  }, {})

  return (
    <>
      {deleteError && <p className="error">{deleteError}</p>}

      <div className="filter-bar">
        <Search size={16} className="filter-icon" />
        <input
          type="search"
          placeholder={t('teacher.filterPlaceholder')}
          value={filter}
          onChange={e => setFilter(e.target.value)}
        />
        <span className="count">{t('teacher.recordingCount', filtered.length)}</span>
      </div>

      {Object.entries(grouped).map(([key, { name, recs }]) => (
        <section key={key} className="family-section">
          <h2 className="family-name">{name}</h2>
          <ul className="recording-list">
            {recs.map(r => (
              <li key={r.id} className={`recording-item ${activeId === r.id ? 'active' : ''}`}>
                <div className="recording-meta">
                  <div>
                    <span className="recording-date">
                      {new Date(r.recordedAt).toLocaleDateString(dateLocale, { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' })}
                    </span>
                    {r.uploaderName && r.uploaderName !== name && (
                      <span className="uploader-badge"> · {r.uploaderName}</span>
                    )}
                  </div>
                  <div className="recording-actions">
                    <button className="btn-icon btn-toggle" onClick={() => toggleRecording(r.id)} title={activeId === r.id ? t('teacher.close') : t('teacher.open')}>
                      <Icon name="chevron-down" size={16} style={activeId === r.id ? { transform: 'rotate(180deg)' } : {}} />
                      {r.commentCount > 0 && activeId !== r.id && <span className="comment-count-badge">{r.commentCount}</span>}
                    </button>
                    <button className="btn-icon btn-delete" onClick={() => handleDelete(r.id)} title={t('common.delete')}>
                      <Trash2 size={15} />
                    </button>
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
                          value={newComment}
                          onChange={e => setNewComment(e.target.value)}
                          onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitComment(r.id) } }}
                        />
                        <button
                          className="btn-icon btn-send"
                          onClick={() => submitComment(r.id)}
                          disabled={submitting || !newComment.trim()}
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
        </section>
      ))}

      {filtered.length === 0 && <p className="empty">{t('teacher.noRecordings')}</p>}
    </>
  )
}

// ── Add Member Modal ───────────────────────────────────────────────────────

function AddMemberModal({ family, onClose, onCreated, t }) {
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setSubmitting(true)
    try {
      const member = await createMember(family.id, { name, email, password })
      onCreated(member)
      onClose()
    } catch (err) {
      setError(err.response?.data?.error ?? t('teacher.addMemberError'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal" onClick={e => e.stopPropagation()}>
        <div className="modal-header">
          <h2>{t('teacher.addMemberTitle', family.name)}</h2>
          <button className="btn-icon btn-ghost" onClick={onClose}><X size={18} /></button>
        </div>
        <form onSubmit={handleSubmit}>
          <label>
            {t('teacher.memberName')}
            <input type="text" value={name} onChange={e => setName(e.target.value)} required autoFocus />
          </label>
          <label>
            {t('teacher.memberEmail')}
            <input type="email" value={email} onChange={e => setEmail(e.target.value)} required />
          </label>
          <label>
            {t('teacher.memberPassword')}
            <input type="password" value={password} onChange={e => setPassword(e.target.value)} required minLength={6} />
          </label>
          {error && <p className="error">{error}</p>}
          <div className="modal-actions">
            <button type="submit" disabled={submitting}>
              {submitting ? t('common.sending') : t('teacher.addMember')}
            </button>
            <button type="button" className="btn-ghost" onClick={onClose}>{t('common.cancel')}</button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ── Schüler Tab ────────────────────────────────────────────────────────────

function StudentsTab({ t }) {
  const [familyGroups, setFamilyGroups] = useState([])
  const [loaded, setLoaded] = useState(false)
  const [newFamilyName, setNewFamilyName] = useState('')
  const [creating, setCreating] = useState(false)
  const [addMemberFamily, setAddMemberFamily] = useState(null)

  useEffect(() => {
    getFamilyMembers().then(data => { setFamilyGroups(data); setLoaded(true) })
  }, [])

  const handleCreateFamily = async (e) => {
    e.preventDefault()
    if (!newFamilyName.trim()) return
    setCreating(true)
    try {
      const family = await createFamily(newFamilyName.trim())
      setFamilyGroups(prev => [...prev, { familyId: family.id, familyName: family.name, members: [] }].sort((a, b) => a.familyName.localeCompare(b.familyName)))
      setNewFamilyName('')
    } finally {
      setCreating(false)
    }
  }

  const handleDeleteFamily = async (familyId, familyName) => {
    if (!confirm(t('teacher.confirmDeleteFamily', familyName))) return
    try {
      await deleteFamily(familyId)
      setFamilyGroups(prev => prev.filter(g => g.familyId !== familyId))
    } catch {}
  }

  const handleToggleStudent = async (userId) => {
    try {
      const { isStudent } = await toggleStudent(userId)
      setFamilyGroups(prev => prev.map(g => ({
        ...g,
        members: g.members.map(m => m.id === userId ? { ...m, isStudent } : m),
      })))
    } catch {}
  }

  const handleDeleteMember = async (userId, memberName) => {
    if (!confirm(t('teacher.confirmDeleteMember', memberName))) return
    try {
      await deleteMember(userId)
      setFamilyGroups(prev => prev.map(g => ({
        ...g,
        members: g.members.filter(m => m.id !== userId),
      })))
    } catch {}
  }

  const handleMemberCreated = (familyId, member) => {
    setFamilyGroups(prev => prev.map(g =>
      g.familyId === familyId
        ? { ...g, members: [...g.members, member].sort((a, b) => a.name.localeCompare(b.name)) }
        : g
    ))
  }

  return (
    <>
      <form className="family-create-form" onSubmit={handleCreateFamily}>
        <input
          type="text"
          value={newFamilyName}
          onChange={e => setNewFamilyName(e.target.value)}
          placeholder={t('teacher.familyNamePlaceholder')}
        />
        <button type="submit" className="btn-primary" disabled={creating || !newFamilyName.trim()}>
          <Icon name="add" size={15} style={{ display: 'inline', verticalAlign: 'middle', marginRight: 4 }} />
          {t('teacher.createFamily')}
        </button>
      </form>

      {!loaded && <p className="empty">{t('common.loading')}</p>}
      {loaded && familyGroups.length === 0 && <p className="empty">{t('teacher.noFamilies')}</p>}

      {familyGroups.map(g => (
        <section key={g.familyId} className="students-section">
          <div className="students-section-header">
            <h2>{g.familyName}</h2>
            <button
              className="btn-icon-text btn-ghost"
              onClick={() => setAddMemberFamily({ id: g.familyId, name: g.familyName })}
              title={t('teacher.addMember')}
            >
              <UserPlus size={14} />
            </button>
            <button
              className="btn-icon btn-delete"
              onClick={() => handleDeleteFamily(g.familyId, g.familyName)}
              title={t('teacher.deleteFamily')}
            >
              <Trash2 size={15} />
            </button>
          </div>
          {g.members.length === 0 ? (
            <p className="empty">{t('teacher.noMembers')}</p>
          ) : (
            <ul className="member-list">
              {g.members.map(m => (
                <li key={m.id} className={`member-item${m.isStudent ? ' is-student' : ''}`}>
                  <span className="member-name">{m.name}</span>
                  {m.isStudent && (
                    <span className="student-badge">
                      <Icon name="check" size={12} style={{ display: 'inline', verticalAlign: 'middle', marginRight: 3 }} />
                      {t('teacher.studentBadge')}
                    </span>
                  )}
                  <button
                    className="btn-student-toggle"
                    onClick={() => handleToggleStudent(m.id)}
                    title={m.isStudent ? t('teacher.unmarkStudent') : t('teacher.markStudent')}
                  >
                    {m.isStudent
                      ? <Icon name="close" size={13} style={{ display: 'inline', verticalAlign: 'middle' }} />
                      : <Icon name="check" size={13} style={{ display: 'inline', verticalAlign: 'middle' }} />}
                  </button>
                  <button
                    className="btn-icon btn-delete"
                    onClick={() => handleDeleteMember(m.id, m.name)}
                    title={t('teacher.deleteMember')}
                  >
                    <Trash2 size={13} />
                  </button>
                </li>
              ))}
            </ul>
          )}
        </section>
      ))}

      {addMemberFamily && (
        <AddMemberModal
          family={addMemberFamily}
          t={t}
          onClose={() => setAddMemberFamily(null)}
          onCreated={(member) => handleMemberCreated(addMemberFamily.id, member)}
        />
      )}
    </>
  )
}

// ── Unterricht Tab ─────────────────────────────────────────────────────────

function LessonsTab({ t, dateLocale }) {
  const [lessons, setLessons] = useState([])
  const [loaded, setLoaded] = useState(false)
  const [newDate, setNewDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [newTitle, setNewTitle] = useState('')
  const [creating, setCreating] = useState(false)
  const [activeLessonId, setActiveLessonId] = useState(null)
  const [attendanceMap, setAttendanceMap] = useState({})
  const [attendanceError, setAttendanceError] = useState({})
  const [homeworkMap, setHomeworkMap] = useState({})
  const [lightboxSrc, setLightboxSrc] = useState(null)
  const [listError, setListError] = useState('')
  const [wheelLessonId, setWheelLessonId] = useState(null)
  const [summaryOpenId, setSummaryOpenId] = useState(null)
  const [summaryDraft, setSummaryDraft] = useState('')
  const [summarySaving, setSummarySaving] = useState(false)
  const [summarySavedId, setSummarySavedId] = useState(null)
  const [summarySaveError, setSummarySaveError] = useState(null)

  useEffect(() => {
    getLessons()
      .then(data => { setLessons(data); setLoaded(true) })
      .catch(err => {
        setListError(err.response?.data?.error ?? err.message ?? 'Fehler beim Laden')
        setLoaded(true)
      })
  }, [])

  const handleCreate = async (e) => {
    e.preventDefault()
    if (!newDate) return
    setCreating(true)
    try {
      const lesson = await createLesson({ date: newDate, title: newTitle.trim() || null })
      setLessons(prev => [lesson, ...prev])
      setNewTitle('')
    } finally {
      setCreating(false)
    }
  }

  const handleDelete = async (id) => {
    if (!confirm(t('teacher.confirmDeleteLesson'))) return
    try {
      await deleteLesson(id)
      setLessons(prev => prev.filter(l => l.id !== id))
      if (activeLessonId === id) setActiveLessonId(null)
    } catch {}
  }

  const toggleLesson = async (id) => {
    if (activeLessonId === id) { setActiveLessonId(null); return }
    setActiveLessonId(id)
    if (!attendanceMap[id]) {
      try {
        const data = await getLessonAttendance(id)
        setAttendanceMap(prev => ({ ...prev, [id]: data }))
      } catch (err) {
        const msg = err.response?.data?.error ?? err.message ?? 'Fehler beim Laden'
        setAttendanceError(prev => ({ ...prev, [id]: msg }))
      }
    }
    const lesson = lessons.find(l => l.id === id)
    if (lesson?.homeworkAssigned && !homeworkMap[id]) {
      try {
        const data = await getHomeworkAllForLesson(id)
        setHomeworkMap(prev => ({ ...prev, [id]: data }))
      } catch {}
    }
  }

  const handleToggleSummary = (lesson) => {
    if (summaryOpenId === lesson.id) {
      setSummaryOpenId(null)
    } else {
      setSummaryOpenId(lesson.id)
      setSummaryDraft(lesson.summary ?? '')
      setSummarySavedId(null)
      setSummarySaveError(null)
    }
  }

  const handleSaveSummary = async (id) => {
    setSummarySaving(true)
    setSummarySaveError(null)
    try {
      const updated = await patchLesson(id, { summary: summaryDraft })
      setLessons(prev => prev.map(l => l.id === id ? { ...l, summary: updated.summary } : l))
      setSummarySavedId(id)
    } catch {
      setSummarySaveError(id)
    } finally {
      setSummarySaving(false)
    }
  }

  const handleAttendance = async (lessonId, studentId, present) => {
    try {
      await setAttendance(lessonId, studentId, present)
      setAttendanceMap(prev => {
        const entry = prev[lessonId]
        if (!entry) return prev
        const students = entry.students.map(s => s.id === studentId ? { ...s, present } : s)
        const presentCount = students.filter(s => s.present).length
        return {
          ...prev,
          [lessonId]: { ...entry, students, lesson: { ...entry.lesson, presentCount } },
        }
      })
      setLessons(prev => prev.map(l => {
        if (l.id !== lessonId) return l
        const delta = present ? 1 : -1
        return { ...l, presentCount: Math.max(0, l.presentCount + delta) }
      }))
    } catch {}
  }

  const formatLessonDate = (dateStr) => {
    const [y, m, d] = dateStr.split('-')
    return new Date(+y, +m - 1, +d).toLocaleDateString(dateLocale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  }

  const groupByFamily = (students) => {
    const map = {}
    for (const s of students) {
      if (!map[s.familyName]) map[s.familyName] = []
      map[s.familyName].push(s)
    }
    return map
  }

  return (
    <>
      <form className="lesson-create-form" onSubmit={handleCreate}>
        <label>
          {t('teacher.lessonDate')}
          <input type="date" value={newDate} onChange={e => setNewDate(e.target.value)} required />
        </label>
        <label>
          {t('teacher.lessonTitle')}
          <input type="text" value={newTitle} onChange={e => setNewTitle(e.target.value)} placeholder="z.B. Lektion 5" />
        </label>
        <button type="submit" className="btn-primary" disabled={creating || !newDate} style={{ alignSelf: 'flex-end' }}>
          <Icon name="add" size={15} style={{ display: 'inline', verticalAlign: 'middle', marginRight: 4 }} />
          {t('teacher.createLesson')}
        </button>
      </form>

      {!loaded && <p className="empty">{t('common.loading')}</p>}
      {listError && <p className="error">{listError}</p>}
      {loaded && !listError && lessons.length === 0 && <p className="empty">{t('teacher.noLessons')}</p>}

      <ul className="lesson-list">
        {lessons.map(l => {
          const isActive = activeLessonId === l.id
          const att = attendanceMap[l.id]
          const presentCount = att ? att.lesson.presentCount : l.presentCount
          const totalStudents = att ? att.students.length : l.totalStudents
          const grouped = att ? groupByFamily(att.students) : null

          return (
            <li key={l.id} className={`lesson-item${isActive ? ' active' : ''}`}>
              <div className="lesson-header">
                <div className="lesson-date">
                  {formatLessonDate(l.date)}
                  {l.title && <div className="lesson-title-text">{l.title}</div>}
                </div>
                {totalStudents > 0 && (
                  <span className="lesson-count">{t('teacher.lessonCount', presentCount, totalStudents)}</span>
                )}
                <div className="lesson-actions">
                  {isActive && att && (
                    <button
                      className="btn-icon-text btn-ghost btn-wheel"
                      onClick={() => setWheelLessonId(l.id)}
                      title={t('teacher.luckyWheel')}
                    >
                      🎡
                    </button>
                  )}
                  <button
                    className={`btn-icon-text btn-ghost btn-summary-toggle${summaryOpenId === l.id ? ' summary-active' : ''}`}
                    onClick={e => { e.stopPropagation(); handleToggleSummary(l) }}
                    title={t('teacher.summary')}
                  >
                    📝
                  </button>
                  <button className="btn-icon btn-toggle" onClick={() => toggleLesson(l.id)} title={isActive ? t('teacher.close') : t('teacher.attendance')}>
                    <Icon name="chevron-down" size={16} style={isActive ? { transform: 'rotate(180deg)' } : {}} />
                  </button>
                  <button className="btn-icon btn-delete" onClick={() => handleDelete(l.id)} title={t('common.delete')}>
                    <Trash2 size={15} />
                  </button>
                </div>
              </div>

              {isActive && att && (
                <div className="attendance-list">
                  {Object.entries(grouped).map(([familyName, students]) => (
                    <div key={familyName}>
                      <div className="attendance-family">{familyName}</div>
                      {students.map(s => (
                        <div key={s.id} className="attendance-student">
                          <span className="attendance-student-name">{s.name}</span>
                          <button
                            className={`btn-attendance${s.present ? ' present' : ''}`}
                            onClick={() => handleAttendance(l.id, s.id, !s.present)}
                          >
                            <Icon name={s.present ? 'check' : 'close'} size={13} />
                            {s.present ? t('teacher.present') : t('teacher.absent')}
                          </button>
                        </div>
                      ))}
                    </div>
                  ))}
                  {att.students.length === 0 && <p className="empty">{t('teacher.noRecordings')}</p>}
                </div>
              )}
              {isActive && !att && (
                <div className="attendance-list">
                  {attendanceError[l.id]
                    ? <p className="error">{attendanceError[l.id]}</p>
                    : <p className="empty">{t('common.loading')}</p>}
                </div>
              )}

              {summaryOpenId === l.id && (
                <div className="summary-editor-section">
                  <textarea
                    className="summary-textarea"
                    value={summaryDraft}
                    onChange={e => { setSummaryDraft(e.target.value); setSummarySavedId(null); setSummarySaveError(null) }}
                    placeholder={t('teacher.summaryPlaceholder')}
                    rows={4}
                  />
                  <div className="summary-actions">
                    <button
                      className="btn-primary btn-save-summary"
                      onClick={() => handleSaveSummary(l.id)}
                      disabled={summarySaving}
                    >
                      {summarySaving ? '…' : t('teacher.summarySave')}
                    </button>
                    {summarySavedId === l.id && <span className="summary-saved-hint">{t('teacher.summarySaved')} ✓</span>}
                    {summarySaveError === l.id && <span className="summary-error-hint">{t('teacher.summarySaveError')}</span>}
                  </div>
                </div>
              )}

              {isActive && l.homeworkAssigned && (
                <div className="homework-submissions-section">
                  <h4 className="homework-submissions-title">{t('teacher.homeworkTitle')} 📚</h4>
                  {homeworkMap[l.id] ? (
                    homeworkMap[l.id].families.map(f => (
                      <div key={f.id} className={`hw-family-row${f.submitted ? ' hw-submitted' : ''}`}>
                        <span className="hw-family-name">{f.name}</span>
                        {f.submitted
                          ? <span className="hw-badge hw-badge-ok">{t('homework.submitted')} ({f.images.length})</span>
                          : <span className="hw-badge hw-badge-missing">{t('homework.notSubmitted')}</span>
                        }
                        {f.images.length > 0 && (
                          <div className="homework-gallery-sm">
                            {f.images.map(img => (
                              <AuthImage key={img.id} id={img.id} alt={img.originalFilename} className="hw-thumb-img-sm" onZoom={setLightboxSrc} />
                            ))}
                          </div>
                        )}
                      </div>
                    ))
                  ) : (
                    <p className="empty">{t('common.loading')}</p>
                  )}
                </div>
              )}
            </li>
          )
        })}
      </ul>

      {wheelLessonId && attendanceMap[wheelLessonId] && (
        <LuckyWheel
          students={attendanceMap[wheelLessonId].students.filter(s => s.present)}
          onClose={() => setWheelLessonId(null)}
          t={t}
        />
      )}
      {lightboxSrc && <Lightbox src={lightboxSrc} onClose={() => setLightboxSrc(null)} />}
    </>
  )
}

// ── Hausaufgaben Tab ───────────────────────────────────────────────────────

const HOMEWORK_TYPE_DEFS = [
  { key: 'lesen',       icon: '🎙️' },
  { key: 'schreiben',   icon: '📷' },
  { key: 'schriftlich', icon: '📝' },
  { key: 'malen',       icon: '🎨' },
  { key: 'sonstiges',   icon: '🎙️📷' },
]

function HomeworkTab({ t, dateLocale }) {
  const [lessons, setLessons] = useState([])
  const [loaded, setLoaded] = useState(false)
  const [saving, setSaving] = useState({})
  const [submissionsOpen, setSubmissionsOpen] = useState({})
  const [submissionsData, setSubmissionsData] = useState({})
  const [familyOpen, setFamilyOpen] = useState({})
  const [lightboxSrc, setLightboxSrc] = useState(null)

  useEffect(() => {
    getLessons().then(data => { setLessons(data); setLoaded(true) })
  }, [])

  const toggleType = async (lessonId, type) => {
    const lesson = lessons.find(l => l.id === lessonId)
    if (!lesson) return
    const current = lesson.homeworkTypes ?? []
    const next = current.includes(type)
      ? current.filter(k => k !== type)
      : [...current, type]

    setSaving(prev => ({ ...prev, [lessonId]: true }))
    try {
      const updated = await patchLesson(lessonId, { homeworkTypes: next })
      setLessons(prev => prev.map(l => l.id === lessonId ? { ...l, homeworkTypes: updated.homeworkTypes } : l))
    } catch {}
    setSaving(prev => ({ ...prev, [lessonId]: false }))
  }

  const toggleSubmissions = async (lessonId) => {
    const isOpen = !!submissionsOpen[lessonId]
    setSubmissionsOpen(prev => ({ ...prev, [lessonId]: !isOpen }))
    if (!isOpen && !submissionsData[lessonId]) {
      try {
        const data = await getHomeworkByType(lessonId)
        setSubmissionsData(prev => ({ ...prev, [lessonId]: data }))
      } catch {}
    }
  }

  const formatDate = (dateStr) => {
    const [y, m, d] = dateStr.split('-')
    return new Date(+y, +m - 1, +d).toLocaleDateString(dateLocale, { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' })
  }

  if (!loaded) return <p className="empty">{t('common.loading')}</p>
  if (lessons.length === 0) return <p className="empty">{t('teacher.hwNoLessons')}</p>

  return (
    <>
      <ul className="hw-lesson-list">
        {lessons.map(l => {
          const types = l.homeworkTypes ?? []
          const isSaving = saving[l.id]
          const isSubOpen = !!submissionsOpen[l.id]
          return (
            <li key={l.id} className="hw-lesson-item">
              <div className="hw-lesson-header">
                <span className="hw-lesson-date">{formatDate(l.date)}</span>
                {l.title && <span className="hw-lesson-title">{l.title}</span>}
              </div>
              <div className="hw-type-toggles">
                {HOMEWORK_TYPE_DEFS.map(({ key, icon }) => (
                  <button
                    key={key}
                    className={`btn-hw-type${types.includes(key) ? ' active' : ''}${isSaving ? ' saving' : ''}`}
                    onClick={() => toggleType(l.id, key)}
                    disabled={isSaving}
                  >
                    <span className="hw-type-icon">{icon}</span>
                    {t(`homework.type.${key}`)}
                  </button>
                ))}
              </div>
              {types.length > 0 && (
                <button
                  className={`btn-hw-submissions-toggle${isSubOpen ? ' active' : ''}`}
                  onClick={() => toggleSubmissions(l.id)}
                >
                  📊 {t('homework.submissions')}
                  <span className="hw-sub-chevron">{isSubOpen ? ' ▲' : ' ▼'}</span>
                </button>
              )}
              {isSubOpen && (
                <div className="hw-submissions-panel">
                  {submissionsData[l.id] ? (() => {
                    const byTypeData = submissionsData[l.id].byType
                    const types = Object.keys(byTypeData)
                    const familyMap = {}
                    types.forEach(type => {
                      byTypeData[type].families.forEach(f => {
                        if (!familyMap[f.id]) familyMap[f.id] = { id: f.id, name: f.name, types: {} }
                        familyMap[f.id].types[type] = { images: f.images, audio: f.audio, submitted: f.submitted }
                      })
                    })
                    const familyList = Object.values(familyMap).sort((a, b) => a.name.localeCompare(b.name))
                    return familyList.map(f => {
                      const fKey = `${l.id}-${f.id}`
                      const isOpen = !!familyOpen[fKey]
                      const submittedCount = Object.values(f.types).filter(e => e.submitted).length
                      const totalCount = Object.keys(f.types).length
                      const allDone = submittedCount === totalCount
                      return (
                        <div key={f.id} className="hw-sub-family-block">
                          <button
                            className={`hw-sub-family-header${allDone ? ' all-done' : ''}`}
                            onClick={() => setFamilyOpen(prev => ({ ...prev, [fKey]: !isOpen }))}
                          >
                            <span className="hw-sub-family-name">{f.name}</span>
                            <span className={`hw-sub-family-status${allDone ? ' status-ok' : ' status-missing'}`}>
                              {submittedCount}/{totalCount}
                            </span>
                            <ChevronDown size={14} className={`hw-sub-chevron-icon${isOpen ? ' open' : ''}`} />
                          </button>
                          {isOpen && types.map(type => {
                            const entry = f.types[type]
                            if (!entry) return null
                            const def = HOMEWORK_TYPE_DEFS.find(d => d.key === type)
                            return (
                              <div key={type} className={`hw-sub-type-row${entry.submitted ? ' hw-submitted' : ''}`}>
                                <span className="hw-sub-type-label">{def?.icon} {t(`homework.type.${type}`)}</span>
                                {entry.submitted
                                  ? <span className="hw-badge hw-badge-ok">{t('homework.submitted')}</span>
                                  : <span className="hw-badge hw-badge-missing">{t('homework.notSubmitted')}</span>
                                }
                                {entry.images.length > 0 && (
                                  <div className="homework-gallery-sm">
                                    {entry.images.map(img => (
                                      <AuthImage key={img.id} id={img.id} alt={img.originalFilename} className="hw-thumb-img-sm" onZoom={setLightboxSrc} />
                                    ))}
                                  </div>
                                )}
                                {entry.audio.length > 0 && (
                                  <div className="hw-sub-audio">
                                    {entry.audio.map(a => (
                                      <AuthHomeworkAudio key={a.id} id={a.id} className="hw-audio-player" />
                                    ))}
                                  </div>
                                )}
                              </div>
                            )
                          })}
                        </div>
                      )
                    })
                  })() : (
                    <p className="empty">{t('common.loading')}</p>
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

// ── Kommunikation Tab ──────────────────────────────────────────────────────

function KommunikationTab({ t, dateLocale }) {
  const [lessons, setLessons] = useState([])
  const [loaded, setLoaded] = useState(false)
  const [openLessons, setOpenLessons] = useState({})
  const [lessonData, setLessonData] = useState({}) // { lessonId: { students, messages } }
  const [newMsg, setNewMsg] = useState({}) // { `${lid}-${sid}`: { open, text, pendingAudios, pendingImages, recording, mediaRecorder, chunks, sending } }
  const [editState, setEditState] = useState({}) // { msgId: { text, saving } }
  const [lightboxSrc, setLightboxSrc] = useState(null)

  useEffect(() => {
    getLessons().then(data => { setLessons(data); setLoaded(true) })
  }, [])

  const formatDate = (dateStr) => {
    const [y, m, d] = dateStr.split('-')
    return new Date(+y, +m - 1, +d).toLocaleDateString(dateLocale, { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' })
  }

  const loadLessonData = async (lessonId) => {
    const [attData, messages] = await Promise.all([
      getLessonAttendance(lessonId),
      getLessonFeedback(lessonId),
    ])
    const presentStudents = attData.students.filter(s => s.present)
    setLessonData(prev => ({ ...prev, [lessonId]: { students: presentStudents, messages } }))
  }

  const toggleLesson = async (lessonId) => {
    const isOpen = !!openLessons[lessonId]
    setOpenLessons(prev => ({ ...prev, [lessonId]: !isOpen }))
    if (!isOpen && !lessonData[lessonId]) {
      await loadLessonData(lessonId)
    }
  }

  const reloadMessages = async (lessonId) => {
    const messages = await getLessonFeedback(lessonId)
    setLessonData(prev => ({ ...prev, [lessonId]: { ...prev[lessonId], messages } }))
  }

  // ── New message form helpers ──

  const msgKey = (lid, sid) => `${lid}-${sid}`

  const openNewMsg = (lid, sid) => {
    const k = msgKey(lid, sid)
    setNewMsg(prev => ({ ...prev, [k]: { open: true, text: '', pendingAudios: [], pendingImages: [], recording: false, mediaRecorder: null, chunks: [], sending: false } }))
  }

  const closeNewMsg = (lid, sid) => {
    const k = msgKey(lid, sid)
    const state = newMsg[k]
    if (state?.mediaRecorder && state.recording) {
      try { state.mediaRecorder.stop() } catch {}
    }
    state?.pendingAudios?.forEach(a => URL.revokeObjectURL(a.url))
    setNewMsg(prev => { const n = { ...prev }; delete n[k]; return n })
  }

  const startRecording = async (lid, sid) => {
    const k = msgKey(lid, sid)
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      const mr = new MediaRecorder(stream)
      const chunks = []
      mr.ondataavailable = e => { if (e.data.size > 0) chunks.push(e.data) }
      mr.onstop = () => {
        stream.getTracks().forEach(t => t.stop())
        const blob = new Blob(chunks, { type: mr.mimeType || 'audio/webm' })
        const url = URL.createObjectURL(blob)
        setNewMsg(prev => ({
          ...prev,
          [k]: { ...prev[k], recording: false, mediaRecorder: null, chunks: [], pendingAudios: [...(prev[k]?.pendingAudios || []), { blob, url, mimeType: mr.mimeType || 'audio/webm' }] }
        }))
      }
      mr.start()
      setNewMsg(prev => ({ ...prev, [k]: { ...prev[k], recording: true, mediaRecorder: mr, chunks } }))
    } catch {}
  }

  const stopRecording = (lid, sid) => {
    const k = msgKey(lid, sid)
    const mr = newMsg[k]?.mediaRecorder
    if (mr) mr.stop()
  }

  const removePendingAudio = (lid, sid, idx) => {
    const k = msgKey(lid, sid)
    setNewMsg(prev => {
      const audios = [...(prev[k]?.pendingAudios || [])]
      URL.revokeObjectURL(audios[idx].url)
      audios.splice(idx, 1)
      return { ...prev, [k]: { ...prev[k], pendingAudios: audios } }
    })
  }

  const addPendingImage = (lid, sid, files) => {
    const k = msgKey(lid, sid)
    setNewMsg(prev => ({ ...prev, [k]: { ...prev[k], pendingImages: [...(prev[k]?.pendingImages || []), ...Array.from(files)] } }))
  }

  const removePendingImage = (lid, sid, idx) => {
    const k = msgKey(lid, sid)
    setNewMsg(prev => {
      const imgs = [...(prev[k]?.pendingImages || [])]
      imgs.splice(idx, 1)
      return { ...prev, [k]: { ...prev[k], pendingImages: imgs } }
    })
  }

  const sendMessage = async (lid, sid) => {
    const k = msgKey(lid, sid)
    const state = newMsg[k]
    if (!state) return
    setNewMsg(prev => ({ ...prev, [k]: { ...prev[k], sending: true } }))
    try {
      const msg = await createFeedbackMessage(lid, sid, state.text || null)
      for (const audio of state.pendingAudios) {
        const file = new File([audio.blob], 'recording.webm', { type: audio.mimeType })
        await uploadFeedbackAttachment(msg.id, file)
      }
      for (const img of state.pendingImages) {
        await uploadFeedbackAttachment(msg.id, img)
      }
      state.pendingAudios.forEach(a => URL.revokeObjectURL(a.url))
      setNewMsg(prev => { const n = { ...prev }; delete n[k]; return n })
      await reloadMessages(lid)
    } catch {
      setNewMsg(prev => ({ ...prev, [k]: { ...prev[k], sending: false } }))
    }
  }

  // ── Edit message helpers ──

  const startEdit = (msg) => {
    setEditState(prev => ({ ...prev, [msg.id]: { text: msg.text || '', saving: false } }))
  }

  const saveEdit = async (msg, lessonId) => {
    setEditState(prev => ({ ...prev, [msg.id]: { ...prev[msg.id], saving: true } }))
    try {
      await patchFeedbackMessage(msg.id, editState[msg.id]?.text || null)
      setEditState(prev => { const n = { ...prev }; delete n[msg.id]; return n })
      await reloadMessages(lessonId)
    } catch {
      setEditState(prev => ({ ...prev, [msg.id]: { ...prev[msg.id], saving: false } }))
    }
  }

  const cancelEdit = (msgId) => {
    setEditState(prev => { const n = { ...prev }; delete n[msgId]; return n })
  }

  // ── Delete helpers ──

  const handleDeleteMessage = async (msgId, lessonId) => {
    if (!window.confirm(t('teacher.comm.confirmDeleteMessage'))) return
    await deleteFeedbackMessage(msgId)
    await reloadMessages(lessonId)
  }

  const handleDeleteAttachment = async (attId, lessonId) => {
    await deleteFeedbackAttachment(attId)
    await reloadMessages(lessonId)
  }

  // ── Add attachment to existing message ──

  const handleAddAudioToMsg = async (msg, lessonId) => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      const mr = new MediaRecorder(stream)
      const chunks = []
      mr.ondataavailable = e => { if (e.data.size > 0) chunks.push(e.data) }
      mr.onstop = async () => {
        stream.getTracks().forEach(t => t.stop())
        const blob = new Blob(chunks, { type: mr.mimeType || 'audio/webm' })
        const file = new File([blob], 'recording.webm', { type: blob.type })
        await uploadFeedbackAttachment(msg.id, file)
        await reloadMessages(lessonId)
      }
      mr.start()
      // Auto-stop after 5 min safeguard — user stops manually
      setLessonData(prev => ({
        ...prev,
        [lessonId]: {
          ...prev[lessonId],
          _recording: { msgId: msg.id, mr }
        }
      }))
    } catch {}
  }

  const handleStopAddAudio = async (lessonId) => {
    const mr = lessonData[lessonId]?._recording?.mr
    if (mr) mr.stop()
    setLessonData(prev => ({ ...prev, [lessonId]: { ...prev[lessonId], _recording: null } }))
  }

  const handleAddImageToMsg = async (msg, lessonId, files) => {
    for (const file of Array.from(files)) {
      await uploadFeedbackAttachment(msg.id, file)
    }
    await reloadMessages(lessonId)
  }

  if (!loaded) return <p className="empty">{t('common.loading')}</p>
  if (lessons.length === 0) return <p className="empty">{t('teacher.comm.noLessons')}</p>

  return (
    <>
      <ul className="hw-lesson-list">
        {lessons.map(l => {
          const isOpen = !!openLessons[l.id]
          const data = lessonData[l.id]
          return (
            <li key={l.id} className="hw-lesson-item">
              <button className="hw-lesson-header comm-lesson-toggle" onClick={() => toggleLesson(l.id)}>
                <span className="hw-lesson-date">{formatDate(l.date)}</span>
                {l.title && <span className="hw-lesson-title">{l.title}</span>}
                <ChevronDown size={14} className={`hw-sub-chevron-icon${isOpen ? ' open' : ''}`} />
              </button>

              {isOpen && (
                <div className="comm-lesson-body">
                  {!data ? (
                    <p className="empty">{t('common.loading')}</p>
                  ) : data.students.length === 0 ? (
                    <p className="empty">{t('teacher.comm.noStudentsPresent')}</p>
                  ) : (
                    data.students.map(student => {
                      const k = msgKey(l.id, student.id)
                      const msgState = newMsg[k]
                      const studentMessages = (data.messages || []).filter(m => m.studentId === student.id)
                      const activeRecording = data._recording?.msgId

                      return (
                        <div key={student.id} className="comm-student-block">
                          <div className="comm-student-header">
                            <span className="comm-student-name">{student.name}</span>
                            <span className="comm-student-family">{student.familyName}</span>
                          </div>

                          {studentMessages.length === 0 && !msgState && (
                            <p className="comm-no-messages">{t('teacher.comm.noMessages')}</p>
                          )}

                          {studentMessages.map(msg => {
                            const isEditing = !!editState[msg.id]
                            const isRecordingForMsg = activeRecording === msg.id
                            return (
                              <div key={msg.id} className="comm-message-card">
                                <div className="comm-message-actions">
                                  <button className="btn-icon-sm" title={t('teacher.comm.editText')} onClick={() => isEditing ? cancelEdit(msg.id) : startEdit(msg)}>✏️</button>
                                  <button className="btn-icon-sm" title={t('teacher.comm.deleteMessage')} onClick={() => handleDeleteMessage(msg.id, l.id)}>🗑️</button>
                                </div>

                                {isEditing ? (
                                  <div className="comm-edit-form">
                                    <textarea
                                      className="comm-textarea"
                                      value={editState[msg.id]?.text || ''}
                                      onChange={e => setEditState(prev => ({ ...prev, [msg.id]: { ...prev[msg.id], text: e.target.value } }))}
                                      rows={3}
                                    />
                                    <div className="comm-form-actions">
                                      <button className="btn-sm btn-primary" disabled={editState[msg.id]?.saving} onClick={() => saveEdit(msg, l.id)}>
                                        {editState[msg.id]?.saving ? t('teacher.comm.savingText') : t('teacher.comm.saveText')}
                                      </button>
                                      <button className="btn-sm btn-ghost" onClick={() => cancelEdit(msg.id)}>{t('teacher.comm.cancel')}</button>
                                    </div>
                                  </div>
                                ) : (
                                  msg.text && <p className="comm-message-text">{msg.text}</p>
                                )}

                                {msg.attachments.length > 0 && (
                                  <div className="comm-attachments">
                                    {msg.attachments.map(att => (
                                      <div key={att.id} className="comm-attachment">
                                        {att.type === 'audio'
                                          ? <AuthFeedbackAudio id={att.id} />
                                          : <AuthFeedbackImage id={att.id} onZoom={setLightboxSrc} />
                                        }
                                        <button className="btn-icon-sm comm-att-delete" title={t('teacher.comm.deleteAttachment')} onClick={() => handleDeleteAttachment(att.id, l.id)}>✕</button>
                                      </div>
                                    ))}
                                  </div>
                                )}

                                <div className="comm-add-attachment-row">
                                  {isRecordingForMsg ? (
                                    <button className="btn-sm btn-danger" onClick={() => handleStopAddAudio(l.id)}>⏹ {t('teacher.comm.stopRecording')}</button>
                                  ) : (
                                    <button className="btn-sm btn-ghost" onClick={() => handleAddAudioToMsg(msg, l.id)}>🎙️ {t('teacher.comm.addAudio')}</button>
                                  )}
                                  <label className="btn-sm btn-ghost comm-img-label">
                                    📷 {t('teacher.comm.addImage')}
                                    <input type="file" accept="image/*" multiple style={{ display: 'none' }} onChange={e => handleAddImageToMsg(msg, l.id, e.target.files)} />
                                  </label>
                                </div>
                              </div>
                            )
                          })}

                          {!msgState ? (
                            <button className="btn-sm btn-primary comm-add-btn" onClick={() => openNewMsg(l.id, student.id)}>
                              + {t('teacher.comm.addMessage')}
                            </button>
                          ) : (
                            <div className="comm-new-msg-form">
                              <textarea
                                className="comm-textarea"
                                placeholder={t('teacher.comm.messagePlaceholder')}
                                value={msgState.text}
                                onChange={e => setNewMsg(prev => ({ ...prev, [k]: { ...prev[k], text: e.target.value } }))}
                                rows={3}
                              />

                              {msgState.pendingAudios.length > 0 && (
                                <div className="comm-pending-audios">
                                  {msgState.pendingAudios.map((a, i) => (
                                    <div key={i} className="comm-pending-audio-row">
                                      <audio controls src={a.url} className="hw-audio-player" />
                                      <button className="btn-icon-sm" onClick={() => removePendingAudio(l.id, student.id, i)}>✕</button>
                                    </div>
                                  ))}
                                </div>
                              )}

                              {msgState.pendingImages.length > 0 && (
                                <div className="comm-pending-images">
                                  {msgState.pendingImages.map((img, i) => (
                                    <div key={i} className="comm-pending-img-wrap">
                                      <img src={URL.createObjectURL(img)} alt="" className="hw-thumb-img-sm" />
                                      <button className="btn-icon-sm comm-att-delete" onClick={() => removePendingImage(l.id, student.id, i)}>✕</button>
                                    </div>
                                  ))}
                                </div>
                              )}

                              <div className="comm-form-toolbar">
                                {msgState.recording ? (
                                  <button className="btn-sm btn-danger" onClick={() => stopRecording(l.id, student.id)}>⏹ {t('teacher.comm.stopRecording')}</button>
                                ) : (
                                  <button className="btn-sm btn-ghost" onClick={() => startRecording(l.id, student.id)}>🎙️ {t('teacher.comm.addAudio')}</button>
                                )}
                                <label className="btn-sm btn-ghost comm-img-label">
                                  📷 {t('teacher.comm.addImage')}
                                  <input type="file" accept="image/*" multiple style={{ display: 'none' }} onChange={e => addPendingImage(l.id, student.id, e.target.files)} />
                                </label>
                              </div>

                              <div className="comm-form-actions">
                                <button className="btn-sm btn-primary" disabled={msgState.sending} onClick={() => sendMessage(l.id, student.id)}>
                                  {msgState.sending ? t('teacher.comm.sending') : t('teacher.comm.send')}
                                </button>
                                <button className="btn-sm btn-ghost" onClick={() => closeNewMsg(l.id, student.id)}>{t('teacher.comm.cancel')}</button>
                              </div>
                            </div>
                          )}
                        </div>
                      )
                    })
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

export default function TeacherDashboard() {
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
        <h1>童声 <span className="role-badge"><GraduationCap size={13} /></span></h1>
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
        <button className={tab === 'homework' ? 'active' : ''} onClick={() => setTab('homework')} title={t('teacher.tabHomework')}>
          <BookOpen size={20} />
        </button>
        <button className={tab === 'students' ? 'active' : ''} onClick={() => setTab('students')} title={t('teacher.tabStudents')}>
          <Users size={20} />
        </button>
        <button className={tab === 'lessons' ? 'active' : ''} onClick={() => setTab('lessons')} title={t('teacher.tabLessons')}>
          <Calendar size={20} />
        </button>
        <button className={tab === 'communication' ? 'active' : ''} onClick={() => setTab('communication')} title={t('teacher.tabCommunication')}>
          <MessageSquare size={20} />
        </button>
      </nav>

      <main className="dashboard-main">
        {tab === 'lessons' && <LessonsTab t={t} dateLocale={dateLocale} />}
        {tab === 'students' && <StudentsTab t={t} />}
        {tab === 'homework' && <HomeworkTab t={t} dateLocale={dateLocale} />}
        {tab === 'communication' && <KommunikationTab t={t} dateLocale={dateLocale} />}
      </main>
    </div>
  )
}
