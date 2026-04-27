import { useState, useEffect } from 'react'
import { Trash2, LogOut, Send, UserPlus, Copy, X, Search } from 'lucide-react'
import Icon from '../components/Icon'
import { getRecordings, deleteRecording, fetchAudioBlob, getComments, addComment } from '../api/recordings'
import { getFamilies, getInvitations, createInvitation, deleteInvitation } from '../api/invitations'
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
  const [role, setRole] = useState('family')
  const [familyGroupId, setFamilyGroupId] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [created, setCreated] = useState(null)

  const baseUrl = window.location.origin

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setSubmitting(true)
    try {
      const inv = await createInvitation({ email, role, familyGroupId: role === 'family_member' ? familyGroupId : null })
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
              {t('teacher.inviteRole')}
              <select value={role} onChange={e => { setRole(e.target.value); setFamilyGroupId('') }}>
                <option value="family">{t('teacher.roleFamily')}</option>
                <option value="family_member">{t('teacher.roleFamilyMember')}</option>
              </select>
            </label>
            {role === 'family_member' && (
              <label>
                {t('teacher.inviteFamily')}
                <select value={familyGroupId} onChange={e => setFamilyGroupId(e.target.value)} required>
                  <option value="">{t('teacher.selectFamily')}</option>
                  {families.map(f => (
                    <option key={f.familyGroupId} value={f.familyGroupId}>{f.name}</option>
                  ))}
                </select>
              </label>
            )}
            {error && <p className="error">{error}</p>}
            <div className="modal-actions">
              <button type="submit" disabled={submitting || (role === 'family_member' && !familyGroupId)}>
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

  const [showInvite, setShowInvite] = useState(false)
  const [families, setFamilies] = useState([])
  const [invitations, setInvitations] = useState([])
  const [showInvitations, setShowInvitations] = useState(false)

  useEffect(() => {
    getRecordings().then(setRecordings)
    getFamilies().then(setFamilies)
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

      <main className="dashboard-main">
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
      </main>

      {showInvite && (
        <InviteModal
          families={families}
          t={t}
          onClose={() => setShowInvite(false)}
          onCreated={(inv) => setInvitations(prev => [inv, ...prev])}
        />
      )}
    </div>
  )
}
