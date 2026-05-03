import { useState, useEffect } from 'react'
import { Trash2, LogOut, Send, UserPlus, Users, X, Search, GraduationCap } from 'lucide-react'
import Icon from '../components/Icon'
import LuckyWheel from '../components/LuckyWheel'
import { getRecordings, deleteRecording, fetchAudioBlob, getComments, addComment, reactToComment } from '../api/recordings'
import { createFamily, deleteFamily, getFamilyMembers, createMember, deleteMember } from '../api/families'
import { toggleStudent, getLessons, createLesson, deleteLesson, getLessonAttendance, setAttendance, patchLesson } from '../api/lessons'
import { getHomeworkAllForLesson, fetchHomeworkImageBlob } from '../api/homework'
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

  const handleToggleHomework = async (id, current) => {
    try {
      const updated = await patchLesson(id, { homeworkAssigned: !current })
      setLessons(prev => prev.map(l => l.id === id ? { ...l, homeworkAssigned: updated.homeworkAssigned } : l))
      if (!current && activeLessonId === id && !homeworkMap[id]) {
        const data = await getHomeworkAllForLesson(id)
        setHomeworkMap(prev => ({ ...prev, [id]: data }))
      }
    } catch {}
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
                    className={`btn-icon-text btn-ghost btn-hw-toggle${l.homeworkAssigned ? ' hw-active' : ''}`}
                    onClick={e => { e.stopPropagation(); handleToggleHomework(l.id, l.homeworkAssigned) }}
                    title={l.homeworkAssigned ? t('teacher.homeworkAssigned') : t('teacher.homeworkNotAssigned')}
                  >
                    📚
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

// ── Main Dashboard ─────────────────────────────────────────────────────────

export default function TeacherDashboard() {
  const { user, signOut } = useAuth()
  const { locale, setLocale, t } = useLocale()
  const navigate = useNavigate()
  const [tab, setTab] = useState('recordings')


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
        <h1>同声 <span className="role-badge"><GraduationCap size={13} /></span></h1>
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
        <button className={tab === 'recordings' ? 'active' : ''} onClick={() => setTab('recordings')}>
          <Icon name="list" size={15} />
          {t('teacher.tabRecordings')}
        </button>
        <button className={tab === 'students' ? 'active' : ''} onClick={() => setTab('students')}>
          <Users size={15} />
          {t('teacher.tabStudents')}
        </button>
        <button className={tab === 'lessons' ? 'active' : ''} onClick={() => setTab('lessons')}>
          <Icon name="calendar" size={15} />
          {t('teacher.tabLessons')}
        </button>
      </nav>

      <main className="dashboard-main">
        {tab === 'recordings' && <RecordingsTab t={t} dateLocale={dateLocale} />}
        {tab === 'students' && <StudentsTab t={t} />}
        {tab === 'lessons' && <LessonsTab t={t} dateLocale={dateLocale} />}
      </main>
    </div>
  )
}
