import api from './client'

export const login = (email, password) =>
  api.post('/login', { email, password }).then((r) => r.data)

export const logout = () => api.post('/logout')

export const getMe = () => api.get('/me').then((r) => r.data)

export const updateLocale = (locale) =>
  api.patch('/me/locale', { locale }).then((r) => r.data)
