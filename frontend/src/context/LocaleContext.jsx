import { createContext, useContext, useState } from 'react'
import translations from '../i18n'

const LocaleContext = createContext(null)

export function LocaleProvider({ children }) {
  const [locale, setLocaleState] = useState(
    () => localStorage.getItem('locale') || 'zh'
  )

  const setLocale = (l) => {
    localStorage.setItem('locale', l)
    setLocaleState(l)
  }

  const t = (key, param) => {
    const val = translations[locale]?.[key] ?? translations.de[key] ?? key
    return typeof val === 'function' ? val(param) : val
  }

  return (
    <LocaleContext.Provider value={{ locale, setLocale, t }}>
      {children}
    </LocaleContext.Provider>
  )
}

export const useLocale = () => useContext(LocaleContext)
