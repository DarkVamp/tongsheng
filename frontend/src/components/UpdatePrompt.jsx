import { useRegisterSW } from 'virtual:pwa-register/react'
import { useLocale } from '../context/LocaleContext'

export default function UpdatePrompt() {
  const { t } = useLocale()
  const {
    needRefresh: [needRefresh, setNeedRefresh],
    updateServiceWorker,
  } = useRegisterSW()

  if (!needRefresh) return null

  return (
    <div style={{
      position: 'fixed',
      bottom: '1rem',
      left: '50%',
      transform: 'translateX(-50%)',
      background: '#4f46e5',
      color: 'white',
      borderRadius: '0.5rem',
      padding: '0.75rem 1.25rem',
      display: 'flex',
      gap: '0.75rem',
      alignItems: 'center',
      zIndex: 9999,
      boxShadow: '0 4px 16px rgba(0,0,0,0.35)',
      whiteSpace: 'nowrap',
    }}>
      <span style={{ fontSize: '0.9rem' }}>{t('update.available')}</span>
      <button
        onClick={() => updateServiceWorker(true)}
        style={{
          background: 'white',
          color: '#4f46e5',
          border: 'none',
          borderRadius: '0.25rem',
          padding: '0.3rem 0.8rem',
          cursor: 'pointer',
          fontWeight: 600,
          fontSize: '0.85rem',
        }}
      >
        {t('update.now')}
      </button>
      <button
        onClick={() => setNeedRefresh(false)}
        style={{
          background: 'transparent',
          color: 'rgba(255,255,255,0.8)',
          border: 'none',
          cursor: 'pointer',
          fontSize: '1rem',
          lineHeight: 1,
          padding: '0 0.1rem',
        }}
        aria-label="Schließen"
      >
        ✕
      </button>
    </div>
  )
}
