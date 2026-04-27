import api from './client'

export const login = (email, password) =>
  api.post('/login', { email, password }).then((r) => r.data)

export const logout = () => api.post('/logout')

export const getMe = () => api.get('/me').then((r) => r.data)

export const updateLocale = (locale) =>
  api.patch('/me/locale', { locale }).then((r) => r.data)

export const validateInvitation = (token) =>
  api.get(`/register/validate?token=${token}`).then((r) => r.data)

export const register = (token, familyName, password) =>
  api.post('/register', { token, familyName, password }).then((r) => r.data)
