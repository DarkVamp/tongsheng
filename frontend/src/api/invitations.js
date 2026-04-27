import api from './client'

export const getInvitations = () => api.get('/invitations').then(r => r.data)

export const createInvitation = (data) => api.post('/invitations', data).then(r => r.data)

export const deleteInvitation = (id) => api.post(`/invitations/${id}/delete`)
