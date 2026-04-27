import api from './client'

export const getFamilies = () => api.get('/families').then(r => r.data)

export const createFamily = (name) => api.post('/families', { name }).then(r => r.data)

export const deleteFamily = (id) => api.post(`/families/${id}/delete`)

export const getFamilyMembers = () => api.get('/families/members').then(r => r.data)
