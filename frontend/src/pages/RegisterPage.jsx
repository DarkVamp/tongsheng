import { useState, useEffect } from 'react'
import { useSearchParams, useNavigate } from 'react-router-dom'
import { validateInvitation, register } from '../api/auth'
import { useAuth } from '../context/AuthContext'
import { useLocale } from '../context/LocaleContext'

export default function RegisterPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const { signIn } = useAuth()
  const { t } = useLocale()

  const token = params.get('token') ?? ''

  const [invitation, setInvitation] = useState(null)
  const [loadError, setLoadError] = useState('')
  const [familyName, setFamilyName] = useState('')
  const [password, setPassword] = useState('')
  const [password2, setPassword2] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!token) { setLoadError(t('register.noToken')); return }
    validateInvitation(token)
      .then(setInvitation)
      .catch(() => setLoadError(t('register.invalidToken')))
  }, [token])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')

    if (familyName.trim() === '') { setError(t('register.nameRequired')); return }
    if (password.length < 6) { setError(t('register.passwordTooShort')); return }
    if (password !== password2) { setError(t('register.passwordMismatch')); return }

    setSubmitting(true)
    try {
      const data = await register(token, familyName.trim(), password)
      signIn(data.token, { id: data.id, name: data.name, role: data.role, locale: data.locale, familyGroupId: data.familyGroupId })
      navigate(data.role === 'teacher' ? '/teacher' : '/family', { replace: true })
    } catch (err) {
      setError(err.response?.data?.error ?? t('register.failed'))
    } finally {
      setSubmitting(false)
    }
  }

  if (loadError) {
    return (
      <div className="login-page">
        <div className="login-card">
          <h1>同声</h1>
          <p className="error">{loadError}</p>
        </div>
      </div>
    )
  }

  if (!invitation) {
    return (
      <div className="login-page">
        <div className="login-card">
          <h1>同声</h1>
          <p>{t('common.loading')}</p>
        </div>
      </div>
    )
  }

  const roleLabel = invitation.role === 'family_member'
    ? t('register.roleFamilyMember')
    : t('register.roleFamily')

  return (
    <div className="login-page">
      <div className="login-card">
        <h1>同声</h1>
        <p className="login-subtitle">{t('register.subtitle')}</p>

        <div className="register-info">
          <p><strong>{t('register.email')}:</strong> {invitation.email}</p>
          <p><strong>{t('register.role')}:</strong> {roleLabel}</p>
          {invitation.role === 'family_member' && invitation.familyName && (
            <p><strong>{t('register.family')}:</strong> {invitation.familyName}</p>
          )}
        </div>

        <form onSubmit={handleSubmit}>
          <label>
            {t('register.yourName')}
            <input
              type="text"
              value={familyName}
              onChange={e => setFamilyName(e.target.value)}
              placeholder={t('register.namePlaceholder')}
              autoFocus
              required
            />
          </label>
          <label>
            {t('register.password')}
            <input
              type="password"
              value={password}
              onChange={e => setPassword(e.target.value)}
              required
            />
          </label>
          <label>
            {t('register.passwordConfirm')}
            <input
              type="password"
              value={password2}
              onChange={e => setPassword2(e.target.value)}
              required
            />
          </label>
          {error && <p className="error">{error}</p>}
          <button type="submit" disabled={submitting}>
            {submitting ? t('register.submitting') : t('register.submit')}
          </button>
        </form>
      </div>
    </div>
  )
}
