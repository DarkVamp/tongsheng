import { useState, useEffect } from 'react'
import { Trash2, LogOut, Send, UserPlus, Copy, X, Search } from 'lucide-react'
import Icon from '../components/Icon'
import { getRecordings, deleteRecording, fetchAudioBlob, getComments, addComment } from '../api/recordings'
import { getInvitations, createInvitation, deleteInvitation } from '../api/invitations'
import { getFamilies, createFamily, deleteFamily, getFamilyMembers } from '../api/families'
import { toggleStudent, getLessons, createLesson, deleteLesson, getLessonAttendance, setAttendance } from '../api/lessons'
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

function InviteModal({ families, onClose, onCreated, t }) {
  const [email, setEmail] = useState('')
  const [familyId, setFamilyId] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [created, setCreated] = useState(null)

  const baseUrl = window.location.origin

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setSubmitting(true)
    try {
      const inv = await createInvitation({ email, familyId: familyId ? Number(familyId) : null })
      setCreated(inv)
      onCreated(inv)
    } catch (err) {
      setError(err.response?.data?.error ?? t('teacher.inviteError'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal" onClick={e => e.stopPropagation()}>
        <div className="modal-header">
          <h2>{t('teacher.inviteTitle')}</h2>
          <button className="btn-icon btn-ghost" onClick={onClose}><X size={18} /></button>
        </div>

        {created ? (
          <div className="invite-success">
            <p>{t('teacher.inviteSuccess')}</p>
            <div className="invite-link-box">
              <input
                readOnly
                value={`${baseUrl}/register?token=${created.token}`}
                onFocus={e => e.target.select()}
              />
              <button onClick={() => navigator.clipboard?.writeText(`${baseUrl}/register?token=${created.token}`)}>
                <Copy size={15} />
                <span>{t('teacher.copyLink')}</span>
              </button>
            </div>
            <p className="invite-expires">{t('teacher.inviteExpires', new Date(created.expiresAt).toLocaleDateString())}</p>
            <button className="btn-ghost" onClick={onClose}>{t('teacher.close')}</button>
          </div>
        ) : (
          <form onSubmit={handleSubmit}>
            <label>
              {t('teacher.inviteEmail')}
              <input type="email" value={email} onChange={e => setEmail(e.target.value)} required autoFocus />
            </label>
            <label>
              {t('teacher.inviteFamily')}
              <select value={familyId} onChange={e => setFamilyId(e.target.value)} required>
                <option value="">{t('teacher.selectFamily')}</option>
                {families.map(f => (
                  <option key={f.id} value={f.id}>{f.name}</option>
                ))}
              </select>
            </label>
            {error && <p className="error">{error}</p>}
            <div className="modal-actions">
              <button type="submit" disabled={submitting || !familyId}>
                {submitting ? t('common.sending') : t('teacher.inviteSend')}
              </button>
              <button type="button" className="btn-ghost" onClick={onClose}>{t('common.cancel')}</button>
            </div>
          </form>
        )}
      </div>
    </div>
  )
}

// ── Recordings Tab ─────────────────────────────────────────────────────────

function RecordingsTab({ t, dateLocale, families, invitations, setInvitations }) {
  const [recordings, setRecordings] = useState([])
  const [filter, setFilter] = useState('')
  const [activeId, setActiveId] = useState(null)
  const [comments, setComments] = useState({})
  const [newComment, setNewComment] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [deleteError, setDeleteError] = useState('')
  const [showInvite, setShowInvite] = useState(false)
  const [showInvitations, setShowInvitations] = useState(false)

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

  const handleDeleteInvitation = async (id) => {
    try {
      await deleteInvitation(id)
      setInvitations(prev => prev.filter(i => i.id !== id))
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
      <div className="invite-bar">
        <button className="btn-icon-text btn-primary" onClick={() => setShowInvite(true)}>
          <UserPlus size={16} />
          <span>{t('teacher.inviteButton')}</span>
        </button>
        {invitations.length > 0 && (
          <button className="btn-ghost" onClick={() => setShowInvitations(v => !v)}>
            {t('teacher.pendingInvitations', invitations.length)}
          </button>
        )}
      </div>

      {showInvitations && invitations.length > 0 && (
        <section className="invitations-section">
          <h3>{t('teacher.pendingInvitationsTitle')}</h3>
          <ul className="invitation-list">
            {invitations.map(inv => (
              <li key={inv.id} className="invitation-item">
                <span className="inv-email">{inv.email}</span>
                <span className="inv-role">{inv.role === 'family_member' ? t('teacher.roleFamilyMember') : t('teacher.roleFamily')}</span>
                <button className="btn-icon btn-delete" onClick={() => handleDeleteInvitation(inv.id)} title={t('common.delete')}>
                  <Trash2 size={14} />
                </button>
              </li>
            ))}
          </ul>
        </section>
      )}

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

      {showInvite && (
        <InviteModal
          families={families}
          t={t}
          onClose={() => setShowInvite(false)}
          onCreated={(inv) => setInvitations(prev => [inv, ...prev])}
        />
      )}
    </>
  )
}

// ── Schüler Tab ────────────────────────────────────────────────────────────

function StudentsTab({ t, onFamiliesChanged }) {
  const [familyGroups, setFamilyGroups] = useState([])
  const [loaded, setLoaded] = useState(false)
  const [newFamilyName, setNewFamilyName] = useState('')
  const [creating, setCreating] = useState(false)

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
      onFamiliesChanged()
    } finally {
      setCreating(false)
    }
  }

  const handleDeleteFamily = async (familyId, familyName) => {
    if (!confirm(t('teacher.confirmDeleteFamily', familyName))) return
    try {
      await deleteFamily(familyId)
      setFamilyGroups(prev => prev.filter(g => g.familyId !== familyId))
      onFamiliesChanged()
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
                </li>
              ))}
            </ul>
          )}
        </section>
      ))}
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

  useEffect(() => {
    getLessons().then(data => { setLessons(data); setLoaded(true) })
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
      const data = await getLessonAttendance(id)
      setAttendanceMap(prev => ({ ...prev, [id]: data }))
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
      {loaded && lessons.length === 0 && <p className="empty">{t('teacher.noLessons')}</p>}

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
              {isActive && !att && <div className="attendance-list"><p className="empty">{t('common.loading')}</p></div>}
            </li>
          )
        })}
      </ul>
    </>
  )
}

