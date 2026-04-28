import api from './client'

export const getFamilies = () => api.get('/families').then(r => r.data)

export const createFamily = (name) => api.post('/families', { name }).then(r => r.data)

export const deleteFamily = (id) => api.post(`/families/${id}/delete`)

export const getFamilyMembers = () => api.get('/families/members').then(r => r.data)

export const createMember = (familyId, data) => api.post(`/families/${familyId}/members`, data).then(r => r.data)

export const deleteMember = (userId) => api.post(`/families/members/${userId}/delete`)
