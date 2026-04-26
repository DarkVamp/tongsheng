import api from './client'

export const getRecordings = () => api.get('/recordings').then((r) => r.data)

export const uploadRecording = (file) => {
  const form = new FormData()
  form.append('audio', file)
  return api.post('/recordings', form).then((r) => r.data)
}

export const deleteRecording = (id) => api.post(`/recordings/${id}/delete`)

export const fetchAudioBlob = (id) =>
  api.get(`/recordings/${id}/audio`, { responseType: 'blob' }).then((r) => URL.createObjectURL(r.data))

export const getComments = (recordingId) =>
  api.get(`/recordings/${recordingId}/comments`).then((r) => r.data)

export const addComment = (recordingId, content) =>
  api.post(`/recordings/${recordingId}/comments`, { content }).then((r) => r.data)