// ── Main Dashboard ─────────────────────────────────────────────────────────

export default function TeacherDashboard() {
  const { user, signOut } = useAuth()
  const { locale, setLocale, t } = useLocale()
  const navigate = useNavigate()
  const [tab, setTab] = useState('recordings')
  const [families, setFamilies] = useState([])
  const [invitations, setInvitations] = useState([])

  const reloadFamilies = () => getFamilies().then(setFamilies)

  useEffect(() => {
    reloadFamilies()
    getInvitations().then(setInvitations)
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
          <Icon name="person" size={15} />
          {t('teacher.tabStudents')}
        </button>
        <button className={tab === 'lessons' ? 'active' : ''} onClick={() => setTab('lessons')}>
          <Icon name="calendar" size={15} />
          {t('teacher.tabLessons')}
        </button>
      </nav>

      <main className="dashboard-main">
        {tab === 'recordings' && (
          <RecordingsTab
            t={t}
            dateLocale={dateLocale}
            families={families}
            invitations={invitations}
            setInvitations={setInvitations}
          />
        )}
        {tab === 'students' && <StudentsTab t={t} onFamiliesChanged={reloadFamilies} />}
        {tab === 'lessons' && <LessonsTab t={t} dateLocale={dateLocale} />}
      </main>
    </div>
  )
}
