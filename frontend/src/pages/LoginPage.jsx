import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { login } from '../api/auth'
import { useAuth } from '../context/AuthContext'
import { useLocale } from '../context/LocaleContext'

export default function LoginPage() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const { signIn } = useAuth()
  const { locale, setLocale, t } = useLocale()
  const navigate = useNavigate()

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const data = await login(email, password)
      if (data.locale) setLocale(data.locale)
      signIn(data.token, { id: data.id, role: data.role, name: data.name, locale: data.locale, familyId: data.familyId })
      navigate(data.role === 'teacher' ? '/teacher' : '/family')
    } catch {
      setError(t('login.error'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="login-page">
      <div className="login-card">
        <h1>童声</h1>
        <p className="login-subtitle">{t('login.subtitle')}</p>
        <form onSubmit={handleSubmit}>
          <label>{t('login.email')}
            <input type="email" value={email} onChange={e => setEmail(e.target.value)} required autoComplete="email" />
          </label>
          <label>{t('login.password')}
            <input type="password" value={password} onChange={e => setPassword(e.target.value)} required autoComplete="current-password" />
          </label>
          {error && <p className="error">{error}</p>}
          <button type="submit" disabled={loading}>
            {loading ? t('login.submitting') : t('login.submit')}
          </button>
        </form>
        <div className="locale-switcher">
          <button className={locale === 'zh' ? 'active' : ''} onClick={() => setLocale('zh')} title="中文">🇨🇳</button>
          <button className={locale === 'de' ? 'active' : ''} onClick={() => setLocale('de')} title="Deutsch">🇩🇪</button>
        </div>
        <p className="login-copyright">
          &copy; {new Date().getFullYear()} <a href="https://ralf-kraus.com/" target="_blank" rel="noopener noreferrer">Ralf Kraus</a>
        </p>
      </div>
    </div>
  )
}
